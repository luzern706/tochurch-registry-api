<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * 성도 인증 — 내 교회를 출석교회로 선택한 교회로 회원(gh_account)의 승인/거절 처리.
 * docs/schema/19_reg_church_member_certs.sql 참조 — 테이블에는 승인/거절 결과만 저장하고
 * "대기"는 (내 교회 소속인데 현재 교회 기준 처리 행이 없는 계정)으로 실시간 계산한다.
 * 행의 church_id 가 gh_account.church_no 와 다르면(성도가 교회를 바꿈) 처리 결과는 무효다.
 */
class MemberCertRepository
{
    /** 현재 교회 소속 + 현재 교회 기준 인증 결과를 붙인 기본 쿼리 */
    private function baseQuery(int $churchId)
    {
        return DB::table('gh_account as a')
            ->leftJoin('reg_church_member_certs as c', function ($join) {
                $join->on('c.account_no', '=', 'a.account_no')
                     ->on('c.church_id', '=', 'a.church_no');
            })
            ->where('a.church_no', $churchId)
            ->where('a.status', 'ALIVE');
    }

    public function getList(int $churchId, array $filters): array
    {
        $status = $filters['status'] ?? 'pending';

        $query = $this->baseQuery($churchId);
        match ($status) {
            'approved' => $query->where('c.status', 'approved'),
            'rejected' => $query->where('c.status', 'rejected'),
            default    => $query->whereNull('c.id'),
        };

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
            ->get([
                'a.account_no', 'a.name', 'a.phone', 'a.email', 'a.church_job', 'a.registered',
                'c.status as cert_status', 'c.reason', 'c.updated_at as processed_at',
            ])
            ->toArray();

        return ['total' => $total, 'page' => $page, 'size' => $size, 'list' => $rows];
    }

    /** 탭 건수 — 대기/승인/거절 */
    public function getCounts(int $churchId): array
    {
        $row = $this->baseQuery($churchId)
            ->selectRaw("COUNT(*) AS total, SUM(c.status = 'approved') AS approved, SUM(c.status = 'rejected') AS rejected")
            ->first();

        $total    = (int) ($row->total ?? 0);
        $approved = (int) ($row->approved ?? 0);
        $rejected = (int) ($row->rejected ?? 0);

        return ['pending' => $total - $approved - $rejected, 'approved' => $approved, 'rejected' => $rejected];
    }

    /**
     * 요청한 계정 중 내 교회 소속(활성)인 계정만 반환 — 다른 교회 계정 처리 방지
     *
     * @param int[] $accountNos
     * @return object[] account_no, name, phone
     */
    public function getOwnAccounts(int $churchId, array $accountNos): array
    {
        if (empty($accountNos)) {
            return [];
        }

        return DB::table('gh_account')
            ->where('church_no', $churchId)
            ->where('status', 'ALIVE')
            ->whereIn('account_no', $accountNos)
            ->get(['account_no', 'name', 'phone'])
            ->all();
    }

    /**
     * 승인/거절 결과 저장 — 계정당 1행(이전 교회 행이 있으면 덮어씀)
     *
     * @param int[] $accountNos
     */
    public function upsertResult(int $churchId, array $accountNos, string $status, ?string $reason, int $createdBy): void
    {
        $now  = now();
        $rows = array_map(fn ($no) => [
            'account_no' => $no,
            'church_id'  => $churchId,
            'status'     => $status,
            'reason'     => $reason,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ], $accountNos);

        DB::table('reg_church_member_certs')->upsert(
            $rows,
            ['account_no'],
            ['church_id', 'status', 'reason', 'created_by', 'updated_at']
        );
    }

    /** 처리 결과를 지워 "대기"로 되돌림 — 내 교회 행만 삭제 */
    public function deleteResult(int $churchId, int $accountNo): int
    {
        return DB::table('reg_church_member_certs')
            ->where('account_no', $accountNo)
            ->where('church_id', $churchId)
            ->delete();
    }
}
