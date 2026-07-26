<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class PermissionRepository
{
    public function getByChurch(int $churchId): array
    {
        return DB::table('reg_role_permissions')
            ->where('church_id', $churchId)
            ->get(['role', 'page_code', 'can_access'])
            ->all();
    }

    public function getByRole(int $churchId, string $role): array
    {
        return DB::table('reg_role_permissions')
            ->where('church_id', $churchId)
            ->where('role', $role)
            ->get(['page_code', 'can_access'])
            ->all();
    }

    /** 지정한 page_code 목록 중 실제로 오버라이드가 저장된 것만 [page_code => bool] 로 반환 */
    public function getOverridesForPages(int $churchId, string $role, array $pageCodes): array
    {
        if (empty($pageCodes)) {
            return [];
        }
        return DB::table('reg_role_permissions')
            ->where('church_id', $churchId)
            ->where('role', $role)
            ->whereIn('page_code', $pageCodes)
            ->pluck('can_access', 'page_code')
            ->map(fn ($v) => (bool) $v)
            ->all();
    }

    /** @param array<int, array{page_code:string, can_access:bool}> $rows */
    public function upsertMany(int $churchId, string $role, array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('reg_role_permissions')->updateOrInsert(
                ['church_id' => $churchId, 'role' => $role, 'page_code' => $row['page_code']],
                ['can_access' => (int) $row['can_access'], 'updated_at' => now()]
            );
        }
    }

    /** admin_type별 관리자 수 (역할 목록 화면의 "사용자 N명") */
    public function getRoleUserCounts(int $churchId): array
    {
        return DB::table('gh_church_admin')
            ->select('admin_type', DB::raw('COUNT(*) as cnt'))
            ->where('church_no', $churchId)
            ->where('status', 'ALIVE')
            ->groupBy('admin_type')
            ->pluck('cnt', 'admin_type')
            ->all();
    }
}
