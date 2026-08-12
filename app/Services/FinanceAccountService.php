<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\FinanceAccountRepository;
use Illuminate\Http\JsonResponse;

class FinanceAccountService
{
    private const FILLABLE = [
        'type', 'name', 'bank', 'account_number', 'description',
        'use_for_offering', 'use_for_expense', 'is_active', 'sort_order',
    ];

    protected FinanceAccountRepository $financeAccountRepository;

    public function __construct(FinanceAccountRepository $financeAccountRepository)
    {
        $this->financeAccountRepository = $financeAccountRepository;
    }

    public function getList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $list = $this->financeAccountRepository->getList($churchId, $filters);
            $active = array_filter($list, fn ($a) => (int) $a->is_active === 1);

            return ApiResponse::success([
                'total'         => count($list),
                'active_count'  => count($active),
                'list'          => $list,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceAccountService] getList error: " . $e->getMessage(), "finance_account");
            return ApiResponse::fail('INTERNAL_ERROR', '계좌 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /** 헌금/지출 입력 화면 원장 드롭다운 전용 — usage: 'offering' | 'expense' */
    public function getActiveList(int $authMemberId, string $usage): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $column = $usage === 'expense' ? 'use_for_expense' : 'use_for_offering';
            $list = $this->financeAccountRepository->getActiveList($churchId, $column);

            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceAccountService] getActiveList error: " . $e->getMessage(), "finance_account");
            return ApiResponse::fail('INTERNAL_ERROR', '원장 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function register(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            $data['church_id'] = $churchId;

            $newId = $this->financeAccountRepository->insert($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::FINANCE_SETTING,
                summary:     "계좌 등록: {$data['name']}",
                targetId:    (string) $newId,
                targetLabel: $data['name'],
                created:     $data,
            );

            return ApiResponse::success(['account_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceAccountService] register error: " . $e->getMessage(), "finance_account");
            return ApiResponse::fail('INTERNAL_ERROR', '계좌 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function update(int $authMemberId, int $accountId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $account = $this->financeAccountRepository->getById($accountId);
            if ($account === null || (int) $account->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '계좌를 찾을 수 없습니다.', 404);
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            if (!empty($data)) {
                $this->financeAccountRepository->update($accountId, $data);
            }

            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::FINANCE_SETTING,
                summary:       "계좌 수정: {$account->name}",
                targetId:      (string) $accountId,
                targetLabel:   $account->name,
                before:        (array) $account,
                after:         array_merge((array) $account, $data),
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['account_id' => $accountId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceAccountService] update error: " . $e->getMessage(), "finance_account");
            return ApiResponse::fail('INTERNAL_ERROR', '계좌 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function delete(int $authMemberId, int $accountId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $account = $this->financeAccountRepository->getById($accountId);
            if ($account === null || (int) $account->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '계좌를 찾을 수 없습니다.', 404);
            }

            $this->financeAccountRepository->delete($accountId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::FINANCE_SETTING,
                summary:     "계좌 삭제: {$account->name}",
                targetId:    (string) $accountId,
                targetLabel: $account->name,
                deleted:     (array) $account,
            );

            return ApiResponse::success(['account_id' => $accountId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceAccountService] delete error: " . $e->getMessage(), "finance_account");
            return ApiResponse::fail('INTERNAL_ERROR', '계좌 삭제 중 오류가 발생했습니다.', 500);
        }
    }
}
