<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class PushNotificationRepository
{
    /**
     * 그룹선택 칩에 쓸 실제 직분/교인구분/신급 값 목록(교회 내 실사용 값만, 코드表 아님)
     */
    public function getDistinctProfileValues(int $churchId): array
    {
        $rows = DB::table('reg_member_profiles as p')
            ->join('reg_members as m', 'm.id', '=', 'p.member_id')
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0)
            ->select('p.position', 'p.member_type', 'p.baptism_grade')
            ->get();

        $pick = fn (string $col) => $rows->pluck($col)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'positions'      => $pick('position'),
            'member_types'   => $pick('member_type'),
            'baptism_grades' => $pick('baptism_grade'),
        ];
    }

    /**
     * 그룹선택(조직/직분/교인구분/신급 칩)으로 대상 교인 ID 산출.
     * 카테고리 내부는 OR, 카테고리 간은 AND. 아무 칩도 없으면 빈 배열(안전한 기본값).
     */
    public function resolveGroupMemberIds(int $churchId, array $filters): array
    {
        $orgIds        = array_map('intval', $filters['org_ids'] ?? []);
        $positions     = $filters['positions'] ?? [];
        $memberTypes   = $filters['member_types'] ?? [];
        $baptismGrades = $filters['baptism_grades'] ?? [];

        if (empty($orgIds) && empty($positions) && empty($memberTypes) && empty($baptismGrades)) {
            return [];
        }

        $query = DB::table('reg_members as m')
            ->leftJoin('reg_member_profiles as p', 'p.member_id', '=', 'm.id')
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0);

        if (!empty($orgIds)) {
            $query->whereExists(function ($q) use ($orgIds) {
                $q->select(DB::raw(1))
                  ->from('reg_member_organizations as mo')
                  ->whereColumn('mo.member_id', 'm.id')
                  ->whereIn('mo.organization_id', $orgIds);
            });
        }
        if (!empty($positions)) {
            $query->whereIn('p.position', $positions);
        }
        if (!empty($memberTypes)) {
            $query->whereIn('p.member_type', $memberTypes);
        }
        if (!empty($baptismGrades)) {
            $query->whereIn('p.baptism_grade', $baptismGrades);
        }

        return $query->distinct()->pluck('m.id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * 대상 교인들의 발송 시점 도달가능성 스냅샷 계산.
     * 도달가능 = 교회로 계정연동(churchero_user_id) + 기기토큰 존재(gh_push_token_v4)
     *          + 수신동의(gh_account_settings.notification.master, 설정 자체가 없으면 기본값 true로 간주)
     *
     * gh_push_token_v4 / gh_account_settings 는 03_gh_www_api 가 관리하는 기존 공유 테이블 — 읽기 전용 조회만 한다.
     */
    public function getReachabilitySnapshots(int $churchId, array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $orgSub = DB::table('reg_member_organizations')
            ->select('member_id', DB::raw('MIN(organization_id) as organization_id'))
            ->groupBy('member_id');

        $members = DB::table('reg_members as m')
            ->leftJoinSub($orgSub, 'mo', fn ($j) => $j->on('mo.member_id', '=', 'm.id'))
            ->leftJoin('reg_organizations as org', 'org.id', '=', 'mo.organization_id')
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0)
            ->whereIn('m.id', $memberIds)
            ->get(['m.id', 'm.name', 'm.phone', 'm.churchero_user_id', 'org.name as org_name']);

        $accountNos = $members->pluck('churchero_user_id')
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $tokenAccountNos = empty($accountNos) ? [] : DB::table('gh_push_token_v4')
            ->whereIn('account_no', $accountNos)
            ->distinct()
            ->pluck('account_no')
            ->map(fn ($v) => (int) $v)
            ->all();
        $tokenSet = array_flip($tokenAccountNos);

        $consentMap = [];
        if (!empty($accountNos)) {
            $settingsRows = DB::table('gh_account_settings')
                ->where('setting_key', 'notification')
                ->whereIn('account_no', $accountNos)
                ->get(['account_no', 'setting_value']);

            foreach ($settingsRows as $row) {
                $decoded = json_decode((string) $row->setting_value, true);
                $consentMap[(int) $row->account_no] = is_array($decoded) ? (($decoded['master'] ?? true) !== false) : true;
            }
        }

        $result = [];
        foreach ($members as $m) {
            $accountNo = $m->churchero_user_id !== null ? (int) $m->churchero_user_id : null;

            if ($accountNo === null) {
                $reachable = false;
                $reason    = 'no_account';
            } elseif (!isset($tokenSet[$accountNo])) {
                $reachable = false;
                $reason    = 'no_token';
            } elseif (($consentMap[$accountNo] ?? true) === false) {
                $reachable = false;
                $reason    = 'optout';
            } else {
                $reachable = true;
                $reason    = null;
            }

            $result[] = (object) [
                'member_id'   => (int) $m->id,
                'member_name' => $m->name,
                'org_name'    => $m->org_name,
                'phone'       => $m->phone,
                'reachable'   => $reachable,
                'skip_reason' => $reason,
            ];
        }

        return $result;
    }

    public function insertRecipients(int $messageId, array $snapshots): void
    {
        if (empty($snapshots)) {
            return;
        }

        $rows = array_map(fn ($s) => [
            'message_id'  => $messageId,
            'member_id'   => $s->member_id,
            'member_name' => $s->member_name,
            'org_name'    => $s->org_name,
            'phone'       => $s->phone,
            'reachable'   => $s->reachable ? 1 : 0,
            'skip_reason' => $s->skip_reason,
            'created_at'  => now(),
        ], $snapshots);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('reg_push_recipients')->insert($chunk);
        }
    }

    public function getRecipientsByMessage(int $messageId): array
    {
        return DB::table('reg_push_recipients')
            ->where('message_id', $messageId)
            ->orderBy('reachable', 'desc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 재발송 대상 산출 — includePermanent=false 면 no_token(재시도 여지 있는 사유)만,
     * true 면 no_account/optout(계정·동의 문제)까지 포함
     */
    public function getSkippedMemberIds(int $messageId, bool $includePermanent): array
    {
        $query = DB::table('reg_push_recipients')
            ->where('message_id', $messageId)
            ->where('reachable', 0);

        $query->where(function ($q) use ($includePermanent) {
            $q->where('skip_reason', 'no_token');
            if ($includePermanent) {
                $q->orWhereIn('skip_reason', ['no_account', 'optout']);
            }
        });

        return $query->pluck('member_id')->map(fn ($v) => (int) $v)->all();
    }

    public function countByReachable(int $messageId): array
    {
        $rows = DB::table('reg_push_recipients')
            ->select('reachable', 'skip_reason', DB::raw('COUNT(*) as cnt'))
            ->where('message_id', $messageId)
            ->groupBy('reachable', 'skip_reason')
            ->get();

        $summary = ['reachable' => 0, 'no_account' => 0, 'no_token' => 0, 'optout' => 0];
        foreach ($rows as $r) {
            if ((int) $r->reachable === 1) {
                $summary['reachable'] += (int) $r->cnt;
            } elseif (isset($summary[$r->skip_reason])) {
                $summary[$r->skip_reason] += (int) $r->cnt;
            }
        }

        return $summary;
    }
}
