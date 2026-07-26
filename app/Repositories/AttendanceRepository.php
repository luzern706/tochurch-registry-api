<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class AttendanceRepository
{
    /**
     * (member_id, service_id, attend_date) unique key 기준 UPSERT
     *
     * 입력된 row 만 저장 (미체크는 row 없음 = unknown 으로 표시).
     */
    public function upsertAttendances(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        return DB::table('reg_attendance_records')->upsert(
            $rows,
            ['member_id', 'service_id', 'attend_date'],
            ['status', 'note', 'recorded_by', 'updated_at']
        );
    }

    /**
     * 특정 예배/날짜의 출석부 (교회 내 활성 교인 전체 LEFT JOIN)
     * 옵션 필터: organization_id (소속 교인만)
     */
    public function getRosterForService(int $churchId, int $serviceId, string $attendDate, ?int $organizationId): array
    {
        // 교인별 첫 번째 소속 조직명 서브쿼리
        $orgSub = DB::table('reg_member_organizations as mo2')
            ->join('reg_organizations as o2', 'o2.id', '=', 'mo2.organization_id')
            ->select('mo2.member_id', DB::raw('MIN(o2.name) as organization_name'))
            ->groupBy('mo2.member_id');

        $query = DB::table('reg_members as m')
            ->leftJoin('reg_attendance_records as ar', function ($join) use ($serviceId, $attendDate) {
                $join->on('ar.member_id', '=', 'm.id')
                     ->where('ar.service_id', '=', $serviceId)
                     ->where('ar.attend_date', '=', $attendDate);
            })
            ->leftJoin('reg_member_profiles as p', 'p.member_id', '=', 'm.id')
            ->leftJoin('reg_codes as c', function ($join) {
                $join->on('c.code_value', '=', 'p.position')
                     ->where('c.group_key', '=', 'position');
            })
            ->leftJoinSub($orgSub, 'org_sub', 'org_sub.member_id', '=', 'm.id')
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0);

        if ($organizationId !== null) {
            $query->join('reg_member_organizations as mo_f', function ($join) use ($organizationId) {
                $join->on('mo_f.member_id', '=', 'm.id')
                     ->where('mo_f.organization_id', '=', $organizationId);
            });
        }

        return $query->orderBy('m.name', 'asc')
            ->get([
                'm.id as member_id', 'm.member_no', 'm.name', 'm.gender', 'm.status as member_status',
                'c.code_label as position',
                'org_sub.organization_name',
                'ar.id as attendance_id', 'ar.status as attendance_status',
                'ar.note', 'ar.attend_date', 'ar.recorded_by',
            ])
            ->toArray();
    }

    /**
     * 특정 교인의 기간별 출석 이력
     */
    public function getHistoryByMember(int $memberId, ?string $fromDate, ?string $toDate, ?int $serviceId): array
    {
        $query = DB::table('reg_attendance_records as ar')
            ->join('gh_church_timetable as s', 's.timetable_no', '=', 'ar.service_id')
            ->where('ar.member_id', $memberId);

        if ($fromDate !== null) {
            $query->where('ar.attend_date', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $query->where('ar.attend_date', '<=', $toDate);
        }
        if ($serviceId !== null) {
            $query->where('ar.service_id', $serviceId);
        }

        return $query->orderBy('ar.attend_date', 'desc')
            ->get([
                'ar.id', 'ar.attend_date', 'ar.status', 'ar.note',
                'ar.service_id', 's.name as service_name',
            ])
            ->toArray();
    }
}
