<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * 봉사 팀(reg_service_teams) + 참여자 매핑(reg_service_members) 접근
 * — 'Service' 충돌 회피 위해 도메인명을 Volunteer 로 통칭
 */
class VolunteerRepository
{
    // ─────────────── 봉사 팀 (reg_service_teams) ───────────────

    public function getTeamList(int $churchId, array $filters): array
    {
        $query = DB::table('reg_service_teams')
            ->where('church_id', $churchId);

        if (!empty($filters['service_type'])) {
            $query->where('service_type', $filters['service_type']);
        }
        if (!empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', (int) (bool) $filters['is_active']);
        }
        if (!empty($filters['manager_member_id'])) {
            $query->where('manager_member_id', (int) $filters['manager_member_id']);
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'LIKE', $kw)
                  ->orWhere('description', 'LIKE', $kw);
            });
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('id', 'desc')
            ->forPage($page, $size)
            ->get()
            ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    public function getTeamById(int $teamId): ?stdClass
    {
        return DB::table('reg_service_teams')
            ->where('id', $teamId)
            ->first();
    }

    public function insertTeam(array $data): int
    {
        return (int) DB::table('reg_service_teams')->insertGetId($data);
    }

    public function updateTeam(int $teamId, array $data): int
    {
        return DB::table('reg_service_teams')
            ->where('id', $teamId)
            ->update($data);
    }

    public function deactivateTeam(int $teamId): int
    {
        return DB::table('reg_service_teams')
            ->where('id', $teamId)
            ->update(['is_active' => 0]);
    }

    // ─────────────── 참여자 매핑 (reg_service_members) ───────────────

    public function getMappingByTeamAndMember(int $teamId, int $memberId): ?stdClass
    {
        return DB::table('reg_service_members')
            ->where('service_team_id', $teamId)
            ->where('member_id', $memberId)
            ->first();
    }

    public function insertMapping(array $data): int
    {
        return (int) DB::table('reg_service_members')->insertGetId($data);
    }

    public function updateMapping(int $mappingId, array $data): int
    {
        return DB::table('reg_service_members')
            ->where('id', $mappingId)
            ->update($data);
    }

    public function getVolunteersByTeam(int $teamId, array $filters): array
    {
        $query = DB::table('reg_service_members as sm')
            ->join('reg_members as m', 'm.id', '=', 'sm.member_id')
            ->where('sm.service_team_id', $teamId)
            ->where('m.is_deleted', 0);

        if (isset($filters['status'])) {
            $query->where('sm.status', $filters['status']);
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('sm.status', 'asc')
            ->orderBy('m.name', 'asc')
            ->forPage($page, $size)
            ->get([
                'sm.id as mapping_id', 'sm.role', 'sm.joined_at', 'sm.status',
                'm.id as member_id', 'm.member_no', 'm.name', 'm.email', 'm.phone',
            ])
            ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    public function getTeamsByMember(int $memberId): array
    {
        return DB::table('reg_service_members as sm')
            ->join('reg_service_teams as t', 't.id', '=', 'sm.service_team_id')
            ->where('sm.member_id', $memberId)
            ->orderBy('sm.status', 'asc')
            ->orderBy('t.name', 'asc')
            ->get([
                'sm.id as mapping_id', 'sm.role', 'sm.joined_at', 'sm.status',
                't.id as team_id', 't.name as team_name', 't.service_type',
                't.mode', 't.is_active as team_is_active',
            ])
            ->toArray();
    }
}
