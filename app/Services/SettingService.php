<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\SettingRepository;
use Illuminate\Http\JsonResponse;

class SettingService
{
    protected SettingRepository $settingRepository;

    public function __construct(SettingRepository $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }

    public function getSettings(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $rows = $this->settingRepository->getByKeys($churchId, $input['keys']);

            // key => value 맵으로 변환, 없는 키는 null
            $map = array_fill_keys($input['keys'], null);
            foreach ($rows as $row) {
                $map[$row->setting_key] = $row->setting_value;
            }

            return ApiResponse::success(['settings' => $map]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[SettingService] getSettings error: " . $e->getMessage(), "setting");
            return ApiResponse::fail('INTERNAL_ERROR', '설정 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function saveSettings(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            foreach ($input['settings'] as $key => $value) {
                $this->settingRepository->upsert($churchId, $key, $value);
            }

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::SETTING,
                summary:     "설정 저장: " . implode(', ', array_keys($input['settings'])),
                targetId:    (string) $churchId,
                targetLabel: '교회 설정',
                created:     $input['settings'],
            );

            return ApiResponse::success(['saved' => count($input['settings'])]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[SettingService] saveSettings error: " . $e->getMessage(), "setting");
            return ApiResponse::fail('INTERNAL_ERROR', '설정 저장 중 오류가 발생했습니다.', 500);
        }
    }
}
