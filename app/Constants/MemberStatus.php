<?php

namespace App\Constants;

/**
 * 교인 등록상태 코드 (reg_members.status ENUM)
 *
 * DB 에는 영문 코드를 저장하고 화면 라벨은 여기서 매핑한다.
 * 기존 값(active/inactive/unknown)은 의미만 재정의되어 그대로 유지된다.
 * 스키마: docs/schema/17_reg_members_register_alter.sql
 */
class MemberStatus
{
    const ACTIVE      = 'active';       // 재적
    const NEW_FAMILY  = 'new_family';   // 새가족
    const INACTIVE    = 'inactive';     // 장기결석
    const TRANSFERRED = 'transferred';  // 이전
    const REMOVED     = 'removed';      // 제적
    const DECEASED    = 'deceased';     // 소천
    const UNKNOWN     = 'unknown';      // 미분류

    public static function labels(): array
    {
        return [
            self::ACTIVE      => '재적',
            self::NEW_FAMILY  => '새가족',
            self::INACTIVE    => '장기결석',
            self::TRANSFERRED => '이전',
            self::REMOVED     => '제적',
            self::DECEASED    => '소천',
            self::UNKNOWN     => '미분류',
        ];
    }

    public static function all(): array
    {
        return array_keys(self::labels());
    }

    /**
     * 신규 등록 시 선택 가능한 상태 — 이전·제적·소천은 등록 후 상태 변경으로 처리
     */
    public static function registerable(): array
    {
        return [self::ACTIVE, self::NEW_FAMILY, self::INACTIVE, self::UNKNOWN];
    }
}
