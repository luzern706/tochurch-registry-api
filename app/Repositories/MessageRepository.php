<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class MessageRepository
{
    private const FIELDS = [
        'm.id', 'm.church_id', 'm.title', 'm.content', 'm.send_type',
        'm.sent_at', 'm.sent_by', 'm.recipient_count', 'm.created_at',
        'a.name as sender_name',
    ];

    private function baseQuery()
    {
        return DB::table('reg_messages as m')
            ->leftJoin('gh_church_admin as a', 'a.admin_no', '=', 'm.sent_by');
    }

    public function getMessageList(int $churchId, array $filters): array
    {
        $query = $this->baseQuery()
            ->where('m.church_id', $churchId);

        if (!empty($filters['send_type'])) {
            $query->where('m.send_type', $filters['send_type']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('m.sent_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('m.sent_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('m.title', 'LIKE', $kw)
                  ->orWhere('m.content', 'LIKE', $kw)
                  ->orWhere('a.name', 'LIKE', $kw);
            });
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('m.sent_at', 'desc')
            ->orderBy('m.id', 'desc')
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

    public function getMessageById(int $messageId): ?stdClass
    {
        return $this->baseQuery()
            ->where('m.id', $messageId)
            ->first(self::FIELDS);
    }

    public function insertMessage(array $data): int
    {
        return (int) DB::table('reg_messages')->insertGetId($data);
    }

    public function deleteMessage(int $messageId): int
    {
        return DB::table('reg_messages')
            ->where('id', $messageId)
            ->delete();
    }

    /**
     * target_type=all 대상자 ID 산출 (church 내 미삭제 교인 전체)
     */
    public function resolveAllMemberIds(int $churchId): array
    {
        return DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * target_type=organization 대상자 ID 산출
     */
    public function resolveOrganizationMemberIds(int $churchId, int $organizationId): array
    {
        return DB::table('reg_member_organizations as mo')
            ->join('reg_members as m', 'm.id', '=', 'mo.member_id')
            ->where('mo.organization_id', $organizationId)
            ->where('m.church_id', $churchId)
            ->where('m.is_deleted', 0)
            ->pluck('m.id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * target_type=members 입력 ID 중 동일 교회·미삭제 교인만 추림
     */
    public function filterValidMemberIds(int $churchId, array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }
        return DB::table('reg_members')
            ->where('church_id', $churchId)
            ->where('is_deleted', 0)
            ->whereIn('id', $memberIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
