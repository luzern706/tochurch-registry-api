<?php

namespace App\Helpers;

use App\Constants\AppLinkStatus;

/**
 * 교적 ↔ 교회로 앱 연결 상태 판정 (순수 함수 — DB 조회는 MemberRepository::getAppLinkContext 가 담당)
 * 등록 완료 화면·교인 상세·(향후) 교인 목록이 같은 규칙을 쓰도록 공용화.
 */
class AppLinkStatusHelper
{
    /**
     * @param object      $member         reg_members 행 (name, phone, churchero_user_id, unlinked_at)
     * @param object|null $linkedAccount  churchero_user_id 에 해당하는 gh_account 행 (name, phone) — 없으면 null
     * @param int         $candidateCount 같은 교회 미연동 계정 중 휴대폰 일치 수
     * @param bool        $inviteJoined   초대 링크로 가입했고 관리자 연결 승인 대기 중
     */
    public static function resolve(object $member, ?object $linkedAccount, int $candidateCount, bool $inviteJoined = false): string
    {
        if (!empty($member->churchero_user_id)) {
            if ($linkedAccount === null) {
                return AppLinkStatus::MISMATCH;
            }
            $nameOk  = trim((string) $linkedAccount->name) === trim((string) $member->name);
            $phoneOk = self::digits($linkedAccount->phone) !== ''
                && self::digits($linkedAccount->phone) === self::digits($member->phone);
            return ($nameOk && $phoneOk) ? AppLinkStatus::LINKED : AppLinkStatus::MISMATCH;
        }

        if ($inviteJoined) {
            return AppLinkStatus::PENDING;
        }
        if ($candidateCount >= 2) {
            return AppLinkStatus::DUPLICATE;
        }
        if ($candidateCount === 1) {
            return AppLinkStatus::PENDING;
        }
        if (!empty($member->unlinked_at)) {
            return AppLinkStatus::RELEASED;
        }
        return AppLinkStatus::UNLINKED;
    }

    public static function label(string $status): string
    {
        return AppLinkStatus::labels()[$status] ?? $status;
    }

    public static function digits(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }
}
