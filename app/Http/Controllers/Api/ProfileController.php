<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileRequest;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    protected ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function getMyProfile(Request $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->profileService->getMyProfile($adminNo);
    }

    public function updateMyProfile(ProfileRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->profileService->updateMyProfile($request->validated(), $adminNo);
    }

    public function changePassword(ProfileRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->profileService->changePassword($request->validated(), $adminNo);
    }
}
