<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class MemberRepository
{
    /**
     * 교회 단위 교인 목록 조회 (페이지네이션 포함)
     *
     * 필터: keyword + search_type(name|phone|email, 미지정시 전체 OR), status, page, size
     * 반환: ['total' => int, 'list' => array]
     */
    public function getMemberList(int $churchId, array $filters): array
    {
        $m = 'reg_members';

        $query = DB::table($m)
            ->leftJoin('reg_member_profiles as p', 'p.member_id', '=', "$m.id")
            ->where("$m.church_id", $churchId)
            ->where("$m.is_deleted", 0);

        if (!empty($filters['keyword'])) {
            $kw   = '%' . $filters['keyword'] . '%';
            $type = $filters['search_type'] ?? null;

            $query->where(function ($q) use ($kw, $type, $m) {
                if ($type === 'name') {
                    $q->where("$m.name", 'LIKE', $kw);
                } elseif ($type === 'phone') {
                    $q->where("$m.phone", 'LIKE', $kw);
                } elseif ($type === 'email') {
                    $q->where("$m.email", 'LIKE', $kw);
                } else {
                    $q->where("$m.name", 'LIKE', $kw)
                      ->orWhere("$m.email", 'LIKE', $kw)
                      ->orWhere("$m.phone", 'LIKE', $kw)
                      ->orWhere("$m.member_no", 'LIKE', $kw);
                }
            });
        }

        if (!empty($filters['status'])) {
            $query->where("$m.status", $filters['status']);
        }

        if (!empty($filters['org_ids'])) {
            $orgIds = array_map('intval', (array) $filters['org_ids']);
            $query->whereExists(function ($q) use ($orgIds, $m) {
                $q->select(DB::raw(1))
                  ->from('reg_member_organizations as _mo')
                  ->whereColumn('_mo.member_id', "$m.id")
                  ->whereIn('_mo.organization_id', $orgIds);
            });
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $sortMap = [
            'name'       => ["$m.name",       'asc'],
            'birth_date' => ["$m.birth_date",  'asc'],
            'created_at' => ["$m.id",          'desc'],
        ];
        [$sortCol, $sortDir] = $sortMap[$filters['sort_by'] ?? ''] ?? ["$m.id", 'desc'];

        $list = $query
            ->orderBy($sortCol, $sortDir)
            ->forPage($page, $size)
            ->get([
                "$m.id", "$m.church_id", "$m.member_no",
                "$m.email", "$m.name", "$m.nickname",
                "$m.phone", "$m.gender",
                "$m.birth_date", "$m.birth_type",
                "$m.status", "$m.churchero_user_id",
                "$m.address_main", "$m.created_at",
                'p.position',
            ])
            ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    public function getMemberById(int $memberId): ?stdClass
    {
        return DB::table('reg_members')
            ->where('id', $memberId)
            ->where('is_deleted', 0)
            ->first();
    }

    public function getMemberProfileByMemberId(int $memberId): ?stdClass
    {
        return DB::table('reg_member_profiles')
            ->where('member_id', $memberId)
            ->first();
    }

    public function getChurchIdByMemberId(int $memberId): ?int
    {
        $row = DB::table('reg_members')
            ->where('id', $memberId)
            ->where('is_deleted', 0)
            ->first(['church_id']);

        return $row ? (int) $row->church_id : null;
    }

    public function existsByEmailInChurch(int $churchId, string $email, ?int $excludeMemberId = null): bool
    {
        $query = DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('email', $email)
            ->where('is_deleted', 0);

        if ($excludeMemberId !== null) {
            $query->where('id', '!=', $excludeMemberId);
        }

        return $query->exists();
    }

    /**
     * 교회 단위 오늘자 member_no 발급용 시퀀스 카운트
     */
    public function countMemberNoByPrefix(string $prefix): int
    {
        return DB::table('reg_members')
            ->where('member_no', 'LIKE', $prefix . '%')
            ->count();
    }

    public function insertMember(array $data): int
    {
        return (int) DB::table('reg_members')->insertGetId($data);
    }

    public function insertMemberProfile(array $data): int
    {
        return (int) DB::table('reg_member_profiles')->insertGetId($data);
    }

    public function updateMember(int $memberId, array $data): int
    {
        return DB::table('reg_members')
            ->where('id', $memberId)
            ->where('is_deleted', 0)
            ->update($data);
    }

    public function updateMemberProfile(int $memberId, array $data): int
    {
        return DB::table('reg_member_profiles')
            ->where('member_id', $memberId)
            ->update($data);
    }

    public function softDeleteMember(int $memberId): int
    {
        return DB::table('reg_members')
            ->where('id', $memberId)
            ->where('is_deleted', 0)
            ->update(['is_deleted' => 1]);
    }
}
