<?php

namespace App\Constants;

/**
 * 교적·재정 서비스 사용 여부 판정 규칙 (OnboardingService, PermissionService 공용).
 *
 * 판정은 교회 단위다 — "이 교회가 교적·재정을 쓰고 있는가".
 * 교회 안에 교적(perm_registry) 또는 재정(perm_finance) 권한이 켜진 활성 담당자가 있거나,
 * 이미 교적 데이터가 있으면 사용 중으로 본다. 둘 다 아니면 안내 페이지 대상이다.
 */
class ServiceAccessPolicy
{
    /** 서비스 신청 전에도 쓸 수 있는 무료 메뉴(권한 매트릭스의 중메뉴 코드) */
    public const FREE_MENUS = [AuditMenuCode::WEBSITE];

    /** @param array{registry:bool, finance:bool, has_data:bool} $usage OnboardingRepository::getChurchServiceUsage() 결과 */
    public static function usesService(array $usage): bool
    {
        return $usage['registry'] || $usage['finance'] || $usage['has_data'];
    }
}
