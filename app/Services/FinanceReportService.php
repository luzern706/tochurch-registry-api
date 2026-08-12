<?php

namespace App\Services;

use App\Constants\ExpenseCategoryGroup;
use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\BudgetRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\ReportRepository;
use Illuminate\Http\JsonResponse;

class FinanceReportService
{
    protected ReportRepository $reportRepository;
    protected ExpenseRepository $expenseRepository;
    protected BudgetRepository $budgetRepository;

    public function __construct(
        ReportRepository $reportRepository,
        ExpenseRepository $expenseRepository,
        BudgetRepository $budgetRepository
    ) {
        $this->reportRepository  = $reportRepository;
        $this->expenseRepository = $expenseRepository;
        $this->budgetRepository  = $budgetRepository;
    }

    /** 월간 보고 — 선택 연/월의 수입·지출 요약, 지출은 해당 연도 예산의 월 배분(연간÷12) 대비 사용률 */
    public function getMonthlyReport(int $authMemberId, int $year, int $month): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $fromDate = sprintf('%04d-%02d-01', $year, $month);
            $toDate   = date('Y-m-t', strtotime($fromDate));

            $incomeTotals    = $this->reportRepository->getOfferingTotals($churchId, $fromDate, $toDate, null);
            $incomeByCategory = $this->reportRepository->getOfferingByCategory($churchId, $fromDate, $toDate);
            $incomeTotal = $incomeTotals['total_amount'];
            $incomeRows = array_map(fn ($row) => [
                'category' => $row->category,
                'amount'   => (int) $row->total_amount,
                'pct'      => $incomeTotal > 0 ? round($row->total_amount / $incomeTotal * 100, 1) : 0,
            ], $incomeByCategory);

            $expenseTotals = $this->expenseRepository->getTotals($churchId, $fromDate, $toDate);
            $expenseTotal  = $expenseTotals['total_amount'];
            $expenseByCategory26 = $this->expenseRepository->getCategoryTotals($churchId, $fromDate, $toDate);
            $expenseByGroup = $this->rollupExpenseToGroups($expenseByCategory26);

            $yearBudgets = $this->budgetRepository->getBudgetList($churchId, $year);
            $budgetByCategory = [];
            foreach ($yearBudgets as $b) {
                $budgetByCategory[$b->category] = (int) $b->amount;
            }

            $expenseRows = [];
            foreach (ExpenseCategoryGroup::ORDER as $category) {
                $amt = $expenseByGroup[$category] ?? 0;
                if ($amt <= 0) {
                    continue;
                }
                $monthlyBudget = isset($budgetByCategory[$category]) ? (int) round($budgetByCategory[$category] / 12) : 0;
                $expenseRows[] = [
                    'category'       => $category,
                    'amount'         => $amt,
                    'pct'            => $expenseTotal > 0 ? round($amt / $expenseTotal * 100, 1) : 0,
                    'monthly_budget' => $monthlyBudget,
                    'rate'           => $monthlyBudget > 0 ? round($amt / $monthlyBudget * 100, 1) : null,
                ];
            }
            usort($expenseRows, fn ($a, $b) => $b['amount'] <=> $a['amount']);

            $receiptSummary = $this->expenseRepository->getReceiptSummary($churchId, ['from_date' => $fromDate, 'to_date' => $toDate]);

            return ApiResponse::success([
                'year' => $year, 'month' => $month, 'from_date' => $fromDate, 'to_date' => $toDate,
                'income_total'  => $incomeTotal,
                'expense_total' => $expenseTotal,
                'net'           => $incomeTotal - $expenseTotal,
                'income_rows'   => $incomeRows,
                'expense_rows'  => $expenseRows,
                'top_expense_category'  => $expenseRows[0] ?? null,
                'receipt_missing_count' => $receiptSummary['missing'],
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceReportService] getMonthlyReport error: " . $e->getMessage(), "finance_report");
            return ApiResponse::fail('INTERNAL_ERROR', '월간 보고 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /** 연간 보고 — 선택 연도(현재 연도면 연초~오늘) 수입·지출 요약, 전년 동기간과 비교 */
    public function getAnnualReport(int $authMemberId, int $year): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $currentYear = (int) date('Y');
            $fromDate = "{$year}-01-01";
            $toDate   = $year >= $currentYear ? date('Y-m-d') : "{$year}-12-31";
            $prevFrom = ($year - 1) . '-01-01';
            $prevTo   = ($year - 1) . '-12-31';

            $incomeTotals     = $this->reportRepository->getOfferingTotals($churchId, $fromDate, $toDate, null);
            $incomeTotalsPrev = $this->reportRepository->getOfferingTotals($churchId, $prevFrom, $prevTo, null);
            $incomeByCategory     = $this->reportRepository->getOfferingByCategory($churchId, $fromDate, $toDate);
            $incomeByCategoryPrev = $this->reportRepository->getOfferingByCategory($churchId, $prevFrom, $prevTo);
            $incomeTotal = $incomeTotals['total_amount'];

            $incomePrevMap = [];
            foreach ($incomeByCategoryPrev as $row) {
                $incomePrevMap[$row->category] = (int) $row->total_amount;
            }
            $incomeRows = array_map(function ($row) use ($incomeTotal, $incomePrevMap) {
                $prevAmt = $incomePrevMap[$row->category] ?? 0;
                return [
                    'category' => $row->category,
                    'amount'   => (int) $row->total_amount,
                    'pct'      => $incomeTotal > 0 ? round($row->total_amount / $incomeTotal * 100, 1) : 0,
                    'yoy_pct'  => $prevAmt > 0 ? round(($row->total_amount - $prevAmt) / $prevAmt * 100, 1) : null,
                ];
            }, $incomeByCategory);
            $incomeChangePct = $incomeTotalsPrev['total_amount'] > 0
                ? round(($incomeTotal - $incomeTotalsPrev['total_amount']) / $incomeTotalsPrev['total_amount'] * 100, 1)
                : null;

            $expenseTotals = $this->expenseRepository->getTotals($churchId, $fromDate, $toDate);
            $expenseTotal  = $expenseTotals['total_amount'];
            $expenseByCategory26 = $this->expenseRepository->getCategoryTotals($churchId, $fromDate, $toDate);
            $expenseByGroup = $this->rollupExpenseToGroups($expenseByCategory26);

            $yearBudgets = $this->budgetRepository->getBudgetList($churchId, $year);
            $budgetByCategory = [];
            foreach ($yearBudgets as $b) {
                $budgetByCategory[$b->category] = (int) $b->amount;
            }
            $totalAnnualBudget = array_sum($budgetByCategory);

            $expenseRows = [];
            foreach (ExpenseCategoryGroup::ORDER as $category) {
                $amt = $expenseByGroup[$category] ?? 0;
                if ($amt <= 0) {
                    continue;
                }
                $budget = $budgetByCategory[$category] ?? 0;
                $expenseRows[] = [
                    'category' => $category,
                    'amount'   => $amt,
                    'pct'      => $expenseTotal > 0 ? round($amt / $expenseTotal * 100, 1) : 0,
                    'budget'   => $budget,
                    'rate'     => $budget > 0 ? round($amt / $budget * 100, 1) : null,
                ];
            }
            usort($expenseRows, fn ($a, $b) => $b['amount'] <=> $a['amount']);

            return ApiResponse::success([
                'year' => $year, 'from_date' => $fromDate, 'to_date' => $toDate,
                'prev_from_date' => $prevFrom, 'prev_to_date' => $prevTo,
                'income_total'  => $incomeTotal,
                'income_change_pct' => $incomeChangePct,
                'expense_total' => $expenseTotal,
                'net'           => $incomeTotal - $expenseTotal,
                'usage_rate'    => $totalAnnualBudget > 0 ? round($expenseTotal / $totalAnnualBudget * 100, 1) : null,
                'income_rows'   => $incomeRows,
                'expense_rows'  => $expenseRows,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceReportService] getAnnualReport error: " . $e->getMessage(), "finance_report");
            return ApiResponse::fail('INTERNAL_ERROR', '연간 보고 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /** 26개 지출 세부 항목 합계를 7개 예산 대분류로 롤업 */
    private function rollupExpenseToGroups(array $categoryTotals): array
    {
        $result = array_fill_keys(ExpenseCategoryGroup::ORDER, 0);
        foreach ($categoryTotals as $row) {
            $group = ExpenseCategoryGroup::groupOf($row['category']);
            if ($group !== null) {
                $result[$group] += $row['total_amount'];
            }
        }
        return $result;
    }
}
