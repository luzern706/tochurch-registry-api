<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\ProfileRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    protected ProfileRepository $profileRepository;

    public function __construct(ProfileRepository $profileRepository)
    {
        $this->profileRepository = $profileRepository;
    }

    public function getMyProfile(int $adminNo): JsonResponse
    {
        try {
            $profile = $this->profileRepository->getMyProfile($adminNo);
            if ($profile === null) {
                return ApiResponse::fail('NOT_FOUND', '계정 정보를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success($profile);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ProfileService] getMyProfile error: " . $e->getMessage(), "profile");
            return ApiResponse::fail('INTERNAL_ERROR', '내 정보 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateMyProfile(array $data, int $adminNo): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest() ?? 0;

            $before = $this->profileRepository->getMyProfile($adminNo);
            if ($before === null) {
                return ApiResponse::fail('NOT_FOUND', '계정 정보를 찾을 수 없습니다.', 404);
            }

            $updateFields  = [];
            $compareFields = [];
            foreach (['name', 'email', 'phone'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateFields[$field] = $data[$field];
                    $compareFields[]      = $field;
                }
            }

            if (empty($updateFields)) {
                return ApiResponse::fail('VALIDATION_FAILED', '수정할 항목이 없습니다.', 400);
            }

            $this->profileRepository->updateMyProfile($adminNo, $updateFields);

            AuditLogHelper::logUpdate(
                memberId:      $adminNo,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::PROFILE,
                summary:       "내 정보 수정: {$before->name}",
                targetId:      (string) $adminNo,
                targetLabel:   $before->name,
                before:        (array) $before,
                after:         array_merge((array) $before, $updateFields),
                compareFields: $compareFields,
            );

            return ApiResponse::success(['admin_no' => $adminNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ProfileService] updateMyProfile error: " . $e->getMessage(), "profile");
            return ApiResponse::fail('INTERNAL_ERROR', '내 정보 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function changePassword(array $data, int $adminNo): JsonResponse
    {
        try {
            $churchId    = JwtHelper::getChurchIdFromRequest() ?? 0;
            $currentHash = $this->profileRepository->findPasswordHashByNo($adminNo);

            if ($currentHash === null) {
                return ApiResponse::fail('NOT_FOUND', '계정 정보를 찾을 수 없습니다.', 404);
            }

            if (!Hash::check($data['current_password'], $currentHash)) {
                return ApiResponse::fail('INVALID_CREDENTIALS', '현재 비밀번호가 올바르지 않습니다.', 400);
            }

            $this->profileRepository->updatePassword($adminNo, Hash::make($data['new_password']));

            AuditLogHelper::logAction(
                memberId:   $adminNo,
                churchId:   $churchId,
                menuCode:   AuditMenuCode::PROFILE,
                actionType: AuditActionType::UPDATE,
                summary:    '비밀번호 변경',
                targetId:   (string) $adminNo,
            );

            return ApiResponse::success(['admin_no' => $adminNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ProfileService] changePassword error: " . $e->getMessage(), "profile");
            return ApiResponse::fail('INTERNAL_ERROR', '비밀번호 변경 중 오류가 발생했습니다.', 500);
        }
    }
}
