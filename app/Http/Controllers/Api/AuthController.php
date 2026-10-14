<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSignInRequest;
use App\Http\Requests\SetupPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function adminSignIn(AdminSignInRequest $request): JsonResponse
    {
        return $this->authService->adminSignIn($request->validated());
    }

    /** 비밀번호 설정 링크 토큰 확인 (로그인 전) */
    public function checkSetupToken(SetupPasswordRequest $request): JsonResponse
    {
        return $this->authService->checkSetupToken($request->validated()['token']);
    }

    /** 비밀번호 설정 링크로 최초 비밀번호 설정 (로그인 전) */
    public function setupPassword(SetupPasswordRequest $request): JsonResponse
    {
        $v = $request->validated();
        return $this->authService->setupPassword($v['token'], $v['new_password']);
    }

    public function signOut(Request $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->authService->signOut($adminNo);
    }
}
