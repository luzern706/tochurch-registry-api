<?php

namespace App\Constants;

/**
 * 감사 로그 액션 타입 상수 (교적 시스템 — 실제 사용 액션만 포함)
 *
 * reg_audit_logs.action_type 컬럼에 저장되는 값
 */
class AuditActionType
{
    // ========== 인증 ==========
    /** 로그인 성공 */
    const LOGIN = 'LOGIN';

    /** 로그아웃 */
    const LOGOUT = 'LOGOUT';

    // ========== 공통 CRUD ==========
    /** 생성 */
    const CREATE = 'CREATE';

    /** 수정 (진도 갱신 포함) */
    const UPDATE = 'UPDATE';

    /** 삭제 (soft / hard 양쪽 모두 — 도메인별 의미는 summary 로 구분) */
    const DELETE = 'DELETE';

    // ========== 매핑 ==========
    /** 매핑 배정 (조직 ↔ 교인, 봉사 ↔ 교인) */
    const ASSIGN = 'ASSIGN';

    /** 매핑 해제 */
    const UNASSIGN = 'UNASSIGN';

    // ========== 교육 ==========
    /** 수강 등록 */
    const ENROLL = 'ENROLL';

    /** 수강 취소 */
    const WITHDRAW = 'WITHDRAW';

    // ========== 일괄 작업 ==========
    /** 일괄 UPSERT (출석 기록 등) */
    const BULK_UPDATE = 'BULK_UPDATE';

    // ========== 발송 ==========
    /** 메시지 발송 */
    const SEND = 'SEND';

    // ========== 계정 상태 ==========
    /** 계정 정지 */
    const SUSPEND = 'SUSPEND';

    /** 계정 활성화 (정지 해제) */
    const ACTIVATE = 'ACTIVATE';

    /**
     * 액션 타입의 한글 라벨 (관리자 화면 표시용)
     */
    public static function label(string $action): string
    {
        $labels = [
            self::LOGIN       => '로그인',
            self::LOGOUT      => '로그아웃',
            self::CREATE      => '생성',
            self::UPDATE      => '수정',
            self::DELETE      => '삭제',
            self::ASSIGN      => '배정',
            self::UNASSIGN    => '해제',
            self::ENROLL      => '수강등록',
            self::WITHDRAW    => '수강취소',
            self::BULK_UPDATE => '일괄변경',
            self::SEND        => '발송',
            self::SUSPEND     => '정지',
            self::ACTIVATE    => '활성화',
        ];
        return $labels[$action] ?? $action;
    }

    /**
     * 모든 액션 타입 목록 (검증/조회 옵션 등에 활용)
     */
    public static function all(): array
    {
        return [
            self::LOGIN,
            self::LOGOUT,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::ASSIGN,
            self::UNASSIGN,
            self::ENROLL,
            self::WITHDRAW,
            self::BULK_UPDATE,
            self::SEND,
            self::SUSPEND,
            self::ACTIVATE,
        ];
    }
}
