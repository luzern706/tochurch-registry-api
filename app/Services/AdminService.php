<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\AdminRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AdminService
{
    protected AdminRepository $adminRepository;

    public function __construct(AdminRepository $adminRepository)
    {
        $this->adminRepository = $adminRepository;
    }

    public function getAdminList(array $filters, int $authAdminNo): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest() ?? 0;
            $page     = (int) ($filters['page'] ?? 1);
            $size     = (int) ($filters['size'] ?? 20);

            $data = $this->adminRepository->getAdminList($churchId, $filters, $page, $size);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AdminService] getAdminList error: " . $e->getMessage(), "admin");
            return ApiResponse::fail('INTERNAL_ERROR', '목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getAdminDetail(array $params, int $authAdminNo): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest() ?? 0;
            $admin    = $this->adminRepository->getAdminByNo($params['admin_no'], $churchId);

            if ($admin === null) {
                return ApiResponse::fail('NOT_FOUND', '관리자를 찾을 수 없습니다.', 404);
            }
            return ApiResponse::success($admin);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AdminService] getAdminDetail error: " . $e->getMessage(), "admin");
            return ApiResponse::fail('INTERNAL_ERROR', '상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerAdmin(array $data, int $authAdminNo): JsonResponse
    {
        try {
            $churchId        = JwtHelper::getChurchIdFromRequest() ?? 0;
            $data['church_id'] = $churchId;

            if ($this->adminRepository->findByLoginId($data['login_id']) !== null) {
                return ApiResponse::fail('DUPLICATE_DATA', '이미 사용 중인 아이디입니다.', 400);
            }

            $data['password'] = Hash::make($data['password']);
            $newAdminNo       = $this->adminRepository->createAdmin($data);

            AuditLogHelper::logCreate(
                memberId:    $authAdminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ADMIN,
                summary:     "관리자 등록: {$data['name']} ({$data['login_id']}, {$data['admin_type']})",
                targetId:    (string) $newAdminNo,
                targetLabel: $data['name'],
                created:     ['login_id' => $data['login_id'], 'name' => $data['name'], 'admin_type' => $data['admin_type']],
            );

            return ApiResponse::success(['admin_no' => $newAdminNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AdminService] registerAdmin error: " . $e->getMessage(), "admin");
            return ApiResponse::fail('INTERNAL_ERROR', '관리자 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateAdmin(array $data, int $authAdminNo): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest() ?? 0;
            $adminNo  = $data['admin_no'];

            $before = $this->adminRepository->getAdminByNo($adminNo, $churchId);
            if ($before === null) {
                return ApiResponse::fail('NOT_FOUND', '관리자를 찾을 수 없습니다.', 404);
            }

            $updateFields  = [];
            $compareFields = [];

            if (isset($data['name'])) {
                $updateFields['name'] = $data['name'];
                $compareFields[]      = 'name';
            }
            if (!empty($data['password'])) {
                $updateFields['password'] = Hash::make($data['password']);
            }
            if (isset($data['admin_type'])) {
                $updateFields['admin_type'] = $data['admin_type'];
                $compareFields[]            = 'admin_type';
            }

            if (empty($updateFields)) {
                return ApiResponse::fail('VALIDATION_FAILED', '수정할 항목이 없습니다.', 400);
            }

            $this->adminRepository->updateAdmin($adminNo, $updateFields);

            AuditLogHelper::logUpdate(
                memberId:      $authAdminNo,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::ADMIN,
                summary:       "관리자 수정: {$before->id}",
                targetId:      (string) $adminNo,
                targetLabel:   $before->id,
                before:        (array) $before,
                after:         array_merge((array) $before, $updateFields),
                compareFields: $compareFields,
            );

            return ApiResponse::success(['admin_no' => $adminNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AdminService] updateAdmin error: " . $e->getMessage(), "admin");
            return ApiResponse::fail('INTERNAL_ERROR', '관리자 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function suspendAdmin(array $data, int $authAdminNo): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest() ?? 0;
            $adminNo  = $data['admin_no'];

            if ($adminNo === $authAdminNo) {
                return ApiResponse::fail('INSUFFICIENT_PERMISSION', '자기 자신을 비활성화할 수 없습니다.', 403);
            }

            $admin = $this->adminRepository->getAdminByNoAnyStatus($adminNo, $churchId);
            if ($admin === null) {
                return ApiResponse::fail('NOT_FOUND', '관리자를 찾을 수 없습니다.', 404);
            }
            if ($admin->status === 'INACTIVE') {
                return ApiResponse::fail('DUPLICATE_DATA', '이미 비활성 상태인 계정입니다.', 400);
            }

            $this->adminRepository->suspendAdmin($adminNo);

            AuditLogHelper::logAction(
                memberId:    $authAdminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ADMIN,
                actionType:  AuditActionType::SUSPEND,
                summary:     "관리자 비활성화: {$admin->id} (이전 상태: {$admin->status})",
                targetId:    (string) $adminNo,
                targetLabel: $admin->id,
            );

            return ApiResponse::success(['admin_no' => $adminNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AdminService] suspendAdmin error: " . $e->getMessage(), "admin");
            return ApiResponse::fail('INTERNAL_ERROR', '관리자 비활성화 중 오류가 발생했습니다.', 500);
        }
    }

    public function activateAdmin(array $data, int $authAdminNo): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest() ?? 0;
            $adminNo  = $data['admin_no'];

            $admin = $this->adminRepository->getAdminByNoAnyStatus($adminNo, $churchId);
            if ($admin === null) {
                return ApiResponse::fail('NOT_FOUND', '관리자를 찾을 수 없습니다.', 404);
            }
            if ($admin->status === 'ALIVE') {
                return ApiResponse::fail('DUPLICATE_DATA', '이미 활성 상태인 계정입니다.', 400);
            }

            $this->adminRepository->activateAdmin($adminNo);

            AuditLogHelper::logAction(
                memberId:    $authAdminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ADMIN,
                actionType:  AuditActionType::ACTIVATE,
                summary:     "관리자 활성화: {$admin->id} (이전 상태: {$admin->status})",
                targetId:    (string) $adminNo,
                targetLabel: $admin->id,
            );

            return ApiResponse::success(['admin_no' => $adminNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AdminService] activateAdmin error: " . $e->getMessage(), "admin");
            return ApiResponse::fail('INTERNAL_ERROR', '관리자 활성화 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteAdmin(array $data, int $authAdminNo): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest() ?? 0;
            $adminNo  = $data['admin_no'];

            if ($adminNo === $authAdminNo) {
                return ApiResponse::fail('INSUFFICIENT_PERMISSION', '자기 자신을 삭제할 수 없습니다.', 403);
            }

            $admin = $this->adminRepository->getAdminByNo($adminNo, $churchId);
            if ($admin === null) {
                return ApiResponse::fail('NOT_FOUND', '관리자를 찾을 수 없습니다.', 404);
            }

            $this->adminRepository->deleteAdmin($adminNo);

            AuditLogHelper::logDelete(
                memberId:    $authAdminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ADMIN,
                summary:     "관리자 삭제(소프트): {$admin->id}",
                targetId:    (string) $adminNo,
                targetLabel: $admin->id,
                deleted:     (array) $admin,
            );

            return ApiResponse::success(['admin_no' => $adminNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AdminService] deleteAdmin error: " . $e->getMessage(), "admin");
            return ApiResponse::fail('INTERNAL_ERROR', '관리자 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    public function purgeAdmin(array $data, int $authAdminNo): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest() ?? 0;
            $adminNo  = $data['admin_no'];

            if ($adminNo === $authAdminNo) {
                return ApiResponse::fail('INSUFFICIENT_PERMISSION', '자기 자신을 삭제할 수 없습니다.', 403);
            }

            $admin = $this->adminRepository->getDeletedAdminByNo($adminNo, $churchId);
            if ($admin === null) {
                return ApiResponse::fail('NOT_FOUND', '삭제 상태인 관리자를 찾을 수 없습니다.', 404);
            }

            $this->adminRepository->purgeAdmin($adminNo);

            AuditLogHelper::logDelete(
                memberId:    $authAdminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ADMIN,
                summary:     "관리자 영구삭제: {$admin->id}",
                targetId:    (string) $adminNo,
                targetLabel: $admin->id,
                deleted:     (array) $admin,
            );

            return ApiResponse::success(['admin_no' => $adminNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AdminService] purgeAdmin error: " . $e->getMessage(), "admin");
            return ApiResponse::fail('INTERNAL_ERROR', '관리자 영구삭제 중 오류가 발생했습니다.', 500);
        }
    }

}
