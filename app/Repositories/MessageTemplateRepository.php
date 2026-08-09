<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class MessageTemplateRepository
{
    private const FIELDS = [
        't.id', 't.church_id', 't.name', 't.category', 't.msg_type', 't.scope',
        't.content', 't.unsub_enabled', 't.unsub_text', 't.is_active',
        't.created_by', 't.created_at', 't.updated_at',
        'a.name as created_by_name',
    ];

    private function baseQuery()
    {
        return DB::table('reg_message_templates as t')
            ->leftJoin('gh_church_admin as a', 'a.admin_no', '=', 't.created_by');
    }

    public function getList(int $churchId, array $filters): array
    {
        $query = $this->baseQuery()->where('t.church_id', $churchId);

        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('t.name', 'LIKE', $kw)
                  ->orWhere('t.content', 'LIKE', $kw);
            });
        }
        if (!empty($filters['category'])) {
            $query->where('t.category', $filters['category']);
        }
        if (!empty($filters['msg_type'])) {
            $query->where('t.msg_type', $filters['msg_type']);
        }
        if (!empty($filters['scope'])) {
            $query->where('t.scope', $filters['scope']);
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('t.is_active', (int) (bool) $filters['is_active']);
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('t.updated_at', 'desc')
            ->forPage($page, $size)
            ->get(self::FIELDS)
            ->toArray();

        return ['total' => $total, 'page' => $page, 'size' => $size, 'list' => $list];
    }

    /** 발송 화면(Compose.jsx) 드롭다운용 — 사용중인 것만, 페이지네이션 없이 전체 */
    public function getActiveList(int $churchId): array
    {
        return $this->baseQuery()
            ->where('t.church_id', $churchId)
            ->where('t.is_active', 1)
            ->orderBy('t.name', 'asc')
            ->get(self::FIELDS)
            ->toArray();
    }

    public function getById(int $id): ?stdClass
    {
        return $this->baseQuery()->where('t.id', $id)->first(self::FIELDS);
    }

    public function insert(array $data): int
    {
        return (int) DB::table('reg_message_templates')->insertGetId($data);
    }

    public function update(int $id, array $data): int
    {
        return DB::table('reg_message_templates')->where('id', $id)->update($data);
    }
}
