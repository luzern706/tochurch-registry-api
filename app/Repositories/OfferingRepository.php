<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class OfferingRepository
{
    private const FIELDS = [
        'o.id', 'o.church_id', 'o.member_id', 'o.offer_date', 'o.category',
        'o.amount', 'o.method', 'o.ledger', 'o.recorded_by',
        'o.created_at', 'o.updated_at',
        'm.member_no as member_no', 'm.name as member_name',
    ];

    public function getOfferingList(int $churchId, array $filters): array
    {
        $query = $this->baseQuery()
            ->where('o.church_id', $churchId);

        $this->applyFilters($query, $filters);

        $totalQ = (clone $query);
        $sumQ   = (clone $query);

        $total = $totalQ->count();
        $sum   = (int) $sumQ->sum('o.amount');

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('o.offer_date', 'desc')
            ->orderBy('o.id', 'desc')
            ->forPage($page, $size)
            ->get(self::FIELDS)
            ->toArray();

        return [
            'total'        => $total,
            'total_amount' => $sum,
            'page'         => $page,
            'size'         => $size,
            'list'         => $list,
        ];
    }

    public function getOfferingById(int $offeringId): ?stdClass
    {
        return $this->baseQuery()
            ->where('o.id', $offeringId)
            ->first(self::FIELDS);
    }

    public function insertOffering(array $data): int
    {
        return (int) DB::table('reg_offering_records')->insertGetId($data);
    }

    public function updateOffering(int $offeringId, array $data): int
    {
        return DB::table('reg_offering_records')
            ->where('id', $offeringId)
            ->update($data);
    }

    public function deleteOffering(int $offeringId): int
    {
        return DB::table('reg_offering_records')
            ->where('id', $offeringId)
            ->delete();
    }

    public function getOfferingsByMember(int $memberId, array $filters): array
    {
        $query = $this->baseQuery()
            ->where('o.member_id', $memberId);

        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $sum   = (int) (clone $query)->sum('o.amount');

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('o.offer_date', 'desc')
            ->orderBy('o.id', 'desc')
            ->forPage($page, $size)
            ->get(self::FIELDS)
            ->toArray();

        return [
            'total'        => $total,
            'total_amount' => $sum,
            'page'         => $page,
            'size'         => $size,
            'list'         => $list,
        ];
    }

    private function baseQuery()
    {
        return DB::table('reg_offering_records as o')
            ->leftJoin('reg_members as m', 'm.id', '=', 'o.member_id');
    }

    private function applyFilters($query, array $filters): void
    {
        if (array_key_exists('member_id', $filters) && $filters['member_id'] !== null) {
            $query->where('o.member_id', (int) $filters['member_id']);
        }
        if (!empty($filters['category'])) {
            $query->where('o.category', $filters['category']);
        }
        if (!empty($filters['method'])) {
            $query->where('o.method', $filters['method']);
        }
        if (!empty($filters['ledger'])) {
            $query->where('o.ledger', $filters['ledger']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('o.offer_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('o.offer_date', '<=', $filters['to_date']);
        }
        if (isset($filters['min_amount']) && $filters['min_amount'] !== '') {
            $query->where('o.amount', '>=', (int) $filters['min_amount']);
        }
        if (isset($filters['max_amount']) && $filters['max_amount'] !== '') {
            $query->where('o.amount', '<=', (int) $filters['max_amount']);
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('o.category', 'LIKE', $kw)
                  ->orWhere('o.ledger', 'LIKE', $kw)
                  ->orWhere('m.name', 'LIKE', $kw);
            });
        }
    }
}
