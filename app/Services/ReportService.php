<?php

namespace App\Services;

use App\Constants\ExpenseCategoryGroup;
use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\BudgetRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\MemberRepository;
use App\Repositories\ReportRepository;
use Illuminate\Http\JsonResponse;

class ReportService
{
    protected ReportRepository $reportRepository;
    protected MemberRepository $memberRepository;
    protected ExpenseRepository $expenseRepository;
    protected BudgetRepository $budgetRepository;

    public function __construct(
        ReportRepository $reportRepository,
        MemberRepository $memberRepository,
        ExpenseRepository $expenseRepository,
        BudgetRepository $budgetRepository
    ) {
        $this->reportRepository  = $reportRepository;
        $this->memberRepository  = $memberRepository;
        $this->expenseRepository = $expenseRepository;
        $this->budgetRepository  = $budgetRepository;
    }

    // TODO(권한): 통계 (특히 헌금) 는 추후 재무팀/관리자 권한 도입 시 접근 제한 강화.
    // TODO(캐싱): 호출량 증가 시 일별/월별 집계를 Redis 캐싱 적용 검토.

    public function getMemberStats(int $authMemberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            return ApiResponse::success([
                'total'                  => $this->reportRepository->countMembers($churchId),
                'by_gender'              => $this->reportRepository->getMembersByGender($churchId),
                'by_age_group'           => $this->reportRepository->getMembersByAgeGroup($churchId),
                'by_position'            => $this->reportRepository->getMembersByPosition($churchId),
                'by_attendance_grade'    => $this->reportRepository->getMembersByAttendanceGrade($churchId),
                'by_status'              => $this->reportRepository->getMembersByStatus($churchId),
                'by_member_type'         => $this->reportRepository->getMembersByMemberType($churchId),
                'recent_7d'              => $this->reportRepository->countRecentMembers($churchId, now()->subDays(7)->toDateString()),
                'recent_30d'             => $this->reportRepository->countRecentMembers($churchId, now()->subDays(30)->toDateString()),
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getMemberStats error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '교인 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getAttendanceStats(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $fromDate = $input['from_date'];
            $toDate   = $input['to_date'];
            $serviceId      = isset($input['service_id']) ? (int) $input['service_id'] : null;
            $organizationId = isset($input['organization_id']) ? (int) $input['organization_id'] : null;

            $byStatus = $this->reportRepository->getAttendanceCountByStatus($churchId, $serviceId, $fromDate, $toDate, $organizationId);
            $trend    = $this->reportRepository->getAttendanceTrendDaily($churchId, $serviceId, $fromDate, $toDate, $organizationId);

            // 출석률 계산 (present / (present + absent))
            $present = 0;
            $absent  = 0;
            foreach ($byStatus as $row) {
                if ($row->status === 'present') {
                    $present = (int) $row->count;
                } elseif ($row->status === 'absent') {
                    $absent = (int) $row->count;
                }
            }
            $checked = $present + $absent;
            $rate    = $checked > 0 ? round($present / $checked * 100, 2) : null;

            return ApiResponse::success([
                'from_date'        => $fromDate,
                'to_date'          => $toDate,
                'service_id'       => $serviceId,
                'organization_id'  => $organizationId,
                'by_status'        => $byStatus,
                'attendance_rate'  => $rate,
                'present_count'    => $present,
                'absent_count'     => $absent,
                'daily_trend'      => $trend,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getAttendanceStats error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '출석 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getStatsByService(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $rows = $this->reportRepository->getStatsByService($churchId, $input['from_date'], $input['to_date']);

            $list = array_map(function ($row) {
                $checked  = (int)$row->present_count + (int)$row->absent_count;
                $rate     = $checked > 0 ? round((int)$row->present_count / $checked * 100, 1) : null;
                $avgPresent = (int)$row->date_count > 0
                    ? round((int)$row->present_count / (int)$row->date_count, 1) : null;
                return [
                    'service_id'   => $row->service_id,
                    'service_name' => $row->service_name,
                    'avg_present'  => $avgPresent,
                    'rate'         => $rate,
                ];
            }, $rows);

            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getStatsByService error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '예배별 출석 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getMemberRateDistribution(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $rows  = $this->reportRepository->getMemberRateDistribution($churchId, $input['from_date'], $input['to_date']);
            $total = $this->reportRepository->countMembers($churchId);

            $bucketMap = [];
            foreach ($rows as $row) {
                $bucketMap[$row->bucket] = (int)$row->count;
            }

            $buckets = ['90_plus', '80_89', '70_79', '60_69', 'under_60', 'no_record'];
            $list = array_map(function ($key) use ($bucketMap, $total) {
                $count = $bucketMap[$key] ?? 0;
                return [
                    'bucket'  => $key,
                    'count'   => $count,
                    'percent' => $total > 0 ? round($count / $total * 100, 1) : null,
                ];
            }, $buckets);

            return ApiResponse::success(['total' => $total, 'list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getMemberRateDistribution error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '개인 출석률 분포 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getAttendanceStatsByOrg(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $fromDate       = $input['from_date'];
            $toDate         = $input['to_date'];
            $serviceId      = isset($input['service_id'])      ? (int) $input['service_id']      : null;
            $organizationId = isset($input['organization_id']) ? (int) $input['organization_id'] : null;

            $rows = $this->reportRepository->getAttendanceStatsByOrg($churchId, $serviceId, $fromDate, $toDate, $organizationId);

            $list = array_map(function ($row) {
                $checked = (int)$row->present_count + (int)$row->absent_count;
                $rate    = $checked > 0 ? round((int)$row->present_count / $checked * 100, 1) : null;
                $avgPresent = (int)$row->date_count > 0
                    ? round((int)$row->present_count / (int)$row->date_count, 1)
                    : null;

                return [
                    'org_id'        => $row->org_id,
                    'org_name'      => $row->parent_name
                        ? $row->parent_name . ' > ' . $row->org_name
                        : $row->org_name,
                    'present_count' => (int)$row->present_count,
                    'absent_count'  => (int)$row->absent_count,
                    'member_count'  => (int)$row->member_count,
                    'date_count'    => (int)$row->date_count,
                    'avg_rate'      => $rate,
                    'avg_present'   => $avgPresent,
                ];
            }, $rows);

            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getAttendanceStatsByOrg error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '조직별 출석 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getOfferingStats(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $fromDate = $input['from_date'];
            $toDate   = $input['to_date'];
            $category = $input['category'] ?? null;

            $totals       = $this->reportRepository->getOfferingTotals($churchId, $fromDate, $toDate, $category);
            $byCategory   = $this->reportRepository->getOfferingByCategory($churchId, $fromDate, $toDate);
            $monthlyTrend = $this->reportRepository->getOfferingMonthlyTrend($churchId, $fromDate, $toDate, $category);
            $pivot        = $this->reportRepository->getOfferingCategoryMonthlyPivot($churchId, $fromDate, $toDate);

            return ApiResponse::success([
                'from_date'      => $fromDate,
                'to_date'        => $toDate,
                'category'       => $category,
                'count'          => $totals['count'],
                'total_amount'   => $totals['total_amount'],
                'by_category'    => $byCategory,
                'monthly_trend'  => $monthlyTrend,
                'pivot'          => $pivot,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getOfferingStats error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '헌금 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getVisitStats(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $fromDate = $input['from_date'];
            $toDate   = $input['to_date'];
            $visitorMemberId = isset($input['visitor_member_id']) ? (int) $input['visitor_member_id'] : null;

            $count       = $this->reportRepository->getVisitCount($churchId, $fromDate, $toDate, $visitorMemberId);
            $byVisitor   = $this->reportRepository->getVisitByVisitor($churchId, $fromDate, $toDate, 10);
            $byType      = $this->reportRepository->getVisitByType($churchId, $fromDate, $toDate);

            return ApiResponse::success([
                'from_date'         => $fromDate,
                'to_date'           => $toDate,
                'visitor_member_id' => $visitorMemberId,
                'count'             => $count,
                'top_visitors'      => $byVisitor,
                'by_type'           => $byType,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getVisitStats error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '심방 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 한 화면 요약: 교인 총원 / 이번 주 출석률 / 이번 달 헌금 / 이번 달 심방 건수
     */
    public function getDashboard(int $authMemberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $today        = date('Y-m-d');
            $weekStart    = date('Y-m-d', strtotime('monday this week'));
            $monthStart   = date('Y-m-01');
            $monthEnd     = date('Y-m-t');

            // 교인 총원
            $memberTotal = $this->reportRepository->countMembers($churchId);

            // 이번 주 출석률
            $attByStatus = $this->reportRepository->getAttendanceCountByStatus($churchId, null, $weekStart, $today, null);
            $present = 0;
            $absent  = 0;
            foreach ($attByStatus as $row) {
                if ($row->status === 'present') {
                    $present = (int) $row->count;
                } elseif ($row->status === 'absent') {
                    $absent = (int) $row->count;
                }
            }
            $checked = $present + $absent;
            $rate    = $checked > 0 ? round($present / $checked * 100, 2) : null;

            // 이번 달 헌금
            $offering = $this->reportRepository->getOfferingTotals($churchId, $monthStart, $monthEnd, null);

            // 이번 달 심방 건수
            $visitCount = $this->reportRepository->getVisitCount($churchId, $monthStart, $monthEnd, null);

            // 이번 달 활동 봉사자 / 교육 참여자 (현재 스냅샷, 기간과 무관)
            $activeVolunteers = $this->reportRepository->countActiveVolunteers($churchId);
            $activeEducationParticipants = $this->reportRepository->countActiveEducationParticipants($churchId);

            return ApiResponse::success([
                'as_of_date'         => $today,
                'member_total'       => $memberTotal,
                'this_week' => [
                    'from'            => $weekStart,
                    'to'              => $today,
                    'attendance_rate' => $rate,
                    'present_count'   => $present,
                    'absent_count'    => $absent,
                ],
                'this_month' => [
                    'from'                          => $monthStart,
                    'to'                            => $monthEnd,
                    'offering_total'                => $offering['total_amount'],
                    'offering_count'                => $offering['count'],
                    'visit_count'                   => $visitCount,
                    'active_volunteers'             => $activeVolunteers,
                    'active_education_participants' => $activeEducationParticipants,
                ],
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getDashboard error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '대시보드 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 재정 대시보드 (`/finance/dashboard`) 전용 요약.
     *
     * "현재 잔액"은 실제 계좌 잔액 추적 테이블이 없어 전체 기간 누적 헌금 − 누적 지출로
     * 근사 계산한다(시작 잔액을 0으로 가정) — 실제 은행 잔액과 다를 수 있음, 프론트에서 안내 문구 표시.
     */
    public function getFinanceDashboard(int $authMemberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $today      = date('Y-m-d');
            $monthStart = date('Y-m-01');
            $monthEnd   = date('Y-m-t');
            $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
            $lastMonthEnd   = date('Y-m-t', strtotime('first day of last month'));

            $thisMonthOffering = $this->reportRepository->getOfferingTotals($churchId, $monthStart, $monthEnd, null);
            $lastMonthOffering = $this->reportRepository->getOfferingTotals($churchId, $lastMonthStart, $lastMonthEnd, null);
            $incomeChangePct = $lastMonthOffering['total_amount'] > 0
                ? round(($thisMonthOffering['total_amount'] - $lastMonthOffering['total_amount']) / $lastMonthOffering['total_amount'] * 100, 1)
                : null;

            $thisMonthExpense = $this->expenseRepository->getTotals($churchId, $monthStart, $monthEnd);

            $currentYear = (int) date('Y');
            $yearBudgets = $this->budgetRepository->getBudgetList($churchId, $currentYear);
            $totalAnnualBudget = array_sum(array_map(fn ($b) => (int) $b->amount, $yearBudgets));
            $monthlyBudget = $totalAnnualBudget > 0 ? (int) round($totalAnnualBudget / 12) : 0;
            $budgetUsageRate = $monthlyBudget > 0
                ? round($thisMonthExpense['total_amount'] / $monthlyBudget * 100, 1)
                : null;

            $cumulativeOffering = $this->reportRepository->getOfferingCumulativeTotal($churchId);
            $cumulativeExpense  = $this->expenseRepository->getCumulativeTotal($churchId);
            $balance = $cumulativeOffering - $cumulativeExpense;
            $monthsRunway = $thisMonthExpense['total_amount'] > 0
                ? round($balance / $thisMonthExpense['total_amount'], 1)
                : null;

            $trendFrom = date('Y-m-01', strtotime('-5 months'));
            $incomeTrend  = $this->reportRepository->getOfferingMonthlyTrend($churchId, $trendFrom, $today, null);
            $expenseTrend = $this->expenseRepository->getMonthlyTrend($churchId, $trendFrom, $today);

            $receiptSummary = $this->expenseRepository->getReceiptSummary($churchId, []);

            // 예산 대비 집행률 TOP5 (예산 관리 화면과 동일한 26→7 매핑 롤업 로직)
            $yearFrom = "{$currentYear}-01-01";
            $yearTo   = $currentYear >= (int) date('Y') ? $today : "{$currentYear}-12-31";
            $expenseTotalsByCategory = $this->expenseRepository->getCategoryTotals($churchId, $yearFrom, $yearTo);
            $usedByGroup = array_fill_keys(ExpenseCategoryGroup::ORDER, 0);
            foreach ($expenseTotalsByCategory as $row) {
                $group = ExpenseCategoryGroup::groupOf($row['category']);
                if ($group !== null) {
                    $usedByGroup[$group] += $row['total_amount'];
                }
            }
            $budgetByCategory = [];
            foreach ($yearBudgets as $b) {
                $budgetByCategory[$b->category] = (int) $b->amount;
            }
            $top5 = [];
            foreach (ExpenseCategoryGroup::ORDER as $category) {
                $used   = (int) ($usedByGroup[$category] ?? 0);
                $budget = $budgetByCategory[$category] ?? 0;
                $rate   = $budget > 0 ? round($used / $budget * 100, 1) : null;
                $top5[] = [
                    'category' => $category,
                    'used'     => $used,
                    'budget'   => $budget,
                    'rate'     => $rate,
                    'status'   => $rate === null ? null : ($rate >= 90 ? 'danger' : ($rate >= 80 ? 'warning' : 'normal')),
                ];
            }
            usort($top5, fn ($a, $b) => $b['used'] <=> $a['used']);
            $top5 = array_slice($top5, 0, 5);

            return ApiResponse::success([
                'as_of_date' => $today,
                'this_month' => [
                    'from'                 => $monthStart,
                    'to'                   => $monthEnd,
                    'income_total'         => $thisMonthOffering['total_amount'],
                    'income_change_pct'    => $incomeChangePct,
                    'expense_total'        => $thisMonthExpense['total_amount'],
                    'monthly_budget'       => $monthlyBudget,
                    'budget_usage_rate'    => $budgetUsageRate,
                ],
                'balance' => [
                    'amount'         => $balance,
                    'months_runway'  => $monthsRunway,
                    'is_estimated'   => true,
                ],
                'income_trend'  => $incomeTrend,
                'expense_trend' => $expenseTrend,
                'receipt_missing_count' => $receiptSummary['missing'],
                'top5_categories' => $top5,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getFinanceDashboard error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '재정 대시보드 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 재정 통계 (`/finance/statistics`) 전용 — Overview/수입통계/지출통계/재정흐름분석/기간별비교
     * 5개 탭이 전부 이 하나의 응답에서 필요한 값을 파생해 쓴다.
     *
     * "직전 기간"은 캘린더상의 "지난 달"이 아니라 선택 기간과 정확히 같은 일수의 바로 앞 구간이다
     * (예: 8/1~8/9 조회 시 직전 기간은 7/23~7/31). 정확한 달력 단위 비교가 아님을 프론트에서 안내한다.
     */
    public function getFinanceStats(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $fromDate = $input['from_date'];
            $toDate   = $input['to_date'];

            $periodDays = (int) ((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1;
            $prevTo     = date('Y-m-d', strtotime($fromDate . ' -1 day'));
            $prevFrom   = date('Y-m-d', strtotime($prevTo . ' -' . ($periodDays - 1) . ' days'));

            // ── 수입 ──
            $incomeTotals     = $this->reportRepository->getOfferingTotals($churchId, $fromDate, $toDate, null);
            $incomePrevTotals = $this->reportRepository->getOfferingTotals($churchId, $prevFrom, $prevTo, null);
            $incomeByCategory     = $this->reportRepository->getOfferingByCategory($churchId, $fromDate, $toDate);
            $incomePrevByCategory = $this->reportRepository->getOfferingByCategory($churchId, $prevFrom, $prevTo);
            $incomeMonthlyTrend = $this->reportRepository->getOfferingMonthlyTrend($churchId, $fromDate, $toDate, null);
            $incomeWeeklyTrend  = $this->reportRepository->getOfferingWeeklyTrend($churchId, $fromDate, $toDate);

            $incomeTotal = $incomeTotals['total_amount'];
            $incomeByCategoryPct = array_map(fn ($row) => [
                'category'     => $row->category,
                'total_amount' => (int) $row->total_amount,
                'pct'          => $incomeTotal > 0 ? round($row->total_amount / $incomeTotal * 100, 1) : 0,
            ], $incomeByCategory);

            $incomePrevMap = [];
            foreach ($incomePrevByCategory as $row) {
                $incomePrevMap[$row->category] = (int) $row->total_amount;
            }
            [$incomeRising, $incomeFalling] = $this->findMomExtremes($incomeByCategoryPct, $incomePrevMap);

            $incomeChangePct = $incomePrevTotals['total_amount'] > 0
                ? round(($incomeTotal - $incomePrevTotals['total_amount']) / $incomePrevTotals['total_amount'] * 100, 1)
                : null;

            // ── 지출 ──
            $expenseTotals     = $this->expenseRepository->getTotals($churchId, $fromDate, $toDate);
            $expensePrevTotals = $this->expenseRepository->getTotals($churchId, $prevFrom, $prevTo);
            $expenseByCategory26     = $this->expenseRepository->getCategoryTotals($churchId, $fromDate, $toDate);
            $expensePrevByCategory26 = $this->expenseRepository->getCategoryTotals($churchId, $prevFrom, $prevTo);
            $expenseMonthlyTrend = $this->expenseRepository->getMonthlyTrend($churchId, $fromDate, $toDate);
            $expenseWeeklyTrend  = $this->expenseRepository->getWeeklyTrend($churchId, $fromDate, $toDate);
            $receiptSummary = $this->expenseRepository->getReceiptSummary($churchId, ['from_date' => $fromDate, 'to_date' => $toDate]);

            $expenseTotal = $expenseTotals['total_amount'];
            $expenseByGroup     = $this->rollupExpenseToGroups($expenseByCategory26);
            $expensePrevByGroup = $this->rollupExpenseToGroups($expensePrevByCategory26);

            $expenseByGroupPct = [];
            foreach (ExpenseCategoryGroup::ORDER as $category) {
                $amt = $expenseByGroup[$category] ?? 0;
                if ($amt <= 0) {
                    continue;
                }
                $expenseByGroupPct[] = [
                    'category'     => $category,
                    'total_amount' => $amt,
                    'pct'          => $expenseTotal > 0 ? round($amt / $expenseTotal * 100, 1) : 0,
                ];
            }
            usort($expenseByGroupPct, fn ($a, $b) => $b['total_amount'] <=> $a['total_amount']);

            [$expenseSurging] = $this->findMomExtremes($expenseByGroupPct, $expensePrevByGroup, true);

            $expenseChangePct = $expensePrevTotals['total_amount'] > 0
                ? round(($expenseTotal - $expensePrevTotals['total_amount']) / $expensePrevTotals['total_amount'] * 100, 1)
                : null;

            // ── 예산(연간, 연초~오늘 YTD 기준 — 선택 기간과 무관하게 항상 올해 전체로 계산) ──
            $currentYear = (int) date('Y');
            $yearFrom = "{$currentYear}-01-01";
            $yearTo   = date('Y-m-d');
            $yearBudgets = $this->budgetRepository->getBudgetList($churchId, $currentYear);
            $budgetByCategory = [];
            foreach ($yearBudgets as $b) {
                $budgetByCategory[$b->category] = (int) $b->amount;
            }
            $totalAnnualBudget = array_sum($budgetByCategory);
            $monthlyBudget = $totalAnnualBudget > 0 ? (int) round($totalAnnualBudget / 12) : 0;

            $ytdExpenseByCategory26 = $this->expenseRepository->getCategoryTotals($churchId, $yearFrom, $yearTo);
            $ytdByGroup = $this->rollupExpenseToGroups($ytdExpenseByCategory26);

            $budgetStatus = [];
            foreach (ExpenseCategoryGroup::ORDER as $category) {
                $budget = $budgetByCategory[$category] ?? 0;
                $used   = $ytdByGroup[$category] ?? 0;
                $rate   = $budget > 0 ? round($used / $budget * 100, 1) : null;
                $budgetStatus[] = [
                    'category'      => $category,
                    'annual_budget' => $budget,
                    'ytd_used'      => $used,
                    'rate'          => $rate,
                    'status'        => $rate === null ? null : ($rate >= 90 ? 'danger' : ($rate >= 80 ? 'warning' : 'normal')),
                ];
            }

            $periodMonths = max($periodDays / 30.44, 0.1);
            $budgetUsageRatePeriod = $monthlyBudget > 0
                ? round($expenseTotal / ($monthlyBudget * $periodMonths) * 100, 1)
                : null;

            // ── 순수익(수입-지출) 월별 병합 — 재정흐름분석/비교 탭용 ──
            $incomeByYm = [];
            foreach ($incomeMonthlyTrend as $r) {
                $incomeByYm[$r->ym] = (int) $r->total_amount;
            }
            $expenseByYm = [];
            foreach ($expenseMonthlyTrend as $r) {
                $expenseByYm[$r['ym']] = $r['total_amount'];
            }
            $allYm = array_unique(array_merge(array_keys($incomeByYm), array_keys($expenseByYm)));
            sort($allYm);
            $netMonthly = [];
            $positiveMonths = 0;
            $negativeMonths = 0;
            foreach ($allYm as $ym) {
                $inc = $incomeByYm[$ym] ?? 0;
                $exp = $expenseByYm[$ym] ?? 0;
                $net = $inc - $exp;
                $netMonthly[] = ['ym' => $ym, 'income' => $inc, 'expense' => $exp, 'net' => $net];
                $net >= 0 ? $positiveMonths++ : $negativeMonths++;
            }

            // ── 이상 징후 (실제 계산값 기준 — 지출 급증 30%↑, 연간 예산 90%↑) ──
            $anomalies = [];
            foreach ($expenseByGroupPct as $row) {
                $prevAmt = $expensePrevByGroup[$row['category']] ?? 0;
                if ($prevAmt > 0) {
                    $growth = round(($row['total_amount'] - $prevAmt) / $prevAmt * 100, 1);
                    if ($growth >= 30) {
                        $anomalies[] = [
                            'type' => 'expense_growth', 'category' => $row['category'], 'value' => $growth,
                            'message' => "{$row['category']} 지출이 직전 동일기간 대비 {$growth}% 증가했습니다.",
                        ];
                    }
                }
            }
            foreach ($budgetStatus as $row) {
                if ($row['rate'] !== null && $row['rate'] >= 90) {
                    $anomalies[] = [
                        'type' => 'budget_risk', 'category' => $row['category'], 'value' => $row['rate'],
                        'message' => "{$row['category']}가 연간 예산의 {$row['rate']}%를 사용했습니다.",
                    ];
                }
            }

            return ApiResponse::success([
                'from_date' => $fromDate, 'to_date' => $toDate, 'period_days' => $periodDays,
                'prev_from_date' => $prevFrom, 'prev_to_date' => $prevTo,
                'income' => [
                    'total'            => $incomeTotal,
                    'count'            => $incomeTotals['count'],
                    'prev_total'       => $incomePrevTotals['total_amount'],
                    'change_pct'       => $incomeChangePct,
                    'by_category'      => $incomeByCategoryPct,
                    'top_category'     => $incomeByCategoryPct[0] ?? null,
                    'rising_category'  => $incomeRising,
                    'falling_category' => $incomeFalling,
                    'monthly_trend'    => array_map(fn ($r) => ['ym' => $r->ym, 'total_amount' => (int) $r->total_amount], $incomeMonthlyTrend),
                    'weekly_trend'     => $incomeWeeklyTrend,
                    // 기간별 비교 탭용 — 현재/직전 기간 카테고리별 금액을 나란히 (현재 기간 상위 5개 기준)
                    'category_comparison' => array_map(
                        fn ($row) => ['category' => $row['category'], 'current' => $row['total_amount'], 'prev' => $incomePrevMap[$row['category']] ?? 0],
                        array_slice($incomeByCategoryPct, 0, 5)
                    ),
                ],
                'expense' => [
                    'total'                => $expenseTotal,
                    'count'                => $expenseTotals['count'],
                    'prev_total'           => $expensePrevTotals['total_amount'],
                    'change_pct'           => $expenseChangePct,
                    'by_category_group'    => $expenseByGroupPct,
                    'top_category'         => $expenseByGroupPct[0] ?? null,
                    'surging_category'     => $expenseSurging,
                    'monthly_trend'        => $expenseMonthlyTrend,
                    'weekly_trend'         => $expenseWeeklyTrend,
                    'category_comparison'  => array_map(
                        fn ($row) => ['category' => $row['category'], 'current' => $row['total_amount'], 'prev' => $expensePrevByGroup[$row['category']] ?? 0],
                        array_slice($expenseByGroupPct, 0, 5)
                    ),
                    'receipt_total'        => $receiptSummary['total'],
                    'receipt_missing'      => $receiptSummary['missing'],
                    'receipt_missing_rate' => $receiptSummary['total'] > 0 ? round($receiptSummary['missing'] / $receiptSummary['total'] * 100, 1) : null,
                ],
                'net' => [
                    'total'           => $incomeTotal - $expenseTotal,
                    'prev_total'      => $incomePrevTotals['total_amount'] - $expensePrevTotals['total_amount'],
                    'monthly'         => $netMonthly,
                    'positive_months' => $positiveMonths,
                    'negative_months' => $negativeMonths,
                ],
                'budget' => [
                    'year'               => $currentYear,
                    'annual_total'       => $totalAnnualBudget,
                    'monthly_budget'     => $monthlyBudget,
                    'usage_rate_period'  => $budgetUsageRatePeriod,
                    'status'             => $budgetStatus,
                ],
                'anomalies' => $anomalies,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getFinanceStats error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '재정 통계 조회 중 오류가 발생했습니다.', 500);
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

    /**
     * 전기 대비 가장 많이 증가/감소한 카테고리를 찾는다.
     * @return array{0: array{category:string,mom_pct:float}|null, 1: array{category:string,mom_pct:float}|null}
     */
    private function findMomExtremes(array $currentRows, array $prevMap, bool $onlyRising = false): array
    {
        $rising = null;
        $falling = null;
        foreach ($currentRows as $row) {
            $prevAmt = $prevMap[$row['category']] ?? 0;
            if ($prevAmt <= 0) {
                continue;
            }
            $pct = round(($row['total_amount'] - $prevAmt) / $prevAmt * 100, 1);
            if ($rising === null || $pct > $rising['mom_pct']) {
                $rising = ['category' => $row['category'], 'mom_pct' => $pct];
            }
            if (!$onlyRising && ($falling === null || $pct < $falling['mom_pct'])) {
                $falling = ['category' => $row['category'], 'mom_pct' => $pct];
            }
        }
        if ($rising !== null && $rising['mom_pct'] <= 0) {
            $rising = null;
        }
        if ($falling !== null && $falling['mom_pct'] >= 0) {
            $falling = null;
        }
        return [$rising, $falling];
    }
}
