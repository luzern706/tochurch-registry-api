<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSignInRequest;
use App\Http\Requests\SignInRequest;
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

    public function signIn(SignInRequest $request): JsonResponse
    {
        return $this->authService->signIn($request->validated());
    }

    public function adminSignIn(AdminSignInRequest $request): JsonResponse
    {
        return $this->authService->adminSignIn($request->validated());
    }

    public function signOut(Request $request): JsonResponse
    {
        $memberId = JwtHelper::getAdminNoFromRequest($request);
        if ($memberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->authService->signOut($memberId);
    }
}
