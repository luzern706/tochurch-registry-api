<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * 행정구역 매칭 헬퍼 — 02_gh_admin_api의 동명 헬퍼를 그대로 이식.
 *
 * 다음(카카오) 주소 검색 응답의 행정구역 이름(시도/시군구/동)을
 * gh_sido / gh_sigungu / gh_dong의 번호(no)로 변환합니다.
 *
 * 정책:
 *   - 시도: 응답을 gh_sido의 정식 명칭으로 정규화 후 정확 매칭
 *   - 시군구: 정확 매칭 + 공백 제거 fallback
 *   - 동: 정확 매칭 + 어미(동/리/가) 제거 prefix 매칭
 *
 * 매칭 실패 시 해당 단계 코드는 NULL이 되며, 호출하는 쪽에서
 * 좌표/주소는 정상 저장하도록 위임받습니다.
 */
class RegionMatcher
{
    private const SIDO_NORMALIZE_MAP = [
        '서울'           => '서울특별시',
        '서울특별시'     => '서울특별시',
        '부산'           => '부산광역시',
        '부산광역시'     => '부산광역시',
        '대구'           => '대구광역시',
        '대구광역시'     => '대구광역시',
        '인천'           => '인천광역시',
        '인천광역시'     => '인천광역시',
        '광주'           => '광주광역시',
        '광주광역시'     => '광주광역시',
        '대전'           => '대전광역시',
        '대전광역시'     => '대전광역시',
        '울산'           => '울산광역시',
        '울산광역시'     => '울산광역시',
        '세종'           => '세종시',
        '세종시'         => '세종시',
        '세종특별자치시' => '세종시',
        '경기'           => '경기도',
        '경기도'         => '경기도',
        '강원'           => '강원도',
        '강원도'         => '강원도',
        '강원특별자치도' => '강원도',
        '충북'           => '충청북도',
        '충청북도'       => '충청북도',
        '충남'           => '충청남도',
        '충청남도'       => '충청남도',
        '전북'           => '전라북도',
        '전라북도'       => '전라북도',
        '전북특별자치도' => '전라북도',
        '전남'           => '전라남도',
        '전라남도'       => '전라남도',
        '경북'           => '경상북도',
        '경상북도'       => '경상북도',
        '경남'           => '경상남도',
        '경상남도'       => '경상남도',
        '제주'           => '제주도',
        '제주도'         => '제주도',
        '제주특별자치도' => '제주도',
    ];

    /**
     * @return array{sido_no: int|null, sigungu_no: int|null, dong_no: int|null}
     */
    public static function match(
        ?string $sidoName,
        ?string $sigunguName,
        ?string $dongName = null
    ): array {
        $sidoNo = self::matchSido($sidoName);

        $sigunguNo = null;
        if ($sidoNo !== null && !empty($sigunguName)) {
            $sigunguNo = self::matchSigungu($sidoNo, $sigunguName);
        }

        $dongNo = null;
        if ($sigunguNo !== null && !empty($dongName)) {
            $dongNo = self::matchDong($sigunguNo, $dongName);
        }

        return [
            'sido_no'    => $sidoNo,
            'sigungu_no' => $sigunguNo,
            'dong_no'    => $dongNo,
        ];
    }

    private static function matchSido(?string $sidoName): ?int
    {
        if (empty($sidoName)) {
            return null;
        }

        $sidoName = trim($sidoName);

        $normalized = self::SIDO_NORMALIZE_MAP[$sidoName] ?? null;
        if ($normalized !== null) {
            $rows = DB::select("SELECT sido_no FROM gh_sido WHERE value = ? LIMIT 1", [$normalized]);
            if (!empty($rows)) {
                return (int) $rows[0]->sido_no;
            }
        }

        $rows = DB::select("SELECT sido_no FROM gh_sido WHERE value = ? LIMIT 1", [$sidoName]);
        if (!empty($rows)) {
            return (int) $rows[0]->sido_no;
        }

        return null;
    }

    private static function matchSigungu(int $sidoNo, string $sigunguName): ?int
    {
        $sigunguName = trim($sigunguName);

        $rows = DB::select(
            "SELECT sigungu_no FROM gh_sigungu WHERE sido_no = ? AND value = ? LIMIT 1",
            [$sidoNo, $sigunguName]
        );
        if (!empty($rows)) {
            return (int) $rows[0]->sigungu_no;
        }

        $compact = str_replace(' ', '', $sigunguName);
        if ($compact !== $sigunguName) {
            $rows = DB::select(
                "SELECT sigungu_no FROM gh_sigungu WHERE sido_no = ? AND REPLACE(value, ' ', '') = ? LIMIT 1",
                [$sidoNo, $compact]
            );
            if (!empty($rows)) {
                return (int) $rows[0]->sigungu_no;
            }
        }

        return null;
    }

    private static function matchDong(int $sigunguNo, string $dongName): ?int
    {
        $dongName = trim($dongName);

        $rows = DB::select(
            "SELECT dong_no FROM gh_dong WHERE sigungu_no = ? AND value = ? LIMIT 1",
            [$sigunguNo, $dongName]
        );
        if (!empty($rows)) {
            return (int) $rows[0]->dong_no;
        }

        $base = preg_replace('/(동|리|가)$/u', '', $dongName);
        if ($base !== $dongName && mb_strlen($base) >= 1) {
            $rows = DB::select(
                "SELECT dong_no FROM gh_dong WHERE sigungu_no = ? AND value LIKE ? ORDER BY value ASC LIMIT 1",
                [$sigunguNo, $base . '%']
            );
            if (!empty($rows)) {
                return (int) $rows[0]->dong_no;
            }
        }

        return null;
    }
}
