<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * 봉사 팀(reg_service_teams) + 참여자 매핑(reg_service_members) 접근
 * — 'Service' 충돌 회피 위해 도메인명을 Volunteer 로 통칭
 */
class VolunteerRepository
{
    // ─────────────────────────────────────────────────────────────
    // 헬퍼: SP 단일 결과셋 호출 (PDO 호환, OUT 파라미터 없음)
    // ─────────────────────────────────────────────────────────────

    private function callSp(string $sql, array $params = []): array
    {
        $pdo  = DB::connection()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ) ?: [];
    }

    // ─────────────── 봉사 팀 (reg_service_teams) ───────────────

    /** SP — reg_service_teams + 참여인원 집계 + 담당자명 JOIN (봉사현황 화면 기본 목록) */
    public function getTeamList(int $churchId, array $filters): array
    {
        $serviceType = $filters['service_type'] ?? null;
        $mode        = $filters['mode'] ?? null;
        $isActive    = isset($filters['is_active']) ? (int) (bool) $filters['is_active'] : null;
        $managerId   = $filters['manager_member_id'] ?? null;
        $keyword     = $filters['keyword'] ?? null;
        $page        = max(1, (int) ($filters['page'] ?? 1));
        $size        = max(1, min(100, (int) ($filters['size'] ?? 20)));

        // count: SP와 동일한 WHERE 조건을 단순 쿼리로 별도 처리
        $countQuery = DB::table('reg_service_teams')->where('church_id', $churchId);
        if ($serviceType)        $countQuery->where('service_type', $serviceType);
        if ($mode)                $countQuery->where('mode', $mode);
        if ($isActive !== null)   $countQuery->where('is_active', $isActive);
        if ($managerId)           $countQuery->where('manager_member_id', $managerId);
        if ($keyword)              $countQuery->where(fn($q) => $q
            ->where('name', 'LIKE', "%{$keyword}%")
            ->orWhere('description', 'LIKE', "%{$keyword}%"));
        $total = $countQuery->count();

        // list: SP로 참여인원/담당자명 집계 JOIN 처리
        $list = $this->callSp('CALL sp_v4_reg_volunteer_list(?, ?, ?, ?, ?, ?, ?, ?)', [
            $churchId, $serviceType, $mode, $isActive, $managerId, $keyword, $page, $size,
        ]);

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    public function getTeamById(int $teamId): ?stdClass
    {
        return DB::table('reg_service_teams')
            ->where('id', $teamId)
            ->first();
    }

    public function insertTeam(array $data): int
    {
        return (int) DB::table('reg_service_teams')->insertGetId($data);
    }

    public function updateTeam(int $teamId, array $data): int
    {
        return DB::table('reg_service_teams')
            ->where('id', $teamId)
            ->update($data);
    }

    public function deactivateTeam(int $teamId): int
    {
        return DB::table('reg_service_teams')
            ->where('id', $teamId)
            ->update(['is_active' => 0]);
    }

    // ─────────────── 참여자 매핑 (reg_service_members) ───────────────

    public function getMappingByTeamAndMember(int $teamId, int $memberId): ?stdClass
    {
        return DB::table('reg_service_members')
            ->where('service_team_id', $teamId)
            ->where('member_id', $memberId)
            ->first();
    }

    public function insertMapping(array $data): int
    {
        return (int) DB::table('reg_service_members')->insertGetId($data);
    }

    public function updateMapping(int $mappingId, array $data): int
    {
        return DB::table('reg_service_members')
            ->where('id', $mappingId)
            ->update($data);
    }

    /** SP — reg_service_members + reg_members JOIN */
    public function getVolunteersByTeam(int $teamId, array $filters): array
    {
        $status = $filters['status'] ?? null;

        // count: SP와 동일한 WHERE 조건을 단순 쿼리로 별도 처리
        $countQuery = DB::table('reg_service_members as sm')
            ->join('reg_members as m', 'm.id', '=', 'sm.member_id')
            ->where('sm.service_team_id', $teamId)
            ->where('m.is_deleted', 0);
        if ($status !== null) {
            $countQuery->where('sm.status', $status);
        }
        $total = $countQuery->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        // list: SP로 JOIN 처리
        $list = $this->callSp('CALL sp_v4_reg_volunteer_by_team(?, ?, ?, ?)', [
            $teamId, $status, $page, $size,
        ]);

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    /** SP — reg_service_members + reg_service_teams JOIN */
    public function getTeamsByMember(int $memberId): array
    {
        return $this->callSp('CALL sp_v4_reg_volunteer_teams_by_member(?)', [$memberId]);
    }

    /** SP — reg_service_members + reg_members + reg_service_teams (+ reg_service_attendance) JOIN (교회 전체 봉사 이력) */
    public function getHistory(int $churchId, array $filters): array
    {
        $memberId    = $filters['member_id']    ?? null;
        $teamId      = $filters['team_id']      ?? null;
        $status      = $filters['status']       ?? null;
        $serviceType = $filters['service_type'] ?? null;
        $keyword     = $filters['keyword']      ?? null;
        $fromDate    = $filters['from_date']    ?? null;
        $toDate      = $filters['to_date']      ?? null;
        $page        = max(1, (int) ($filters['page'] ?? 1));
        $size        = max(1, min(100, (int) ($filters['size'] ?? 20)));

        // count: SP와 동일한 WHERE 조건을 단순 쿼리로 별도 처리
        $countQuery = DB::table('reg_service_members as sm')
            ->join('reg_members as m', 'm.id', '=', 'sm.member_id')
            ->join('reg_service_teams as t', 't.id', '=', 'sm.service_team_id')
            ->where('t.church_id', $churchId)
            ->where('m.is_deleted', 0);
        if ($memberId)         $countQuery->where('sm.member_id', $memberId);
        if ($teamId)           $countQuery->where('sm.service_team_id', $teamId);
        if ($status !== null)  $countQuery->where('sm.status', $status);
        if ($serviceType)      $countQuery->where('t.service_type', $serviceType);
        if ($fromDate)         $countQuery->where('sm.joined_at', '>=', $fromDate);
        if ($toDate)            $countQuery->where('sm.joined_at', '<=', $toDate);
        if ($keyword)          $countQuery->where(fn($q) => $q
            ->where('m.name', 'LIKE', "%{$keyword}%")
            ->orWhere('t.name', 'LIKE', "%{$keyword}%"));
        $total = $countQuery->count();

        // list: SP로 JOIN + 출결 집계 처리 (attend_count/last_service_date는 reg_service_attendance 기반 실측값)
        $list = $this->callSp('CALL sp_v4_reg_volunteer_history(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $churchId, $memberId, $teamId, $status, $serviceType, $keyword, $fromDate, $toDate, $page, $size,
        ]);

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    /** SP — reg_service_teams + reg_service_members + reg_service_attendance 집계 (봉사 팀별 이력 요약) */
    public function getTeamHistory(int $churchId, array $filters): array
    {
        $teamId      = $filters['team_id']      ?? null;
        $serviceType = $filters['service_type'] ?? null;
        $mode        = $filters['mode']         ?? null;
        $keyword     = $filters['keyword']      ?? null;
        $fromDate    = $filters['from_date']    ?? null;
        $toDate      = $filters['to_date']      ?? null;
        $page        = max(1, (int) ($filters['page'] ?? 1));
        $size        = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $countQuery = DB::table('reg_service_teams as t')->where('t.church_id', $churchId);
        if ($teamId)      $countQuery->where('t.id', $teamId);
        if ($serviceType) $countQuery->where('t.service_type', $serviceType);
        if ($mode)         $countQuery->where('t.mode', $mode);
        if ($keyword)      $countQuery->where('t.name', 'LIKE', "%{$keyword}%");
        $total = $countQuery->count();

        $list = $this->callSp('CALL sp_v4_reg_volunteer_team_history(?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $churchId, $teamId, $serviceType, $mode, $keyword, $fromDate, $toDate, $page, $size,
        ]);

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    // ─────────────── 봉사 출결 (reg_service_attendance) ───────────────

    /** 팀의 활동중 참여자 + 기록된 출결 데이터 (체크인 화면용) */
    public function getAttendanceSheet(int $teamId): array
    {
        $members = DB::table('reg_service_members as sm')
            ->join('reg_members as m', 'm.id', '=', 'sm.member_id')
            ->where('sm.service_team_id', $teamId)
            ->where('sm.status', 'active')
            ->where('m.is_deleted', 0)
            ->orderBy('m.name')
            ->get(['m.id as member_id', 'm.name', 'm.phone'])
            ->toArray();

        $rows = DB::table('reg_service_attendance')
            ->where('service_team_id', $teamId)
            ->orderBy('service_date', 'desc')
            ->get(['member_id', 'service_date', 'status', 'note'])
            ->toArray();

        $dates = [];
        $attendance = [];
        foreach ($rows as $r) {
            $date = (string) $r->service_date;
            if (!in_array($date, $dates, true)) {
                $dates[] = $date;
            }
            $attendance["{$r->member_id}_{$date}"] = $r->status;
        }

        return [
            'members'    => $members,
            'dates'      => $dates,
            'attendance' => $attendance,
        ];
    }

    /**
     * 특정 날짜 출결 일괄 upsert
     * $records = [['member_id'=>int, 'status'=>string, 'note'=>?string], ...]
     */
    public function saveAttendance(int $teamId, string $serviceDate, array $records): void
    {
        $now = now()->toDateTimeString();
        foreach ($records as $rec) {
            DB::table('reg_service_attendance')->upsert(
                [
                    'service_team_id' => $teamId,
                    'member_id'       => (int) $rec['member_id'],
                    'service_date'    => $serviceDate,
                    'status'          => $rec['status'],
                    'note'            => $rec['note'] ?? null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                ['service_team_id', 'member_id', 'service_date'],
                ['status', 'note', 'updated_at']
            );
        }
    }

    /**
     * 팀별 교인 출결 통계
     * 반환: [{ member_id, name, phone, present, absent, late, excused, total, last_service_date }, ...]
     */
    public function getMemberAttendanceStats(int $teamId): array
    {
        return DB::table('reg_service_attendance as a')
            ->join('reg_members as m', 'm.id', '=', 'a.member_id')
            ->where('a.service_team_id', $teamId)
            ->groupBy('a.member_id', 'm.name', 'm.phone')
            ->orderBy('m.name')
            ->get([
                'a.member_id',
                'm.name',
                'm.phone',
                DB::raw("SUM(a.status = 'present') AS present"),
                DB::raw("SUM(a.status = 'absent')  AS absent"),
                DB::raw("SUM(a.status = 'late')     AS late"),
                DB::raw("SUM(a.status = 'excused')  AS excused"),
                DB::raw("COUNT(*)                   AS total"),
                DB::raw("MAX(CASE WHEN a.status IN ('present','late') THEN a.service_date END) AS last_service_date"),
            ])
            ->toArray();
    }
}
