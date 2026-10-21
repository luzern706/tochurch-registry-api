<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class OnboardingRepository
{
    public function findAdmin(int $adminNo): ?stdClass
    {
        return DB::table('gh_church_admin')
            ->where('admin_no', $adminNo)
            ->where('status', 'ALIVE')
            ->first();
    }

    /**
     * 이 관리자 계정에 연결된 활성(회수되지 않은) 담당자 권한 행.
     * 연결 키는 02_gh_admin_api 의 교적 계정 발급과 동일 — account_no 우선, 없으면 신청 이메일 = 로그인 아이디.
     * 기존 교회 공용 계정(account_no NULL, id 방식)은 권한 행이 없어 빈 배열이 된다.
     */
    public function getActiveRoles(stdClass $admin): array
    {
        $loginId = mb_strtolower((string) $admin->id);

        $query = DB::table('gh_church_admin_role')
            ->where('church_no', $admin->church_no)
            ->whereNull('revoked_at')
            ->where(function ($q) use ($admin, $loginId) {
                if ($admin->account_no !== null) {
                    $q->where('account_no', $admin->account_no)
                      ->orWhereRaw('LOWER(applicant_email) = ?', [$loginId]);
                } else {
                    $q->whereRaw('LOWER(applicant_email) = ?', [$loginId]);
                }
            });

        return $query->get(['role_no', 'perm_registry', 'perm_finance'])->all();
    }

    public function findLatestApplication(int $churchId): ?stdClass
    {
        return DB::table('reg_service_applications')
            ->where('church_id', $churchId)
            ->whereIn('status', ['received', 'completed'])
            ->orderByDesc('application_id')
            ->first();
    }

    public function insertApplication(array $data): int
    {
        return (int) DB::table('reg_service_applications')->insertGetId($data);
    }
}
