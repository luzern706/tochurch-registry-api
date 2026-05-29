<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\EducationRequest;
use App\Services\EducationService;
use Illuminate\Http\JsonResponse;

class EducationController extends Controller
{
    protected EducationService $educationService;

    public function __construct(EducationService $educationService)
    {
        $this->educationService = $educationService;
    }

    // ─────────────── 교육 과정 ───────────────

    public function getList(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->educationService->getCourseList($authMemberId, $request->validated());
    }

    public function getDetail(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->educationService->getCourseDetail($authMemberId, (int) $validated['course_id']);
    }

    public function register(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->educationService->registerCourse($authMemberId, $request->validated());
    }

    public function update(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        $courseId  = (int) $validated['course_id'];
        unset($validated['course_id']);
        return $this->educationService->updateCourse($authMemberId, $courseId, $validated);
    }

    public function delete(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->educationService->deleteCourse($authMemberId, (int) $validated['course_id']);
    }

    // ─────────────── 수강 기록 ───────────────

    public function enroll(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->educationService->enroll(
            $authMemberId,
            (int) $validated['course_id'],
            (int) $validated['member_id']
        );
    }

    public function updateProgress(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->educationService->updateProgress(
            $authMemberId,
            (int) $validated['course_id'],
            (int) $validated['member_id'],
            (int) $validated['progress'],
            $validated['status'] ?? null
        );
    }

    public function withdraw(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->educationService->withdraw(
            $authMemberId,
            (int) $validated['course_id'],
            (int) $validated['member_id']
        );
    }

    public function getEnrollmentsByCourse(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        $courseId  = (int) $validated['course_id'];
        unset($validated['course_id']);
        return $this->educationService->getEnrollmentsByCourse($authMemberId, $courseId, $validated);
    }

    public function getCoursesByMember(EducationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->educationService->getCoursesByMember($authMemberId, (int) $validated['member_id']);
    }
}
