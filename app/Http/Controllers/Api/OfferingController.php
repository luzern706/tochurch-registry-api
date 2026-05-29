<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\OfferingRequest;
use App\Services\OfferingService;
use Illuminate\Http\JsonResponse;

class OfferingController extends Controller
{
    protected OfferingService $offeringService;

    public function __construct(OfferingService $offeringService)
    {
        $this->offeringService = $offeringService;
    }

    public function getList(OfferingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->offeringService->getOfferingList($authMemberId, $request->validated());
    }

    public function getDetail(OfferingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->offeringService->getOfferingDetail($authMemberId, (int) $validated['offering_id']);
    }

    public function register(OfferingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->offeringService->registerOffering($authMemberId, $request->validated());
    }

    public function update(OfferingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated   = $request->validated();
        $offeringId  = (int) $validated['offering_id'];
        unset($validated['offering_id']);
        return $this->offeringService->updateOffering($authMemberId, $offeringId, $validated);
    }

    public function delete(OfferingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->offeringService->deleteOffering($authMemberId, (int) $validated['offering_id']);
    }

    public function getListByMember(OfferingRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        $memberId  = (int) $validated['member_id'];
        unset($validated['member_id']);
        return $this->offeringService->getListByMember($authMemberId, $memberId, $validated);
    }
}
