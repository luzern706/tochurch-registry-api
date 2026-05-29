<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\EducationRepository;
use App\Repositories\MemberRepository;
use Illuminate\Http\JsonResponse;

class EducationService
{
    private const COURSE_FILLABLE = [
        'name', 'description', 'start_date', 'end_date', 'status',
    ];

    protected EducationRepository $educationRepository;
    protected MemberRepository $memberRepository;

    public function __construct(
        EducationRepository $educationRepository,
        MemberRepository $memberRepository
    ) {
        $this->educationRepository = $educationRepository;
        $this->memberRepository    = $memberRepository;
    }

    // ─────────────── 교육 과정 ───────────────

    public function getCourseList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->educationRepository->getCourseList($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getCourseList error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getCourseDetail(int $authMemberId, int $courseId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $course = $this->educationRepository->getCourseById($courseId);
            if ($course === null || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['course' => $course]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getCourseDetail error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerCourse(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = array_intersect_key($input, array_flip(self::COURSE_FILLABLE));
            $data['church_id'] = $churchId;
            $data['status']    = $data['status'] ?? 'upcoming';

            $newId = $this->educationRepository->insertCourse($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                summary:     "교육 과정 등록: {$data['name']}",
                targetId:    (string) $newId,
                targetLabel: $data['name'],
                created:     $data,
            );

            return ApiResponse::success(['course_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] registerCourse error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateCourse(int $authMemberId, int $courseId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $course = $this->educationRepository->getCourseById($courseId);
            if ($course === null || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            $data = array_intersect_key($input, array_flip(self::COURSE_FILLABLE));
            if (!empty($data)) {
                $this->educationRepository->updateCourse($courseId, $data);
            }

            $beforeArr = (array) $course;
            $afterArr  = array_merge($beforeArr, $data);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::EDUCATION,
                summary:       "교육 과정 수정: {$course->name}",
                targetId:      (string) $courseId,
                targetLabel:   $course->name,
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['course_id' => $courseId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] updateCourse error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteCourse(int $authMemberId, int $courseId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $course = $this->educationRepository->getCourseById($courseId);
            if ($course === null || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            if ($this->educationRepository->countEnrollmentsByCourse($courseId) > 0) {
                return ApiResponse::fail(
                    'HAS_CHILDREN',
                    '수강 기록이 있어 삭제할 수 없습니다. 먼저 수강 기록을 정리해주세요.',
                    400
                );
            }

            $this->educationRepository->deleteCourse($courseId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                summary:     "교육 과정 삭제(hard): {$course->name}",
                targetId:    (string) $courseId,
                targetLabel: $course->name,
            );

            return ApiResponse::success(['course_id' => $courseId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] deleteCourse error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    // ─────────────── 수강 기록 ───────────────

    public function enroll(int $authMemberId, int $courseId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $course = $this->educationRepository->getCourseById($courseId);
            if ($course === null || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            $member = $this->memberRepository->getMemberById($memberId);
            if ($member === null || (int) $member->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $existing = $this->educationRepository->getEnrollment($courseId, $memberId);
            if ($existing) {
                // dropped 상태였다면 되살림. completed 는 유지(불필요 재수강 방지).
                if ($existing->status === 'dropped') {
                    $this->educationRepository->updateEnrollment((int) $existing->id, [
                        'status' => 'ongoing',
                    ]);
                }
                $enrollmentId = (int) $existing->id;
            } else {
                $enrollmentId = $this->educationRepository->insertEnrollment([
                    'course_id' => $courseId,
                    'member_id' => $memberId,
                    'progress'  => 0,
                    'status'    => 'ongoing',
                ]);
            }

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                actionType:  AuditActionType::ENROLL,
                summary:     "수강 등록: {$member->name} → {$course->name}",
                targetId:    (string) $courseId,
                targetLabel: $course->name,
                detail:      ['member_id' => $memberId, 'enrollment_id' => $enrollmentId],
            );

            return ApiResponse::success([
                'enrollment_id' => $enrollmentId,
                'course_id'     => $courseId,
                'member_id'     => $memberId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] enroll error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '수강 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateProgress(int $authMemberId, int $courseId, int $memberId, int $progress, ?string $status): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $course = $this->educationRepository->getCourseById($courseId);
            if ($course === null || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            $enrollment = $this->educationRepository->getEnrollment($courseId, $memberId);
            if ($enrollment === null) {
                return ApiResponse::fail('NOT_FOUND', '수강 기록을 찾을 수 없습니다.', 404);
            }

            $progress = max(0, min(100, $progress));
            $data = ['progress' => $progress];

            if ($status !== null) {
                $data['status'] = $status;
            } elseif ($progress === 100 && $enrollment->status === 'ongoing') {
                // 100% 도달 시 자동 완료 처리
                $data['status'] = 'completed';
            }

            $this->educationRepository->updateEnrollment((int) $enrollment->id, $data);

            AuditLogHelper::logUpdate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                summary:     "수강 진도 갱신: course#{$courseId} member#{$memberId} → {$progress}%",
                targetId:    (string) $courseId,
                targetLabel: $course->name,
                before:      ['progress' => (int) $enrollment->progress, 'status' => $enrollment->status],
                after:       ['progress' => $progress, 'status' => $data['status'] ?? $enrollment->status],
            );

            return ApiResponse::success([
                'enrollment_id' => (int) $enrollment->id,
                'course_id'     => $courseId,
                'member_id'     => $memberId,
                'progress'      => $progress,
                'status'        => $data['status'] ?? $enrollment->status,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] updateProgress error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '진도 갱신 중 오류가 발생했습니다.', 500);
        }
    }

    public function withdraw(int $authMemberId, int $courseId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $course = $this->educationRepository->getCourseById($courseId);
            if ($course === null || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            $enrollment = $this->educationRepository->getEnrollment($courseId, $memberId);
            if ($enrollment === null) {
                return ApiResponse::fail('NOT_FOUND', '수강 기록을 찾을 수 없습니다.', 404);
            }

            $this->educationRepository->updateEnrollment((int) $enrollment->id, [
                'status' => 'dropped',
            ]);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                actionType:  AuditActionType::WITHDRAW,
                summary:     "수강 취소(dropped): course#{$courseId} member#{$memberId}",
                targetId:    (string) $courseId,
                targetLabel: $course->name,
                detail:      ['member_id' => $memberId, 'enrollment_id' => (int) $enrollment->id],
            );

            return ApiResponse::success([
                'enrollment_id' => (int) $enrollment->id,
                'course_id'     => $courseId,
                'member_id'     => $memberId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] withdraw error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '수강 취소 중 오류가 발생했습니다.', 500);
        }
    }

    public function getEnrollmentsByCourse(int $authMemberId, int $courseId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $course = $this->educationRepository->getCourseById($courseId);
            if ($course === null || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            $data = $this->educationRepository->getEnrollmentsByCourse($courseId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getEnrollmentsByCourse error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '수강자 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getCoursesByMember(int $authMemberId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $member = $this->memberRepository->getMemberById($memberId);
            if ($member === null || (int) $member->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $list = $this->educationRepository->getCoursesByMember($memberId);

            return ApiResponse::success([
                'member_id' => $memberId,
                'list'      => $list,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getCoursesByMember error: " . $e->getMessage(), "education");
            return ApiResponse::fail('INTERNAL_ERROR', '수강 이력 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
