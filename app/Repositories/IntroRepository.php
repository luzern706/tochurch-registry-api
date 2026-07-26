<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * 관리 > 교회 페이지 "교회소개" — gh_church_intro(공유 테이블) + gh_church_pastor(담임목사).
 * 컬럼 매핑은 docs/schema/gh_church_alter.md "교회소개 기능 컬럼 추가" 참조.
 */
class IntroRepository
{
    private const INTRO_FILLABLE = [
        'slogan', 'vision', 'message', 'hero_title', 'hero_subtitle', 'hero_buttons',
        'intro_title', 'welcome_title', 'welcome_highlight', 'bible_quote', 'directions',
    ];

    public function getIntro(int $churchNo): ?stdClass
    {
        return DB::table('gh_church_intro')->where('church_no', $churchNo)->first();
    }

    public function upsertIntro(int $churchNo, array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::INTRO_FILLABLE));
        if (empty($data)) {
            return;
        }

        $existing = $this->getIntro($churchNo);
        $data['updated'] = now();

        if ($existing) {
            DB::table('gh_church_intro')->where('intro_no', $existing->intro_no)->update($data);
        } else {
            DB::table('gh_church_intro')->insert(array_merge($data, [
                'church_no'  => $churchNo,
                'registered' => now(),
            ]));
        }
    }

    public function getHeadPastor(int $churchNo): ?stdClass
    {
        return DB::table('gh_church_pastor')
            ->where('church_no', $churchNo)
            ->where('is_head', 1)
            ->first();
    }

    public function updatePastor(int $pastorNo, array $data): void
    {
        $data = array_intersect_key($data, array_flip(['name', 'type_no', 'career', 'thumbnail']));
        if (empty($data)) {
            return;
        }
        $data['updated'] = now();
        DB::table('gh_church_pastor')->where('pastor_no', $pastorNo)->update($data);
    }

    public function getPastorTypes(): array
    {
        return DB::table('gh_church_pastor_type')
            ->select('type_no', 'value')
            ->orderBy('type_no')
            ->get()
            ->all();
    }

    public function getFileByNo(int $fileNo): ?stdClass
    {
        return DB::table('gh_file')->where('file_no', $fileNo)->first();
    }

    public function insertFile(array $data): int
    {
        return DB::table('gh_file')->insertGetId(array_merge($data, [
            'status'     => 'ALIVE',
            'registered' => now(),
        ]), 'file_no');
    }

    public function markFileDeleted(int $fileNo): void
    {
        DB::table('gh_file')->where('file_no', $fileNo)->update(['status' => 'DEAD']);
    }
}
