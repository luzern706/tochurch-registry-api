<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageSettingRequest;
use App\Services\MessageSettingService;
use Illuminate\Http\JsonResponse;

class MessageSettingController extends Controller
{
    protected MessageSettingService $messageSettingService;

    public function __construct(MessageSettingService $messageSettingService)
    {
        $this->messageSettingService = $messageSettingService;
    }

    public function getSettings(MessageSettingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->messageSettingService->getSettings($authMemberId);
    }

    public function saveSettings(MessageSettingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->messageSettingService->saveSettings($authMemberId, $request->validated()['settings']);
    }
}
