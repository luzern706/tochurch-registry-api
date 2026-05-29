<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use App\Repositories\OfferingRepository;
use Illuminate\Http\JsonResponse;

class OfferingService
{
    private const FILLABLE = [
        'member_id', 'offer_date', 'category', 'amount',
        'method', 'ledger',
    ];

    protected OfferingRepository $offeringRepository;
    protected MemberRepository $memberRepository;

    public function __construct(
        OfferingRepository $offeringRepository,
        MemberRepository $memberRepository
    ) {
        $this->offeringRepository = $offeringRepository;
        $this->memberRepository   = $memberRepository;
    }

    // TODO(권한): 헌금은 민감 정보. 추후 재무팀/담당자 권한 도입 시 조회/수정/삭제 제한 강화.

    public function getOfferingList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->offeringRepository->getOfferingList($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OfferingService] getOfferingList error: " . $e->getMessage(), "offering");
            return ApiResponse::fail('INTERNAL_ERROR', '헌금 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getOfferingDetail(int $authMemberId, int $offeringId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $offering = $this->offeringRepository->getOfferingById($offeringId);
            if ($offering === null || (int) $offering->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '헌금 기록을 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['offering' => $offering]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OfferingService] getOfferingDetail error: " . $e->getMessage(), "offering");
            return ApiResponse::fail('INTERNAL_ERROR', '헌금 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerOffering(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            if (!empty($input['member_id'])) {
                $member = $this->memberRepository->getMemberById((int) $input['member_id']);
                if ($member === null || (int) $member->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
                }
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            $data['church_id']   = $churchId;
            $data['recorded_by'] = $authMemberId;

            $newId = $this->offeringRepository->insertOffering($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::OFFERING,
                summary:     "헌금 등록: {$data['category']} {$data['amount']}원" . (isset($data['member_id']) ? " (member#{$data['member_id']})" : ' (익명)'),
                targetId:    (string) $newId,
                targetLabel: $data['category'],
                created:     ['category' => $data['category'], 'amount' => $data['amount'], 'method' => $data['method'] ?? null, 'member_id' => $data['member_id'] ?? null],
            );

            return ApiResponse::success(['offering_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OfferingService] registerOffering error: " . $e->getMessage(), "offering");
            return ApiResponse::fail('INTERNAL_ERROR', '헌금 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateOffering(int $authMemberId, int $offeringId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $offering = $this->offeringRepository->getOfferingById($offeringId);
            if ($offering === null || (int) $offering->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '헌금 기록을 찾을 수 없습니다.', 404);
            }

            if (array_key_exists('member_id', $input) && $input['member_id'] !== null) {
                $member = $this->memberRepository->getMemberById((int) $input['member_id']);
                if ($member === null || (int) $member->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
                }
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            if (!empty($data)) {
                $this->offeringRepository->updateOffering($offeringId, $data);
            }

            $beforeArr = (array) $offering;
            $afterArr  = array_merge($beforeArr, $data);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::OFFERING,
                summary:       "헌금 수정: offering#{$offeringId} ({$offering->category})",
                targetId:      (string) $offeringId,
                targetLabel:   $offering->category,
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['offering_id' => $offeringId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OfferingService] updateOffering error: " . $e->getMessage(), "offering");
            return ApiResponse::fail('INTERNAL_ERROR', '헌금 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteOffering(int $authMemberId, int $offeringId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $offering = $this->offeringRepository->getOfferingById($offeringId);
            if ($offering === null || (int) $offering->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '헌금 기록을 찾을 수 없습니다.', 404);
            }

            $this->offeringRepository->deleteOffering($offeringId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::OFFERING,
                summary:     "헌금 삭제(hard): offering#{$offeringId} ({$offering->category} {$offering->amount}원)",
                targetId:    (string) $offeringId,
                targetLabel: $offering->category,
                deleted:     ['category' => $offering->category, 'amount' => (int) $offering->amount, 'offer_date' => $offering->offer_date],
            );

            return ApiResponse::success(['offering_id' => $offeringId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OfferingService] deleteOffering error: " . $e->getMessage(), "offering");
            return ApiResponse::fail('INTERNAL_ERROR', '헌금 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    public function getListByMember(int $authMemberId, int $memberId, array $filters): JsonResponse
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

            $data = $this->offeringRepository->getOfferingsByMember($memberId, $filters);
            $data['member_id'] = $memberId;
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OfferingService] getListByMember error: " . $e->getMessage(), "offering");
            return ApiResponse::fail('INTERNAL_ERROR', '교인 헌금 이력 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
