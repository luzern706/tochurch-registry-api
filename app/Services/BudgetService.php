<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\ExpenseCategoryGroup;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\BudgetRepository;
use App\Repositories\ExpenseRepository;
use Illuminate\Http\JsonResponse;

class BudgetService
{
    protected BudgetRepository $budgetRepository;
    protected ExpenseRepository $expenseRepository;

    public function __construct(BudgetRepository $budgetRepository, ExpenseRepository $expenseRepository)
    {
        $this->budgetRepository  = $budgetRepository;
        $this->expenseRepository = $expenseRepository;
    }

    // TODO(권한): 예산도 헌금/지출과 동일하게 민감 정보. 추후 재무팀/담당자 권한 도입 시 강화.

    /** 예산 현황 / 연간 예산 탭 공용 — 대분류 7종 전체(예산·집행액·집행률) + 요약 */
    public function getBudgetStatus(int $authMemberId, int $year): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $budgets = $this->budgetRepository->getBudgetList($churchId, $year);
            $byCategory = [];
            foreach ($budgets as $b) {
                $byCategory[$b->category] = $b;
            }

            $currentYear = (int) date('Y');
            $fromDate = "{$year}-01-01";
            $toDate   = $year >= $currentYear ? date('Y-m-d') : "{$year}-12-31";

            $expenseTotals = $this->expenseRepository->getCategoryTotals($churchId, $fromDate, $toDate);
            $usedByGroup = array_fill_keys(ExpenseCategoryGroup::ORDER, 0);
            foreach ($expenseTotals as $row) {
                $group = ExpenseCategoryGroup::groupOf($row['category']);
                if ($group !== null) {
                    $usedByGroup[$group] += $row['total_amount'];
                }
            }

            $rows = [];
            $totalBudget = 0;
            $totalUsed   = 0;
            $riskCount   = 0;
            foreach (ExpenseCategoryGroup::ORDER as $category) {
                $budget = isset($byCategory[$category]) ? (int) $byCategory[$category]->amount : 0;
                $used   = (int) ($usedByGroup[$category] ?? 0);
                $rate   = $budget > 0 ? round($used / $budget * 100, 1) : 0.0;
                $status = $rate >= 90 ? 'danger' : ($rate >= 80 ? 'warning' : 'normal');
                if ($rate >= 90) {
                    $riskCount++;
                }

                $rows[] = [
                    'budget_id' => $byCategory[$category]->id ?? null,
                    'category'  => $category,
                    'budget'    => $budget,
                    'used'      => $used,
                    'remaining' => $budget - $used,
                    'rate'      => $rate,
                    'status'    => $status,
                    'formula'   => $byCategory[$category]->formula ?? null,
                ];

                $totalBudget += $budget;
                $totalUsed   += $used;
            }

            $lastUpdated = $this->budgetRepository->getLastUpdated($churchId, $year);

            return ApiResponse::success([
                'year'             => $year,
                'from_date'        => $fromDate,
                'to_date'          => $toDate,
                'rows'             => $rows,
                'total_budget'     => $totalBudget,
                'total_used'       => $totalUsed,
                'total_remaining'  => $totalBudget - $totalUsed,
                'overall_rate'     => $totalBudget > 0 ? round($totalUsed / $totalBudget * 100, 1) : null,
                'risk_count'       => $riskCount,
                'last_updated_at'       => $lastUpdated->updated_at ?? null,
                'last_updated_by_name'  => $lastUpdated->updated_by_name ?? null,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[BudgetService] getBudgetStatus error: " . $e->getMessage(), "budget");
            return ApiResponse::fail('INTERNAL_ERROR', '예산 현황 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerBudget(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $year     = (int) $input['year'];
            $category = $input['category'];

            $existing = $this->budgetRepository->getBudgetByCategory($churchId, $year, $category);
            if ($existing !== null) {
                return ApiResponse::fail('DUPLICATE_DATA', "{$year}년 {$category} 예산이 이미 등록되어 있습니다.", 400);
            }

            $newId = $this->budgetRepository->insertBudget([
                'church_id'  => $churchId,
                'year'       => $year,
                'category'   => $category,
                'amount'     => (int) $input['amount'],
                'formula'    => $input['formula'] ?? null,
                'updated_by' => $authMemberId,
            ]);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::BUDGET,
                summary:     "예산 등록: {$year}년 {$category} {$input['amount']}원",
                targetId:    (string) $newId,
                targetLabel: "{$year}년 {$category}",
                created:     ['year' => $year, 'category' => $category, 'amount' => (int) $input['amount']],
            );

            return ApiResponse::success(['budget_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[BudgetService] registerBudget error: " . $e->getMessage(), "budget");
            return ApiResponse::fail('INTERNAL_ERROR', '예산 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateBudget(int $authMemberId, int $budgetId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $budget = $this->budgetRepository->getBudgetById($budgetId);
            if ($budget === null || (int) $budget->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '예산 항목을 찾을 수 없습니다.', 404);
            }

            $data = array_intersect_key($input, array_flip(['amount', 'formula']));
            $data['updated_by'] = $authMemberId;
            $this->budgetRepository->updateBudget($budgetId, $data);

            $beforeArr = (array) $budget;
            $afterArr  = array_merge($beforeArr, $data);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::BUDGET,
                summary:       "예산 수정: {$budget->year}년 {$budget->category}",
                targetId:      (string) $budgetId,
                targetLabel:   "{$budget->year}년 {$budget->category}",
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys(array_diff_key($data, ['updated_by' => true])),
            );

            return ApiResponse::success(['budget_id' => $budgetId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[BudgetService] updateBudget error: " . $e->getMessage(), "budget");
            return ApiResponse::fail('INTERNAL_ERROR', '예산 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteBudget(int $authMemberId, int $budgetId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $budget = $this->budgetRepository->getBudgetById($budgetId);
            if ($budget === null || (int) $budget->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '예산 항목을 찾을 수 없습니다.', 404);
            }

            $this->budgetRepository->deleteBudget($budgetId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::BUDGET,
                summary:     "예산 삭제: {$budget->year}년 {$budget->category} {$budget->amount}원",
                targetId:    (string) $budgetId,
                targetLabel: "{$budget->year}년 {$budget->category}",
                deleted:     ['year' => $budget->year, 'category' => $budget->category, 'amount' => (int) $budget->amount],
            );

            return ApiResponse::success(['budget_id' => $budgetId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[BudgetService] deleteBudget error: " . $e->getMessage(), "budget");
            return ApiResponse::fail('INTERNAL_ERROR', '예산 삭제 중 오류가 발생했습니다.', 500);
        }
    }
}
