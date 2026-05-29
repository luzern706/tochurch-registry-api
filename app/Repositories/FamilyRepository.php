<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class FamilyRepository
{
    /**
     * 특정 교인의 가족 관계 목록 (member_id 기준 1방향)
     * 관련 교인의 기본 정보 JOIN
     */
    public function getFamiliesByMember(int $memberId): array
    {
        return DB::table('reg_families as f')
            ->join('reg_members as m', 'm.id', '=', 'f.related_member_id')
            ->where('f.member_id', $memberId)
            ->where('m.is_deleted', 0)
            ->orderBy('f.id', 'asc')
            ->get([
                'f.id', 'f.member_id', 'f.related_member_id', 'f.relation_type',
                'f.family_note', 'f.created_at',
                'm.member_no', 'm.name as related_name', 'm.gender as related_gender',
                'm.birth_date as related_birth_date',
            ])
            ->toArray();
    }

    public function getPair(int $memberId, int $relatedMemberId): ?stdClass
    {
        return DB::table('reg_families')
            ->where('member_id', $memberId)
            ->where('related_member_id', $relatedMemberId)
            ->first();
    }

    public function insertPair(array $data): int
    {
        return (int) DB::table('reg_families')->insertGetId($data);
    }

    public function updatePair(int $memberId, int $relatedMemberId, array $data): int
    {
        return DB::table('reg_families')
            ->where('member_id', $memberId)
            ->where('related_member_id', $relatedMemberId)
            ->update($data);
    }

    public function deletePair(int $memberId, int $relatedMemberId): int
    {
        return DB::table('reg_families')
            ->where('member_id', $memberId)
            ->where('related_member_id', $relatedMemberId)
            ->delete();
    }
}
