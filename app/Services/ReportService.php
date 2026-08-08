<?php

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use App\Repositories\ReportRepository;
use Illuminate\Http\JsonResponse;

class ReportService
{
    protected ReportRepository $reportRepository;
    protected MemberRepository $memberRepository;

    public function __construct(
        ReportRepository $reportRepository,
        MemberRepository $memberRepository
    ) {
        $this->reportRepository = $reportRepository;
        $this->memberRepository = $memberRepository;
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
                    'from'              => $monthStart,
                    'to'                => $monthEnd,
                    'offering_total'    => $offering['total_amount'],
                    'offering_count'    => $offering['count'],
                    'visit_count'       => $visitCount,
                ],
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ReportService] getDashboard error: " . $e->getMessage(), "report");
            return ApiResponse::fail('INTERNAL_ERROR', '대시보드 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
