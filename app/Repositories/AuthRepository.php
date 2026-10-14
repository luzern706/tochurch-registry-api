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

    /**
     * 비밀번호 설정 링크 토큰 조회 (02_gh_admin_api 가 gh_church_admin_pw_token 에 해시로 저장).
     * 미사용·미무효화·미만료 토큰이고 계정이 ALIVE 인 경우에만 반환. $forUpdate 는 트랜잭션 안에서 행 잠금.
     */
    public function findValidSetupToken(string $tokenHash, bool $forUpdate = false): ?stdClass
    {
        $query = DB::table('gh_church_admin_pw_token as t')
            ->join('gh_church_admin as a', 'a.admin_no', '=', 't.admin_no')
            ->leftJoin('gh_church as c', 'c.church_no', '=', 'a.church_no')
            ->where('t.token_hash', $tokenHash)
            ->whereNull('t.consumed_at')
            ->whereNull('t.invalidated_at')
            ->where('t.expires_at', '>', now())
            ->where('a.status', 'ALIVE')
            ->select('t.token_no', 't.admin_no', 'a.church_no', 'a.id as login_id', 'a.name as admin_name', 'c.name as church_name');

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** 설정한 비밀번호 저장 + 해당 계정의 설정 링크 토큰 전부 사용 처리(1회용) */
    public function applySetupPassword(int $adminNo, string $hashedPassword): void
    {
        DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->update(['password' => $hashedPassword, 'updated' => now()]);

        DB::table('gh_church_admin_pw_token')
            ->where('admin_no', $adminNo)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
