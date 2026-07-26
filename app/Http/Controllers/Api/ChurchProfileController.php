<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChurchProfileRequest;
use App\Services\ChurchProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChurchProfileController extends Controller
{
    protected ChurchProfileService $churchProfileService;

    public function __construct(ChurchProfileService $churchProfileService)
    {
        $this->churchProfileService = $churchProfileService;
    }

    private function requireAuth(Request $request): array
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        $churchNo = JwtHelper::getChurchIdFromRequest();
        return [$adminNo, $churchNo];
    }

    public function getProfile(Request $request): JsonResponse
    {
        [$adminNo, $churchNo] = $this->requireAuth($request);
        if ($adminNo === null || $churchNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->churchProfileService->getProfile($churchNo);
    }

    public function updateProfile(ChurchProfileRequest $request): JsonResponse
    {
        [$adminNo, $churchNo] = $this->requireAuth($request);
        if ($adminNo === null || $churchNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->churchProfileService->updateProfile($request->validated(), $adminNo, $churchNo);
    }

    public function getPastorOptions(Request $request): JsonResponse
    {
        [$adminNo, $churchNo] = $this->requireAuth($request);
        if ($adminNo === null || $churchNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->churchProfileService->getPastorOptions($churchNo);
    }

    public function getDenominationOptions(Request $request): JsonResponse
    {
        [$adminNo, $churchNo] = $this->requireAuth($request);
        if ($adminNo === null || $churchNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->churchProfileService->getDenominationOptions();
    }

    public function uploadFile(ChurchProfileRequest $request): JsonResponse
    {
        [$adminNo, $churchNo] = $this->requireAuth($request);
        if ($adminNo === null || $churchNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->churchProfileService->uploadFile(
            $validated['slot'],
            $request->file('file'),
            $churchNo,
            $adminNo
        );
    }

    public function deleteFile(ChurchProfileRequest $request): JsonResponse
    {
        [$adminNo, $churchNo] = $this->requireAuth($request);
        if ($adminNo === null || $churchNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->churchProfileService->deleteFile($request->validated()['slot'], $churchNo, $adminNo);
    }
}
