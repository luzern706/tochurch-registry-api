<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    public function getList(ExpenseRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->expenseService->getExpenseList($authMemberId, $request->validated());
    }

    public function getDetail(ExpenseRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->expenseService->getExpenseDetail($authMemberId, (int) $validated['expense_id']);
    }

    public function register(ExpenseRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->expenseService->registerExpense($authMemberId, $request->validated());
    }

    public function update(ExpenseRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated  = $request->validated();
        $expenseId  = (int) $validated['expense_id'];
        unset($validated['expense_id']);
        return $this->expenseService->updateExpense($authMemberId, $expenseId, $validated);
    }

    public function delete(ExpenseRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->expenseService->deleteExpense($authMemberId, (int) $validated['expense_id']);
    }

    public function getCategoryStats(ExpenseRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->expenseService->getCategoryStats($authMemberId, $request->validated());
    }

    public function getReceiptStats(ExpenseRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->expenseService->getReceiptStats($authMemberId, $request->validated());
    }

    public function uploadReceipt(ExpenseRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $churchId = JwtHelper::getChurchIdFromRequest();
        if ($churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->expenseService->uploadReceipt($request->validated()['file'], $churchId);
    }
}
