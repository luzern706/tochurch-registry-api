<?php

namespace App\Helpers;

use App\Constants\AuditActionType;
use App\Constants\AuditResult;
use App\Repositories\AuditLogRepository;

/**
 * 감사 로그 헬퍼 (관리자 API V4 패턴 적용)
 *
 * Service 의 write 액션 뒤에 정적 호출.
 * 내부에서 try/catch 로 감싸 audit 실패가 본 비즈니스 흐름을 중단시키지 않도록 보장.
 */
class AuditLogHelper
{
    /**
     * 생성 액션 로그
     */
    public static function logCreate(
        int $memberId,
        int $churchId,
        string $menuCode,
        string $summary,
        string|int|null $targetId = null,
        ?string $targetLabel = null,
        ?array $created = null,
        string $result = AuditResult::SUCCESS,
    ): void {
        self::write(
            memberId:    $memberId,
            churchId:    $churchId,
            menuCode:    $menuCode,
            actionType:  AuditActionType::CREATE,
            summary:     $summary,
            targetId:    $targetId,
            targetLabel: $targetLabel,
            detail:      $created !== null ? ['created' => $created] : null,
            result:      $result,
        );
    }

    /**
     * 수정 액션 로그 (before/after 비교 → change_detail JSON)
     *
     * @param array|null $compareFields 지정 시 해당 키만 diff. null 이면 모든 키 diff.
     */
    public static function logUpdate(
        int $memberId,
        int $churchId,
        string $menuCode,
        string $summary,
        string|int|null $targetId = null,
        ?string $targetLabel = null,
        ?array $before = null,
        ?array $after = null,
        ?array $compareFields = null,
        string $result = AuditResult::SUCCESS,
    ): void {
        $detail = self::buildDiff($before, $after, $compareFields);

        self::write(
            memberId:    $memberId,
            churchId:    $churchId,
            menuCode:    $menuCode,
            actionType:  AuditActionType::UPDATE,
            summary:     $summary,
            targetId:    $targetId,
            targetLabel: $targetLabel,
            detail:      $detail,
            result:      $result,
        );
    }

    /**
     * 삭제 액션 로그 (soft/hard 모두 — 의미 구분은 summary 에서)
     */
    public static function logDelete(
        int $memberId,
        int $churchId,
        string $menuCode,
        string $summary,
        string|int|null $targetId = null,
        ?string $targetLabel = null,
        ?array $deleted = null,
        string $result = AuditResult::SUCCESS,
    ): void {
        self::write(
            memberId:    $memberId,
            churchId:    $churchId,
            menuCode:    $menuCode,
            actionType:  AuditActionType::DELETE,
            summary:     $summary,
            targetId:    $targetId,
            targetLabel: $targetLabel,
            detail:      $deleted !== null ? ['deleted' => $deleted] : null,
            result:      $result,
        );
    }

    /**
     * 그 외 액션 (LOGIN, LOGOUT, ASSIGN, UNASSIGN, ENROLL, WITHDRAW, BULK_UPDATE, SEND)
     */
    public static function logAction(
        int $memberId,
        int $churchId,
        string $menuCode,
        string $actionType,
        string $summary,
        string|int|null $targetId = null,
        ?string $targetLabel = null,
        ?array $detail = null,
        string $result = AuditResult::SUCCESS,
    ): void {
        self::write(
            memberId:    $memberId,
            churchId:    $churchId,
            menuCode:    $menuCode,
            actionType:  $actionType,
            summary:     $summary,
            targetId:    $targetId,
            targetLabel: $targetLabel,
            detail:      $detail,
            result:      $result,
        );
    }

    /**
     * 내부 공통 INSERT. 예외는 LogHelper 로 파일 로깅하고 삼킨다.
     */
    private static function write(
        int $memberId,
        int $churchId,
        string $menuCode,
        string $actionType,
        string $summary,
        string|int|null $targetId,
        ?string $targetLabel,
        ?array $detail,
        string $result,
    ): void {
        try {
            $changeDetail = null;
            if ($detail !== null) {
                $encoded = json_encode($detail, JSON_UNESCAPED_UNICODE);
                // 너무 큰 페이로드는 저장 안 함 (~64KB 가이드라인)
                if ($encoded !== false && strlen($encoded) <= 65000) {
                    $changeDetail = $encoded;
                }
            }

            /** @var AuditLogRepository $repo */
            $repo = app(AuditLogRepository::class);
            $repo->insertLog([
                'church_id'     => $churchId,
                'member_id'     => $memberId,
                'menu_code'     => $menuCode,
                'action_type'   => $actionType,
                'target_id'     => $targetId !== null ? (string) $targetId : null,
                'target_label'  => self::clip($targetLabel, 200),
                'summary'       => self::clip($summary, 500) ?? '',
                'change_detail' => $changeDetail,
                'result'        => $result,
                'ip_address'    => self::resolveIp(),
            ]);
        } catch (\Throwable $e) {
            LogHelper::logWrite(
                "[AuditLogHelper] write error: " . $e->getMessage()
                . " menu={$menuCode} action={$actionType} target={$targetId}",
                "audit"
            );
        }
    }

    /**
     * before/after 비교해서 변경된 필드만 추출
     */
    private static function buildDiff(?array $before, ?array $after, ?array $compareFields): ?array
    {
        if ($before === null && $after === null) {
            return null;
        }

        $before = $before ?? [];
        $after  = $after  ?? [];
        $keys   = $compareFields ?? array_unique(array_merge(array_keys($before), array_keys($after)));

        $diff = [];
        foreach ($keys as $k) {
            $b = $before[$k] ?? null;
            $a = $after[$k]  ?? null;
            if ($b === $a) {
                continue;
            }
            $diff[$k] = ['before' => $b, 'after' => $a];
        }

        return empty($diff) ? null : ['changes' => $diff];
    }

    private static function clip(?string $s, int $max): ?string
    {
        if ($s === null) {
            return null;
        }
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
    }

    private static function resolveIp(): ?string
    {
        try {
            return request()?->ip();
        } catch (\Throwable) {
            return null;
        }
    }
}
