<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class FinanceAccountRepository
{
    private const FIELDS = [
        'id', 'church_id', 'type', 'name', 'bank', 'account_number', 'description',
        'use_for_offering', 'use_for_expense', 'is_active', 'sort_order',
        'created_at', 'updated_at',
    ];

    public function getList(int $churchId, array $filters): array
    {
        $query = DB::table('reg_finance_accounts')->where('church_id', $churchId);
        $this->applyFilters($query, $filters);

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get(self::FIELDS)
            ->toArray();
    }

    /** 헌금/지출 입력 화면의 원장 드롭다운용 — 활성 + 해당 용도로 표시 설정된 계좌만 */
    public function getActiveList(int $churchId, string $usageColumn): array
    {
        return DB::table('reg_finance_accounts')
            ->where('church_id', $churchId)
            ->where('is_active', 1)
            ->where($usageColumn, 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getById(int $accountId): ?stdClass
    {
        return DB::table('reg_finance_accounts')
            ->where('id', $accountId)
            ->first(self::FIELDS);
    }

    public function insert(array $data): int
    {
        return (int) DB::table('reg_finance_accounts')->insertGetId($data);
    }

    public function update(int $accountId, array $data): int
    {
        return DB::table('reg_finance_accounts')
            ->where('id', $accountId)
            ->update($data);
    }

    public function delete(int $accountId): int
    {
        return DB::table('reg_finance_accounts')
            ->where('id', $accountId)
            ->delete();
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['bank'])) {
            $query->where('bank', $filters['bank']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== null) {
            $query->where('is_active', (int) $filters['is_active']);
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'LIKE', $kw)
                  ->orWhere('account_number', 'LIKE', $kw);
            });
        }
    }
}
