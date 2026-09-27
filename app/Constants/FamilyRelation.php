<?php

namespace App\Constants;

/**
 * 가족 관계 (reg_families.relation_type)
 *
 * reg_families(member_id=A, related_member_id=B, relation_type=T) 는 "B 는 A 의 T" 를 뜻한다.
 */
class FamilyRelation
{
    /**
     * 양방향 자동 변환표 (A→B 가 T 이면 B→A 는 REVERSE[T])
     */
    const REVERSE = [
        '배우자'   => '배우자',
        '부모'     => '자녀',
        '자녀'     => '부모',
        '형제자매' => '형제자매',
        '기타'     => '기타',
    ];

    /**
     * "세대주와의 관계"(relation 코드그룹 값) → reg_families.relation_type
     *  세대주(본인) 는 가족 레코드를 만들지 않으므로 매핑 없음. 목록에 없는 교회별 사용자 코드는 기타.
     */
    const HOUSEHOLD_TO_FAMILY = [
        '배우자'   => '배우자',
        '자녀'     => '자녀',
        '부'       => '부모',
        '모'       => '부모',
        '부모'     => '부모',
        '형제'     => '형제자매',
        '자매'     => '형제자매',
        '형제자매' => '형제자매',
    ];

    /** 본인이 세대주임을 뜻하는 household_relation 값 */
    const HOUSEHOLD_SELF = '세대주';

    public static function fromHousehold(string $householdRelation): string
    {
        return self::HOUSEHOLD_TO_FAMILY[$householdRelation] ?? '기타';
    }
}
