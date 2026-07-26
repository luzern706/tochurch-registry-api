<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PrayerRequest;
use App\Services\PrayerService;
use Illuminate\Http\JsonResponse;

class PrayerController extends Controller
{
    protected PrayerService $prayerService;

    public function __construct(PrayerService $prayerService)
    {
        $this->prayerService = $prayerService;
    }

    public function getList(PrayerRequest $request): JsonResponse
    {
        [$churchId, $authId] = $this->auth($request);
        if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->prayerService->getList($request->validated(), $churchId);
    }

    public function getDetail(PrayerRequest $request): JsonResponse
    {
        [$churchId, $authId] = $this->auth($request);
        if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->prayerService->getDetail((int) $request->input('id'), $churchId);
    }

    public function register(PrayerRequest $request): JsonResponse
    {
        [$churchId, $authId] = $this->auth($request);
        if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->prayerService->register($request->validated(), $churchId, $authId);
    }

    public function update(PrayerRequest $request): JsonResponse
    {
        [$churchId, $authId] = $this->auth($request);
        if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->prayerService->update(
            (int) $request->input('id'),
            $request->validated(),
            $churchId,
            $authId
        );
    }

    public function delete(PrayerRequest $request): JsonResponse
    {
        [$churchId, $authId] = $this->auth($request);
        if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->prayerService->delete((int) $request->input('id'), $churchId, $authId);
    }

    public function updateStatus(PrayerRequest $request): JsonResponse
    {
        [$churchId, $authId] = $this->auth($request);
        if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->prayerService->updateStatus(
            (int) $request->input('id'),
            $request->validated(),
            $churchId,
            $authId
        );
    }

    public function getStats(PrayerRequest $request): JsonResponse
    {
        [$churchId, $authId] = $this->auth($request);
        if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->prayerService->getStats($churchId);
    }

    private function auth($request): array
    {
        $churchId = JwtHelper::getChurchIdFromRequest();
        $authId   = JwtHelper::getAdminNoFromRequest($request);
        return [$churchId, $authId ?? 0];
    }
}
