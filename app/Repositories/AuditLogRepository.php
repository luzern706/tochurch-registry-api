<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class AuditLogRepository
{
    public function insertLog(array $data): int
    {
        return (int) DB::table('reg_audit_logs')->insertGetId($data);
    }
}
