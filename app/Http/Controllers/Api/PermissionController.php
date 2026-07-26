<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function getMatrix(Request $request): JsonResponse
    {
        $adminNo  = JwtHelper::getAdminNoFromRequest($request);
        $churchId = JwtHelper::getChurchIdFromRequest();
        if ($adminNo === null || $churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->permissionService->getMatrix($churchId);
    }

    /** 로그인한 본인의 권한만 조회 — jwt.auth만 필요 (프론트 메뉴 필터링/페이지 가드용) */
    public function getMyPermissions(Request $request): JsonResponse
    {
        $adminNo  = JwtHelper::getAdminNoFromRequest($request);
        $churchId = JwtHelper::getChurchIdFromRequest();
        if ($adminNo === null || $churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $role = JwtHelper::getAdminRoleFromRequest();
        return $this->permissionService->getMyPermissions($churchId, $role);
    }

    public function saveRole(PermissionRequest $request): JsonResponse
    {
        $adminNo  = JwtHelper::getAdminNoFromRequest($request);
        $churchId = JwtHelper::getChurchIdFromRequest();
        if ($adminNo === null || $churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->permissionService->saveRole($churchId, $adminNo, $validated['role'], $validated['permissions']);
    }
}
