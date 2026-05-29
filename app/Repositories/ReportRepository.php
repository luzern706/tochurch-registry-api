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
