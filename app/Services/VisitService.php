<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use App\Repositories\VisitRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VisitService
{
    private const FILLABLE = [
        'member_id', 'visit_type', 'event_reason', 'visit_date',
        'visitor_member_id', 'manager_member_id', 'place', 'companion',
        'attendee_count', 'content', 'common_note', 'private_note',
        'add_to_prayer',
    ];

    protected VisitRepository $visitRepository;
    protected MemberRepository $memberRepository;

    public function __construct(
        VisitRepository $visitRepository,
        MemberRepository $memberRepository
    ) {
        $this->visitRepository  = $visitRepository;
        $this->memberRepository = $memberRepository;
    }

    public function getVisitList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->visitRepository->getVisitList($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VisitService] getVisitList error: " . $e->getMessage(), "visit");
            return ApiResponse::fail('INTERNAL_ERROR', '심방 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getVisitDetail(int $authMemberId, int $visitId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $visit = $this->visitRepository->getVisitById($visitId);
            if ($visit === null || (int) $visit->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '심방 기록을 찾을 수 없습니다.', 404);
            }

            // TODO(권한): private_note 는 추후 권한 체계 도입 시 본인/담당자만 노출하도록 변경
            return ApiResponse::success(['visit' => $visit]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VisitService] getVisitDetail error: " . $e->getMessage(), "visit");
            return ApiResponse::fail('INTERNAL_ERROR', '심방 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerVisit(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $invalid = $this->validateMembersBelongToChurch($churchId, [
                'member_id'         => (int) $input['member_id'],
                'visitor_member_id' => (int) $input['visitor_member_id'],
                'manager_member_id' => isset($input['manager_member_id']) ? (int) $input['manager_member_id'] : null,
            ]);
            if ($invalid !== null) {
                return $invalid;
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            $data['church_id']     = $churchId;
            $data['created_by']    = $authMemberId;
            $data['add_to_prayer'] = (int) (bool) ($data['add_to_prayer'] ?? 0);

            $newId = DB::transaction(function () use ($data, $authMemberId) {
                $visitId = $this->visitRepository->insertVisit($data);

                if ($data['add_to_prayer'] === 1) {
                    $this->visitRepository->insertPrayerRecord([
                        'member_id'   => $data['member_id'],
                        'church_id'   => $data['church_id'],
                        'content'     => $data['content'],
                        'is_resolved' => 0,
                        'created_by'  => $authMemberId,
                    ]);
                }

                return $visitId;
            });

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::VISIT,
                summary:     "심방 등록: 대상 member#{$data['member_id']} ({$data['visit_date']})" . ($data['add_to_prayer'] === 1 ? ' [기도 자동연동]' : ''),
                targetId:    (string) $newId,
                targetLabel: null,
                created:     ['member_id' => $data['member_id'], 'visit_date' => $data['visit_date'], 'visitor_member_id' => $data['visitor_member_id'], 'add_to_prayer' => $data['add_to_prayer']],
            );

            return ApiResponse::success(['visit_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VisitService] registerVisit error: " . $e->getMessage(), "visit");
            return ApiResponse::fail('INTERNAL_ERROR', '심방 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateVisit(int $authMemberId, int $visitId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $visit = $this->visitRepository->getVisitById($visitId);
            if ($visit === null || (int) $visit->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '심방 기록을 찾을 수 없습니다.', 404);
            }

            $membersToCheck = [];
            if (array_key_exists('member_id', $input)) {
                $membersToCheck['member_id'] = (int) $input['member_id'];
            }
            if (array_key_exists('visitor_member_id', $input)) {
                $membersToCheck['visitor_member_id'] = (int) $input['visitor_member_id'];
            }
            if (array_key_exists('manager_member_id', $input)) {
                $membersToCheck['manager_member_id'] = $input['manager_member_id'] !== null
                    ? (int) $input['manager_member_id']
                    : null;
            }
            if (!empty($membersToCheck)) {
                $invalid = $this->validateMembersBelongToChurch($churchId, $membersToCheck);
                if ($invalid !== null) {
                    return $invalid;
                }
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));

            $wasInPrayer = (int) $visit->add_to_prayer === 1;
            $becomesPrayer = array_key_exists('add_to_prayer', $data)
                ? (int) (bool) $data['add_to_prayer'] === 1
                : $wasInPrayer;

            if (array_key_exists('add_to_prayer', $data)) {
                $data['add_to_prayer'] = (int) (bool) $data['add_to_prayer'];
            }

            DB::transaction(function () use ($visitId, $visit, $data, $wasInPrayer, $becomesPrayer, $authMemberId) {
                if (!empty($data)) {
                    $this->visitRepository->updateVisit($visitId, $data);
                }

                // add_to_prayer 0→1 전환 시에만 기도 기록 신규 추가
                // (1→0 변경 시 기존 기도 record 는 유지 — 독립적 라이프사이클)
                if (!$wasInPrayer && $becomesPrayer) {
                    $this->visitRepository->insertPrayerRecord([
                        'member_id'   => $data['member_id']  ?? (int) $visit->member_id,
                        'church_id'   => (int) $visit->church_id,
                        'content'     => $data['content']    ?? $visit->content,
                        'is_resolved' => 0,
                        'created_by'  => $authMemberId,
                    ]);
                }
            });

            $beforeArr = (array) $visit;
            $afterArr  = array_merge($beforeArr, $data);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::VISIT,
                summary:       "심방 수정: visit#{$visitId}" . (!$wasInPrayer && $becomesPrayer ? ' [기도 신규연동]' : ''),
                targetId:      (string) $visitId,
                targetLabel:   null,
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['visit_id' => $visitId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VisitService] updateVisit error: " . $e->getMessage(), "visit");
            return ApiResponse::fail('INTERNAL_ERROR', '심방 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteVisit(int $authMemberId, int $visitId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $visit = $this->visitRepository->getVisitById($visitId);
            if ($visit === null || (int) $visit->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '심방 기록을 찾을 수 없습니다.', 404);
            }

            // 연결된 reg_prayer_records 는 유지 (독립적 라이프사이클)
            $this->visitRepository->deleteVisit($visitId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::VISIT,
                summary:     "심방 삭제(hard): visit#{$visitId} (대상 member#{$visit->member_id})",
                targetId:    (string) $visitId,
                targetLabel: null,
                deleted:     ['member_id' => (int) $visit->member_id, 'visit_date' => $visit->visit_date],
            );

            return ApiResponse::success(['visit_id' => $visitId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[VisitService] deleteVisit error: " . $e->getMessage(), "visit");
            return ApiResponse::fail('INTERNAL_ERROR', '심방 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 지정된 모든 교인 ID가 동일 교회에 속해 있는지 검증.
     * 한 명이라도 다른 교회/존재하지 않으면 fail 응답 반환, 모두 OK 면 null.
     */
    private function validateMembersBelongToChurch(int $churchId, array $memberIds): ?JsonResponse
    {
        foreach ($memberIds as $label => $id) {
            if ($id === null) {
                continue;
            }
            $m = $this->memberRepository->getMemberById($id);
            if ($m === null || (int) $m->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', "[$label] 교인 정보를 찾을 수 없습니다.", 404);
            }
        }
        return null;
    }
}
