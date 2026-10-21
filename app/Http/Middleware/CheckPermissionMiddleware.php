<?php

namespace App\Http\Middleware;

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

        if ($role === 'admin') {
            return $next($request);
        }

        if ($churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $adminNo = JwtHelper::getAdminNoFromRequest($request);

        if (in_array('free', $options, true) && $this->permissionService->isFreeTierAdmin($adminNo)) {
            return $next($request);
        }

        if (!$this->permissionService->hasMenuAccess($churchId, $role, $menuCode, $adminNo)) {
            return ApiResponse::fail('INSUFFICIENT_PERMISSION', '이 기능에 대한 권한이 없습니다.', 403);
        }

        return $next($request);
    }
}
