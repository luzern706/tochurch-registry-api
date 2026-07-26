<?php

namespace App\Constants;

/**
 * 권한 매트릭스(설정 > 권한설정)의 기본 정책 — 교회가 아직 커스터마이즈하지
 * 않은 (church_id, role, page_code) 조합에 사용. admin_type='admin'은 이 정책과
 * 무관하게 항상 전체 권한(CheckPermissionMiddleware/PermissionService에서 하드코딩 바이패스).
 *
 * 조회/관리 구분 폐지(단일 접근 권한) — 판단 기준은 항상 "중메뉴" 코드
 * (PermissionMenuTree::parentOf() 로 소메뉴→중메뉴 역참조 후 사용). 같은 중메뉴에 속한
 * 소메뉴는 커스터마이즈 전까지 전부 같은 기본값을 공유한다.
 *
 * 기본 정책 설계 (조회/관리 병합에 따른 재정의):
 *   pastor(담임목회자)   — SETTING/FINANCE_SETTING(시스템·구조 설정) 제외 전 메뉴 접근
 *   minister(사역자)     — 일상 사역 8개 메뉴 + 보고(REPORT, 조회 위주라 유지) 접근,
 *                          재정/설정/관리는 접근 불가(기존엔 조회만 가능했으나 조회/관리가
 *                          하나로 합쳐지며 접근 자체를 부여하지 않는 쪽으로 정리)
 *   volunteer(봉사자)    — 본인 참여와 직결된 7개 메뉴 + 게시판 접근(기존엔 조회만
 *                          가능했으나 동일한 이유로 해당 메뉴 내에서는 전체 접근으로 정리)
 */
class PermissionDefaults
{
    private const PASTOR_EXCLUDE = [AuditMenuCode::SETTING, AuditMenuCode::FINANCE_SETTING];

    private const MINISTER_INCLUDE = [
        AuditMenuCode::MEMBER, AuditMenuCode::ATTENDANCE, AuditMenuCode::VISIT,
        AuditMenuCode::PRAYER, AuditMenuCode::EDUCATION, AuditMenuCode::VOLUNTEER,
        AuditMenuCode::MESSAGE, AuditMenuCode::BOARD, AuditMenuCode::REPORT,
    ];

    private const VOLUNTEER_INCLUDE = [
        AuditMenuCode::MEMBER, AuditMenuCode::ATTENDANCE, AuditMenuCode::VISIT,
        AuditMenuCode::PRAYER, AuditMenuCode::VOLUNTEER, AuditMenuCode::EDUCATION,
        AuditMenuCode::BOARD,
    ];

    /** @param string $menuCode 중메뉴 코드 (소메뉴가 넘어온 경우 호출부에서 parentOf()로 변환 필요) */
    public static function forRole(string $role, string $menuCode): bool
    {
        return match ($role) {
            'pastor'    => !in_array($menuCode, self::PASTOR_EXCLUDE, true),
            'minister'  => in_array($menuCode, self::MINISTER_INCLUDE, true),
            'volunteer' => in_array($menuCode, self::VOLUNTEER_INCLUDE, true),
            default     => false,
        };
    }
}
