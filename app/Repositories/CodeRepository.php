<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class CodeRepository
{
    public function getCodesByGroup(int $churchId, string $groupKey, array $filters = []): array
    {
        $query = DB::table('reg_codes')
            ->where('church_id', $churchId)
            ->where('group_key', $groupKey);

        if (isset($filters['is_active'])) {
            $query->where('is_active', (int) $filters['is_active']);
        }

        return $query->orderBy('sort_order')->orderBy('id')->get()->all();
    }

    public function getCodeById(int $codeId): ?object
    {
        return DB::table('reg_codes')->where('id', $codeId)->first();
    }

    public function existsCodeValue(int $churchId, string $groupKey, string $codeValue, ?int $excludeId = null): bool
    {
        $query = DB::table('reg_codes')
            ->where('church_id', $churchId)
            ->where('group_key', $groupKey)
            ->where('code_value', $codeValue);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function insertCode(array $data): int
    {
        return DB::table('reg_codes')->insertGetId($data);
    }

    public function updateCode(int $codeId, array $data): void
    {
        DB::table('reg_codes')->where('id', $codeId)->update($data);
    }

    public function deleteCode(int $codeId): void
    {
        DB::table('reg_codes')->where('id', $codeId)->delete();
    }
}
