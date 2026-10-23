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
     * 교회 단위 교적·재정 사용 현황.
     *  - registry/finance : 이 교회의 활성(회수되지 않은) 담당자 권한 행 중 해당 권한이 켜진 것이 있는지(gh_church_admin_role)
     *  - has_data         : 교적 데이터가 이미 있는지(교인 기록) — 권한 행 없이 쓰던 교회가 안내 페이지에 갇히지 않게 하는 안전망
     */
    public function getChurchServiceUsage(int $churchNo): array
    {
        $perm = DB::table('gh_church_admin_role')
            ->where('church_no', $churchNo)
            ->whereNull('revoked_at')
            ->selectRaw('COALESCE(MAX(perm_registry), 0) AS registry, COALESCE(MAX(perm_finance), 0) AS finance')
            ->first();

        $hasData = DB::table('reg_members')->where('church_id', $churchNo)->exists();

        return [
            'registry' => (int) $perm->registry === 1,
            'finance'  => (int) $perm->finance === 1,
            'has_data' => $hasData,
        ];
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
