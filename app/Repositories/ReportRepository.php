<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class ReportRepository
{
    // ─────────────── 교인 통계 ───────────────

    public function countMembers(int $churchId): int
    {
        return DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->count();
    }

    public function getMembersByGender(int $churchId): array
    {
        return DB::table('reg_members')
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->groupBy('gender')
            ->get()
            ->toArray();
    }

    public function getMembersByAgeGroup(int $churchId): array
    {
        return DB::table('reg_members')
            ->select(
                DB::raw('FLOOR((YEAR(CURDATE()) - YEAR(birth_date)) / 10) * 10 AS age_group'),
                DB::raw('COUNT(*) as count')
            )
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->whereNotNull('birth_date')
            ->groupBy('age_group')
            ->orderBy('age_group', 'asc')
            ->get()
            ->toArray();
    }

    public function getMembersByPosition(int $churchId): array
    {
        return DB::table('reg_members as m')
            ->join('reg_member_profiles as p', 'p.member_id', '=', 'm.id')
            ->select('p.position', DB::raw('COUNT(*) as count'))
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0)
            ->groupBy('p.position')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    public function getMembersByAttendanceGrade(int $churchId): array
    {
        return DB::table('reg_members as m')
            ->join('reg_member_profiles as p', 'p.member_id', '=', 'm.id')
            ->select('p.attendance_grade', DB::raw('COUNT(*) as count'))
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0)
            ->groupBy('p.attendance_grade')
            ->get()
            ->toArray();
    }

    public function getMembersByStatus(int $churchId): array
    {
        return DB::table('reg_members')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->groupBy('status')
            ->get()
            ->toArray();
    }

    // ─────────────── 출석 통계 ───────────────

    public function getAttendanceCountByStatus(int $churchId, ?int $serviceId, string $fromDate, string $toDate, ?int $organizationId): array
    {
        $query = DB::table('reg_attendance_records as ar')
            ->select('ar.status', DB::raw('COUNT(*) as count'))
            ->where('ar.church_id', $churchId)
            ->whereBetween('ar.attend_date', [$fromDate, $toDate]);

        if ($serviceId !== null) {
            $query->where('ar.service_id', $serviceId);
        }
        if ($organizationId !== null) {
            $query->join('reg_member_organizations as mo', function ($join) use ($organizationId) {
                $join->on('mo.member_id', '=', 'ar.member_id')
                     ->where('mo.organization_id', '=', $organizationId);
            });
        }

        return $query->groupBy('ar.status')->get()->toArray();
    }

    public function getAttendanceTrendDaily(int $churchId, ?int $serviceId, string $fromDate, string $toDate, ?int $organizationId): array
    {
        $query = DB::table('reg_attendance_records as ar')
            ->select(
                'ar.attend_date',
                DB::raw("SUM(CASE WHEN ar.status='present' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN ar.status='absent' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw('COUNT(*) as total_count')
            )
            ->where('ar.church_id', $churchId)
            ->whereBetween('ar.attend_date', [$fromDate, $toDate]);

        if ($serviceId !== null) {
            $query->where('ar.service_id', $serviceId);
        }
        if ($organizationId !== null) {
            $query->join('reg_member_organizations as mo', function ($join) use ($organizationId) {
                $join->on('mo.member_id', '=', 'ar.member_id')
                     ->where('mo.organization_id', '=', $organizationId);
            });
        }

        return $query->groupBy('ar.attend_date')
            ->orderBy('ar.attend_date', 'asc')
            ->get()
            ->toArray();
    }

    public function getStatsByService(int $churchId, string $fromDate, string $toDate): array
    {
        return DB::select("
            SELECT
                s.timetable_no AS service_id,
                s.name AS service_name,
                COALESCE(SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END), 0) AS present_count,
                COALESCE(SUM(CASE WHEN ar.status = 'absent'  THEN 1 ELSE 0 END), 0) AS absent_count,
                COUNT(DISTINCT ar.attend_date) AS date_count
            FROM gh_church_timetable AS s
            LEFT JOIN reg_attendance_records AS ar
                ON ar.service_id  = s.timetable_no
               AND ar.church_id   = ?
               AND ar.attend_date BETWEEN ? AND ?
            WHERE s.church_no = ?
              AND s.is_active  = 1
            GROUP BY s.timetable_no, s.name
            ORDER BY s.sort_order, s.timetable_no
        ", [$churchId, $fromDate, $toDate, $churchId]);
    }

    public function getMemberRateDistribution(int $churchId, string $fromDate, string $toDate): array
    {
        return DB::select("
            SELECT bucket, COUNT(*) AS count
            FROM (
                SELECT
                    m.id,
                    CASE
                        WHEN (present_count + absent_count) = 0 THEN 'no_record'
                        WHEN present_count / (present_count + absent_count) >= 0.9 THEN '90_plus'
                        WHEN present_count / (present_count + absent_count) >= 0.8 THEN '80_89'
                        WHEN present_count / (present_count + absent_count) >= 0.7 THEN '70_79'
                        WHEN present_count / (present_count + absent_count) >= 0.6 THEN '60_69'
                        ELSE 'under_60'
                    END AS bucket
                FROM reg_members AS m
                LEFT JOIN (
                    SELECT member_id,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
                        SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_count
                    FROM reg_attendance_records
                    WHERE church_id = ?
                      AND attend_date BETWEEN ? AND ?
                    GROUP BY member_id
                ) AS ar ON ar.member_id = m.id
                WHERE m.church_id = ? AND m.is_deleted = 0
            ) AS t
            GROUP BY bucket
        ", [$churchId, $fromDate, $toDate, $churchId]);
    }

    public function getAttendanceStatsByOrg(int $churchId, ?int $serviceId, string $fromDate, string $toDate, ?int $organizationId = null): array
    {
        $serviceFilter    = $serviceId !== null ? 'AND ar_sub.service_id = ?' : '';
        $orgFilter        = $organizationId !== null ? 'AND (o.id = ? OR o.parent_id = ?)' : '';

        $sql = "
            SELECT
                o.id          AS org_id,
                o.name        AS org_name,
                po.name       AS parent_name,
                COALESCE(SUM(ar.present_count), 0)  AS present_count,
                COALESCE(SUM(ar.absent_count),  0)  AS absent_count,
                COUNT(DISTINCT m.id)                 AS member_count,
                COALESCE(MAX(ar.date_count),    0)  AS date_count
            FROM reg_organizations AS o
            LEFT JOIN reg_organizations AS po ON po.id = o.parent_id
            LEFT JOIN reg_member_organizations AS mo ON mo.organization_id = o.id
            LEFT JOIN reg_members AS m
                ON m.id = mo.member_id
                AND m.church_id = ?
                AND m.is_deleted = 0
            LEFT JOIN (
                SELECT
                    member_id,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) AS absent_count,
                    COUNT(DISTINCT attend_date) AS date_count
                FROM reg_attendance_records AS ar_sub
                WHERE ar_sub.church_id = ?
                  AND ar_sub.attend_date BETWEEN ? AND ?
                  {$serviceFilter}
                GROUP BY member_id
            ) AS ar ON ar.member_id = m.id
            WHERE o.church_id = ?
            {$orgFilter}
            GROUP BY o.id, o.name, po.name
            ORDER BY COALESCE(po.name, o.name), o.name
        ";

        $bindings = [$churchId, $churchId, $fromDate, $toDate];
        if ($serviceId !== null)    $bindings[] = $serviceId;
        $bindings[] = $churchId;
        if ($organizationId !== null) {
            $bindings[] = $organizationId;
            $bindings[] = $organizationId;
        }

        return DB::select($sql, $bindings);
    }

    // ─────────────── 헌금 통계 ───────────────

    public function getOfferingTotals(int $churchId, string $fromDate, string $toDate, ?string $category): array
    {
        $query = DB::table('reg_offering_records')
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(amount), 0) as total_amount')
            )
            ->where('church_id', $churchId)
            ->whereBetween('offer_date', [$fromDate, $toDate]);

        if ($category !== null) {
            $query->where('category', $category);
        }

        $row = $query->first();
        return [
            'count'        => (int) ($row->count ?? 0),
            'total_amount' => (int) ($row->total_amount ?? 0),
        ];
    }

    public function getOfferingByCategory(int $churchId, string $fromDate, string $toDate): array
    {
        return DB::table('reg_offering_records')
            ->select(
                'category',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('church_id', $churchId)
            ->whereBetween('offer_date', [$fromDate, $toDate])
            ->groupBy('category')
            ->orderBy('total_amount', 'desc')
            ->get()
            ->toArray();
    }

    public function getOfferingMonthlyTrend(int $churchId, string $fromDate, string $toDate, ?string $category): array
    {
        $query = DB::table('reg_offering_records')
            ->select(
                DB::raw('DATE_FORMAT(offer_date, "%Y-%m") as ym'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('church_id', $churchId)
            ->whereBetween('offer_date', [$fromDate, $toDate]);

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->groupBy('ym')->orderBy('ym', 'asc')->get()->toArray();
    }

    // 월(ym) x 헌금항목(category) 교차표 — 기간별 조회 화면의 피벗 테이블용
    public function getOfferingCategoryMonthlyPivot(int $churchId, string $fromDate, string $toDate): array
    {
        return DB::table('reg_offering_records')
            ->select(
                DB::raw('DATE_FORMAT(offer_date, "%Y-%m") as ym'),
                'category',
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('church_id', $churchId)
            ->whereBetween('offer_date', [$fromDate, $toDate])
            ->groupBy('ym', 'category')
            ->orderBy('ym', 'asc')
            ->get()
            ->toArray();
    }

    // ─────────────── 심방 통계 ───────────────

    public function getVisitCount(int $churchId, string $fromDate, string $toDate, ?int $visitorMemberId): int
    {
        $query = DB::table('reg_visit_records')
            ->where('church_id', $churchId)
            ->whereBetween('visit_date', [$fromDate, $toDate]);

        if ($visitorMemberId !== null) {
            $query->where('visitor_member_id', $visitorMemberId);
        }

        return $query->count();
    }

    public function getVisitByVisitor(int $churchId, string $fromDate, string $toDate, int $limit = 10): array
    {
        return DB::table('reg_visit_records as v')
            ->leftJoin('reg_members as m', 'm.id', '=', 'v.visitor_member_id')
            ->select(
                'v.visitor_member_id',
                'm.name as visitor_name',
                DB::raw('COUNT(*) as count')
            )
            ->where('v.church_id', $churchId)
            ->whereBetween('v.visit_date', [$fromDate, $toDate])
            ->groupBy('v.visitor_member_id', 'm.name')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getVisitByType(int $churchId, string $fromDate, string $toDate): array
    {
        return DB::table('reg_visit_records')
            ->select('visit_type', DB::raw('COUNT(*) as count'))
            ->where('church_id', $churchId)
            ->whereBetween('visit_date', [$fromDate, $toDate])
            ->groupBy('visit_type')
            ->get()
            ->toArray();
    }
}
