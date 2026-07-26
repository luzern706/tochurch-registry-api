<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class SettingRepository
{
    public function getByKeys(int $churchId, array $keys): array
    {
        return DB::table('reg_church_settings')
            ->where('church_id', $churchId)
            ->whereIn('setting_key', $keys)
            ->get(['setting_key', 'setting_value'])
            ->toArray();
    }

    public function upsert(int $churchId, string $key, ?string $value): void
    {
        DB::table('reg_church_settings')
            ->updateOrInsert(
                ['church_id' => $churchId, 'setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => now()]
            );
    }
}
