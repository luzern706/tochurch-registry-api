<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class AuthRepository
{
    /**
     * 이메일로 활성 교인 계정 조회
     *
     * is_deleted=0 인 레코드만 반환.
     * uq_email_church (email, church_id) 제약 때문에 동일 이메일이 여러 교회에 존재할 수 있으므로
     * id ASC 우선 매칭. (멀티 교회 로그인 분기 필요해질 경우 별도 메서드로 확장)
     */
    public function findActiveByEmail(string $email): ?stdClass
    {
        return DB::table('reg_members')
            ->where('email', $email)
            ->where('is_deleted', 0)
            ->orderBy('id', 'asc')
            ->first();
    }

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
}
