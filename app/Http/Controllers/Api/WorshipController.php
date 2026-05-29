<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorshipRequest;
use App\Services\WorshipService;
use Illuminate\Http\JsonResponse;

class WorshipController extends Controller
{
    protected WorshipService $worshipService;

    public function __construct(WorshipService $worshipService)
    {
        $this->worshipService = $worshipService;
    }

    public function getList(WorshipRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->worshipService->getWorshipList($authMemberId, $request->validated());
    }

    public function getDetail(WorshipRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->worshipService->getWorshipDetail($authMemberId, (int) $validated['service_id']);
    }

    public function register(WorshipRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->worshipService->registerWorship($authMemberId, $request->validated());
    }

    public function update(WorshipRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        $serviceId = (int) $validated['service_id'];
        unset($validated['service_id']);
        return $this->worshipService->updateWorship($authMemberId, $serviceId, $validated);
    }

    public function delete(WorshipRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->worshipService->deleteWorship($authMemberId, (int) $validated['service_id']);
    }
}
