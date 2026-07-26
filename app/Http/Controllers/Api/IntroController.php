<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\IntroRequest;
use App\Services\IntroService;
use Illuminate\Http\JsonResponse;

class IntroController extends Controller
{
    protected IntroService $introService;

    public function __construct(IntroService $introService)
    {
        $this->introService = $introService;
    }

    private function authMemberId(IntroRequest $request): ?int
    {
        return JwtHelper::getAdminNoFromRequest($request);
    }

    public function getIntro(IntroRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->introService->getIntro();
    }

    public function updateIntro(IntroRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->introService->updateIntro($authMemberId, $request->validated());
    }

    public function updatePastor(IntroRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->introService->updatePastor($authMemberId, $request->validated());
    }

    public function uploadPastorPhoto(IntroRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->introService->uploadPastorPhoto($authMemberId, $request->validated()['photo']);
    }

    public function deletePastorPhoto(IntroRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->introService->deletePastorPhoto($authMemberId);
    }
}
