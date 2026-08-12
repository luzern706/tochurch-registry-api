<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceSettingRequest;
use App\Services\FinanceSettingService;
use Illuminate\Http\JsonResponse;

class FinanceSettingController extends Controller
{
    protected FinanceSettingService $financeSettingService;

    public function __construct(FinanceSettingService $financeSettingService)
    {
        $this->financeSettingService = $financeSettingService;
    }

    public function getSettings(FinanceSettingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->financeSettingService->getSettings($authMemberId);
    }

    public function saveSettings(FinanceSettingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->financeSettingService->saveSettings($authMemberId, $request->validated()['settings']);
    }

    public function uploadSeal(FinanceSettingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $churchId = JwtHelper::getChurchIdFromRequest();
        if ($churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->financeSettingService->uploadSeal($request->validated()['file'], $churchId);
    }
}
