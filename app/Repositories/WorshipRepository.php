<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * reg_services(예배/모임) 테이블 접근
 * — 'Service' 명은 서비스 레이어와 명명 충돌 회피를 위해 Worship 도메인으로 통칭
 */
class WorshipRepository
{
    public function getWorshipList(int $churchId, array $filters): array
    {
        $query = DB::table('reg_services')
            ->where('church_id', $churchId);

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
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    public function getWorshipById(int $serviceId): ?stdClass
    {
        return DB::table('reg_services')
            ->where('id', $serviceId)
            ->first();
    }

    public function insertWorship(array $data): int
    {
        return (int) DB::table('reg_services')->insertGetId($data);
    }

    public function updateWorship(int $serviceId, array $data): int
    {
        return DB::table('reg_services')
            ->where('id', $serviceId)
            ->update($data);
    }

    public function deactivateWorship(int $serviceId): int
    {
        return DB::table('reg_services')
            ->where('id', $serviceId)
            ->update(['is_active' => 0]);
    }
}
