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

    /**
     * 비밀번호 재설정 대상 계정 — 아이디(로그인 ID)나 이메일이 입력한 이메일과 같은 활성(ALIVE) 교회 관리자 계정.
     * 같은 이메일이 여러 교회 계정에 쓰이면 모두 돌려준다(교회별로 링크를 따로 보낸다).
     *
     * @return array<int, stdClass> admin_no, church_no, login_id, admin_name, email, church_name
     */
    public function findAliveAdminsByEmail(string $normalizedEmail, int $limit = 5): array
    {
        return DB::table('gh_church_admin as a')
            ->leftJoin('gh_church as c', 'c.church_no', '=', 'a.church_no')
            ->where('a.status', 'ALIVE')
            ->where(function ($q) use ($normalizedEmail) {
                $q->whereRaw('LOWER(TRIM(a.email)) = ?', [$normalizedEmail])
                    ->orWhereRaw('LOWER(TRIM(a.id)) = ?', [$normalizedEmail]);
            })
            ->orderBy('a.admin_no')
            ->limit($limit)
            ->get(['a.admin_no', 'a.church_no', 'a.id as login_id', 'a.name as admin_name', 'a.email', 'c.name as church_name'])
            ->all();
    }

    /** 최근 N초 안에 이 계정으로 발급된 설정·재설정 토큰 수 — DB 서버 시각 기준(created_at 이 DB 기본값이라 앱 시각과 어긋나지 않게) */
    public function countRecentTokens(int $adminNo, int $seconds): int
    {
        return (int) DB::table('gh_church_admin_pw_token')
            ->where('admin_no', $adminNo)
            ->whereRaw('created_at >= (NOW() - INTERVAL ? SECOND)', [$seconds])
            ->count();
    }

    /** 이전 미사용 토큰을 무효화하고 새 토큰(해시) 저장 — 02_gh_admin_api RegistryAdminRepository::issueToken 과 같은 규칙 */
    public function issueSetupToken(int $adminNo, string $tokenHash, \DateTimeInterface $expiresAt): void
    {
        DB::table('gh_church_admin_pw_token')
            ->where('admin_no', $adminNo)
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now()]);

        DB::table('gh_church_admin_pw_token')->insert([
            'admin_no' => $adminNo,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    /** 수신 거부(바운스·스팸 신고) 처리된 이메일인지 — 03_gh_www_api 웹훅이 채우는 공유 테이블(gh_email_suppression), 없으면 false */
    public function isEmailSuppressed(string $email): bool
    {
        try {
            return DB::table('gh_email_suppression')->where('email', $email)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
