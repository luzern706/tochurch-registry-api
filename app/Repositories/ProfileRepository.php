<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * 내정보 (로그인한 관리자 본인) — gh_church_admin 조회/수정
 */
class ProfileRepository
{
    public function getMyProfile(int $adminNo): ?stdClass
    {
        return DB::table('gh_church_admin')
            ->select('admin_no', 'church_no', 'id', 'name', 'email', 'phone', 'status', 'admin_type', 'last_login_at', 'registered')
            ->where('admin_no', $adminNo)
            ->where('status', '!=', 'DELETE')
            ->first();
    }

    public function updateMyProfile(int $adminNo, array $fields): bool
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->update(array_merge($fields, ['updated' => now()])) > 0;
    }

    public function findPasswordHashByNo(int $adminNo): ?string
    {
        $row = DB::table('gh_church_admin')
            ->select('password')
            ->where('admin_no', $adminNo)
            ->first();

        return $row?->password;
    }

    public function updatePassword(int $adminNo, string $hashedPassword): bool
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->update(['password' => $hashedPassword, 'updated' => now()]) > 0;
    }
}
