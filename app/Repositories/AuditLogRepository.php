<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class AuditLogRepository
{
    public function insertLog(array $data): int
    {
        return (int) DB::table('reg_audit_logs')->insertGetId($data);
    }

    public function getRecentLogs(int $churchId, int $limit): array
    {
        return DB::table('reg_audit_logs as l')
            ->leftJoin('gh_church_admin as a', 'a.admin_no', '=', 'l.member_id')
            ->where('l.church_id', $churchId)
            ->select(
                'l.audit_no', 'l.menu_code', 'l.action_type', 'l.target_label',
                'l.summary', 'l.created_at', 'a.name as admin_name'
            )
            ->orderBy('l.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getLogList(int $churchId, array $filters, int $page, int $size): array
    {
        $query = DB::table('reg_audit_logs as l')
            ->leftJoin('gh_church_admin as a', 'a.admin_no', '=', 'l.member_id')
            ->where('l.church_id', $churchId);

        if (!empty($filters['menu_code'])) {
            $query->where('l.menu_code', $filters['menu_code']);
        }
        if (!empty($filters['action_type'])) {
            $query->where('l.action_type', $filters['action_type']);
        }
        if (!empty($filters['admin_no'])) {
            $query->where('l.member_id', $filters['admin_no']);
        }
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $column  = match ($filters['search_type'] ?? null) {
                'user'    => 'a.name',
                'target'  => 'l.target_label',
                'summary' => 'l.summary',
                default   => null,
            };

            if ($column !== null) {
                $query->where($column, 'like', "%{$keyword}%");
            } else {
                $query->where(function ($q) use ($keyword) {
                    $q->where('l.summary', 'like', "%{$keyword}%")
                      ->orWhere('l.target_label', 'like', "%{$keyword}%")
                      ->orWhere('a.name', 'like', "%{$keyword}%");
                });
            }
        }
        if (!empty($filters['date_from'])) {
            $query->where('l.created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $query->where('l.created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $total = $query->count();
        $list  = (clone $query)
            ->select(
                'l.audit_no', 'l.menu_code', 'l.action_type', 'l.target_label',
                'l.summary', 'l.created_at', 'a.name as admin_name'
            )
            ->orderBy('l.created_at', 'desc')
            ->forPage($page, $size)
            ->get();

        return ['total' => $total, 'list' => $list];
    }
}
