<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class AdminRepository
{
    public function getAdminList(int $churchId, array $filters, int $page, int $size): array
    {
        $query = DB::table('gh_church_admin')
                   ->where('church_no', $churchId);

        if (!empty($filters['search_type']) && !empty($filters['search_value'])) {
            $col = $filters['search_type'] === 'id' ? 'id' : 'name';
            $query->where($col, 'like', '%' . $filters['search_value'] . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['admin_type'])) {
            $query->where('admin_type', $filters['admin_type']);
        }

        $total = $query->count();
        $list  = (clone $query)
                   ->select('admin_no', 'church_no', 'id', 'name', 'status', 'admin_type', 'last_login_at', 'registered')
                   ->orderBy('admin_no', 'desc')
                   ->forPage($page, $size)
                   ->get();

        return ['total' => $total, 'list' => $list];
    }

    public function getAdminByNo(int $adminNo, int $churchId): ?stdClass
    {
        return DB::table('gh_church_admin')
            ->select('admin_no', 'church_no', 'id', 'name', 'status', 'admin_type', 'last_login_at', 'registered')
            ->where('admin_no', $adminNo)
            ->where('church_no', $churchId)
            ->where('status', '!=', 'DELETE')
            ->first();
    }

    public function getAdminByNoAnyStatus(int $adminNo, int $churchId): ?stdClass
    {
        return DB::table('gh_church_admin')
            ->select('admin_no', 'church_no', 'id', 'name', 'status', 'admin_type', 'last_login_at', 'registered')
            ->where('admin_no', $adminNo)
            ->where('church_no', $churchId)
            ->first();
    }

    public function findByLoginId(string $loginId): ?stdClass
    {
        return DB::table('gh_church_admin')
            ->where('id', $loginId)
            ->first();
    }

    public function createAdmin(array $data): int
    {
        // registered, updated 컬럼은 DEFAULT current_timestamp() 이므로 생략
        return DB::table('gh_church_admin')->insertGetId([
            'church_no'  => $data['church_id'],
            'id'         => $data['login_id'],
            'name'       => $data['name'],
            'password'   => $data['password'],
            'status'     => 'ALIVE',
            'admin_type' => $data['admin_type'],
        ]);
    }

    public function updateAdmin(int $adminNo, array $data): bool
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->update(array_merge($data, ['updated' => now()])) > 0;
    }

    public function suspendAdmin(int $adminNo): bool
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->update(['status' => 'INACTIVE', 'updated' => now()]) > 0;
    }

    public function activateAdmin(int $adminNo): bool
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->update(['status' => 'ALIVE', 'updated' => now()]) > 0;
    }

    public function deleteAdmin(int $adminNo): bool
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->update(['status' => 'DELETE', 'updated' => now()]) > 0;
    }

    public function getDeletedAdminByNo(int $adminNo, int $churchId): ?stdClass
    {
        return DB::table('gh_church_admin')
            ->select('admin_no', 'church_no', 'id', 'name', 'status', 'admin_type', 'last_login_at', 'registered')
            ->where('admin_no', $adminNo)
            ->where('church_no', $churchId)
            ->where('status', 'DELETE')
            ->first();
    }

    public function purgeAdmin(int $adminNo): bool
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->where('status', 'DELETE')
            ->delete() > 0;
    }

    public function getUserStats(int $churchId): array
    {
        $active   = DB::table('gh_church_admin')
            ->where('church_no', $churchId)->where('status', 'ALIVE')->count();
        $inactive = DB::table('gh_church_admin')
            ->where('church_no', $churchId)->where('status', 'INACTIVE')->count();

        return [
            'total'    => $active + $inactive,
            'active'   => $active,
            'inactive' => $inactive,
        ];
    }

    public function getRecentLoginCount(int $churchId, int $days): int
    {
        return DB::table('gh_church_admin')
            ->where('church_no', $churchId)
            ->where('status', 'ALIVE')
            ->where('last_login_at', '>=', now()->subDays($days))
            ->count();
    }
}
