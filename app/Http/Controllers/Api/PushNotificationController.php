<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PushNotificationRequest;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;

class PushNotificationController extends Controller
{
    protected PushNotificationService $pushNotificationService;

    public function __construct(PushNotificationService $pushNotificationService)
    {
        $this->pushNotificationService = $pushNotificationService;
    }

    public function getFilterOptions(): JsonResponse
    {
        return $this->pushNotificationService->getFilterOptions();
    }

    public function getTargetSummary(PushNotificationRequest $request): JsonResponse
    {
        return $this->pushNotificationService->getTargetSummary($request->validated());
    }

    public function send(PushNotificationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->pushNotificationService->sendPush($authMemberId, $request->validated());
    }

    public function getDetail(PushNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        return $this->pushNotificationService->getPushDetail((int) $validated['message_id']);
    }

    public function resend(PushNotificationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->pushNotificationService->resendPush($authMemberId, $request->validated());
    }
}
