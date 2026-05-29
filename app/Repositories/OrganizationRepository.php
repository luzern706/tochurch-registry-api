<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class OrganizationRepository
{
    /**
     * 교회 단위 조직 목록 (flat, parent_id 포함)
     * 필터: parent_id (null=최상위만), is_active, keyword
     */
    public function getOrganizationList(int $churchId, array $filters): array
    {
        $query = DB::table('reg_organizations')
            ->where('church_id', $churchId);

        if (array_key_exists('parent_id', $filters)) {
            if ($filters['parent_id'] === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (int) $filters['parent_id']);
            }
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (int) (bool) $filters['is_active']);
        }

        if (!empty($filters['keyword'])) {
            $query->where('name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    public function getOrganizationById(int $organizationId): ?stdClass
    {
        return DB::table('reg_organizations')
            ->where('id', $organizationId)
            ->first();
    }

    public function hasChildren(int $organizationId): bool
    {
        return DB::table('reg_organizations')
            ->where('parent_id', $organizationId)
            ->where('is_active', 1)
            ->exists();
    }

    public function insertOrganization(array $data): int
    {
        return (int) DB::table('reg_organizations')->insertGetId($data);
    }

    public function updateOrganization(int $organizationId, array $data): int
    {
        return DB::table('reg_organizations')
            ->where('id', $organizationId)
            ->update($data);
    }

    public function deactivateOrganization(int $organizationId): int
    {
        return DB::table('reg_organizations')
            ->where('id', $organizationId)
            ->update(['is_active' => 0]);
    }

    public function deleteMappingsByOrganization(int $organizationId): int
    {
        return DB::table('reg_member_organizations')
            ->where('organization_id', $organizationId)
            ->delete();
    }

    // ─────────────── 교인-조직 매핑 ───────────────

    public function getMappingsByMember(int $memberId): array
    {
        return DB::table('reg_member_organizations')
            ->where('member_id', $memberId)
            ->get()
            ->toArray();
    }

    public function getMapping(int $memberId, int $organizationId): ?stdClass
    {
        return DB::table('reg_member_organizations')
            ->where('member_id', $memberId)
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function insertMapping(array $data): int
    {
        return (int) DB::table('reg_member_organizations')->insertGetId($data);
    }

    public function updateMapping(int $mappingId, array $data): int
    {
        return DB::table('reg_member_organizations')
            ->where('id', $mappingId)
            ->update($data);
    }

    public function resetPrimaryForMember(int $memberId, ?int $exceptMappingId = null): int
    {
        $query = DB::table('reg_member_organizations')
            ->where('member_id', $memberId)
            ->where('is_primary', 1);

        if ($exceptMappingId !== null) {
            $query->where('id', '!=', $exceptMappingId);
        }

        return $query->update(['is_primary' => 0]);
    }

    public function deleteMapping(int $memberId, int $organizationId): int
    {
        return DB::table('reg_member_organizations')
            ->where('member_id', $memberId)
            ->where('organization_id', $organizationId)
            ->delete();
    }

    /**
     * 조직 소속 교인 목록 (활성, 미삭제만)
     */
    public function getMembersByOrganization(int $organizationId, array $filters): array
    {
        $query = DB::table('reg_member_organizations as mo')
            ->join('reg_members as m', 'm.id', '=', 'mo.member_id')
            ->where('mo.organization_id', $organizationId)
            ->where('m.is_deleted', 0);

        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('m.name', 'LIKE', $kw)
                  ->orWhere('m.email', 'LIKE', $kw)
                  ->orWhere('m.phone', 'LIKE', $kw)
                  ->orWhere('m.member_no', 'LIKE', $kw);
            });
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('mo.is_primary', 'desc')
            ->orderBy('m.name', 'asc')
            ->forPage($page, $size)
            ->get([
                'm.id', 'm.member_no', 'm.name', 'm.email', 'm.phone',
                'm.gender', 'm.status',
                'mo.is_primary', 'mo.joined_at',
            ])
            ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }
}
