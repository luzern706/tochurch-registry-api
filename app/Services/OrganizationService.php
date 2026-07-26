<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use App\Repositories\OrganizationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    protected OrganizationRepository $organizationRepository;
    protected MemberRepository $memberRepository;

    public function __construct(
        OrganizationRepository $organizationRepository,
        MemberRepository $memberRepository
    ) {
        $this->organizationRepository = $organizationRepository;
        $this->memberRepository       = $memberRepository;
    }

    public function getOrganizationList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $list = $this->organizationRepository->getOrganizationList($churchId, $filters);
            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] getOrganizationList error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 사이드바 그룹 목록용 조직 트리 (최상위 → 하위, 소속 교인 수 포함)
     * jwt.auth만 요구 — 전 역할(pastor/minister/volunteer 포함)이 사이드바를 보므로
     * permission:SETTING 게이트를 걸지 않는다(v4/profile/* 와 동일한 이유).
     */
    public function getSidebarTree(): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $flat = $this->organizationRepository->getSidebarTree($churchId);

            $byParent = [];
            foreach ($flat as $row) {
                $byParent[$row->parent_id ?? 0][] = $row;
            }

            $build = function (int $parentId) use (&$build, $byParent) {
                $nodes = [];
                foreach ($byParent[$parentId] ?? [] as $row) {
                    $children     = $build((int) $row->id);
                    $memberCount  = (int) $row->member_count;
                    $childrenSum  = array_sum(array_column($children, 'member_count'));

                    $nodes[] = [
                        'id'            => (int) $row->id,
                        'name'          => $row->name,
                        'sort_order'    => (int) $row->sort_order,
                        'member_count'  => $memberCount + $childrenSum,
                        'children'      => $children,
                    ];
                }
                return $nodes;
            };

            return ApiResponse::success(['list' => $build(0)]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] getSidebarTree error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 트리 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getOrganizationDetail(int $authMemberId, int $organizationId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $org = $this->organizationRepository->getOrganizationById($organizationId);
            if ($org === null || (int) $org->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '조직 정보를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['organization' => $org]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] getOrganizationDetail error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerOrganization(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $parentId = isset($input['parent_id']) ? (int) $input['parent_id'] : null;
            if ($parentId !== null) {
                $parent = $this->organizationRepository->getOrganizationById($parentId);
                if ($parent === null || (int) $parent->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '상위 조직을 찾을 수 없습니다.', 404);
                }
            }

            $newId = $this->organizationRepository->insertOrganization([
                'church_id'   => $churchId,
                'parent_id'   => $parentId,
                'name'        => $input['name'],
                'description' => $input['description'] ?? null,
                'sort_order'  => $input['sort_order'] ?? 0,
                'is_active'   => 1,
            ]);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ORGANIZATION,
                summary:     "조직 등록: {$input['name']}",
                targetId:    (string) $newId,
                targetLabel: $input['name'],
                created:     ['name' => $input['name'], 'parent_id' => $parentId],
            );

            return ApiResponse::success(['organization_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] registerOrganization error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateOrganization(int $authMemberId, int $organizationId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $org = $this->organizationRepository->getOrganizationById($organizationId);
            if ($org === null || (int) $org->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '조직 정보를 찾을 수 없습니다.', 404);
            }

            $data = [];
            if (array_key_exists('name', $input)) {
                $data['name'] = $input['name'];
            }
            if (array_key_exists('description', $input)) {
                $data['description'] = $input['description'];
            }
            if (array_key_exists('sort_order', $input)) {
                $data['sort_order'] = (int) $input['sort_order'];
            }
            if (array_key_exists('is_active', $input)) {
                $data['is_active'] = (int) (bool) $input['is_active'];
            }
            if (array_key_exists('parent_id', $input)) {
                $newParentId = $input['parent_id'] === null ? null : (int) $input['parent_id'];

                if ($newParentId === $organizationId) {
                    return ApiResponse::fail('VALIDATION_FAILED', '자기 자신을 상위 조직으로 지정할 수 없습니다.', 400);
                }

                if ($newParentId !== null) {
                    $parent = $this->organizationRepository->getOrganizationById($newParentId);
                    if ($parent === null || (int) $parent->church_id !== $churchId) {
                        return ApiResponse::fail('NOT_FOUND', '상위 조직을 찾을 수 없습니다.', 404);
                    }

                    if ($this->isDescendantOf($newParentId, $organizationId)) {
                        return ApiResponse::fail('VALIDATION_FAILED', '하위 조직을 상위 조직으로 지정할 수 없습니다.', 400);
                    }
                }

                $data['parent_id'] = $newParentId;
            }

            if (!empty($data)) {
                $this->organizationRepository->updateOrganization($organizationId, $data);
            }

            $beforeArr = (array) $org;
            $afterArr  = array_merge($beforeArr, $data);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::ORGANIZATION,
                summary:       "조직 수정: {$org->name}",
                targetId:      (string) $organizationId,
                targetLabel:   $org->name,
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['organization_id' => $organizationId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] updateOrganization error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteOrganization(int $authMemberId, int $organizationId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $org = $this->organizationRepository->getOrganizationById($organizationId);
            if ($org === null || (int) $org->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '조직 정보를 찾을 수 없습니다.', 404);
            }

            if ($this->organizationRepository->hasChildren($organizationId)) {
                return ApiResponse::fail('HAS_CHILDREN', '하위 조직이 있어 삭제할 수 없습니다. 먼저 하위 조직을 정리해주세요.', 400);
            }

            DB::transaction(function () use ($organizationId) {
                $this->organizationRepository->deleteMappingsByOrganization($organizationId);
                $this->organizationRepository->deactivateOrganization($organizationId);
            });

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ORGANIZATION,
                summary:     "조직 삭제(soft, 매핑 정리): {$org->name}",
                targetId:    (string) $organizationId,
                targetLabel: $org->name,
            );

            return ApiResponse::success(['organization_id' => $organizationId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] deleteOrganization error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    public function assignMember(int $authMemberId, int $memberId, int $organizationId, array $input): JsonResponse
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

            $org = $this->organizationRepository->getOrganizationById($organizationId);
            if ($org === null || (int) $org->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '조직 정보를 찾을 수 없습니다.', 404);
            }

            $isPrimary = (int) (bool) ($input['is_primary'] ?? 0);
            $joinedAt  = $input['joined_at'] ?? null;

            $existing = $this->organizationRepository->getMapping($memberId, $organizationId);

            DB::transaction(function () use ($existing, $memberId, $organizationId, $isPrimary, $joinedAt) {
                if ($existing) {
                    $this->organizationRepository->updateMapping((int) $existing->id, [
                        'is_primary' => $isPrimary,
                        'joined_at'  => $joinedAt,
                    ]);
                    $mappingId = (int) $existing->id;
                } else {
                    $mappingId = $this->organizationRepository->insertMapping([
                        'member_id'       => $memberId,
                        'organization_id' => $organizationId,
                        'is_primary'      => $isPrimary,
                        'joined_at'       => $joinedAt,
                    ]);
                }

                if ($isPrimary === 1) {
                    $this->organizationRepository->resetPrimaryForMember($memberId, $mappingId);
                }
            });

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ORGANIZATION,
                actionType:  AuditActionType::ASSIGN,
                summary:     "조직 배정: {$member->name} → {$org->name}" . ($isPrimary === 1 ? ' (주소속)' : ''),
                targetId:    (string) $organizationId,
                targetLabel: $org->name,
                detail:      ['member_id' => $memberId, 'is_primary' => $isPrimary, 'joined_at' => $joinedAt],
            );

            return ApiResponse::success([
                'member_id'       => $memberId,
                'organization_id' => $organizationId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] assignMember error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 배정 중 오류가 발생했습니다.', 500);
        }
    }

    public function unassignMember(int $authMemberId, int $memberId, int $organizationId): JsonResponse
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

            $org = $this->organizationRepository->getOrganizationById($organizationId);
            if ($org === null || (int) $org->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '조직 정보를 찾을 수 없습니다.', 404);
            }

            $affected = $this->organizationRepository->deleteMapping($memberId, $organizationId);
            if ($affected === 0) {
                return ApiResponse::fail('NOT_FOUND', '해당 매핑을 찾을 수 없습니다.', 404);
            }

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ORGANIZATION,
                actionType:  AuditActionType::UNASSIGN,
                summary:     "조직 매핑 해제: {$member->name} ↛ {$org->name}",
                targetId:    (string) $organizationId,
                targetLabel: $org->name,
                detail:      ['member_id' => $memberId],
            );

            return ApiResponse::success([
                'member_id'       => $memberId,
                'organization_id' => $organizationId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] unassignMember error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 매핑 해제 중 오류가 발생했습니다.', 500);
        }
    }

    public function getMembersByOrganization(int $authMemberId, int $organizationId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $org = $this->organizationRepository->getOrganizationById($organizationId);
            if ($org === null || (int) $org->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '조직 정보를 찾을 수 없습니다.', 404);
            }

            $data = $this->organizationRepository->getMembersByOrganization($organizationId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[OrganizationService] getMembersByOrganization error: " . $e->getMessage(), "organization");
            return ApiResponse::fail('INTERNAL_ERROR', '조직 교인 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * candidateAncestorId 가 startId 의 조상에 해당하는지 검사 (순환참조 방지용)
     * = "candidateAncestorId 의 조상 사슬에 startId 가 들어있는가?"
     */
    private function isDescendantOf(int $candidateAncestorId, int $startId): bool
    {
        $cursor = $candidateAncestorId;
        $guard  = 0;

        while ($cursor !== null && $guard < 50) {
            $org = $this->organizationRepository->getOrganizationById($cursor);
            if ($org === null) {
                return false;
            }
            if ((int) $org->id === $startId) {
                return true;
            }
            $cursor = $org->parent_id !== null ? (int) $org->parent_id : null;
            $guard++;
        }

        return false;
    }
}
