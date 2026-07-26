<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use App\Repositories\VolunteerRepository;
use Illuminate\Http\JsonResponse;

class VolunteerService
{
    private const TEAM_FILLABLE = [
        'name', 'description', 'service_type', 'mode', 'frequency',
        'day_of_week', 'service_time', 'event_date', 'start_time', 'end_time',
        'required_count', 'manager_member_id', 'participation_type', 'is_active',
    ];

    private const MAPPING_FILLABLE = [
        'role', 'joined_at', 'status',
    ];

    protected VolunteerRepository $volunteerRepository;
    protected MemberRepository $memberRepository;

    public function __construct(
        VolunteerRepository $volunteerRepository,
        MemberRepository $memberRepository
    ) {
        $this->volunteerRepository = $volunteerRepository;
        $this->memberRepository    = $memberRepository;
    }

    // ─────────────── 봉사 팀 ───────────────

    public function getTeamList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->volunteerRepository->getTeamList($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] getTeamList error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사 팀 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getTeamDetail(int $authMemberId, int $teamId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['team' => $team]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] getTeamDetail error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사 팀 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerTeam(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            if (!empty($input['manager_member_id'])) {
                $manager = $this->memberRepository->getMemberById((int) $input['manager_member_id']);
                if ($manager === null || (int) $manager->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '담당자 교인을 찾을 수 없습니다.', 404);
                }
            }

            $data = array_intersect_key($input, array_flip(self::TEAM_FILLABLE));
            $data['church_id'] = $churchId;
            $data['is_active'] = $data['is_active'] ?? 1;

            $newId = $this->volunteerRepository->insertTeam($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::VOLUNTEER,
                summary:     "봉사 팀 등록: {$data['name']}",
                targetId:    (string) $newId,
                targetLabel: $data['name'],
                created:     ['name' => $data['name'], 'mode' => $data['mode'] ?? null, 'service_type' => $data['service_type'] ?? null],
            );

            return ApiResponse::success(['team_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] registerTeam error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사 팀 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateTeam(int $authMemberId, int $teamId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            if (array_key_exists('manager_member_id', $input) && $input['manager_member_id'] !== null) {
                $manager = $this->memberRepository->getMemberById((int) $input['manager_member_id']);
                if ($manager === null || (int) $manager->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '담당자 교인을 찾을 수 없습니다.', 404);
                }
            }

            $data = array_intersect_key($input, array_flip(self::TEAM_FILLABLE));
            if (!empty($data)) {
                $this->volunteerRepository->updateTeam($teamId, $data);
            }

            $beforeArr = (array) $team;
            $afterArr  = array_merge($beforeArr, $data);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::VOLUNTEER,
                summary:       "봉사 팀 수정: {$team->name}",
                targetId:      (string) $teamId,
                targetLabel:   $team->name,
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['team_id' => $teamId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] updateTeam error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사 팀 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteTeam(int $authMemberId, int $teamId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            $this->volunteerRepository->deactivateTeam($teamId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::VOLUNTEER,
                summary:     "봉사 팀 삭제(soft): {$team->name}",
                targetId:    (string) $teamId,
                targetLabel: $team->name,
            );

            return ApiResponse::success(['team_id' => $teamId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] deleteTeam error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사 팀 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    // ─────────────── 참여자 매핑 ───────────────

    public function assignVolunteer(int $authMemberId, int $teamId, int $memberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            $member = $this->memberRepository->getMemberById($memberId);
            if ($member === null || (int) $member->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $data = array_intersect_key($input, array_flip(self::MAPPING_FILLABLE));
            $data['status'] = $data['status'] ?? 'active';

            $existing = $this->volunteerRepository->getMappingByTeamAndMember($teamId, $memberId);
            if ($existing) {
                $this->volunteerRepository->updateMapping((int) $existing->id, $data);
                $mappingId = (int) $existing->id;
            } else {
                $data['service_team_id'] = $teamId;
                $data['member_id']       = $memberId;
                $mappingId = $this->volunteerRepository->insertMapping($data);
            }

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::VOLUNTEER,
                actionType:  AuditActionType::ASSIGN,
                summary:     "봉사자 배정: {$member->name} → {$team->name}" . (!empty($data['role']) ? " ({$data['role']})" : ''),
                targetId:    (string) $teamId,
                targetLabel: $team->name,
                detail:      ['member_id' => $memberId, 'role' => $data['role'] ?? null, 'status' => $data['status']],
            );

            return ApiResponse::success([
                'mapping_id' => $mappingId,
                'team_id'    => $teamId,
                'member_id'  => $memberId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] assignVolunteer error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사자 배정 중 오류가 발생했습니다.', 500);
        }
    }

    public function unassignVolunteer(int $authMemberId, int $teamId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            $mapping = $this->volunteerRepository->getMappingByTeamAndMember($teamId, $memberId);
            if ($mapping === null) {
                return ApiResponse::fail('NOT_FOUND', '봉사자 매핑을 찾을 수 없습니다.', 404);
            }

            // soft 해제 (이력 보존)
            $this->volunteerRepository->updateMapping((int) $mapping->id, ['status' => 'inactive']);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::VOLUNTEER,
                actionType:  AuditActionType::UNASSIGN,
                summary:     "봉사자 해제(soft): member#{$memberId} ↛ {$team->name}",
                targetId:    (string) $teamId,
                targetLabel: $team->name,
                detail:      ['member_id' => $memberId, 'mapping_id' => (int) $mapping->id],
            );

            return ApiResponse::success([
                'mapping_id' => (int) $mapping->id,
                'team_id'    => $teamId,
                'member_id'  => $memberId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] unassignVolunteer error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사자 해제 중 오류가 발생했습니다.', 500);
        }
    }

    public function getVolunteersByTeam(int $authMemberId, int $teamId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            $data = $this->volunteerRepository->getVolunteersByTeam($teamId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] getVolunteersByTeam error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사자 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getTeamsByMember(int $authMemberId, int $memberId): JsonResponse
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

            $list = $this->volunteerRepository->getTeamsByMember($memberId);

            return ApiResponse::success([
                'member_id' => $memberId,
                'list'      => $list,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] getTeamsByMember error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '교인 봉사 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /** 교회 전체 봉사 이력 (교인/팀 미지정 시 전체 조회, 검색으로 좁힘) */
    public function getHistory(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->volunteerRepository->getHistory($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] getHistory error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사 이력 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /** 봉사 팀별 이력 요약 (봉사이력 화면 "봉사 중심" 탭) */
    public function getTeamHistory(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->volunteerRepository->getTeamHistory($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] getTeamHistory error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '봉사 팀 이력 조회 중 오류가 발생했습니다.', 500);
        }
    }

    // ─────────────── 봉사 출결 ───────────────

    public function getAttendanceSheet(int $authMemberId, int $teamId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success($this->volunteerRepository->getAttendanceSheet($teamId));
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] getAttendanceSheet error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '출결 시트 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function saveAttendance(int $authMemberId, int $teamId, string $serviceDate, array $records): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            $this->volunteerRepository->saveAttendance($teamId, $serviceDate, $records);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::VOLUNTEER,
                actionType:  AuditActionType::BULK_UPDATE,
                summary:     "봉사 출결 저장: {$team->name} {$serviceDate} (" . count($records) . "명)",
                targetId:    (string) $teamId,
                targetLabel: $team->name,
                detail:      ['service_date' => $serviceDate, 'count' => count($records)],
            );

            return ApiResponse::success([
                'team_id'      => $teamId,
                'service_date' => $serviceDate,
                'saved'        => count($records),
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] saveAttendance error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '출결 저장 중 오류가 발생했습니다.', 500);
        }
    }

    public function getMemberAttendanceStats(int $authMemberId, int $teamId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $team = $this->volunteerRepository->getTeamById($teamId);
            if ($team === null || (int) $team->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '봉사 팀 정보를 찾을 수 없습니다.', 404);
            }

            $list = $this->volunteerRepository->getMemberAttendanceStats($teamId);
            return ApiResponse::success(['team_id' => $teamId, 'list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VolunteerService] getMemberAttendanceStats error: " . $e->getMessage(), "volunteer");
            return ApiResponse::fail('INTERNAL_ERROR', '출석 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
