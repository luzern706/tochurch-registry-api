<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class VisitRepository
{
    private const FIELDS = [
        'v.id', 'v.member_id', 'v.church_id', 'v.visit_type', 'v.event_reason',
        'v.visit_date', 'v.visitor_member_id', 'v.manager_member_id', 'v.place',
        'v.companion', 'v.attendee_count', 'v.content', 'v.common_note',
        'v.private_note', 'v.add_to_prayer', 'v.created_by',
        'v.created_at', 'v.updated_at',
        'm.name as member_name', 'm.member_no as member_member_no',
        'vm.name as visitor_name',
    ];

    public function getVisitList(int $churchId, array $filters): array
    {
        $query = DB::table('reg_visit_records as v')
            ->leftJoin('reg_members as m', 'm.id', '=', 'v.member_id')
            ->leftJoin('reg_members as vm', 'vm.id', '=', 'v.visitor_member_id')
            ->where('v.church_id', $churchId);

        if (!empty($filters['member_id'])) {
            $query->where('v.member_id', (int) $filters['member_id']);
        }
        if (!empty($filters['visitor_member_id'])) {
            $query->where('v.visitor_member_id', (int) $filters['visitor_member_id']);
        }
        if (!empty($filters['visit_type'])) {
            $query->where('v.visit_type', $filters['visit_type']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('v.visit_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('v.visit_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('v.content', 'LIKE', $kw)
                  ->orWhere('v.common_note', 'LIKE', $kw)
                  ->orWhere('m.name', 'LIKE', $kw);
            });
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('v.visit_date', 'desc')
            ->orderBy('v.id', 'desc')
            ->forPage($page, $size)
            ->get(self::FIELDS)
            ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    public function getVisitById(int $visitId): ?stdClass
    {
        return DB::table('reg_visit_records as v')
            ->leftJoin('reg_members as m', 'm.id', '=', 'v.member_id')
            ->leftJoin('reg_members as vm', 'vm.id', '=', 'v.visitor_member_id')
            ->where('v.id', $visitId)
            ->first(self::FIELDS);
    }

    public function insertVisit(array $data): int
    {
        return (int) DB::table('reg_visit_records')->insertGetId($data);
    }

    public function updateVisit(int $visitId, array $data): int
    {
        return DB::table('reg_visit_records')
            ->where('id', $visitId)
            ->update($data);
    }

    public function deleteVisit(int $visitId): int
    {
        return DB::table('reg_visit_records')
            ->where('id', $visitId)
            ->delete();
    }

    public function insertPrayerRecord(array $data): int
    {
        return (int) DB::table('reg_prayer_records')->insertGetId($data);
    }
}
