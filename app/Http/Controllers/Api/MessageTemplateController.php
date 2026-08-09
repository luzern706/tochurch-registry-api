<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageTemplateRequest;
use App\Services\MessageTemplateService;
use Illuminate\Http\JsonResponse;

class MessageTemplateController extends Controller
{
    protected MessageTemplateService $service;

    public function __construct(MessageTemplateService $service)
    {
        $this->service = $service;
    }

    private function authId(MessageTemplateRequest $request): ?int
    {
        return JwtHelper::getAdminNoFromRequest($request);
    }

    public function getList(MessageTemplateRequest $request): JsonResponse
    {
        $authMemberId = $this->authId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->service->getList($authMemberId, $request->validated());
    }

    public function getActiveList(MessageTemplateRequest $request): JsonResponse
    {
        $authMemberId = $this->authId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->service->getActiveList($authMemberId);
    }

    public function getDetail(MessageTemplateRequest $request): JsonResponse
    {
        $authMemberId = $this->authId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->service->getDetail($authMemberId, (int) $request->validated()['id']);
    }

    public function register(MessageTemplateRequest $request): JsonResponse
    {
        $authMemberId = $this->authId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->service->register($authMemberId, $request->validated());
    }

    public function update(MessageTemplateRequest $request): JsonResponse
    {
        $authMemberId = $this->authId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        $validated = $request->validated();
        $id = (int) $validated['id'];
        unset($validated['id']);

        return $this->service->update($authMemberId, $id, $validated);
    }

    public function duplicate(MessageTemplateRequest $request): JsonResponse
    {
        $authMemberId = $this->authId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        return $this->service->duplicate($authMemberId, (int) $request->validated()['id']);
    }

    public function toggleActive(MessageTemplateRequest $request): JsonResponse
    {
        $authMemberId = $this->authId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

        $validated = $request->validated();
        return $this->service->toggleActive($authMemberId, (int) $validated['id'], (bool) $validated['is_active']);
    }
}
