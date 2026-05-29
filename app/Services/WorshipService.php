<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\WorshipRepository;
use Illuminate\Http\JsonResponse;

class WorshipService
{
    private const FILLABLE = [
        'name', 'day_of_week', 'target_org_id', 'sort_order', 'is_active',
    ];

    protected WorshipRepository $worshipRepository;
    protected MemberRepository $memberRepository;
    protected OrganizationRepository $organizationRepository;

    public function __construct(
        WorshipRepository $worshipRepository,
        MemberRepository $memberRepository,
        OrganizationRepository $organizationRepository
    ) {
        $this->worshipRepository      = $worshipRepository;
        $this->memberRepository       = $memberRepository;
        $this->organizationRepository = $organizationRepository;
    }

    public function getWorshipList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $list = $this->worshipRepository->getWorshipList($churchId, $filters);
            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[WorshipService] getWorshipList error: " . $e->getMessage(), "worship");
            return ApiResponse::fail('INTERNAL_ERROR', '예배 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getWorshipDetail(int $authMemberId, int $serviceId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $worship = $this->worshipRepository->getWorshipById($serviceId);
            if ($worship === null || (int) $worship->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '예배 정보를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['worship' => $worship]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[WorshipService] getWorshipDetail error: " . $e->getMessage(), "worship");
            return ApiResponse::fail('INTERNAL_ERROR', '예배 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerWorship(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            if (!empty($input['target_org_id'])) {
                $org = $this->organizationRepository->getOrganizationById((int) $input['target_org_id']);
                if ($org === null || (int) $org->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '대상 조직을 찾을 수 없습니다.', 404);
                }
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            $data['church_id'] = $churchId;
            $data['is_active'] = $data['is_active'] ?? 1;

            $newId = $this->worshipRepository->insertWorship($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::WORSHIP,
                summary:     "예배 등록: {$input['name']}",
                targetId:    (string) $newId,
                targetLabel: $input['name'],
                created:     $data,
            );

            return ApiResponse::success(['service_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[WorshipService] registerWorship error: " . $e->getMessage(), "worship");
            return ApiResponse::fail('INTERNAL_ERROR', '예배 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateWorship(int $authMemberId, int $serviceId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $worship = $this->worshipRepository->getWorshipById($serviceId);
            if ($worship === null || (int) $worship->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '예배 정보를 찾을 수 없습니다.', 404);
            }

            if (array_key_exists('target_org_id', $input) && $input['target_org_id'] !== null) {
                $org = $this->organizationRepository->getOrganizationById((int) $input['target_org_id']);
                if ($org === null || (int) $org->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '대상 조직을 찾을 수 없습니다.', 404);
                }
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            if (!empty($data)) {
                $this->worshipRepository->updateWorship($serviceId, $data);
            }

            $beforeArr = (array) $worship;
            $afterArr  = array_merge($beforeArr, $data);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::WORSHIP,
                summary:       "예배 수정: {$worship->name}",
                targetId:      (string) $serviceId,
                targetLabel:   $worship->name,
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['service_id' => $serviceId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[WorshipService] updateWorship error: " . $e->getMessage(), "worship");
            return ApiResponse::fail('INTERNAL_ERROR', '예배 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteWorship(int $authMemberId, int $serviceId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $worship = $this->worshipRepository->getWorshipById($serviceId);
            if ($worship === null || (int) $worship->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '예배 정보를 찾을 수 없습니다.', 404);
            }

            $this->worshipRepository->deactivateWorship($serviceId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::WORSHIP,
                summary:     "예배 삭제(soft): {$worship->name}",
                targetId:    (string) $serviceId,
                targetLabel: $worship->name,
            );

            return ApiResponse::success(['service_id' => $serviceId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[WorshipService] deleteWorship error: " . $e->getMessage(), "worship");
            return ApiResponse::fail('INTERNAL_ERROR', '예배 삭제 중 오류가 발생했습니다.', 500);
        }
    }
}
