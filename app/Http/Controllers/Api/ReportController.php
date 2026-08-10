<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function getMemberStats(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->reportService->getMemberStats($authMemberId);
    }

    public function getAttendanceStats(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->reportService->getAttendanceStats($authMemberId, $request->validated());
    }

    public function getStatsByService(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->reportService->getStatsByService($authMemberId, $request->validated());
    }

    public function getMemberRateDistribution(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->reportService->getMemberRateDistribution($authMemberId, $request->validated());
    }

    public function getAttendanceStatsByOrg(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->reportService->getAttendanceStatsByOrg($authMemberId, $request->validated());
    }

    public function getOfferingStats(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->reportService->getOfferingStats($authMemberId, $request->validated());
    }

    public function getVisitStats(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->reportService->getVisitStats($authMemberId, $request->validated());
    }

    public function getDashboard(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->reportService->getDashboard($authMemberId);
    }

    public function getFinanceDashboard(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->reportService->getFinanceDashboard($authMemberId);
    }

    public function getFinanceStats(ReportRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->reportService->getFinanceStats($authMemberId, $request->validated());
    }
}
