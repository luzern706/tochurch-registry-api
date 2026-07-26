<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class PrayerRepository
{
    public function getPrayerList(array $filters): array
    {
        $churchId = $filters['church_id'];

        $query = DB::table('reg_prayer_records as p')
            ->leftJoin('reg_members as m',   'm.id',  '=', 'p.member_id')
            ->leftJoin('reg_member_profiles as mp', 'mp.member_id', '=', 'm.id')
            ->leftJoin('reg_member_organizations as mo', function ($j) {
                $j->on('mo.member_id', '=', 'm.id')->where('mo.is_primary', 1);
            })
            ->leftJoin('reg_organizations as o', 'o.id', '=', 'mo.organization_id')
            ->leftJoin('reg_members as mgr', 'mgr.id', '=', 'p.manager_member_id')
            ->where('p.church_id', $churchId)
            ->select([
                'p.id',
                'p.member_id',
                'p.non_member_name',
                'p.title',
                'p.content',
                'p.visibility',
                'p.status',
                'p.visit_record_id',
                'p.is_resolved',
                'p.created_by',
                'p.created_at',
                'm.name as member_name',
                'o.name as org_name',
                'mgr.name as manager_name',
            ]);

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('p.title', 'like', "%{$kw}%")
                  ->orWhere('m.name', 'like', "%{$kw}%")
                  ->orWhere('p.non_member_name', 'like', "%{$kw}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        if (isset($filters['linked'])) {
            if ($filters['linked']) {
                $query->whereNotNull('p.visit_record_id');
            } else {
                $query->whereNull('p.visit_record_id');
            }
        }

        if (!empty($filters['start_date'])) {
            $query->where('p.created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('p.created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $total = $query->count();
        $size  = $filters['size'] ?? 20;
        $page  = $filters['page'] ?? 1;
        $list  = $query->orderBy('p.created_at', 'desc')
                       ->offset(($page - 1) * $size)
                       ->limit($size)
                       ->get();

        return ['total' => $total, 'list' => $list];
    }

    public function getPrayerById(int $id, int $churchId): ?object
    {
        return DB::table('reg_prayer_records as p')
            ->leftJoin('reg_members as m',   'm.id',  '=', 'p.member_id')
            ->leftJoin('reg_member_organizations as mo', function ($j) {
                $j->on('mo.member_id', '=', 'm.id')->where('mo.is_primary', 1);
            })
            ->leftJoin('reg_organizations as o', 'o.id', '=', 'mo.organization_id')
            ->leftJoin('reg_members as mgr', 'mgr.id', '=', 'p.manager_member_id')
            ->where('p.id', $id)
            ->where('p.church_id', $churchId)
            ->select([
                'p.*',
                'm.name as member_name',
                'o.name as org_name',
                'mgr.name as manager_name',
            ])
            ->first();
    }

    public function createPrayer(array $data): int
    {
        return DB::table('reg_prayer_records')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function updatePrayer(int $id, array $data): void
    {
        DB::table('reg_prayer_records')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));
    }

    public function deletePrayer(int $id): void
    {
        DB::table('reg_prayer_records')->where('id', $id)->delete();
    }

    public function updateStatus(int $id, string $status): void
    {
        $data = ['status' => $status, 'updated_at' => now()];
        if ($status === 'completed') {
            $data['is_resolved'] = 1;
        }
        DB::table('reg_prayer_records')->where('id', $id)->update($data);
    }

    public function getStats(int $churchId): array
    {
        $base = DB::table('reg_prayer_records')->where('church_id', $churchId);

        return [
            'total'     => (clone $base)->count(),
            'active'    => (clone $base)->where('status', 'active')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'linked'    => (clone $base)->whereNotNull('visit_record_id')->count(),
        ];
    }
}
