<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\NoticeRequest;
use App\Services\NoticeService;
use Illuminate\Http\JsonResponse;

class NoticeController extends Controller
{
    protected NoticeService $noticeService;

    public function __construct(NoticeService $noticeService)
    {
        $this->noticeService = $noticeService;
    }

    public function getList(NoticeRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->noticeService->getNoticeList($adminNo, $validated['type'] ?? null);
    }

    public function getDetail(NoticeRequest $request): JsonResponse
    {
        $adminNo = JwtHelper::getAdminNoFromRequest($request);
        if ($adminNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->noticeService->getNoticeDetail($adminNo, (int) $validated['notice_no']);
    }

    public function getResourceList(): JsonResponse
    {
        return $this->noticeService->getResourceList();
    }
}
