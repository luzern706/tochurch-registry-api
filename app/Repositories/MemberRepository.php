<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class MemberRepository
{
    /**
     * 교회 단위 교인 목록 조회 (페이지네이션 포함)
     *
     * 필터: keyword(name/email/phone LIKE), status, page, size
     * 반환: ['total' => int, 'list' => array]
     */
    public function getMemberList(int $churchId, array $filters): array
    {
        $query = DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('is_deleted', 0);

        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'LIKE', $kw)
                  ->orWhere('email', 'LIKE', $kw)
                  ->orWhere('phone', 'LIKE', $kw)
                  ->orWhere('member_no', 'LIKE', $kw);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('id', 'desc')
            ->forPage($page, $size)
            ->get([
                'id', 'church_id', 'member_no', 'email', 'name', 'nickname',
                'phone', 'gender', 'birth_date', 'birth_type', 'status', 'created_at',
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
