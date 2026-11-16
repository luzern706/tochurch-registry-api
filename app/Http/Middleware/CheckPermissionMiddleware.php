<?php

namespace App\Http\Middleware;

use App\Constants\ServiceAccessPolicy;
use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 라우트 사용: ->middleware('permission:MENU_CODE')
 * admin_type='admin'은 항상 통과(하드코딩 바이패스). 그 외 역할은 소속 소메뉴 중
 * 하나라도 접근 가능하면 통과 — PermissionService::hasMenuAccess() 참조.
 *
 * 'permission:MENU_CODE,free' — 무료 기능(교회 페이지 관리)이 함께 쓰는 라우트 그룹용 옵션.
 * 교적·재정 서비스 신청 전 계정(PermissionService::isFreeTierAdmin)도 추가로 통과시킨다.
 * 다른 역할의 권한 판정은 옵션이 없을 때와 완전히 같다.
 *
 * 서비스 신청 전 교회(isFreeTierAdmin)는 무료 메뉴(ServiceAccessPolicy::FREE_MENUS)와 'free' 옵션
 * 라우트를 제외한 모든 permission 라우트에서 403 으로 막힌다(admin 역할 포함).
 */
class CheckPermissionMiddleware
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function handle(Request $request, Closure $next, string $menuCode, string ...$options): Response
    {
        $role     = JwtHelper::getAdminRoleFromRequest();
        $churchId = JwtHelper::getChurchIdFromRequest();
        $adminNo  = JwtHelper::getAdminNoFromRequest($request);

        // 교적·재정 서비스 신청 전 교회는 무료 메뉴 밖의 API 를 서버에서도 막는다(역할 무관 — admin 포함).
        // 화면(ServiceGate)만 막으면 API 직접 호출로 우회되므로 서버 판정이 기준이다.
        $isFreeRoute = in_array('free', $options, true) || in_array($menuCode, ServiceAccessPolicy::FREE_MENUS, true);
        if (!$isFreeRoute && $adminNo !== null && $this->permissionService->isFreeTierAdmin($adminNo)) {
            return ApiResponse::fail('INSUFFICIENT_PERMISSION', '교적·재정 서비스 신청 후 이용할 수 있는 기능입니다.', 403);
        }

        if ($role === 'admin') {
            return $next($request);
        }

        // 교회 관리자 전용 메뉴(성도 인증 등) — 위 admin 바이패스를 통과하지 못한 역할은 'free' 옵션·권한 매트릭스와 무관하게 차단
        if (in_array($menuCode, ServiceAccessPolicy::ADMIN_ONLY_MENUS, true)) {
            return ApiResponse::fail('INSUFFICIENT_PERMISSION', '교회 관리자만 사용할 수 있는 기능입니다.', 403);
        }

        if ($churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        if (in_array('free', $options, true) && $this->permissionService->isFreeTierAdmin($adminNo)) {
            return $next($request);
        }

        if (!$this->permissionService->hasMenuAccess($churchId, $role, $menuCode, $adminNo)) {
            return ApiResponse::fail('INSUFFICIENT_PERMISSION', '이 기능에 대한 권한이 없습니다.', 403);
        }

        return $next($request);
    }
}
