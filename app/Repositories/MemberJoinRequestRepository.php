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

        $invites = $this->getJoinedInvitesByAccounts($churchId, array_map(fn ($r) => (int) $r->account_no, $rows));
        foreach ($rows as $row) {
            $row->match_candidates = $this->findMatchCandidates($churchId, $row->name, $row->phone);
            $row->invite = $invites[(int) $row->account_no] ?? null;
        }

        return ['total' => $total, 'page' => $page, 'size' => $size, 'list' => $rows];
    }

    /**
     * 휴대폰 번호가 정확히 일치하는 기존 교인 후보 (자동 매칭 제안용)
     */
    private function findMatchCandidates(int $churchId, string $name, ?string $phone): array
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return [];
        }

        // gh_account(01012345678) 와 reg_members(010-1234-5678) 저장 형식이 달라 숫자만 비교
        return DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '.', '') = ?", [$digits])
            ->get(['id', 'name', 'phone'])
            ->toArray();
    }

    /**
     * 초대 링크로 가입한 계정 → 초대 대상 교적 (웹앱이 가입 시 reg_member_invites 에
     * status=joined / joined_account_no 를 기록한 건 중, 교적이 아직 미연동인 것만)
     *
     * @return array account_no => object(invite_id, member_id, member_name, member_phone, member_birth_date, joined_at)
     */
    public function getJoinedInvitesByAccounts(int $churchId, array $accountNos): array
    {
        if (empty($accountNos)) {
            return [];
        }

        $rows = DB::table('reg_member_invites as i')
            ->join('reg_members as m', 'm.id', '=', 'i.member_id')
            ->where('i.church_id', $churchId)
            ->where('i.status', 'joined')
            ->whereIn('i.joined_account_no', $accountNos)
            ->where('m.is_deleted', 0)
            ->whereNull('m.churchero_user_id')
            ->orderByDesc('i.id')
            ->get([
                'i.id as invite_id', 'i.joined_account_no', 'i.joined_at',
                'm.id as member_id', 'm.name as member_name', 'm.phone as member_phone', 'm.birth_date as member_birth_date',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->joined_account_no] ??= $r; // 계정당 최신 1건
        }
        return $out;
    }

    public function isAccountLinked(int $churchId, int $accountNo): bool
    {
        return DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('churchero_user_id', $accountNo)
            ->where('is_deleted', 0)
            ->exists();
    }

    /**
     * 교적에 계정 연결 — 이미 다른 계정이 연결된 교적은 건드리지 않음(영향 행 0)
     */
    public function linkMemberAccount(int $memberId, int $accountNo): int
    {
        return DB::table('reg_members')
            ->where('id', $memberId)
            ->where('is_deleted', 0)
            ->whereNull('churchero_user_id')
            ->update(['churchero_user_id' => $accountNo, 'unlinked_at' => null]);
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
