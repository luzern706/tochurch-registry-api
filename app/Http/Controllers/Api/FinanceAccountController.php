<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceAccountRequest;
use App\Services\FinanceAccountService;
use Illuminate\Http\JsonResponse;

class FinanceAccountController extends Controller
{
    protected FinanceAccountService $financeAccountService;

    public function __construct(FinanceAccountService $financeAccountService)
    {
        $this->financeAccountService = $financeAccountService;
    }

    public function getList(FinanceAccountRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->financeAccountService->getList($authMemberId, $request->validated());
    }

    public function getActiveList(FinanceAccountRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->financeAccountService->getActiveList($authMemberId, $validated['usage']);
    }

    public function register(FinanceAccountRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->financeAccountService->register($authMemberId, $request->validated());
    }

    public function update(FinanceAccountRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated  = $request->validated();
        $accountId  = (int) $validated['account_id'];
        unset($validated['account_id']);
        return $this->financeAccountService->update($authMemberId, $accountId, $validated);
    }

    public function delete(FinanceAccountRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->financeAccountService->delete($authMemberId, (int) $validated['account_id']);
    }
}
