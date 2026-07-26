<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\PermissionDefaults;
use App\Constants\PermissionMenuTree;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\LogHelper;
use App\Repositories\PermissionRepository;
use Illuminate\Http\JsonResponse;

class PermissionService
{
    private const ROLE_LABELS = [
        'admin'     => '관리자',
        'pastor'    => '담임목회자',
        'minister'  => '사역자',
        'volunteer' => '봉사자',
    ];

    /** admin_type='admin'은 항상 전체 권한 — DB에 저장하지 않고 하드코딩 바이패스 */
    private const CUSTOMIZABLE_ROLES = ['pastor', 'minister', 'volunteer'];

    protected PermissionRepository $repository;

    public function __construct(PermissionRepository $repository)
    {
        $this->repository = $repository;
    }

    /** 소메뉴(페이지) 코드의 접근 여부 — 오버라이드가 없으면 소속 중메뉴의 기본 정책 사용 */
    private function resolvePageAccess(string $role, string $pageCode, array $overrides): bool
    {
        if (array_key_exists($pageCode, $overrides)) {
            return $overrides[$pageCode];
        }
        $parentMenu = PermissionMenuTree::parentOf($pageCode) ?? $pageCode;
        return PermissionDefaults::forRole($role, $parentMenu);
    }

    public function getMatrix(int $churchId): JsonResponse
    {
        try {
            $tree     = PermissionMenuTree::tree();
            $allPages = PermissionMenuTree::allPages();
            $rows     = $this->repository->getByChurch($churchId);
            $userCounts = $this->repository->getRoleUserCounts($churchId);

            // role|page_code => bool
            $overridesByRole = [];
            foreach ($rows as $row) {
                $overridesByRole[$row->role][$row->page_code] = (bool) $row->can_access;
            }

            $matrix = [];
            foreach (array_keys(self::ROLE_LABELS) as $role) {
                $matrix[$role] = [];
                foreach ($allPages as $pageCode) {
                    if ($role === 'admin') {
                        $matrix[$role][$pageCode] = true;
                        continue;
                    }
                    $matrix[$role][$pageCode] = $this->resolvePageAccess($role, $pageCode, $overridesByRole[$role] ?? []);
                }
            }

            $roles = [];
            foreach (self::ROLE_LABELS as $key => $label) {
                $roles[] = ['key' => $key, 'label' => $label, 'user_count' => (int) ($userCounts[$key] ?? 0)];
            }

            return ApiResponse::success(['roles' => $roles, 'groups' => $tree, 'matrix' => $matrix]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[PermissionService] getMatrix error: " . $e->getMessage(), "permission");
            return ApiResponse::fail('INTERNAL_ERROR', '권한 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /** 로그인한 본인의 역할 기준 권한만 반환 — 프론트 메뉴 필터링/페이지 가드용 (super.auth 불필요) */
    public function getMyPermissions(int $churchId, string $role): JsonResponse
    {
        try {
            if (!array_key_exists($role, self::ROLE_LABELS)) {
                return ApiResponse::fail('VALIDATION_FAILED', "알 수 없는 역할: {$role}", 400);
            }

            $allPages = PermissionMenuTree::allPages();

            if ($role === 'admin') {
                $permissions = array_fill_keys($allPages, true);
            } else {
                $overrides = $this->repository->getOverridesForPages($churchId, $role, $allPages);
                $permissions = [];
                foreach ($allPages as $pageCode) {
                    $permissions[$pageCode] = $this->resolvePageAccess($role, $pageCode, $overrides);
                }
            }

            return ApiResponse::success(['role' => $role, 'permissions' => $permissions]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[PermissionService] getMyPermissions error: " . $e->getMessage(), "permission");
            return ApiResponse::fail('INTERNAL_ERROR', '권한 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 중메뉴(API 라우트 그룹) 단위 접근 여부 — CheckPermissionMiddleware 전용.
     * 소속 소메뉴 중 하나라도 접근 가능하면 그 메뉴에 걸린 API 전체를 허용한다.
     */
    public function hasMenuAccess(int $churchId, string $role, string $menuCode): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if (!array_key_exists($role, self::ROLE_LABELS)) {
            return false;
        }

        $pageCodes = PermissionMenuTree::pagesOfMenu($menuCode);
        if (empty($pageCodes)) {
            return false;
        }

        $overrides = $this->repository->getOverridesForPages($churchId, $role, $pageCodes);
        foreach ($pageCodes as $pageCode) {
            if ($this->resolvePageAccess($role, $pageCode, $overrides)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int, array{page_code:string, can_access:bool}> $permissions */
    public function saveRole(int $churchId, int $adminNo, string $role, array $permissions): JsonResponse
    {
        try {
            if (!in_array($role, self::CUSTOMIZABLE_ROLES, true)) {
                return ApiResponse::fail('VALIDATION_FAILED', "'{$role}' 역할의 권한은 변경할 수 없습니다.", 400);
            }

            $allowedPages = PermissionMenuTree::allPages();
            $unknown = array_diff(array_column($permissions, 'page_code'), $allowedPages);
            if (!empty($unknown)) {
                return ApiResponse::fail('VALIDATION_FAILED', '허용되지 않은 화면: ' . implode(',', $unknown), 400);
            }

            $before = $this->repository->getByRole($churchId, $role);

            $this->repository->upsertMany($churchId, $role, $permissions);

            AuditLogHelper::logUpdate(
                memberId:      $adminNo,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::SETTING,
                summary:       "권한 설정 변경: " . (self::ROLE_LABELS[$role] ?? $role),
                targetId:      $role,
                targetLabel:   self::ROLE_LABELS[$role] ?? $role,
                before:        ['permissions' => $before],
                after:         ['permissions' => $permissions],
                compareFields: ['permissions'],
            );

            return ApiResponse::success(['role' => $role]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[PermissionService] saveRole error: " . $e->getMessage(), "permission");
            return ApiResponse::fail('INTERNAL_ERROR', '권한 저장 중 오류가 발생했습니다.', 500);
        }
    }
}
