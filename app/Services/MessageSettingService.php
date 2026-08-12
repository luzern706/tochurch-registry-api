<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\SettingRepository;
use Illuminate\Http\JsonResponse;

/**
 * 문자 발송 설정 (`/member/messaging/settings`) — "설정 값"만 실연동한다.
 *
 * 이 프로젝트의 메시지 발송(`MessageService::send`)은 아직 실제 SMS 게이트웨이를
 * 호출하지 않고 `reg_messages`에 발송 기록만 저장한다(CLAUDE.md TODO로 이미 명시됨).
 * 따라서 이 화면의 업체 선택/API키/발신번호/발송정책/수신거부 정책은 전부 "저장만 되고
 * 아직 아무 것도 강제하지 않는" 설정값이다 — reg_church_settings(교직설정·재정설정이
 * 이미 쓰던 범용 키-값 테이블) 재사용, 신규 테이블 없음.
 *
 * "연동 테스트"·"발신번호 인증"·"테스트 문자 발송"·"요금/잔액 조회"는 실제 게이트웨이
 * 호출이 필요해 이번 스코프에서 제외(사용자 확인 완료) — 프론트에서 비활성화 처리.
 */
class MessageSettingService
{
    public const KEYS = [
        'message_sms_vendor',
        'message_sms_api_key',
        'message_sms_api_secret',
        'message_sender_phone',
        'message_sender_name',
        'message_max_batch_size',
        'message_max_daily_count',
        'message_night_restrict_start',
        'message_night_restrict_end',
        'message_unsub_keyword',
        'message_unsub_mode',
    ];

    protected SettingRepository $settingRepository;

    public function __construct(SettingRepository $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }

    public function getSettings(int $authMemberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $rows = $this->settingRepository->getByKeys($churchId, self::KEYS);
            $map = array_fill_keys(self::KEYS, null);
            foreach ($rows as $row) {
                $map[$row->setting_key] = $row->setting_value;
            }

            return ApiResponse::success(['settings' => $map]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageSettingService] getSettings error: " . $e->getMessage(), "message_setting");
            return ApiResponse::fail('INTERNAL_ERROR', '문자 발송 설정 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function saveSettings(int $authMemberId, array $settings): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $allowed = array_intersect_key($settings, array_flip(self::KEYS));
            foreach ($allowed as $key => $value) {
                $this->settingRepository->upsert($churchId, $key, $value);
            }

            // API Key/Secret 원문은 감사 로그에 남기지 않음
            $logged = $allowed;
            foreach (['message_sms_api_key', 'message_sms_api_secret'] as $secretKey) {
                if (array_key_exists($secretKey, $logged) && $logged[$secretKey] !== null) {
                    $logged[$secretKey] = '(저장됨)';
                }
            }

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MESSAGE,
                summary:     "문자 발송 설정 저장: " . implode(', ', array_keys($allowed)),
                targetId:    (string) $churchId,
                targetLabel: '문자 발송 설정',
                created:     $logged,
            );

            return ApiResponse::success(['saved' => count($allowed)]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageSettingService] saveSettings error: " . $e->getMessage(), "message_setting");
            return ApiResponse::fail('INTERNAL_ERROR', '문자 발송 설정 저장 중 오류가 발생했습니다.', 500);
        }
    }
}
