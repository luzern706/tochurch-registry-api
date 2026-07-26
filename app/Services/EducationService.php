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
    private const COURSE_FILLABLE  = [
        'name', 'description', 'recommendation', 'target', 'edu_type',
        'total_weeks', 'total_rounds', 'use_attendance', 'completion_rate',
        'repeat_mode', 'auto_complete',
    ];
    private const SESSION_FILLABLE = [
        'course_id', 'generation', 'instructor',
        'start_date', 'end_date', 'capacity', 'total_rounds', 'status', 'memo',
    ];

    protected EducationRepository $educationRepository;
    protected MemberRepository    $memberRepository;

    public function __construct(
        EducationRepository $educationRepository,
        MemberRepository    $memberRepository
    ) {
        $this->educationRepository = $educationRepository;
        $this->memberRepository    = $memberRepository;
    }

    // ─────────────────────────────────────────────────────────────
    // 교육 과정 템플릿 CRUD
    // ─────────────────────────────────────────────────────────────

    public function getCourseList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            return ApiResponse::success(
                $this->educationRepository->getCourseList($churchId, $filters)
            );
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getCourseList: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getCourseDetail(int $authMemberId, int $courseId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $course = $this->educationRepository->getCourseById($courseId);
            if (!$course || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['course' => $course]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getCourseDetail: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerCourse(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $data = array_intersect_key($input, array_flip(self::COURSE_FILLABLE));
            $data['church_id'] = $churchId;

            $newId = $this->educationRepository->insertCourse($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                summary:     "교육과정 등록: {$data['name']}",
                targetId:    (string) $newId,
                targetLabel: $data['name'],
                created:     $data,
            );

            return ApiResponse::success(['course_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] registerCourse: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateCourse(int $authMemberId, int $courseId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $course = $this->educationRepository->getCourseById($courseId);
            if (!$course || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            $data = array_intersect_key($input, array_flip(self::COURSE_FILLABLE));
            if (!empty($data)) {
                $this->educationRepository->updateCourse($courseId, $data);
            }

            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::EDUCATION,
                summary:       "교육과정 수정: {$course->name}",
                targetId:      (string) $courseId,
                targetLabel:   $course->name,
                before:        (array) $course,
                after:         array_merge((array) $course, $data),
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['course_id' => $courseId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] updateCourse: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteCourse(int $authMemberId, int $courseId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $course = $this->educationRepository->getCourseById($courseId);
            if (!$course || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            if ($this->educationRepository->countSessionsByCourse($courseId) > 0) {
                return ApiResponse::fail(
                    'HAS_CHILDREN',
                    '기수가 존재하여 삭제할 수 없습니다. 기수를 먼저 삭제해주세요.',
                    400
                );
            }

            $this->educationRepository->deleteCourse($courseId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                summary:     "교육과정 삭제: {$course->name}",
                targetId:    (string) $courseId,
                targetLabel: $course->name,
            );

            return ApiResponse::success(['course_id' => $courseId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] deleteCourse: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '교육 과정 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 교육 기수 CRUD
    // ─────────────────────────────────────────────────────────────

    public function getSessionList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            return ApiResponse::success(
                $this->educationRepository->getSessionList($churchId, $filters)
            );
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getSessionList: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '기수 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getSessionDetail(int $authMemberId, int $sessionId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['session' => $session]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getSessionDetail: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '기수 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerSession(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $course = $this->educationRepository->getCourseById((int) $input['course_id']);
            if (!$course || (int) $course->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교육 과정을 찾을 수 없습니다.', 404);
            }

            $data = array_intersect_key($input, array_flip(self::SESSION_FILLABLE));
            $data['church_id'] = $churchId;
            $data['status']    = $data['status'] ?? 'upcoming';

            $newId = $this->educationRepository->insertSession($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                summary:     "기수 등록: {$course->name} {$data['generation']}",
                targetId:    (string) $newId,
                targetLabel: "{$course->name} {$data['generation']}",
                created:     $data,
            );

            return ApiResponse::success(['session_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] registerSession: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '기수 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateSession(int $authMemberId, int $sessionId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            $updateable = array_diff(self::SESSION_FILLABLE, ['course_id']);
            $data = array_intersect_key($input, array_flip($updateable));

            if (!empty($data)) {
                $this->educationRepository->updateSession($sessionId, $data);
            }

            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::EDUCATION,
                summary:       "기수 수정: {$session->course_name} {$session->generation}",
                targetId:      (string) $sessionId,
                targetLabel:   "{$session->course_name} {$session->generation}",
                before:        (array) $session,
                after:         array_merge((array) $session, $data),
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['session_id' => $sessionId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] updateSession: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '기수 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteSession(int $authMemberId, int $sessionId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            if ($this->educationRepository->countEnrollmentsBySession($sessionId) > 0) {
                return ApiResponse::fail(
                    'HAS_CHILDREN',
                    '수강자가 존재하여 삭제할 수 없습니다. 수강자를 먼저 취소해주세요.',
                    400
                );
            }

            $this->educationRepository->deleteSession($sessionId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                summary:     "기수 삭제: {$session->course_name} {$session->generation}",
                targetId:    (string) $sessionId,
                targetLabel: "{$session->course_name} {$session->generation}",
            );

            return ApiResponse::success(['session_id' => $sessionId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] deleteSession: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '기수 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 수강 기록
    // ─────────────────────────────────────────────────────────────

    public function enroll(int $authMemberId, int $sessionId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            $member = $this->memberRepository->getMemberById($memberId);
            if (!$member || (int) $member->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $existing = $this->educationRepository->getEnrollment($sessionId, $memberId);
            if ($existing) {
                if ($existing->status === 'dropped') {
                    $this->educationRepository->updateEnrollment((int) $existing->id, ['status' => 'ongoing']);
                }
                $enrollmentId = (int) $existing->id;
            } else {
                $enrollmentId = $this->educationRepository->insertEnrollment([
                    'session_id' => $sessionId,
                    'member_id'  => $memberId,
                    'progress'   => 0,
                    'status'     => 'ongoing',
                ]);
            }

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                actionType:  AuditActionType::ENROLL,
                summary:     "수강 등록: {$member->name} → {$session->course_name} {$session->generation}",
                targetId:    (string) $sessionId,
                targetLabel: "{$session->course_name} {$session->generation}",
                detail:      ['member_id' => $memberId, 'enrollment_id' => $enrollmentId],
            );

            return ApiResponse::success([
                'enrollment_id' => $enrollmentId,
                'session_id'    => $sessionId,
                'member_id'     => $memberId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] enroll: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '수강 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateProgress(int $authMemberId, int $sessionId, int $memberId, int $progress, ?string $status): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            $enrollment = $this->educationRepository->getEnrollment($sessionId, $memberId);
            if (!$enrollment) {
                return ApiResponse::fail('NOT_FOUND', '수강 기록을 찾을 수 없습니다.', 404);
            }

            $progress    = max(0, min(100, $progress));
            $finalStatus = $status;
            if ($finalStatus === null && $progress === 100 && $enrollment->status === 'ongoing') {
                $finalStatus = 'completed';
            }

            $this->educationRepository->updateEnrollment((int) $enrollment->id, array_filter([
                'progress' => $progress,
                'status'   => $finalStatus,
            ], fn($v) => $v !== null));

            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::EDUCATION,
                summary:       "수강 진도 갱신: {$session->course_name} {$session->generation} member#{$memberId} → {$progress}%",
                targetId:      (string) $sessionId,
                targetLabel:   "{$session->course_name} {$session->generation}",
                before:        ['progress' => (int) $enrollment->progress, 'status' => $enrollment->status],
                after:         ['progress' => $progress, 'status' => $finalStatus ?? $enrollment->status],
                compareFields: ['progress', 'status'],
            );

            return ApiResponse::success([
                'enrollment_id' => (int) $enrollment->id,
                'session_id'    => $sessionId,
                'member_id'     => $memberId,
                'progress'      => $progress,
                'status'        => $finalStatus ?? $enrollment->status,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] updateProgress: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '진도 갱신 중 오류가 발생했습니다.', 500);
        }
    }

    public function withdraw(int $authMemberId, int $sessionId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            $enrollment = $this->educationRepository->getEnrollment($sessionId, $memberId);
            if (!$enrollment) {
                return ApiResponse::fail('NOT_FOUND', '수강 기록을 찾을 수 없습니다.', 404);
            }

            $this->educationRepository->updateEnrollment((int) $enrollment->id, ['status' => 'dropped']);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                actionType:  AuditActionType::WITHDRAW,
                summary:     "수강 취소: member#{$memberId} ← {$session->course_name} {$session->generation}",
                targetId:    (string) $sessionId,
                targetLabel: "{$session->course_name} {$session->generation}",
                detail:      ['member_id' => $memberId, 'enrollment_id' => (int) $enrollment->id],
            );

            return ApiResponse::success([
                'enrollment_id' => (int) $enrollment->id,
                'session_id'    => $sessionId,
                'member_id'     => $memberId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] withdraw: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '수강 취소 중 오류가 발생했습니다.', 500);
        }
    }

    public function getEnrollmentsBySession(int $authMemberId, int $sessionId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(
                $this->educationRepository->getEnrollmentsBySession($sessionId, $filters)
            );
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getEnrollmentsBySession: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '수강자 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 교육 회차별 출결
    // ─────────────────────────────────────────────────────────────

    public function getAttendanceSheet(int $authMemberId, int $sessionId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success($this->educationRepository->getAttendanceSheet($sessionId));
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getAttendanceSheet: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '출결 시트 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function saveRoundAttendance(int $authMemberId, int $sessionId, int $roundNo, ?string $sessionDate, array $records): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            if ($session->total_rounds && $roundNo > (int) $session->total_rounds) {
                return ApiResponse::fail('VALIDATION_FAILED', "회차 번호가 총 횟수({$session->total_rounds})를 초과합니다.", 400);
            }

            $this->educationRepository->saveRoundAttendance($sessionId, $roundNo, $sessionDate, $records);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EDUCATION,
                actionType:  AuditActionType::BULK_UPDATE,
                summary:     "출결 저장: {$session->course_name} {$session->generation} {$roundNo}회차 (" . count($records) . "명)",
                targetId:    (string) $sessionId,
                targetLabel: "{$session->course_name} {$session->generation}",
                detail:      ['round_no' => $roundNo, 'count' => count($records)],
            );

            return ApiResponse::success(['session_id' => $sessionId, 'round_no' => $roundNo, 'saved' => count($records)]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] saveRoundAttendance: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '출결 저장 중 오류가 발생했습니다.', 500);
        }
    }

    public function getMemberAttendanceStats(int $authMemberId, int $sessionId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $session = $this->educationRepository->getSessionById($sessionId);
            if (!$session || (int) $session->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '기수를 찾을 수 없습니다.', 404);
            }

            $list = $this->educationRepository->getMemberAttendanceStats($sessionId);
            return ApiResponse::success(['session_id' => $sessionId, 'list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getMemberAttendanceStats: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '출석률 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getSessionsByMember(int $authMemberId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if (!$churchId) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $member = $this->memberRepository->getMemberById($memberId);
            if (!$member || (int) $member->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $list = $this->educationRepository->getSessionsByMember($memberId);

            return ApiResponse::success(['member_id' => $memberId, 'list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[EducationService] getSessionsByMember: " . $e->getMessage(), 'education');
            return ApiResponse::fail('INTERNAL_ERROR', '수강 이력 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
