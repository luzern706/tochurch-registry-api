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

    /** 예배/모임 (gh_church_timetable) */
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

    /** 코드 관리 (교인구분/직분/관계 등 선택 목록) */
    const CODE = 'CODE';

    /** 관리자 계정 관리 (슈퍼 관리자 전용) */
    const ADMIN = 'ADMIN';

    /** 교적 설정 (KPI 기준 / 자동화 규칙 등) */
    const SETTING = 'SETTING';

    /** 기도 목록 */
    const PRAYER = 'PRAYER';

    /** 내정보 (로그인한 관리자 본인 프로필/비밀번호) */
    const PROFILE = 'PROFILE';

    /** 교회 기본정보 (설정 > 기본정보) */
    const CHURCH_PROFILE = 'CHURCH_PROFILE';

    /** 보고서/통계 (조회 전용) */
    const REPORT = 'REPORT';

    /** 게시판 (백엔드 미구현 — 권한 매트릭스 표시 전용) */
    const BOARD = 'BOARD';

    /** 재정 > 지출 (백엔드 미구현 — 권한 매트릭스 표시 전용) */
    const EXPENSE = 'EXPENSE';

    /** 재정 > 통계 (백엔드 미구현 — 권한 매트릭스 표시 전용) */
    const FINANCE_STATS = 'FINANCE_STATS';

    /** 재정 > 예산 (백엔드 미구현 — 권한 매트릭스 표시 전용) */
    const BUDGET = 'BUDGET';

    /** 재정 > 보고 (백엔드 미구현 — 권한 매트릭스 표시 전용) */
    const FINANCE_REPORT = 'FINANCE_REPORT';

    /** 재정 > 설정 (백엔드 미구현 — 권한 매트릭스 표시 전용) */
    const FINANCE_SETTING = 'FINANCE_SETTING';

    /** 관리 > 교회 페이지 (공개 홈페이지 콘텐츠 — 예배안내는 WORSHIP, 나머지는 이 코드 사용) */
    const WEBSITE = 'WEBSITE';

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
            self::MESSAGE      => '문자',
            self::CODE         => '코드 관리',
            self::ADMIN        => '관리자',
            self::SETTING      => '설정',
            self::PRAYER       => '기도',
            self::PROFILE      => '내정보',
            self::CHURCH_PROFILE => '교회 기본정보',
            self::REPORT       => '보고',
            self::BOARD          => '게시판',
            self::EXPENSE        => '지출',
            self::FINANCE_STATS  => '통계',
            self::BUDGET         => '예산',
            self::FINANCE_REPORT => '보고',
            self::FINANCE_SETTING => '설정',
            self::WEBSITE         => '교회 페이지',
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
            self::CODE,
            self::ADMIN,
            self::SETTING,
            self::PRAYER,
            self::PROFILE,
            self::CHURCH_PROFILE,
            self::REPORT,
            self::BOARD,
            self::EXPENSE,
            self::FINANCE_STATS,
            self::BUDGET,
            self::FINANCE_REPORT,
            self::FINANCE_SETTING,
            self::WEBSITE,
        ];
    }
}
