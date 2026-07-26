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
 */
class CheckPermissionMiddleware
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function handle(Request $request, Closure $next, string $menuCode): Response
    {
        $role     = JwtHelper::getAdminRoleFromRequest();
        $churchId = JwtHelper::getChurchIdFromRequest();

        if ($role === 'admin') {
            return $next($request);
        }

        if ($churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        if (!$this->permissionService->hasMenuAccess($churchId, $role, $menuCode)) {
            return ApiResponse::fail('INSUFFICIENT_PERMISSION', '이 기능에 대한 권한이 없습니다.', 403);
        }

        return $next($request);
    }
}
