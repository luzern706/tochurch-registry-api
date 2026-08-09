<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemberJoinRequestRequest;
use App\Services\MemberJoinRequestService;
use Illuminate\Http\JsonResponse;

class MemberJoinRequestController extends Controller
{
    protected MemberJoinRequestService $service;

    public function __construct(MemberJoinRequestService $service)
    {
        $this->service = $service;
    }

    public function getJoinRequestList(MemberJoinRequestRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->service->getList($authMemberId, $request->validated());
    }

    public function holdJoinRequest(MemberJoinRequestRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->service->hold($authMemberId, $request->validated());
    }

    public function rejectJoinRequest(MemberJoinRequestRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->service->reject($authMemberId, $request->validated());
    }
}
