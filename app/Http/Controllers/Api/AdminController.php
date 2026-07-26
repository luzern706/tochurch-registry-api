<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequest;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function getList(AdminRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->adminService->getAdminList($request->validated(), $adminNo);
    }

    public function getDetail(AdminRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->adminService->getAdminDetail($request->validated(), $adminNo);
    }

    public function register(AdminRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->adminService->registerAdmin($request->validated(), $adminNo);
    }

    public function update(AdminRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->adminService->updateAdmin($request->validated(), $adminNo);
    }

    public function suspend(AdminRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->adminService->suspendAdmin($request->validated(), $adminNo);
    }

    public function activate(AdminRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->adminService->activateAdmin($request->validated(), $adminNo);
    }

    public function delete(AdminRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->adminService->deleteAdmin($request->validated(), $adminNo);
    }

    public function purge(AdminRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->adminService->purgeAdmin($request->validated(), $adminNo);
    }

}
