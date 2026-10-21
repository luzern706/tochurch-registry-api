<?php

namespace App\Constants;

/**
 * 교적·재정 서비스 사용 권한 판정 규칙 (OnboardingService, PermissionService 공용).
 *
 * 권한 소스는 gh_church_admin_role.perm_registry / perm_finance.
 * 권한 행이 아예 없는 계정(기존 교회 공용 계정 등)은 이전부터 교적을 쓰고 있었으므로 사용 가능으로 본다.
 */
class ServiceAccessPolicy
{
    /** 서비스 신청 전에도 쓸 수 있는 무료 메뉴(권한 매트릭스의 중메뉴 코드) */
    public const FREE_MENUS = [AuditMenuCode::WEBSITE];

    /** @param array<int, object> $roles OnboardingRepository::getActiveRoles() 결과 */
    public static function hasAccess(array $roles): bool
    {
        if ($roles === []) {
            return true;
        }
        foreach ($roles as $role) {
            if ((int) $role->perm_registry === 1 || (int) $role->perm_finance === 1) {
                return true;
            }
        }
        return false;
    }
}
