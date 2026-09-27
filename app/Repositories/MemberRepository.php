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

        // 교인당 대표 소속 1건만 가져오는 서브쿼리 (복수 소속 시 중복 행 방지, VisitRepository와 동일 패턴)
        $orgSub = DB::table('reg_member_organizations')
            ->select('member_id', DB::raw('MIN(organization_id) as organization_id'))
            ->groupBy('member_id');

        $query = DB::table($m)
            ->leftJoin('reg_member_profiles as p', 'p.member_id', '=', "$m.id")
            ->leftJoinSub($orgSub, 'mo', fn ($j) => $j->on('mo.member_id', '=', "$m.id"))
            ->leftJoin('reg_organizations as org', 'org.id', '=', 'mo.organization_id')
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
                "$m.status", "$m.churchero_user_id", "$m.unlinked_at",
                "$m.address_main", "$m.created_at",
                'p.position', 'p.member_type',
                'org.name as org_name',
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

    /**
     * 동일인 확인 후보 — 이름 일치 OR 휴대폰(숫자만 비교) 일치.
     * 정렬: 이름+휴대폰 모두 일치 → 휴대폰만 → 이름만, 생년월일 일치는 같은 그룹 안에서 우선.
     */
    public function findDuplicateCandidates(int $churchId, string $name, string $phoneDigits, ?string $birthDate, int $limit = 5): array
    {
        $phoneExpr = "REPLACE(REPLACE(REPLACE(m.phone, '-', ''), ' ', ''), '.', '')";

        $orgSub = DB::table('reg_member_organizations')
            ->select('member_id', DB::raw('MIN(organization_id) as organization_id'))
            ->groupBy('member_id');

        $query = DB::table('reg_members as m')
            ->leftJoinSub($orgSub, 'mo', fn ($j) => $j->on('mo.member_id', '=', 'm.id'))
            ->leftJoin('reg_organizations as org', 'org.id', '=', 'mo.organization_id')
            ->leftJoin('reg_organizations as porg', 'porg.id', '=', 'org.parent_id')
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0)
            ->where(function ($q) use ($name, $phoneDigits, $phoneExpr) {
                $q->where('m.name', $name);
                if ($phoneDigits !== '') {
                    $q->orWhereRaw("$phoneExpr = ?", [$phoneDigits]);
                }
            });

        $nameMatch  = DB::raw('(m.name = ' . DB::getPdo()->quote($name) . ') as name_match');
        $phoneMatch = $phoneDigits !== ''
            ? DB::raw("($phoneExpr = " . DB::getPdo()->quote($phoneDigits) . ') as phone_match')
            : DB::raw('0 as phone_match');
        $birthMatch = $birthDate
            ? DB::raw('(m.birth_date = ' . DB::getPdo()->quote($birthDate) . ') as birth_match')
            : DB::raw('0 as birth_match');

        return $query
            ->orderByRaw('(phone_match * 2 + name_match) DESC, birth_match DESC, m.id DESC')
            ->limit($limit)
            ->get([
                'm.id', 'm.name', 'm.gender', 'm.birth_date', 'm.phone',
                'org.name as org_name', 'porg.name as parent_org_name',
                $nameMatch, $phoneMatch, $birthMatch,
            ])
            ->toArray();
    }

    /**
     * 앱 연결 상태 판정에 필요한 원천 데이터 일괄 조회 (목록 화면 재사용 대비 배치형)
     *
     * @param  array $members reg_members 행 배열 (id, phone, churchero_user_id 필수)
     * @return array member_id => ['account' => ?object, 'candidate_count' => int, 'invite_joined' => bool]
     */
    public function getAppLinkContext(int $churchId, array $members): array
    {
        $out = [];
        if (empty($members)) {
            return $out;
        }

        $accountNos = array_values(array_filter(array_map(fn ($m) => $m->churchero_user_id ?? null, $members)));
        $accounts = empty($accountNos) ? collect() : DB::table('gh_account')
            ->whereIn('account_no', $accountNos)
            ->get(['account_no', 'name', 'phone'])
            ->keyBy('account_no');

        // 같은 교회의 미연동(교적에 연결 안 된) · 반려되지 않은 활성 계정의 휴대폰 숫자 → 개수
        $digitsWanted = array_values(array_unique(array_filter(array_map(
            fn ($m) => empty($m->churchero_user_id) ? preg_replace('/\D/', '', (string) $m->phone) : '',
            $members
        ))));
        $candidateCounts = [];
        if (!empty($digitsWanted)) {
            $accPhone = "REPLACE(REPLACE(REPLACE(a.phone, '-', ''), ' ', ''), '.', '')";
            $rows = DB::table('gh_account as a')
                ->where('a.church_no', $churchId)
                ->where('a.status', 'ALIVE')
                ->whereIn(DB::raw($accPhone), $digitsWanted)
                ->whereNotExists(function ($q) use ($churchId) {
                    $q->select(DB::raw(1))
                      ->from('reg_members as rm')
                      ->whereColumn('rm.churchero_user_id', 'a.account_no')
                      ->where('rm.church_id', $churchId)
                      ->where('rm.is_deleted', 0);
                })
                ->whereNotExists(function ($q) use ($churchId) {
                    $q->select(DB::raw(1))
                      ->from('reg_member_join_requests as r')
                      ->whereColumn('r.account_no', 'a.account_no')
                      ->where('r.church_id', $churchId)
                      ->where('r.status', 'rejected');
                })
                ->groupBy(DB::raw($accPhone))
                ->get([DB::raw("$accPhone as digits"), DB::raw('COUNT(*) as cnt')]);
            foreach ($rows as $r) {
                $candidateCounts[$r->digits] = (int) $r->cnt;
            }
        }

        // 초대 링크로 가입했지만 관리자 연결 승인 전인 교적
        $unlinkedIds = array_values(array_map(fn ($m) => $m->id, array_filter($members, fn ($m) => empty($m->churchero_user_id))));
        $inviteJoined = empty($unlinkedIds) ? [] : array_flip(DB::table('reg_member_invites')
            ->where('church_id', $churchId)
            ->where('status', 'joined')
            ->whereIn('member_id', $unlinkedIds)
            ->pluck('member_id')->map(fn ($v) => (int) $v)->all());

        foreach ($members as $m) {
            $digits = preg_replace('/\D/', '', (string) $m->phone);
            $out[$m->id] = [
                'invite_joined'   => isset($inviteJoined[(int) $m->id]),
                'account'         => !empty($m->churchero_user_id) ? ($accounts[$m->churchero_user_id] ?? null) : null,
                'candidate_count' => empty($m->churchero_user_id) && $digits !== '' ? ($candidateCounts[$digits] ?? 0) : 0,
            ];
        }
        return $out;
    }

    public function insertInvite(array $data): int
    {
        return (int) DB::table('reg_member_invites')->insertGetId($data);
    }

    public function getLatestInvite(int $memberId): ?stdClass
    {
        return DB::table('reg_member_invites')
            ->where('member_id', $memberId)
            ->orderByDesc('id')
            ->first(['id', 'phone', 'status', 'expires_at', 'created_at']);
    }
}
