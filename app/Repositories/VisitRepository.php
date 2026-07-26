<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class VisitRepository
{
    private const FIELDS = [
        'v.id', 'v.member_id', 'v.church_id', 'v.visit_type', 'v.event_reason',
        'v.visit_date', 'v.visitor_member_id', 'v.manager_member_id', 'v.place',
        'v.companion', 'v.attendee_count', 'v.content', 'v.common_note',
        'v.private_note', 'v.add_to_prayer', 'v.status', 'v.created_by',
        'v.created_at', 'v.updated_at',
        'm.name as member_name', 'm.member_no as member_member_no',
        'vm.name as visitor_name',
        'mgr.name as manager_name',
        'org.name as org_name',
    ];

    public function getVisitList(int $churchId, array $filters): array
    {
        // 교인당 대표 소속 1건만 가져오는 서브쿼리 (복수 소속 시 중복 행 방지)
        $orgSub = DB::table('reg_member_organizations')
            ->select('member_id', DB::raw('MIN(organization_id) as organization_id'))
            ->groupBy('member_id');

        $query = DB::table('reg_visit_records as v')
            ->leftJoin('reg_members as m',   'm.id',   '=', 'v.member_id')
            ->leftJoin('reg_members as vm',  'vm.id',  '=', 'v.visitor_member_id')
            ->leftJoin('reg_members as mgr', 'mgr.id', '=', 'v.manager_member_id')
            ->leftJoinSub($orgSub, 'mo', fn($j) => $j->on('mo.member_id', '=', 'v.member_id'))
            ->leftJoin('reg_organizations as org', 'org.id', '=', 'mo.organization_id')
            ->where('v.church_id', $churchId);

        if (!empty($filters['member_id'])) {
            $query->where('v.member_id', (int) $filters['member_id']);
        }
        if (!empty($filters['visitor_member_id'])) {
            $query->where('v.visitor_member_id', (int) $filters['visitor_member_id']);
        }
        if (!empty($filters['visit_type'])) {
            $query->where('v.visit_type', $filters['visit_type']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('v.visit_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('v.visit_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['event_reason'])) {
            $query->where('v.event_reason', $filters['event_reason']);
        }
        if (!empty($filters['org_ids'])) {
            $orgIds = array_map('intval', (array) $filters['org_ids']);
            $query->whereExists(function ($q) use ($orgIds) {
                $q->select(DB::raw(1))
                  ->from('reg_member_organizations as _mo')
                  ->whereColumn('_mo.member_id', 'v.member_id')
                  ->whereIn('_mo.organization_id', $orgIds);
            });
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('v.content', 'LIKE', $kw)
                  ->orWhere('v.common_note', 'LIKE', $kw)
                  ->orWhere('m.name', 'LIKE', $kw);
            });
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('v.visit_date', 'desc')
            ->orderBy('v.id', 'desc')
            ->forPage($page, $size)
            ->get(self::FIELDS)
            ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
            'stats' => $this->getVisitStats($churchId),
        ];
    }

    public function getVisitStats(int $churchId): array
    {
        $base = DB::table('reg_visit_records')
            ->where('church_id', $churchId);

        $total          = (clone $base)->count();
        $annualTotal    = (clone $base)->where('visit_type', 'annual')->count();
        $annualActive   = (clone $base)->where('visit_type', 'annual')->where('status', '!=', 'cancelled')->count();
        $annualCompleted= (clone $base)->where('visit_type', 'annual')->where('status', 'completed')->count();
        $eventTotal     = (clone $base)->where('visit_type', 'event')->count();

        $annualRate = $annualActive > 0
            ? round($annualCompleted / $annualActive * 100, 1)
            : 0;

        return [
            'total'           => $total,
            'annual'          => $annualTotal,
            'annual_active'   => $annualActive,
            'annual_completed'=> $annualCompleted,
            'annual_rate'     => $annualRate,
            'event'           => $eventTotal,
        ];
    }

    public function getVisitById(int $visitId): ?stdClass
    {
        $orgSub = DB::table('reg_member_organizations')
            ->select('member_id', DB::raw('MIN(organization_id) as organization_id'))
            ->groupBy('member_id');

        return DB::table('reg_visit_records as v')
            ->leftJoin('reg_members as m',   'm.id',   '=', 'v.member_id')
            ->leftJoin('reg_members as vm',  'vm.id',  '=', 'v.visitor_member_id')
            ->leftJoin('reg_members as mgr', 'mgr.id', '=', 'v.manager_member_id')
            ->leftJoinSub($orgSub, 'mo', fn($j) => $j->on('mo.member_id', '=', 'v.member_id'))
            ->leftJoin('reg_organizations as org', 'org.id', '=', 'mo.organization_id')
            ->where('v.id', $visitId)
            ->first(self::FIELDS);
    }

    public function insertVisit(array $data): int
    {
        return (int) DB::table('reg_visit_records')->insertGetId($data);
    }

    public function updateVisit(int $visitId, array $data): int
    {
        return DB::table('reg_visit_records')
            ->where('id', $visitId)
            ->update($data);
    }

    public function deleteVisit(int $visitId): int
    {
        return DB::table('reg_visit_records')
            ->where('id', $visitId)
            ->delete();
    }

    /**
     * 선택된 교인들을 일괄 대심방 완료 처리
     */
    public function bulkCompleteVisit(int $churchId, int $authMemberId, array $memberIds, string $visitDate): int
    {
        $rows = array_map(fn($memberId) => [
            'church_id'         => $churchId,
            'member_id'         => $memberId,
            'visitor_member_id' => $authMemberId,
            'manager_member_id' => $authMemberId,
            'visit_type'        => 'annual',
            'status'            => 'completed',
            'visit_date'        => $visitDate,
            'content'           => '일괄 완료 처리',
            'created_by'        => $authMemberId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $memberIds);

        DB::table('reg_visit_records')->insert($rows);
        return count($rows);
    }

    /**
     * 교적설정의 장기결석 기준 주수 반환 (필터 파라미터가 있으면 우선)
     */
    private function getLongAbsentWeeks(int $churchId, array $filters): int
    {
        if (!empty($filters['absence_weeks'])) {
            return max(1, (int) $filters['absence_weeks']);
        }
        $raw = DB::table('reg_church_settings')
            ->where('church_id', $churchId)
            ->where('setting_key', 'auto_long_absent')
            ->value('setting_value');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (isset($decoded['weeks'])) {
                return max(1, (int) $decoded['weeks']);
            }
        }
        return 4;
    }

    /**
     * 주일예배 기준 장기결석 교인 목록 + 심방 현황 KPI
     */
    public function getAbsenceTargetList(int $churchId, array $filters): array
    {
        $absenceWeeks = $this->getLongAbsentWeeks($churchId, $filters);
        $cutoffDate   = now()->subWeeks($absenceWeeks)->toDateString();

        // 주일예배 서비스 ID (day_of_week = 0)
        $sundayIds = DB::table('gh_church_timetable')
            ->where('church_no', $churchId)
            ->where('day_of_week', 0)
            ->where('is_active', 1)
            ->pluck('timetable_no');

        // 교인당 대표 소속
        $orgSub = DB::table('reg_member_organizations')
            ->select('member_id', DB::raw('MIN(organization_id) as organization_id'))
            ->groupBy('member_id');

        // 마지막 주일예배 출석일 (주일 서비스 없으면 전체 출석 기준)
        $lastAttSub = DB::table('reg_attendance_records')
            ->select('member_id', DB::raw('MAX(attend_date) as last_attend_date'))
            ->where('church_id', $churchId)
            ->where('status', 'present')
            ->when($sundayIds->isNotEmpty(), fn($q) => $q->whereIn('service_id', $sundayIds))
            ->groupBy('member_id');

        $base = DB::table('reg_members as m')
            ->leftJoin('reg_member_profiles as p', 'p.member_id', '=', 'm.id')
            ->leftJoinSub($orgSub,     'mo', fn($j) => $j->on('mo.member_id', '=', 'm.id'))
            ->leftJoin('reg_organizations as org', 'org.id', '=', 'mo.organization_id')
            ->leftJoinSub($lastAttSub, 'la', fn($j) => $j->on('la.member_id', '=', 'm.id'))
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0)
            ->where(function ($q) use ($cutoffDate) {
                $q->whereNull('la.last_attend_date')
                  ->orWhere('la.last_attend_date', '<', $cutoffDate);
            });

        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $base->where(fn($q) => $q->where('m.name', 'LIKE', $kw)
                                     ->orWhere('m.phone', 'LIKE', $kw));
        }
        if (!empty($filters['org_ids'])) {
            $orgIds = array_map('intval', (array) $filters['org_ids']);
            $base->whereExists(fn($q) => $q->select(DB::raw(1))
                ->from('reg_member_organizations as _mo')
                ->whereColumn('_mo.member_id', 'm.id')
                ->whereIn('_mo.organization_id', $orgIds));
        }

        // ── KPI: 전체 대상 목록에서 심방 현황 집계 ─────────────────────────
        // 장기결석 시작일(last_attend_date) 이후 대심방 완료/진행 여부를 스칼라 서브쿼리로 산출
        $kpiRows = (clone $base)->get([
            'm.id',
            DB::raw("(
                SELECT COUNT(*) FROM reg_visit_records vr
                WHERE vr.member_id = m.id
                  AND vr.visit_type = 'annual'
                  AND vr.status = 'completed'
                  AND vr.visit_date > COALESCE(la.last_attend_date, '1900-01-01')
            ) as completed_cnt"),
            DB::raw("(
                SELECT COUNT(*) FROM reg_visit_records vr
                WHERE vr.member_id = m.id
                  AND vr.visit_type = 'annual'
                  AND vr.status IN ('scheduled', 'in_progress')
                  AND vr.visit_date > COALESCE(la.last_attend_date, '1900-01-01')
            ) as inprogress_cnt"),
        ])->toArray();

        $kpiCompleted = 0; $kpiInProgress = 0; $kpiUnvisited = 0;
        foreach ($kpiRows as $row) {
            if ($row->completed_cnt > 0)      $kpiCompleted++;
            elseif ($row->inprogress_cnt > 0) $kpiInProgress++;
            else                              $kpiUnvisited++;
        }

        // ── 페이지 리스트 (visit_status 카드 필터 지원) ───────────────────
        $page     = max(1, (int) ($filters['page'] ?? 1));
        $size     = max(1, min(100, (int) ($filters['size'] ?? 20)));
        $listBase = clone $base;

        $visitStatus = $filters['visit_status'] ?? null;
        if ($visitStatus === 'completed') {
            $listBase->whereExists(fn($q) => $q->select(DB::raw(1))
                ->from('reg_visit_records as _vr')
                ->whereColumn('_vr.member_id', 'm.id')
                ->where('_vr.visit_type', 'annual')
                ->where('_vr.status', 'completed')
                ->whereRaw("_vr.visit_date > COALESCE(la.last_attend_date, '1900-01-01')"));
        } elseif ($visitStatus === 'in_progress') {
            $listBase
                ->whereExists(fn($q) => $q->select(DB::raw(1))
                    ->from('reg_visit_records as _vr')
                    ->whereColumn('_vr.member_id', 'm.id')
                    ->where('_vr.visit_type', 'annual')
                    ->whereIn('_vr.status', ['scheduled', 'in_progress'])
                    ->whereRaw("_vr.visit_date > COALESCE(la.last_attend_date, '1900-01-01')"))
                ->whereNotExists(fn($q) => $q->select(DB::raw(1))
                    ->from('reg_visit_records as _vr2')
                    ->whereColumn('_vr2.member_id', 'm.id')
                    ->where('_vr2.visit_type', 'annual')
                    ->where('_vr2.status', 'completed')
                    ->whereRaw("_vr2.visit_date > COALESCE(la.last_attend_date, '1900-01-01')"));
        } elseif ($visitStatus === 'unvisited') {
            $listBase->whereNotExists(fn($q) => $q->select(DB::raw(1))
                ->from('reg_visit_records as _vr')
                ->whereColumn('_vr.member_id', 'm.id')
                ->where('_vr.visit_type', 'annual')
                ->whereIn('_vr.status', ['scheduled', 'in_progress', 'completed'])
                ->whereRaw("_vr.visit_date > COALESCE(la.last_attend_date, '1900-01-01')"));
        }

        $listTotal = $listBase->count();

        $list = (clone $listBase)
            ->orderByRaw('la.last_attend_date IS NULL DESC')
            ->orderBy('la.last_attend_date', 'asc')
            ->orderBy('m.name', 'asc')
            ->forPage($page, $size)
            ->get([
                'm.id', 'm.name', 'm.member_no',
                'p.position',
                'org.name as org_name',
                'la.last_attend_date',
                DB::raw("CASE
                    WHEN la.last_attend_date IS NULL THEN NULL
                    ELSE DATEDIFF(CURDATE(), la.last_attend_date) DIV 7
                END as absence_weeks"),
                DB::raw("(
                    SELECT COUNT(*) FROM reg_visit_records vr
                    WHERE vr.member_id = m.id
                      AND vr.visit_type = 'annual'
                      AND vr.status = 'completed'
                      AND vr.visit_date > COALESCE(la.last_attend_date, '1900-01-01')
                ) as completed_cnt"),
                DB::raw("(
                    SELECT COUNT(*) FROM reg_visit_records vr
                    WHERE vr.member_id = m.id
                      AND vr.visit_type = 'annual'
                      AND vr.status IN ('scheduled', 'in_progress')
                      AND vr.visit_date > COALESCE(la.last_attend_date, '1900-01-01')
                ) as inprogress_cnt"),
                DB::raw("(
                    SELECT mgr.name FROM reg_visit_records vr
                    LEFT JOIN reg_members mgr ON mgr.id = vr.manager_member_id
                    WHERE vr.member_id = m.id
                      AND vr.visit_type = 'annual'
                      AND vr.status = 'completed'
                      AND vr.visit_date > COALESCE(la.last_attend_date, '1900-01-01')
                    ORDER BY vr.visit_date DESC LIMIT 1
                ) as last_manager_name"),
            ])
            ->toArray();

        return [
            'total'              => $listTotal,
            'page'               => $page,
            'size'               => $size,
            'list'               => $list,
            'absence_weeks'      => $absenceWeeks,
            'has_sunday_service' => $sundayIds->isNotEmpty(),
            'kpi_total'          => count($kpiRows),
            'kpi_unvisited'      => $kpiUnvisited,
            'kpi_in_progress'    => $kpiInProgress,
            'kpi_completed'      => $kpiCompleted,
        ];
    }

    public function insertPrayerRecord(array $data): int
    {
        return (int) DB::table('reg_prayer_records')->insertGetId($data);
    }

    /**
     * 해당 연도에 대심방(annual)이 없는 교인 목록 + 마지막 심방/출석 정보
     */
    public function getUnvisitedList(int $churchId, string $year, array $filters): array
    {
        $from     = "{$year}-01-01";
        $to       = "{$year}-12-31";
        $w4start  = now()->subWeeks(4)->toDateString(); // 최근 4주 시작일

        // 대표 소속 서브쿼리 (교인당 1행)
        $orgSub = DB::table('reg_member_organizations')
            ->select('member_id', DB::raw('MIN(organization_id) as organization_id'))
            ->groupBy('member_id');

        // 마지막 대심방일 서브쿼리 (취소 제외)
        $lastVisitSub = DB::table('reg_visit_records')
            ->select('member_id', DB::raw('MAX(visit_date) as last_visit_date'))
            ->where('visit_type', 'annual')
            ->where('status', '!=', 'cancelled')
            ->groupBy('member_id');

        // 마지막 출석일 서브쿼리
        $lastAttSub = DB::table('reg_attendance_records')
            ->select('member_id', DB::raw('MAX(attend_date) as last_attend_date'))
            ->where('church_id', $churchId)
            ->where('status', 'present')
            ->groupBy('member_id');

        // 최근 4주 출석 횟수 서브쿼리
        $recentAttSub = DB::table('reg_attendance_records')
            ->select('member_id', DB::raw('COUNT(DISTINCT attend_date) as recent_count'))
            ->where('church_id', $churchId)
            ->where('status', 'present')
            ->where('attend_date', '>=', $w4start)
            ->groupBy('member_id');

        $query = DB::table('reg_members as m')
            ->leftJoin('reg_member_profiles as p',   'p.member_id',  '=', 'm.id')
            ->leftJoinSub($orgSub,        'mo',   fn($j) => $j->on('mo.member_id',   '=', 'm.id'))
            ->leftJoin('reg_organizations as org',   'org.id',       '=', 'mo.organization_id')
            ->leftJoinSub($lastVisitSub,  'lv',   fn($j) => $j->on('lv.member_id',   '=', 'm.id'))
            ->leftJoinSub($lastAttSub,    'la',   fn($j) => $j->on('la.member_id',   '=', 'm.id'))
            ->leftJoinSub($recentAttSub,  'ra',   fn($j) => $j->on('ra.member_id',   '=', 'm.id'))
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0)
            // 해당 연도 대심방 완료·예정 기록이 없는 교인만 (취소는 포함)
            ->whereNotExists(function ($q) use ($from, $to) {
                $q->select(DB::raw(1))
                  ->from('reg_visit_records as vx')
                  ->whereColumn('vx.member_id', 'm.id')
                  ->where('vx.visit_type', 'annual')
                  ->whereIn('vx.status', ['completed', 'scheduled', 'in_progress'])
                  ->whereBetween('vx.visit_date', [$from, $to]);
            });

        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('m.name', 'LIKE', $kw)
                  ->orWhere('m.phone', 'LIKE', $kw);
            });
        }
        if (!empty($filters['position'])) {
            $query->where('p.position', $filters['position']);
        }
        // 출석 상태 필터: 최근 4주 출석 횟수 기준
        if (!empty($filters['att_status'])) {
            match ($filters['att_status']) {
                'normal'    => $query->whereRaw('COALESCE(ra.recent_count, 0) >= 3'),
                'irregular' => $query->whereRaw('COALESCE(ra.recent_count, 0) BETWEEN 1 AND 2'),
                'absent'    => $query->whereRaw('COALESCE(ra.recent_count, 0) = 0'),
                default     => null,
            };
        }
        if (!empty($filters['org_ids'])) {
            $orgIds = array_map('intval', (array) $filters['org_ids']);
            $query->whereExists(function ($q) use ($orgIds) {
                $q->select(DB::raw(1))
                  ->from('reg_member_organizations as _mo')
                  ->whereColumn('_mo.member_id', 'm.id')
                  ->whereIn('_mo.organization_id', $orgIds);
            });
        }

        $total = (clone $query)->count();
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $size  = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query
            ->orderByRaw('lv.last_visit_date IS NULL DESC')
            ->orderBy('lv.last_visit_date', 'asc')
            ->orderBy('m.name', 'asc')
            ->forPage($page, $size)
            ->get([
                'm.id', 'm.name', 'm.member_no',
                'p.position',
                'org.name as org_name',
                'lv.last_visit_date',
                'la.last_attend_date',
                DB::raw('COALESCE(ra.recent_count, 0) as recent_attend_count'),
                // 취소 제외한 마지막 대심방 담당자
                DB::raw('(
                    SELECT mgr.name
                    FROM reg_visit_records vr
                    LEFT JOIN reg_members mgr ON mgr.id = vr.manager_member_id
                    WHERE vr.member_id = m.id AND vr.visit_type = \'annual\'
                      AND vr.status != \'cancelled\'
                    ORDER BY vr.visit_date DESC
                    LIMIT 1
                ) as last_manager_name'),
                // 해당 연도 대심방 취소 여부
                DB::raw("(
                    SELECT COUNT(*)
                    FROM reg_visit_records vc
                    WHERE vc.member_id = m.id AND vc.visit_type = 'annual'
                      AND vc.status = 'cancelled'
                      AND vc.visit_date BETWEEN '{$from}' AND '{$to}'
                ) as has_cancelled"),
            ])
            ->toArray();

        // 해당 연도 대심방 완료(completed) 교인 수
        $visitedCount = DB::table('reg_visit_records as v')
            ->join('reg_members as m2', 'm2.id', '=', 'v.member_id')
            ->where('m2.church_id', $churchId)
            ->where('m2.is_deleted', 0)
            ->where('v.visit_type', 'annual')
            ->where('v.status', 'completed')
            ->whereBetween('v.visit_date', [$from, $to])
            ->distinct()
            ->count('v.member_id');

        // 해당 연도 대심방 진행중+예정 교인 수 (완료된 교인 제외)
        $inProgressCount = DB::table('reg_visit_records as v')
            ->join('reg_members as m2', 'm2.id', '=', 'v.member_id')
            ->where('m2.church_id', $churchId)
            ->where('m2.is_deleted', 0)
            ->where('v.visit_type', 'annual')
            ->whereIn('v.status', ['in_progress', 'scheduled'])
            ->whereBetween('v.visit_date', [$from, $to])
            ->whereNotExists(function ($q) use ($from, $to) {
                $q->select(DB::raw(1))
                  ->from('reg_visit_records as vc')
                  ->whereColumn('vc.member_id', 'v.member_id')
                  ->where('vc.visit_type', 'annual')
                  ->where('vc.status', 'completed')
                  ->whereBetween('vc.visit_date', [$from, $to]);
            })
            ->distinct()
            ->count('v.member_id');

        $totalMembers = DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->count();

        return [
            'total'             => $total,
            'page'              => $page,
            'size'              => $size,
            'list'              => $list,
            'visited_count'     => $visitedCount,
            'in_progress_count' => $inProgressCount,
            'total_members'     => $totalMembers,
        ];
    }

}
