<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * 교적 "기본정보" 화면 — gh_church/gh_church_location/gh_church_pastor는
 * 관리자 페이지·공개 홈페이지와 공유하는 테이블이라 컬럼을 추가해 함께 쓴다
 * (docs/schema/gh_church_alter.md 참조). 문서 생성 전용(소속증명서/문서머리글/
 * 기부금영수증안내문)만 교적 전용 reg_church_settings에 저장한다.
 */
class ChurchProfileRepository
{
    private const CHURCH_FILLABLE = [
        'name', 'group_no', 'phone', 'phone2', 'email', 'homepage', 'youtube_url', 'taxNo',
        'thumbnail', 'logo_file_no', 'church_create_date',
    ];

    public function getChurch(int $churchNo): ?stdClass
    {
        return DB::table('gh_church')
            ->select('church_no', 'group_no', 'name', 'phone', 'phone2', 'email', 'homepage', 'youtube_url', 'taxNo', 'thumbnail', 'logo_file_no', 'church_create_date')
            ->where('church_no', $churchNo)
            ->first();
    }

    public function updateChurch(int $churchNo, array $fields): bool
    {
        $data = array_intersect_key($fields, array_flip(self::CHURCH_FILLABLE));
        if (empty($data)) {
            return false;
        }
        $data['updated'] = now();
        return DB::table('gh_church')->where('church_no', $churchNo)->update($data) >= 0;
    }

    public function getLocation(int $churchNo): ?stdClass
    {
        return DB::table('gh_church_location')
            ->select('location_no', 'church_no', 'address', 'address_detail', 'postcode',
                'sido_no', 'sigungu_no', 'dong_no', 'latitude', 'longitude')
            ->where('church_no', $churchNo)
            ->first();
    }

    /**
     * @param array{sido_no:?int, sigungu_no:?int, dong_no:?int, latitude:?float, longitude:?float} $region
     *        주소가 바뀔 때만 호출부(ChurchProfileService)가 RegionMatcher/GeocodeHelper로 채워서 전달.
     */
    public function upsertLocation(int $churchNo, string $address, ?string $addressDetail, ?string $postcode, array $region = []): void
    {
        $existing = $this->getLocation($churchNo);
        $data = ['address' => $address, 'updated' => now()];
        if ($addressDetail !== null) {
            $data['address_detail'] = $addressDetail;
        }
        if ($postcode !== null) {
            $data['postcode'] = $postcode;
        }
        foreach (['sido_no', 'sigungu_no', 'dong_no', 'latitude', 'longitude'] as $key) {
            if (array_key_exists($key, $region)) {
                $data[$key] = $region[$key];
            }
        }

        if ($existing) {
            DB::table('gh_church_location')->where('location_no', $existing->location_no)->update($data);
        } else {
            DB::table('gh_church_location')->insert(array_merge($data, [
                'church_no'  => $churchNo,
                'registered' => now(),
            ]));
        }
    }

    public function getChurchGroups(): array
    {
        return DB::table('gh_church_group')
            ->select('group_no', 'value')
            ->where('status', 'ALIVE')
            ->orderBy('group_no')
            ->get()
            ->all();
    }

    public function getPastorsByChurch(int $churchNo): array
    {
        return DB::table('gh_church_pastor as p')
            ->leftJoin('gh_church_pastor_type as t', 't.type_no', '=', 'p.type_no')
            ->select('p.pastor_no', 'p.name', 'p.type_no', 't.value as type_value', 'p.is_head')
            ->where('p.church_no', $churchNo)
            ->orderBy('p.pastor_no')
            ->get()
            ->all();
    }

    public function getPastorByNo(int $pastorNo): ?stdClass
    {
        return DB::table('gh_church_pastor')
            ->select('pastor_no', 'church_no', 'name', 'is_head')
            ->where('pastor_no', $pastorNo)
            ->first();
    }

    public function getHeadPastor(int $churchNo): ?stdClass
    {
        return DB::table('gh_church_pastor')
            ->select('pastor_no', 'name')
            ->where('church_no', $churchNo)
            ->where('is_head', 1)
            ->first();
    }

    /** 담임목사 지정 — 같은 교회의 기존 담임 플래그를 내리고 새로 지정 (트랜잭션) */
    public function setHeadPastor(int $churchNo, ?int $pastorNo): void
    {
        DB::transaction(function () use ($churchNo, $pastorNo) {
            DB::table('gh_church_pastor')->where('church_no', $churchNo)->where('is_head', 1)->update(['is_head' => 0]);
            if ($pastorNo !== null) {
                DB::table('gh_church_pastor')->where('pastor_no', $pastorNo)->where('church_no', $churchNo)->update(['is_head' => 1]);
            }
        });
    }

    public function insertFile(array $data): int
    {
        return DB::table('gh_file')->insertGetId(array_merge($data, [
            'status'     => 'ALIVE',
            'registered' => now(),
        ]), 'file_no');
    }

    public function getFileByNo(int $fileNo): ?stdClass
    {
        return DB::table('gh_file')->where('file_no', $fileNo)->first();
    }

    public function markFileDeleted(int $fileNo): void
    {
        DB::table('gh_file')->where('file_no', $fileNo)->update(['status' => 'DEAD']);
    }
}
