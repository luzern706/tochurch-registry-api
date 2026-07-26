<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SystemRequest;
use App\Services\SystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    protected SystemService $systemService;

    public function __construct(SystemService $systemService)
    {
        $this->systemService = $systemService;
    }

    public function getOverview(Request $request): JsonResponse
    {
        $adminNo  = JwtHelper::getAdminNoFromRequest($request);
        $churchId = JwtHelper::getChurchIdFromRequest();
        if ($adminNo === null || $churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->systemService->getOverview($churchId);
    }

    public function getAuditLogs(SystemRequest $request): JsonResponse
    {
        $adminNo  = JwtHelper::getAdminNoFromRequest($request);
        $churchId = JwtHelper::getChurchIdFromRequest();
        if ($adminNo === null || $churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->systemService->getAuditLogs($request->validated(), $churchId);
    }
}
