<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberCertRepository;
use Illuminate\Http\JsonResponse;

/**
 * 성도 인증 — 교회 관리자가 소속 성도를 승인/거절/되돌림. 무료 메뉴(교적·재정 신청 전에도 사용).
 */
class MemberCertService
{
    protected MemberCertRepository $repository;

    public function __construct(MemberCertRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->repository->getList($churchId, $filters);
            $data['counts'] = $this->repository->getCounts($churchId);

            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberCertService] getList error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '성도 인증 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /** 승인 — 한 명이든 여러 명이든 account_nos 배열로 받는다(일괄 승인) */
    public function approve(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $requested = array_values(array_unique(array_map('intval', $input['account_nos'])));
            $accounts  = $this->repository->getOwnAccounts($churchId, $requested);
            if (empty($accounts)) {
                return ApiResponse::fail('NOT_FOUND', '승인할 성도를 찾을 수 없습니다.', 404);
            }

            $accountNos = array_map(fn ($a) => (int) $a->account_no, $accounts);
            $this->repository->upsertResult($churchId, $accountNos, 'approved', null, $authMemberId);

            $label = count($accounts) === 1 ? $accounts[0]->name : "{$accounts[0]->name} 외 " . (count($accounts) - 1) . '명';
            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER_CERT,
                actionType:  AuditActionType::APPROVE,
                summary:     "성도 인증 승인: {$label}",
                targetId:    count($accounts) === 1 ? (string) $accountNos[0] : null,
                targetLabel: $label,
                detail:      ['account_nos' => $accountNos],
            );

            return ApiResponse::success(['approved' => count($accountNos), 'skipped' => count($requested) - count($accountNos)]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberCertService] approve error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '성도 인증 승인 중 오류가 발생했습니다.', 500);
        }
    }

    public function reject(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $accountNo = (int) $input['account_no'];
            $accounts  = $this->repository->getOwnAccounts($churchId, [$accountNo]);
            if (empty($accounts)) {
                return ApiResponse::fail('NOT_FOUND', '대상 성도를 찾을 수 없습니다.', 404);
            }

            $reason = $input['reason'] ?? null;
            $this->repository->upsertResult($churchId, [$accountNo], 'rejected', $reason, $authMemberId);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER_CERT,
                actionType:  AuditActionType::REJECT,
                summary:     "성도 인증 거절: {$accounts[0]->name}",
                targetId:    (string) $accountNo,
                targetLabel: $accounts[0]->name,
                detail:      ['account_no' => $accountNo, 'reason' => $reason],
            );

            return ApiResponse::success(['account_no' => $accountNo, 'status' => 'rejected']);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberCertService] reject error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '성도 인증 거절 중 오류가 발생했습니다.', 500);
        }
    }

    /** 승인/거절 결과를 지워 "인증 대기"로 되돌림 */
    public function reset(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $accountNo = (int) $input['account_no'];
            $accounts  = $this->repository->getOwnAccounts($churchId, [$accountNo]);
            if (empty($accounts)) {
                return ApiResponse::fail('NOT_FOUND', '대상 성도를 찾을 수 없습니다.', 404);
            }

            if ($this->repository->deleteResult($churchId, $accountNo) === 0) {
                return ApiResponse::fail('NOT_FOUND', '되돌릴 처리 결과가 없습니다.', 404);
            }

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER_CERT,
                actionType:  AuditActionType::UNASSIGN,
                summary:     "성도 인증 되돌림(대기): {$accounts[0]->name}",
                targetId:    (string) $accountNo,
                targetLabel: $accounts[0]->name,
                detail:      ['account_no' => $accountNo],
            );

            return ApiResponse::success(['account_no' => $accountNo, 'status' => 'pending']);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberCertService] reset error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '성도 인증 되돌림 중 오류가 발생했습니다.', 500);
        }
    }
}
