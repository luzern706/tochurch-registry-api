<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceReportRequest;
use App\Services\FinanceReportService;
use Illuminate\Http\JsonResponse;

class FinanceReportController extends Controller
{
    protected FinanceReportService $financeReportService;

    public function __construct(FinanceReportService $financeReportService)
    {
        $this->financeReportService = $financeReportService;
    }

    public function getMonthly(FinanceReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->financeReportService->getMonthlyReport($authMemberId, (int) $validated['year'], (int) $validated['month']);
    }

    public function getAnnual(FinanceReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->financeReportService->getAnnualReport($authMemberId, (int) $validated['year']);
    }
}
