<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MediaRequest;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    private function authMemberId(MediaRequest $request): ?int
    {
        return JwtHelper::getAdminNoFromRequest($request);
    }

    // ── 설교 영상 ──
    public function sermonGetList(MediaRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->getSermonList($request->validated());
    }

    public function sermonSetFeaturedMode(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->setSermonFeaturedMode($authMemberId, $request->validated()['featured_mode']);
    }

    public function sermonRegister(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->registerSermon($authMemberId, $request->validated());
    }

    public function sermonUpdate(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        $contentNo = (int) $validated['content_no'];
        unset($validated['content_no']);
        return $this->mediaService->updateSermon($authMemberId, $contentNo, $validated);
    }

    public function sermonDelete(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->deleteSermon($authMemberId, (int) $request->validated()['content_no']);
    }

    // ── 교회 영상 ──
    public function churchVideoGetList(MediaRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->getChurchVideoList($request->validated());
    }

    public function churchVideoRegister(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->registerChurchVideo($authMemberId, $request->validated());
    }

    public function churchVideoUpdate(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        $contentNo = (int) $validated['content_no'];
        unset($validated['content_no']);
        return $this->mediaService->updateChurchVideo($authMemberId, $contentNo, $validated);
    }

    public function churchVideoDelete(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->deleteChurchVideo($authMemberId, (int) $request->validated()['content_no']);
    }

    // ── 사진 갤러리 ──
    public function galleryGetList(MediaRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->getGalleryList($request->validated());
    }

    public function galleryRegister(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->registerGallery($authMemberId, $request->validated());
    }

    public function galleryUpdate(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        $contentNo = (int) $validated['content_no'];
        unset($validated['content_no']);
        return $this->mediaService->updateGallery($authMemberId, $contentNo, $validated);
    }

    public function galleryDelete(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->deleteGallery($authMemberId, (int) $request->validated()['content_no']);
    }

    // ── 사역 & 활동 사진 ──
    public function ministryGetList(MediaRequest $request): JsonResponse
    {
        if ($this->authMemberId($request) === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->getMinistryList($request->validated());
    }

    public function ministryRegister(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->registerMinistry($authMemberId, $request->validated());
    }

    public function ministryUpdate(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $validated = $request->validated();
        $contentNo = (int) $validated['content_no'];
        unset($validated['content_no']);
        return $this->mediaService->updateMinistry($authMemberId, $contentNo, $validated);
    }

    public function ministryDelete(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->deleteMinistry($authMemberId, (int) $request->validated()['content_no']);
    }

    // ── 이미지 업로드 ──
    public function uploadImage(MediaRequest $request): JsonResponse
    {
        $authMemberId = $this->authMemberId($request);
        if ($authMemberId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        $churchNo = JwtHelper::getChurchIdFromRequest();
        if ($churchNo === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        return $this->mediaService->uploadImage($request->validated()['image'], $churchNo);
    }
}
