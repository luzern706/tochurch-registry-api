<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\FileUploadConstants;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Helpers\S3FileHelper;
use App\Repositories\SettingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * 재정 > 설정 (`/finance/settings`) — "정책 값"만 실연동한다.
 *
 * 원본 mock의 7개 섹션 중 계정과목(재정구조) CRUD·초기이월금 원장·결산 실행·
 * 마감 실행/이력·기부금영수증 발행 실행/이력은 별도 테이블·워크플로 설계가
 * 필요한 큰 기능이라 이번 스코프에서 제외(사용자 확인 완료) — 화면에서 비활성화
 * 처리. 여기서는 토글/드롭다운/텍스트 입력형 "정책" 필드만 reg_church_settings
 * (교직설정이 이미 쓰던 범용 키-값 테이블)에 저장한다. 신규 테이블 없음.
 */
class FinanceSettingService
{
    /** 프론트에서 관리하는 재정 설정 키 전체 목록 — getSettings 응답을 이 목록으로 고정 */
    public const KEYS = [
        // 회계기수/결산 > 회계 정책
        'finance_fiscal_year_start_month',
        'finance_accounting_method',
        'finance_auto_carry_forward',
        // 기부금영수증 설정 > 기본 설정
        'finance_receipt_country',
        'finance_receipt_issue_year',
        'finance_receipt_serial_start',
        // 기부금영수증 설정 > 발행자 정보
        'finance_receipt_org_id_number',
        'finance_receipt_ceo_name',
        'finance_receipt_org_name',
        'finance_receipt_address',
        'finance_receipt_seal_url',
        // 기부금영수증 설정 > 발행 정책
        'finance_receipt_auto_issue',
        'finance_receipt_hometax_submit',
        'finance_receipt_allow_individual_request',
        'finance_receipt_batch_issue_date',
        'finance_receipt_min_amount',
        // 마감관리 > 마감 정책
        'finance_closing_monthly_auto',
        'finance_closing_year_end_auto',
        'finance_closing_lock_after_close',
        'finance_closing_alert_timing',
        'finance_closing_next_date',
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
            LogHelper::logWrite("[FinanceSettingService] getSettings error: " . $e->getMessage(), "finance_setting");
            return ApiResponse::fail('INTERNAL_ERROR', '재정 설정 조회 중 오류가 발생했습니다.', 500);
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

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::FINANCE_SETTING,
                summary:     "재정 설정 저장: " . implode(', ', array_keys($allowed)),
                targetId:    (string) $churchId,
                targetLabel: '재정 설정',
                created:     $allowed,
            );

            return ApiResponse::success(['saved' => count($allowed)]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceSettingService] saveSettings error: " . $e->getMessage(), "finance_setting");
            return ApiResponse::fail('INTERNAL_ERROR', '재정 설정 저장 중 오류가 발생했습니다.', 500);
        }
    }

    public function uploadSeal(UploadedFile $file, int $churchId): JsonResponse
    {
        $err = S3FileHelper::validate($file, FileUploadConstants::ALLOWED_IMAGE_EXTENSIONS, '직인 이미지');
        if ($err !== null) {
            return ApiResponse::fail('INVALID_FILE_TYPE', $err, 400);
        }

        try {
            $upload = S3FileHelper::upload($file, 'uploads/finance_seal', "church_{$churchId}_seal_" . time() . '_' . uniqid());
            S3FileHelper::cleanupLocalTemp($upload['local_path']);
            return ApiResponse::success(['url' => $upload['s3_url']]);
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('UPLOAD_FAILED', '직인 이미지 업로드에 실패했습니다.', 500);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FinanceSettingService] uploadSeal error: " . $e->getMessage(), "finance_setting");
            return ApiResponse::fail('INTERNAL_ERROR', '직인 이미지 업로드 중 오류가 발생했습니다.', 500);
        }
    }
}
