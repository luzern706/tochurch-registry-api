<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\NewsRequest;
use App\Services\NewsService;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    protected NewsService $newsService;

    public function __construct(NewsService $newsService)
    {
        $this->newsService = $newsService;
    }

    private function authMemberId(NewsRequest $request): ?int
    {
        return JwtHelper::getAdminNoFromRequest($request);
    }

    // ── 교회 소식 ──
    public function getList(NewsRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->getNewsList($request->validated());
    }

    public function register(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->registerNews($authMemberId, $request->validated());
    }

    public function update(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        $contentNo = (int) $validated['content_no'];
        unset($validated['content_no']);
        return $this->newsService->updateNews($authMemberId, $contentNo, $validated);
    }

    public function delete(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->deleteNews($authMemberId, (int) $request->validated()['content_no']);
    }

    public function togglePin(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        return $this->newsService->togglePinNews($authMemberId, (int) $validated['content_no'], (bool) $validated['is_top']);
    }

    // ── 주보 게시판 ──
    public function bulletinGetList(NewsRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->getBulletinList($request->validated());
    }

    public function bulletinRegister(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->registerBulletin($authMemberId, $request->validated());
    }

    public function bulletinUpdate(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        $contentNo = (int) $validated['content_no'];
        unset($validated['content_no']);
        return $this->newsService->updateBulletin($authMemberId, $contentNo, $validated);
    }

    public function bulletinDelete(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->deleteBulletin($authMemberId, (int) $request->validated()['content_no']);
    }

    // ── 묻고 답하기 ──
    public function qnaGetList(NewsRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->getQnaList($request->validated());
    }

    public function qnaRegister(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->registerQna($authMemberId, $request->validated());
    }

    public function qnaUpdate(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        $contentNo = (int) $validated['content_no'];
        unset($validated['content_no']);
        return $this->newsService->updateQna($authMemberId, $contentNo, $validated);
    }

    public function qnaDelete(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->deleteQna($authMemberId, (int) $request->validated()['content_no']);
    }

    public function qnaAnswer(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        return $this->newsService->answerQna($authMemberId, (int) $validated['content_no'], $validated['comment']);
    }

    // ── 상담 신청 ──
    public function counselingGetList(NewsRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->getCounselingList($request->validated());
    }

    public function counselingGetDetail(NewsRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->getCounselingDetail((int) $request->validated()['content_no']);
    }

    public function counselingUpdate(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        $contentNo = (int) $validated['content_no'];
        unset($validated['content_no']);
        return $this->newsService->updateCounseling($authMemberId, $contentNo, $validated);
    }

    public function counselingDelete(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->deleteCounseling($authMemberId, (int) $request->validated()['content_no']);
    }

    // ── 성도한마디 ──
    public function testimonyGetList(NewsRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->getTestimonyList($request->validated());
    }

    public function testimonyUpdate(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        return $this->newsService->updateTestimonyStatus($authMemberId, (int) $validated['content_no'], (bool) $validated['is_selected']);
    }

    public function testimonyDelete(NewsRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->newsService->deleteTestimony($authMemberId, (int) $request->validated()['content_no']);
    }
}
