<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class BudgetRepository
{
    private const FIELDS = [
        'id', 'church_id', 'year', 'category', 'amount', 'formula', 'updated_by',
        'created_at', 'updated_at',
    ];

    public function getBudgetList(int $churchId, int $year): array
    {
        return DB::table('reg_budgets')
            ->where('church_id', $churchId)
            ->where('year', $year)
            ->get(self::FIELDS)
            ->toArray();
    }

    public function getBudgetByCategory(int $churchId, int $year, string $category): ?stdClass
    {
        return DB::table('reg_budgets')
            ->where('church_id', $churchId)
            ->where('year', $year)
            ->where('category', $category)
            ->first(self::FIELDS);
    }

    public function getBudgetById(int $budgetId): ?stdClass
    {
        return DB::table('reg_budgets')
            ->where('id', $budgetId)
            ->first(self::FIELDS);
    }

    public function insertBudget(array $data): int
    {
        return (int) DB::table('reg_budgets')->insertGetId($data);
    }

    public function updateBudget(int $budgetId, array $data): int
    {
        return DB::table('reg_budgets')
            ->where('id', $budgetId)
            ->update($data);
    }

    public function deleteBudget(int $budgetId): int
    {
        return DB::table('reg_budgets')
            ->where('id', $budgetId)
            ->delete();
    }

    /** 최종 수정자 이름까지 포함한 가장 최근 수정 정보 (연도 전체 기준) */
    public function getLastUpdated(int $churchId, int $year): ?stdClass
    {
        return DB::table('reg_budgets as b')
            ->leftJoin('gh_church_admin as a', 'a.admin_no', '=', 'b.updated_by')
            ->where('b.church_id', $churchId)
            ->where('b.year', $year)
            ->orderByDesc('b.updated_at')
            ->first(['b.updated_at', 'a.name as updated_by_name']);
    }
}
