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

    private function auth(EducationRequest $request): int|JsonResponse
    {
        $id = JwtHelper::getAdminNoFromRequest($request);
        return $id ?? ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
    }

    // ─────────────── 교육 과정 템플릿 ───────────────

    public function getCourseList(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->getCourseList($auth, $request->validated());
    }

    public function getCourseDetail(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->getCourseDetail($auth, (int) $request->validated()['course_id']);
    }

    public function registerCourse(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->registerCourse($auth, $request->validated());
    }

    public function updateCourse(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        $validated = $request->validated();
        $courseId  = (int) $validated['course_id'];
        unset($validated['course_id']);
        return $this->educationService->updateCourse($auth, $courseId, $validated);
    }

    public function deleteCourse(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->deleteCourse($auth, (int) $request->validated()['course_id']);
    }

    // ─────────────── 교육 기수 ───────────────

    public function getSessionList(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->getSessionList($auth, $request->validated());
    }

    public function getSessionDetail(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->getSessionDetail($auth, (int) $request->validated()['session_id']);
    }

    public function registerSession(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->registerSession($auth, $request->validated());
    }

    public function updateSession(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        $validated = $request->validated();
        $sessionId = (int) $validated['session_id'];
        unset($validated['session_id']);
        return $this->educationService->updateSession($auth, $sessionId, $validated);
    }

    public function deleteSession(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->deleteSession($auth, (int) $request->validated()['session_id']);
    }

    // ─────────────── 수강 기록 ───────────────

    public function enroll(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        $validated = $request->validated();
        return $this->educationService->enroll(
            $auth,
            (int) $validated['session_id'],
            (int) $validated['member_id']
        );
    }

    public function updateProgress(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        $validated = $request->validated();
        return $this->educationService->updateProgress(
            $auth,
            (int) $validated['session_id'],
            (int) $validated['member_id'],
            (int) $validated['progress'],
            $validated['status'] ?? null
        );
    }

    public function withdraw(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        $validated = $request->validated();
        return $this->educationService->withdraw(
            $auth,
            (int) $validated['session_id'],
            (int) $validated['member_id']
        );
    }

    public function getEnrollmentsBySession(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        $validated = $request->validated();
        $sessionId = (int) $validated['session_id'];
        unset($validated['session_id']);
        return $this->educationService->getEnrollmentsBySession($auth, $sessionId, $validated);
    }

    public function getSessionsByMember(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->getSessionsByMember($auth, (int) $request->validated()['member_id']);
    }

    // ─────────────── 교육 회차별 출결 ───────────────

    public function getAttendanceSheet(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->getAttendanceSheet($auth, (int) $request->validated()['session_id']);
    }

    public function saveRoundAttendance(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        $v = $request->validated();
        return $this->educationService->saveRoundAttendance(
            $auth,
            (int) $v['session_id'],
            (int) $v['round_no'],
            $v['session_date'] ?? null,
            $v['records'],
        );
    }

    public function getMemberAttendanceStats(EducationRequest $request): JsonResponse
    {
        $auth = $this->auth($request);
        if ($auth instanceof JsonResponse) return $auth;
        return $this->educationService->getMemberAttendanceStats($auth, (int) $request->validated()['session_id']);
    }
}
