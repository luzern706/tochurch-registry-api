<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AppLinkStatusHelper;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberJoinRequestRepository;
use Illuminate\Http\JsonResponse;

class MemberJoinRequestService
{
    protected MemberJoinRequestRepository $repository;

    public function __construct(MemberJoinRequestRepository $repository)
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

            $data = $this->repository->getPendingList($churchId, $filters);

            // 초대 링크 가입 건: 앱 계정 ↔ 초대 교적의 이름·휴대폰 일치 여부 (관리자 판단 근거)
            foreach ($data['list'] as $row) {
                if ($row->invite !== null) {
                    $row->invite->name_match  = trim((string) $row->invite->member_name) === trim((string) $row->name);
                    $row->invite->phone_match = AppLinkStatusHelper::digits($row->invite->member_phone) !== ''
                        && AppLinkStatusHelper::digits($row->invite->member_phone) === AppLinkStatusHelper::digits($row->phone);
                }
            }

            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberJoinRequestService] getList error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '가입 연동 대기 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 초대 링크 가입 승인 — 초대 대상 교적에 앱 계정을 연결 (B안: 링크 가입은 자동 연결하지 않고 관리자가 확인 후 연결)
     */
    public function approveInvite(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $accountNo = (int) $input['account_no'];
            $account = $this->repository->getAccountById($accountNo);
            if ($account === null || (int) $account->church_no !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '대상 계정을 찾을 수 없습니다.', 404);
            }

            $invite = $this->repository->getJoinedInvitesByAccounts($churchId, [$accountNo])[$accountNo] ?? null;
            if ($invite === null) {
                return ApiResponse::fail('NOT_FOUND', '초대 링크 가입 기록이 없거나 이미 연결된 교적입니다.', 404);
            }
            if ($this->repository->isAccountLinked($churchId, $accountNo)) {
                return ApiResponse::fail('DUPLICATE_DATA', '이 계정은 이미 다른 교적에 연결되어 있습니다.', 400);
            }

            if ($this->repository->linkMemberAccount((int) $invite->member_id, $accountNo) === 0) {
                return ApiResponse::fail('DUPLICATE_DATA', '초대 대상 교적이 이미 다른 계정과 연결되어 있습니다.', 400);
            }

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER,
                actionType:  AuditActionType::ASSIGN,
                summary:     "초대 가입 연결: 교적 {$invite->member_name} ↔ 앱 계정 {$account->name}",
                targetId:    (string) $invite->member_id,
                targetLabel: $invite->member_name,
                detail:      ['invite_id' => (int) $invite->invite_id, 'account_no' => $accountNo],
            );

            return ApiResponse::success(['member_id' => (int) $invite->member_id, 'account_no' => $accountNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberJoinRequestService] approveInvite error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '초대 가입 연결 중 오류가 발생했습니다.', 500);
        }
    }

    public function hold(int $authMemberId, array $input): JsonResponse
    {
        return $this->setStatus($authMemberId, $input, 'held', AuditActionType::HOLD, '보류');
    }

    public function reject(int $authMemberId, array $input): JsonResponse
    {
        return $this->setStatus($authMemberId, $input, 'rejected', AuditActionType::REJECT, '반려');
    }

    private function setStatus(int $authMemberId, array $input, string $status, string $actionType, string $actionLabel): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $accountNo = (int) $input['account_no'];
            $account = $this->repository->getAccountById($accountNo);
            if ($account === null || (int) $account->church_no !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '대상 계정을 찾을 수 없습니다.', 404);
            }

            $reason = $input['reason'] ?? null;
            $this->repository->upsertStatus($churchId, $accountNo, $status, $reason, $authMemberId);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER,
                actionType:  $actionType,
                summary:     "가입 연동 {$actionLabel}: {$account->name} ({$account->phone})",
                targetId:    (string) $accountNo,
                targetLabel: $account->name,
                detail:      ['account_no' => $accountNo, 'reason' => $reason],
            );

            return ApiResponse::success(['account_no' => $accountNo, 'status' => $status]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberJoinRequestService] setStatus error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '처리 중 오류가 발생했습니다.', 500);
        }
    }
}
