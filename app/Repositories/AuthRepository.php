<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class AuthRepository
{
    /**
     * 로그인 ID 로 활성 교회 관리자 계정 조회 (gh_church_admin)
     *
     * status = 'ALIVE' 인 계정만 반환.
     * gh_church_admin.id (TEXT) 는 로그인에 사용하는 아이디 필드.
     */
    public function findAdminByLoginId(string $loginId): ?stdClass
    {
        return DB::table('gh_church_admin')
            ->where('id', $loginId)
            ->where('status', 'ALIVE')
            ->first();
    }

    public function findAdminByAdminNo(int $adminNo): ?stdClass
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->first();
    }

    public function updateLastLoginAt(int $adminNo): void
    {
        DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->update(['last_login_at' => now()]);
    }

    public function getChurchName(int $churchNo): ?string
    {
        return DB::table('gh_church')
            ->where('church_no', $churchNo)
            ->value('name');
    }
}
