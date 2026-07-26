<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\FileUploadConstants;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\GeocodeHelper;
use App\Helpers\LogHelper;
use App\Helpers\RegionMatcher;
use App\Helpers\S3FileHelper;
use App\Repositories\ChurchProfileRepository;
use App\Repositories\SettingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class ChurchProfileService
{
    private const SETTING_RECEIPT_NOTICE     = 'church_receipt_notice';
    private const SETTING_AFFILIATION_CERT   = 'church_affiliation_cert_file_no';
    private const SETTING_LETTERHEAD         = 'church_letterhead_file_no';

    /**
     * slot => [storage('church_column'|'setting'), column|settingKey, label, allowed extensions, s3 prefix]
     *
     * logo/photo는 공유 테이블 gh_church 컬럼(logo_file_no/thumbnail)에 저장 —
     * 관리자 페이지·공개 홈페이지에서도 동일하게 조회 가능해야 하기 때문.
     * affiliation_cert/letterhead는 교적 문서 생성 전용이라 reg_church_settings에 저장.
     */
    /** slot => [storage, column|settingKey, label, allowed extensions, s3 prefix, gh_file.use_for(varchar10)] */
    private const FILE_SLOTS = [
        'logo'             => ['church_column', 'logo_file_no', '교회 로고',    FileUploadConstants::ALLOWED_IMAGE_EXTENSIONS,    'uploads/church_profile/logo',       'reg_logo'],
        'photo'            => ['church_column', 'thumbnail',    '교회 대표 사진', FileUploadConstants::ALLOWED_IMAGE_EXTENSIONS,    'uploads/church_profile/photo',      'reg_photo'],
        'affiliation_cert' => ['setting',       self::SETTING_AFFILIATION_CERT, '소속증명서',    FileUploadConstants::ALLOWED_DOCUMENT_EXTENSIONS, 'uploads/church_profile/cert',       'reg_cert'],
        'letterhead'       => ['setting',       self::SETTING_LETTERHEAD,       '공식 문서 머리글', FileUploadConstants::ALLOWED_DOCUMENT_EXTENSIONS, 'uploads/church_profile/letterhead', 'reg_doc'],
    ];

    protected ChurchProfileRepository $repository;
    protected SettingRepository $settingRepository;

    public function __construct(ChurchProfileRepository $repository, SettingRepository $settingRepository)
    {
        $this->repository = $repository;
        $this->settingRepository = $settingRepository;
    }

    private function getSetting(int $churchNo, string $key): ?string
    {
        $rows = $this->settingRepository->getByKeys($churchNo, [$key]);
        return $rows[0]->setting_value ?? null;
    }

    public function getProfile(int $churchNo): JsonResponse
    {
        try {
            $church = $this->repository->getChurch($churchNo);
            if ($church === null) {
                return ApiResponse::fail('NOT_FOUND', '교회 정보를 찾을 수 없습니다.', 404);
            }

            $location = $this->repository->getLocation($churchNo);
            $headPastor = $this->repository->getHeadPastor($churchNo);

            $settingRows = $this->settingRepository->getByKeys($churchNo, [
                self::SETTING_RECEIPT_NOTICE, self::SETTING_AFFILIATION_CERT, self::SETTING_LETTERHEAD,
            ]);
            $settingMap = [];
            foreach ($settingRows as $row) {
                $settingMap[$row->setting_key] = $row->setting_value;
            }

            $files = [
                'logo'  => $this->resolveFile($church->logo_file_no),
                'photo' => $this->resolveFile($church->thumbnail),
                'affiliation_cert' => $this->resolveFile($settingMap[self::SETTING_AFFILIATION_CERT] ?? null),
                'letterhead'        => $this->resolveFile($settingMap[self::SETTING_LETTERHEAD] ?? null),
            ];

            return ApiResponse::success([
                'church_no'       => (int) $church->church_no,
                'name'            => $church->name,
                'group_no'        => $church->group_no !== null ? (int) $church->group_no : null,
                'phone'           => $church->phone,
                'phone2'          => $church->phone2,
                'email'           => $church->email,
                'homepage'        => $church->homepage,
                'youtube_url'     => $church->youtube_url,
                'tax_no'          => $church->taxNo,
                'church_create_date' => $church->church_create_date,
                'address'         => $location->address ?? null,
                'address_detail'  => $location->address_detail ?? null,
                'postcode'        => $location->postcode ?? null,
                'receipt_notice'  => $settingMap[self::SETTING_RECEIPT_NOTICE] ?? null,
                'head_pastor'     => $headPastor ? ['pastor_no' => (int) $headPastor->pastor_no, 'name' => $headPastor->name] : null,
                'files'           => $files,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ChurchProfileService] getProfile error: " . $e->getMessage(), "church_profile");
            return ApiResponse::fail('INTERNAL_ERROR', '교회 정보 조회 중 오류가 발생했습니다.', 500);
        }
    }

    private function resolveFile($fileNo): ?array
    {
        if (!$fileNo) {
            return null;
        }
        $file = $this->repository->getFileByNo((int) $fileNo);
        if (!$file || $file->status !== 'ALIVE') {
            return null;
        }
        return ['file_no' => (int) $file->file_no, 'url' => $file->web_path, 'file_name' => $file->file_name];
    }

    public function updateProfile(array $data, int $adminNo, int $churchNo): JsonResponse
    {
        try {
            $before = $this->repository->getChurch($churchNo);
            if ($before === null) {
                return ApiResponse::fail('NOT_FOUND', '교회 정보를 찾을 수 없습니다.', 404);
            }

            if (array_key_exists('head_pastor_no', $data) && $data['head_pastor_no'] !== null) {
                $pastor = $this->repository->getPastorByNo((int) $data['head_pastor_no']);
                if ($pastor === null || (int) $pastor->church_no !== $churchNo) {
                    return ApiResponse::fail('VALIDATION_FAILED', '유효하지 않은 목회자입니다.', 400);
                }
            }

            $churchFields = [];
            foreach (['name', 'group_no', 'phone', 'phone2', 'email', 'homepage', 'youtube_url', 'church_create_date'] as $f) {
                if (array_key_exists($f, $data)) {
                    $churchFields[$f] = $data[$f];
                }
            }
            if (array_key_exists('tax_no', $data)) {
                $churchFields['taxNo'] = $data['tax_no'];
            }

            $hasAddress = array_key_exists('address', $data) && trim((string) $data['address']) !== '';
            $hasAddressDetail = array_key_exists('address_detail', $data);
            $hasPostcode = array_key_exists('postcode', $data);
            $hasHeadPastor = array_key_exists('head_pastor_no', $data);
            $hasReceiptNotice = array_key_exists('receipt_notice', $data);

            if (empty($churchFields) && !$hasAddress && !$hasHeadPastor && !$hasReceiptNotice) {
                return ApiResponse::fail('VALIDATION_FAILED', '수정할 항목이 없습니다.', 400);
            }

            if (!empty($churchFields)) {
                $this->repository->updateChurch($churchNo, $churchFields);
            }
            if ($hasAddress) {
                // 프론트가 매 저장마다 address를 무조건 같이 보내므로(변경 여부와 무관), 여기서도
                // 항상 재계산하면 주소를 안 바꾼 저장(전화번호 수정 등)마다 카카오 API를 부르고,
                // sido_name 등이 안 넘어온 저장에서는 RegionMatcher가 전부 NULL을 반환해 기존
                // sido_no/sigungu_no/dong_no까지 지워버린다. 주소 검색 모달을 실제로 써야만
                // sido_name/sigungu_name/dong_name이 함께 오므로, 그때만 재계산한다.
                $addressChanged = array_key_exists('sido_name', $data)
                    || array_key_exists('sigungu_name', $data)
                    || array_key_exists('dong_name', $data);

                $region = ['sido_no' => null, 'sigungu_no' => null, 'dong_no' => null];
                $coords = ['latitude' => null, 'longitude' => null];
                if ($addressChanged) {
                    $region = RegionMatcher::match(
                        $data['sido_name'] ?? null,
                        $data['sigungu_name'] ?? null,
                        $data['dong_name'] ?? null,
                    );
                    $coords = GeocodeHelper::getCoordinatesFromAddress(trim($data['address']));
                }

                $this->repository->upsertLocation(
                    $churchNo,
                    trim($data['address']),
                    $hasAddressDetail ? $data['address_detail'] : null,
                    $hasPostcode ? $data['postcode'] : null,
                    $addressChanged ? [
                        'sido_no'    => $region['sido_no'],
                        'sigungu_no' => $region['sigungu_no'],
                        'dong_no'    => $region['dong_no'],
                        'latitude'   => $coords['latitude'],
                        'longitude'  => $coords['longitude'],
                    ] : [],
                );
            }
            if ($hasHeadPastor) {
                $this->repository->setHeadPastor($churchNo, $data['head_pastor_no'] !== null ? (int) $data['head_pastor_no'] : null);
            }
            if ($hasReceiptNotice) {
                $this->settingRepository->upsert($churchNo, self::SETTING_RECEIPT_NOTICE, $data['receipt_notice']);
            }

            AuditLogHelper::logUpdate(
                memberId:      $adminNo,
                churchId:      $churchNo,
                menuCode:      AuditMenuCode::CHURCH_PROFILE,
                summary:       "교회 기본정보 수정: {$before->name}",
                targetId:      (string) $churchNo,
                targetLabel:   $before->name,
                before:        (array) $before,
                after:         array_merge((array) $before, $churchFields),
                compareFields: array_keys($churchFields),
            );

            return ApiResponse::success(['church_no' => $churchNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ChurchProfileService] updateProfile error: " . $e->getMessage(), "church_profile");
            return ApiResponse::fail('INTERNAL_ERROR', '교회 정보 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function getPastorOptions(int $churchNo): JsonResponse
    {
        try {
            $rows = $this->repository->getPastorsByChurch($churchNo);
            $list = array_map(fn ($r) => [
                'pastor_no'  => (int) $r->pastor_no,
                'name'       => $r->name,
                'type_value' => $r->type_value,
                'is_head'    => (bool) $r->is_head,
            ], $rows);
            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ChurchProfileService] getPastorOptions error: " . $e->getMessage(), "church_profile");
            return ApiResponse::fail('INTERNAL_ERROR', '목회자 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getDenominationOptions(): JsonResponse
    {
        try {
            $rows = $this->repository->getChurchGroups();
            $list = array_map(fn ($r) => ['group_no' => (int) $r->group_no, 'value' => $r->value], $rows);
            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ChurchProfileService] getDenominationOptions error: " . $e->getMessage(), "church_profile");
            return ApiResponse::fail('INTERNAL_ERROR', '교단 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function uploadFile(string $slot, UploadedFile $file, int $churchNo, int $adminNo): JsonResponse
    {
        if (!isset(self::FILE_SLOTS[$slot])) {
            return ApiResponse::fail('VALIDATION_FAILED', '알 수 없는 파일 종류입니다.', 400);
        }
        [$storage, $target, $label, $allowedExt, $s3Prefix, $useFor] = self::FILE_SLOTS[$slot];

        $err = S3FileHelper::validate($file, $allowedExt, $label);
        if ($err !== null) {
            return ApiResponse::fail('INVALID_FILE_TYPE', $err, 400);
        }

        try {
            $oldFileNo = $this->getCurrentFileNo($churchNo, $storage, $target);

            $upload = S3FileHelper::upload($file, $s3Prefix, "church_{$churchNo}_{$slot}_" . time());

            $newFileNo = $this->repository->insertFile([
                'file_name'     => basename($upload['s3_key']),
                'file_ext'      => $upload['ext'],
                'uploader_type' => 'A',
                'uploader_no'   => $adminNo,
                'use_for'       => $useFor,
                'web_path'      => $upload['s3_url'],
                'file_size'     => intval(round($upload['file_size'] / 1024)),
            ]);

            S3FileHelper::cleanupLocalTemp($upload['local_path']);

            if (!$newFileNo) {
                S3FileHelper::deleteFromS3($upload['s3_key']);
                return ApiResponse::fail('INTERNAL_ERROR', '파일 저장에 실패했습니다.', 500);
            }

            $this->setCurrentFileNo($churchNo, $storage, $target, $newFileNo);

            if ($oldFileNo) {
                $oldFile = $this->repository->getFileByNo((int) $oldFileNo);
                if ($oldFile) {
                    $this->repository->markFileDeleted((int) $oldFileNo);
                    $oldKey = S3FileHelper::extractS3KeyFromUrl($oldFile->web_path);
                    if ($oldKey) {
                        S3FileHelper::deleteFromS3($oldKey);
                    }
                }
            }

            AuditLogHelper::logUpdate(
                memberId:    $adminNo,
                churchId:    $churchNo,
                menuCode:    AuditMenuCode::CHURCH_PROFILE,
                summary:     "교회 {$label} 업로드",
                targetId:    (string) $churchNo,
                targetLabel: $label,
                compareFields: [$slot],
            );

            $newFile = $this->repository->getFileByNo($newFileNo);
            return ApiResponse::success([
                'slot' => $slot,
                'file' => ['file_no' => $newFileNo, 'url' => $newFile->web_path, 'file_name' => $newFile->file_name],
            ]);
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('UPLOAD_FAILED', '파일 업로드에 실패했습니다.', 500);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ChurchProfileService] uploadFile error: " . $e->getMessage(), "church_profile");
            return ApiResponse::fail('INTERNAL_ERROR', '파일 업로드 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteFile(string $slot, int $churchNo, int $adminNo): JsonResponse
    {
        if (!isset(self::FILE_SLOTS[$slot])) {
            return ApiResponse::fail('VALIDATION_FAILED', '알 수 없는 파일 종류입니다.', 400);
        }
        [$storage, $target, $label] = self::FILE_SLOTS[$slot];

        try {
            $fileNo = $this->getCurrentFileNo($churchNo, $storage, $target);
            if (!$fileNo) {
                return ApiResponse::fail('NOT_FOUND', '삭제할 파일이 없습니다.', 404);
            }

            $file = $this->repository->getFileByNo((int) $fileNo);
            $this->setCurrentFileNo($churchNo, $storage, $target, null);
            $this->repository->markFileDeleted((int) $fileNo);

            if ($file) {
                $key = S3FileHelper::extractS3KeyFromUrl($file->web_path);
                if ($key) {
                    S3FileHelper::deleteFromS3($key);
                }
            }

            AuditLogHelper::logDelete(
                memberId:    $adminNo,
                churchId:    $churchNo,
                menuCode:    AuditMenuCode::CHURCH_PROFILE,
                summary:     "교회 {$label} 삭제",
                targetId:    (string) $churchNo,
                targetLabel: $label,
            );

            return ApiResponse::success(['slot' => $slot]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ChurchProfileService] deleteFile error: " . $e->getMessage(), "church_profile");
            return ApiResponse::fail('INTERNAL_ERROR', '파일 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    private function getCurrentFileNo(int $churchNo, string $storage, string $target): ?int
    {
        if ($storage === 'church_column') {
            $church = $this->repository->getChurch($churchNo);
            $val = $church?->{$target};
            return $val ? (int) $val : null;
        }
        $val = $this->getSetting($churchNo, $target);
        return $val ? (int) $val : null;
    }

    private function setCurrentFileNo(int $churchNo, string $storage, string $target, ?int $fileNo): void
    {
        if ($storage === 'church_column') {
            $this->repository->updateChurch($churchNo, [$target => $fileNo]);
            return;
        }
        $this->settingRepository->upsert($churchNo, $target, $fileNo !== null ? (string) $fileNo : null);
    }
}
