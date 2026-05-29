<?php

namespace App\Constants;

/**
 * 감사 로그 메뉴 코드 상수 (교적 시스템)
 *
 * reg_audit_logs.menu_code 컬럼에 저장되는 값
 */
class AuditMenuCode
{
    /** 인증 (로그인/로그아웃) */
    const AUTH = 'AUTH';

    /** 교인 */
    const MEMBER = 'MEMBER';

    /** 조직 (남전도회, 청년부 등) */
    const ORGANIZATION = 'ORGANIZATION';

    /** 가족 관계 */
    const FAMILY = 'FAMILY';

    /** 예배/모임 (reg_services) */
    const WORSHIP = 'WORSHIP';

    /** 출석 기록 */
    const ATTENDANCE = 'ATTENDANCE';

    /** 심방 기록 */
    const VISIT = 'VISIT';

    /** 봉사 (팀 + 참여자) */
    const VOLUNTEER = 'VOLUNTEER';

    /** 교육 (과정 + 수강) */
    const EDUCATION = 'EDUCATION';

    /** 헌금 기록 */
    const OFFERING = 'OFFERING';

    /** 메시지 발송 */
    const MESSAGE = 'MESSAGE';

    /**
     * 메뉴 코드의 한글 라벨 (관리자 화면 표시용)
     */
    public static function label(string $code): string
    {
        $labels = [
            self::AUTH         => '인증',
            self::MEMBER       => '교인',
            self::ORGANIZATION => '조직',
            self::FAMILY       => '가족 관계',
            self::WORSHIP      => '예배/모임',
            self::ATTENDANCE   => '출석',
            self::VISIT        => '심방',
            self::VOLUNTEER    => '봉사',
            self::EDUCATION    => '교육',
            self::OFFERING     => '헌금',
            self::MESSAGE      => '메시지',
        ];
        return $labels[$code] ?? $code;
    }

    /**
     * 모든 메뉴 코드 목록 (검증/조회 옵션 등에 활용)
     */
    public static function all(): array
    {
        return [
            self::AUTH,
            self::MEMBER,
            self::ORGANIZATION,
            self::FAMILY,
            self::WORSHIP,
            self::ATTENDANCE,
            self::VISIT,
            self::VOLUNTEER,
            self::EDUCATION,
            self::OFFERING,
            self::MESSAGE,
        ];
    }
}
