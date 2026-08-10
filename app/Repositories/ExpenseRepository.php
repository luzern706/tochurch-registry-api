<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class ExpenseRepository
{
    private const FIELDS = [
        'id', 'church_id', 'expense_date', 'category', 'amount', 'purpose',
        'vendor', 'method', 'ledger', 'receipt_files', 'note', 'recorded_by',
        'created_at', 'updated_at',
    ];

    public function getExpenseList(int $churchId, array $filters): array
    {
        $query = DB::table('reg_expense_records')
            ->where('church_id', $churchId);

        $this->applyFilters($query, $filters);

        $totalQ = (clone $query);
        $sumQ   = (clone $query);

        $total = $totalQ->count();
        $sum   = (int) $sumQ->sum('amount');

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->forPage($page, $size)
            ->get(self::FIELDS)
            ->map(fn ($row) => $this->decodeRow($row))
            ->toArray();

        return [
            'total'        => $total,
            'total_amount' => $sum,
            'page'         => $page,
            'size'         => $size,
            'list'         => $list,
        ];
    }

    public function getExpenseById(int $expenseId): ?stdClass
    {
        $row = DB::table('reg_expense_records')
            ->where('id', $expenseId)
            ->first(self::FIELDS);

        return $row ? $this->decodeRow($row) : null;
    }

    public function insertExpense(array $data): int
    {
        return (int) DB::table('reg_expense_records')->insertGetId($data);
    }

    public function updateExpense(int $expenseId, array $data): int
    {
        return DB::table('reg_expense_records')
            ->where('id', $expenseId)
            ->update($data);
    }

    public function deleteExpense(int $expenseId): int
    {
        return DB::table('reg_expense_records')
            ->where('id', $expenseId)
            ->delete();
    }

    /** 항목별(카테고리) 합계 — 지출 항목별 조회 탭의 KPI/차트용 */
    public function getCategoryTotals(int $churchId, string $fromDate, string $toDate): array
    {
        return DB::table('reg_expense_records')
            ->where('church_id', $churchId)
            ->whereBetween('expense_date', [$fromDate, $toDate])
            ->selectRaw('category, SUM(amount) as total_amount, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($r) => [
                'category'     => $r->category,
                'total_amount' => (int) $r->total_amount,
                'count'        => (int) $r->count,
            ])
            ->toArray();
    }

    /** 기간 내 건수/합계만 필요할 때 (대시보드 KPI 등, 목록 조회 없이 가벼운 집계) */
    public function getTotals(int $churchId, string $fromDate, string $toDate): array
    {
        $row = DB::table('reg_expense_records')
            ->where('church_id', $churchId)
            ->whereBetween('expense_date', [$fromDate, $toDate])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        return [
            'count'        => (int) ($row->count ?? 0),
            'total_amount' => (int) ($row->total_amount ?? 0),
        ];
    }

    /** 전체 기간 누적 지출 합계 (재정 대시보드 "현재 잔액" 근사 계산용) */
    public function getCumulativeTotal(int $churchId): int
    {
        return (int) DB::table('reg_expense_records')
            ->where('church_id', $churchId)
            ->sum('amount');
    }

    /** 월별 지출 추이 (재정 대시보드 차트용) */
    public function getMonthlyTrend(int $churchId, string $fromDate, string $toDate): array
    {
        return DB::table('reg_expense_records')
            ->where('church_id', $churchId)
            ->whereBetween('expense_date', [$fromDate, $toDate])
            ->selectRaw('DATE_FORMAT(expense_date, "%Y-%m") as ym, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('ym')
            ->orderBy('ym', 'asc')
            ->get()
            ->map(fn ($r) => ['ym' => $r->ym, 'count' => (int) $r->count, 'total_amount' => (int) $r->total_amount])
            ->toArray();
    }

    /** 주차별 지출 추이 (재정 통계 화면용) — fromDate 기준 7일 단위로 구간을 나눔 */
    public function getWeeklyTrend(int $churchId, string $fromDate, string $toDate): array
    {
        return DB::table('reg_expense_records')
            ->where('church_id', $churchId)
            ->whereBetween('expense_date', [$fromDate, $toDate])
            ->selectRaw('FLOOR(DATEDIFF(expense_date, ?) / 7) as week_idx, SUM(amount) as total_amount', [$fromDate])
            ->groupBy('week_idx')
            ->orderBy('week_idx', 'asc')
            ->get()
            ->map(fn ($r) => ['week_idx' => (int) $r->week_idx, 'total_amount' => (int) $r->total_amount])
            ->toArray();
    }

    /** 증빙 관리 탭 KPI — 전체 건수 / 증빙 미첨부 건수 */
    public function getReceiptSummary(int $churchId, array $filters): array
    {
        $base = DB::table('reg_expense_records')->where('church_id', $churchId);
        $this->applyFilters($base, array_diff_key($filters, ['has_receipt' => true]));

        $total   = (clone $base)->count();
        $missing = (clone $base)
            ->where(function ($q) {
                $q->whereNull('receipt_files')
                  ->orWhereRaw("JSON_LENGTH(receipt_files) = 0");
            })
            ->count();

        return ['total' => $total, 'missing' => $missing];
    }

    private function decodeRow(stdClass $row): stdClass
    {
        $row->receipt_files = $row->receipt_files ? (json_decode($row->receipt_files, true) ?: []) : [];
        return $row;
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }
        if (!empty($filters['ledger'])) {
            $query->where('ledger', $filters['ledger']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('expense_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('expense_date', '<=', $filters['to_date']);
        }
        if (array_key_exists('has_receipt', $filters) && $filters['has_receipt'] !== null) {
            if ((bool) $filters['has_receipt']) {
                $query->whereNotNull('receipt_files')
                      ->whereRaw("JSON_LENGTH(receipt_files) > 0");
            } else {
                $query->where(function ($q) {
                    $q->whereNull('receipt_files')
                      ->orWhereRaw("JSON_LENGTH(receipt_files) = 0");
                });
            }
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('category', 'LIKE', $kw)
                  ->orWhere('purpose', 'LIKE', $kw)
                  ->orWhere('vendor', 'LIKE', $kw);
            });
        }
    }
}
