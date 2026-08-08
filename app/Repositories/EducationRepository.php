<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class EducationRepository
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

    // ─────────────────────────────────────────────────────────────
    // 교육 과정 템플릿 (reg_education_courses)
    // ─────────────────────────────────────────────────────────────

    /** SP — 복잡한 최신기수 서브쿼리 + 기수수/수강자수 집계 */
    public function getCourseList(int $churchId, array $filters): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $size    = max(1, min(100, (int) ($filters['size'] ?? 20)));
        $keyword = $filters['keyword']  ?? null;
        $status  = $filters['status']   ?? null;
        $target  = $filters['target']   ?? null;
        $eduType = $filters['edu_type'] ?? null;

        // count: SP와 동일한 WHERE 조건을 단순 쿼리로 별도 처리
        $countQuery = DB::table('reg_education_courses as c')
            ->where('c.church_id', $churchId)
            ->leftJoin(DB::raw('(
                SELECT si.course_id, si.id AS latest_session_id, si.status, si.instructor
                FROM reg_education_sessions si
                INNER JOIN (SELECT course_id, MAX(id) AS max_id FROM reg_education_sessions GROUP BY course_id) sm
                    ON si.course_id = sm.course_id AND si.id = sm.max_id
            ) ls'), 'ls.course_id', '=', 'c.id');

        if ($keyword)  $countQuery->where(fn($q) => $q->where('c.name', 'LIKE', "%{$keyword}%")->orWhere('ls.instructor', 'LIKE', "%{$keyword}%"));
        if ($status)   $countQuery->where('ls.status',  $status);
        if ($target)   $countQuery->where('c.target',   $target);
        if ($eduType)  $countQuery->where('c.edu_type', $eduType);

        $total = $countQuery->count();

        // list: SP로 복잡한 JOIN + 집계 처리
        $list = $this->callSp('CALL sp_v4_reg_edu_course_list(?, ?, ?, ?, ?, ?, ?)', [
            $churchId, $keyword, $status, $target, $eduType, $page, $size,
        ]);

        return compact('total', 'page', 'size', 'list');
    }

    public function getCourseById(int $courseId): ?stdClass
    {
        return DB::table('reg_education_courses')->where('id', $courseId)->first();
    }

    public function insertCourse(array $data): int
    {
        return (int) DB::table('reg_education_courses')->insertGetId($data);
    }

    public function updateCourse(int $courseId, array $data): void
    {
        DB::table('reg_education_courses')->where('id', $courseId)->update($data);
    }

    public function deleteCourse(int $courseId): void
    {
        DB::table('reg_education_courses')->where('id', $courseId)->delete();
    }

    public function countSessionsByCourse(int $courseId): int
    {
        return DB::table('reg_education_sessions')->where('course_id', $courseId)->count();
    }

    // ─────────────────────────────────────────────────────────────
    // 교육 기수 (reg_education_sessions)
    // ─────────────────────────────────────────────────────────────

    /** SP — 과정명 JOIN + 수강 통계 집계 + 페이징 */
    public function getSessionList(int $churchId, array $filters): array
    {
        $page     = max(1, (int) ($filters['page'] ?? 1));
        $size     = max(1, min(100, (int) ($filters['size'] ?? 20)));
        $courseId = $filters['course_id'] ?? null;
        $status   = $filters['status']    ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate   = $filters['to_date']   ?? null;
        $keyword  = $filters['keyword']   ?? null;

        // count: 단순 JOIN 쿼리로 별도 처리
        $countQuery = DB::table('reg_education_sessions as s')
            ->join('reg_education_courses as c', 'c.id', '=', 's.course_id')
            ->where('s.church_id', $churchId);

        if ($courseId) $countQuery->where('s.course_id',  $courseId);
        if ($status)   $countQuery->where('s.status',     $status);
        if ($fromDate) $countQuery->where('s.start_date', '>=', $fromDate);
        if ($toDate)   $countQuery->where('s.end_date',   '<=', $toDate);
        if ($keyword)  $countQuery->where(fn($q) => $q
            ->where('s.generation', 'LIKE', "%{$keyword}%")
            ->orWhere('s.instructor', 'LIKE', "%{$keyword}%")
            ->orWhere('c.name', 'LIKE', "%{$keyword}%"));

        $total = $countQuery->count();

        // list: SP로 복잡한 JOIN + 집계 처리
        $list = $this->callSp('CALL sp_v4_reg_edu_session_list(?, ?, ?, ?, ?, ?, ?, ?)', [
            $churchId, $courseId, $status, $fromDate, $toDate, $keyword, $page, $size,
        ]);

        return compact('total', 'page', 'size', 'list');
    }

    /** SP — 과정명 JOIN + 수강 통계 집계 (단일 행) */
    public function getSessionById(int $sessionId): ?stdClass
    {
        $rows = $this->callSp('CALL sp_v4_reg_edu_session_detail(?)', [$sessionId]);
        if (empty($rows)) return null;
        $row = $rows[0];
        // SP가 church_id를 포함하지 않을 경우 직접 조회해서 보완
        if (!isset($row->church_id) || $row->church_id === null) {
            $raw = DB::table('reg_education_sessions')->where('id', $sessionId)->value('church_id');
            $row->church_id = $raw;
        }
        // SP가 edu_type를 포함하지 않을 경우 course 테이블에서 보완
        if (!isset($row->edu_type) || $row->edu_type === null) {
            $row->edu_type = DB::table('reg_education_courses')
                ->where('id', $row->course_id)
                ->value('edu_type');
        }
        return $row;
    }

    public function insertSession(array $data): int
    {
        return (int) DB::table('reg_education_sessions')->insertGetId($data);
    }

    public function updateSession(int $sessionId, array $data): void
    {
        DB::table('reg_education_sessions')->where('id', $sessionId)->update($data);
    }

    public function deleteSession(int $sessionId): void
    {
        DB::table('reg_education_sessions')->where('id', $sessionId)->delete();
    }

    public function countEnrollmentsBySession(int $sessionId): int
    {
        return DB::table('reg_education_records')->where('session_id', $sessionId)->count();
    }

    // ─────────────────────────────────────────────────────────────
    // 수강 기록 (reg_education_records)
    // ─────────────────────────────────────────────────────────────

    public function getEnrollment(int $sessionId, int $memberId): ?stdClass
    {
        return DB::table('reg_education_records')
            ->where('session_id', $sessionId)
            ->where('member_id',  $memberId)
            ->first();
    }

    public function insertEnrollment(array $data): int
    {
        return (int) DB::table('reg_education_records')->insertGetId($data);
    }

    public function updateEnrollment(int $enrollmentId, array $data): void
    {
        DB::table('reg_education_records')->where('id', $enrollmentId)->update($data);
    }

    public function getEnrollmentsBySession(int $sessionId, array $filters): array
    {
        $query = DB::table('reg_education_records as r')
            ->join('reg_members as m', 'm.id', '=', 'r.member_id')
            ->where('r.session_id', $sessionId)
            ->where('m.is_deleted', 0);

        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        $total = (clone $query)->count();
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $size  = max(1, min(200, (int) ($filters['size'] ?? 100)));

        $list = $query->orderBy('r.status')->orderBy('m.name')
            ->forPage($page, $size)
            ->get([
                'r.id as enrollment_id',
                'r.progress',
                'r.status',
                'r.created_at',
                'm.id as member_id',
                'm.member_no',
                'm.name as member_name',
                'm.phone',
            ])->toArray();

        return compact('total', 'page', 'size', 'list');
    }

    /** SP — 3개 테이블 JOIN */
    public function getSessionsByMember(int $memberId): array
    {
        return $this->callSp('CALL sp_v4_reg_edu_sessions_by_member(?)', [$memberId]);
    }

    // ─────────────────────────────────────────────────────────────
    // 교육 회차별 출결 (reg_education_attendance)
    // ─────────────────────────────────────────────────────────────

    /**
     * 기수의 수강자 × 회차 출결 시트
     * 반환: { total_rounds, members: [...], attendance: { "{member_id}_{round_no}": status } }
     */
    public function getAttendanceSheet(int $sessionId): array
    {
        // 기수 정보 (total_rounds, start_date)
        $session = DB::table('reg_education_sessions')->where('id', $sessionId)->first();
        $totalRounds = (int) ($session?->total_rounds ?? 0);

        // 수강자 목록
        $members = DB::table('reg_education_records as r')
            ->join('reg_members as m', 'm.id', '=', 'r.member_id')
            ->where('r.session_id', $sessionId)
            ->where('m.is_deleted', 0)
            ->orderBy('m.name')
            ->get(['m.id as member_id', 'm.name', 'm.phone', 'r.status as enroll_status'])
            ->toArray();

        // 출결 기록 전체 (해당 기수)
        $rows = DB::table('reg_education_attendance')
            ->where('session_id', $sessionId)
            ->get(['member_id', 'round_no', 'status', 'session_date', 'note'])
            ->toArray();

        // 날짜 맵 (round_no → session_date) : 기수 전체 공통 날짜
        $datemap = [];
        foreach ($rows as $r) {
            if ($r->session_date) $datemap[(int) $r->round_no] = $r->session_date;
        }

        // 출결 맵 { "member_id_round_no" => status } / 메모 맵 { "member_id_round_no" => note }
        $attendance = [];
        $notes      = [];
        foreach ($rows as $r) {
            $attendance["{$r->member_id}_{$r->round_no}"] = $r->status;
            if ($r->note) {
                $notes["{$r->member_id}_{$r->round_no}"] = $r->note;
            }
        }

        return [
            'total_rounds' => $totalRounds,
            'members'      => $members,
            'attendance'   => $attendance,
            'notes'        => $notes,
            'round_dates'  => $datemap,
        ];
    }

    /**
     * 특정 회차 출결 일괄 upsert
     * $records = [['member_id'=>int, 'status'=>string, 'note'=>?string], ...]
     */
    public function saveRoundAttendance(int $sessionId, int $roundNo, ?string $sessionDate, array $records): void
    {
        $now = now()->toDateTimeString();
        foreach ($records as $rec) {
            DB::table('reg_education_attendance')->upsert(
                [
                    'session_id'   => $sessionId,
                    'member_id'    => (int) $rec['member_id'],
                    'round_no'     => $roundNo,
                    'status'       => $rec['status'],
                    'session_date' => $sessionDate,
                    'note'         => $rec['note'] ?? null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ],
                ['session_id', 'member_id', 'round_no'],
                ['status', 'session_date', 'note', 'updated_at']
            );
        }
    }

    /**
     * 기수별 교인 출석률 통계
     * 반환: [{ member_id, name, present, absent, late, excused, total, rate }, ...]
     */
    public function getMemberAttendanceStats(int $sessionId): array
    {
        return DB::table('reg_education_attendance as a')
            ->join('reg_members as m', 'm.id', '=', 'a.member_id')
            ->where('a.session_id', $sessionId)
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
                DB::raw("ROUND(SUM(a.status IN ('present','late')) / COUNT(*) * 100) AS rate"),
            ])
            ->toArray();
    }
}
