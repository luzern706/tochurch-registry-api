<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    protected OnboardingService $onboardingService;

    public function __construct(OnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    public function getStatus(Request $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->onboardingService->getStatus($adminNo);
    }

    public function applyService(Request $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->onboardingService->applyService($adminNo);
    }
}
