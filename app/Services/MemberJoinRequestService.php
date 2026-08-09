<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
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
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberJoinRequestService] getList error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '가입 연동 대기 목록 조회 중 오류가 발생했습니다.', 500);
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
