<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChurchReportRequest;
use App\Services\ChurchReportService;
use Illuminate\Http\JsonResponse;

/** 교회 관리 > 교회 페이지 > 게시글 신고함 (교회 1차 처리) — 두레이 교회게시판 관리분리 ④ */
class ChurchReportController extends Controller
{
    public function __construct(protected ChurchReportService $service)
    {
    }

    public function getSummary(ChurchReportRequest $request): JsonResponse
    {
        $churchId = JwtHelper::getChurchIdFromRequest();
        if (JwtHelper::getAdminNoFromRequest($request) === null || $churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->service->getSummary($churchId);
    }

    public function getList(ChurchReportRequest $request): JsonResponse
    {
        $churchId = JwtHelper::getChurchIdFromRequest();
        if (JwtHelper::getAdminNoFromRequest($request) === null || $churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->service->getList($churchId, $request->validated());
    }

    public function process(ChurchReportRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        $churchId = JwtHelper::getChurchIdFromRequest();
        if ($adminNo === null || $churchId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $v = $request->validated();
        return $this->service->process($churchId, (int) $adminNo, (int) $v['seq'], $v['action'], trim($v['reason']));
    }
}
