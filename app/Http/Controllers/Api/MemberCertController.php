<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemberCertRequest;
use App\Services\MemberCertService;
use Illuminate\Http\JsonResponse;

class MemberCertController extends Controller
{
    protected MemberCertService $service;

    public function __construct(MemberCertService $service)
    {
        $this->service = $service;
    }

    public function getList(MemberCertRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->service->getList($authMemberId, $request->validated());
    }

    public function approve(MemberCertRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->service->approve($authMemberId, $request->validated());
    }

    public function reject(MemberCertRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->service->reject($authMemberId, $request->validated());
    }

    public function reset(MemberCertRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->service->reset($authMemberId, $request->validated());
    }
}
