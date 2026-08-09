<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * 교회로 회원(gh_account) → 교적(reg_members) 연동 대기 목록.
 * docs/schema/10_reg_member_join_requests.sql 참조 — 대상 목록 자체는 안티조인으로
 * 실시간 계산하고, 보류/반려 처리 이력만 reg_member_join_requests 에 저장한다.
 */
class MemberJoinRequestRepository
{
    /**
     * 아직 교적에 연동되지 않은 gh_account 목록 (보류/반려 처리된 계정은 제외)
     */
    public function getPendingList(int $churchId, array $filters): array
    {
        $query = DB::table('gh_account as a')
            ->where('a.church_no', $churchId)
            ->where('a.status', 'ALIVE')
            ->whereNotExists(function ($q) use ($churchId) {
                $q->select(DB::raw(1))
                  ->from('reg_members as m')
                  ->whereColumn('m.churchero_user_id', 'a.account_no')
                  ->where('m.church_id', $churchId)
                  ->where('m.is_deleted', 0);
            })
            ->whereNotExists(function ($q) use ($churchId) {
                $q->select(DB::raw(1))
                  ->from('reg_member_join_requests as r')
                  ->whereColumn('r.account_no', 'a.account_no')
                  ->where('r.church_id', $churchId);
            });

        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('a.name', 'LIKE', $kw)
                  ->orWhere('a.phone', 'LIKE', $kw);
            });
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $rows = $query->orderBy('a.registered', 'desc')
            ->forPage($page, $size)
            ->get(['a.account_no', 'a.name', 'a.phone', 'a.email', 'a.church_job', 'a.registered'])
            ->toArray();

        foreach ($rows as $row) {
            $row->match_candidates = $this->findMatchCandidates($churchId, $row->name, $row->phone);
        }

        return ['total' => $total, 'page' => $page, 'size' => $size, 'list' => $rows];
    }

    /**
     * 휴대폰 번호가 정확히 일치하는 기존 교인 후보 (자동 매칭 제안용)
     */
    private function findMatchCandidates(int $churchId, string $name, ?string $phone): array
    {
        if (empty($phone)) {
            return [];
        }

        return DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->where('phone', $phone)
            ->get(['id', 'name', 'phone'])
            ->toArray();
    }

    public function upsertStatus(int $churchId, int $accountNo, string $status, ?string $reason, int $createdBy): void
    {
        DB::table('reg_member_join_requests')->updateOrInsert(
            ['church_id' => $churchId, 'account_no' => $accountNo],
            ['status' => $status, 'reason' => $reason, 'created_by' => $createdBy, 'updated_at' => now()]
        );
    }

    public function getAccountById(int $accountNo): ?object
    {
        return DB::table('gh_account')
            ->where('account_no', $accountNo)
            ->first(['account_no', 'name', 'phone', 'email', 'church_no']);
    }
}
