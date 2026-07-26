<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * 예배/모임 정의 — gh_church_timetable(공유 테이블) 사용.
 * 원래 reg_services 전용이었으나, 같은 정보를 공개 홈페이지용 gh_church_timetable과
 * 별도로 관리하고 있어 gh_church_timetable로 통합함 (docs/schema/gh_church_alter.md 참조).
 * timetable_no/church_no 컬럼을 id/church_id로 별칭해 기존 API 응답 형태를 유지한다.
 */
class WorshipRepository
{
    private const SELECT_COLUMNS = [
        'timetable_no as id', 'church_no as church_id', 'name', 'description', 'note', 'category',
        'day_of_week', 'start_time', 'target_org_id', 'sort_order', 'is_active',
        'registered as created_at', 'updated as updated_at',
    ];

    public function getWorshipList(int $churchId, array $filters): array
    {
        $query = DB::table('gh_church_timetable')
            ->where('church_no', $churchId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', (int) (bool) $filters['is_active']);
        }

        if (isset($filters['day_of_week'])) {
            $query->where('day_of_week', (int) $filters['day_of_week']);
        }

        if (!empty($filters['keyword'])) {
            $query->where('name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('timetable_no', 'asc')
            ->get(self::SELECT_COLUMNS)
            ->toArray();
    }

    public function getWorshipById(int $serviceId): ?stdClass
    {
        return DB::table('gh_church_timetable')
            ->where('timetable_no', $serviceId)
            ->first(self::SELECT_COLUMNS);
    }

    public function insertWorship(array $data): int
    {
        $data['church_no'] = $data['church_id'];
        unset($data['church_id']);
        $data['registered'] = now();
        $data['updated']    = now();
        $data['place']      = $data['place'] ?? '';
        $data['timetext']   = $data['timetext'] ?? $this->buildTimetext($data['day_of_week'] ?? null, $data['start_time'] ?? null);
        return (int) DB::table('gh_church_timetable')->insertGetId($data, 'timetable_no');
    }

    public function updateWorship(int $serviceId, array $data): int
    {
        $data['updated'] = now();
        return DB::table('gh_church_timetable')
            ->where('timetable_no', $serviceId)
            ->update($data);
    }

    public function deactivateWorship(int $serviceId): int
    {
        return DB::table('gh_church_timetable')
            ->where('timetable_no', $serviceId)
            ->update(['is_active' => 0, 'updated' => now()]);
    }

    private function buildTimetext(?int $dayOfWeek, ?string $startTime): string
    {
        $days = ['일', '월', '화', '수', '목', '금', '토'];
        $label = $dayOfWeek !== null ? "매주 {$days[$dayOfWeek]}요일" : '매일';
        return $startTime ? "{$label} {$startTime}" : $label;
    }
}
