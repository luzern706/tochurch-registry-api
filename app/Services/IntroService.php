<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\FileUploadConstants;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Helpers\S3FileHelper;
use App\Repositories\IntroRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * 관리 > 교회 페이지 "교회소개" — gh_church_intro/gh_church_pastor(공유 테이블) 재사용.
 * 주소/연락처/이메일/홈페이지/창립년도는 이 화면이 아니라 ChurchProfileService(설정 > 기본정보)가
 * 관리하는 동일한 gh_church/gh_church_location 필드를 프론트에서 그대로 재조회·재저장한다.
 */
class IntroService
{
    private const JSON_FIELDS = ['hero_buttons', 'bible_quote', 'directions'];

    /** Quill 리치 텍스트 에디터가 출력하는 필드 — "비어있음" 상태가 빈 문자열이 아니라 <p><br></p> 등이라
     *  02_gh_admin_api와 동일하게 저장 전에 null로 정규화(빈 태그가 실데이터처럼 남지 않도록). */
    private const HTML_FIELDS = ['vision', 'message'];
    private const HTML_JSON_SUBFIELDS = [
        'bible_quote' => ['manual_text'],
        'directions'  => ['location_detail', 'transit_subway', 'transit_car', 'parking_info'],
    ];

    protected IntroRepository $repository;

    public function __construct(IntroRepository $repository)
    {
        $this->repository = $repository;
    }

    private function churchId(): ?int
    {
        return JwtHelper::getChurchIdFromRequest();
    }

    public function getIntro(): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $intro = $this->repository->getIntro($churchId);
            $introData = [
                'slogan'            => $intro->slogan ?? null,
                'vision'            => $intro->vision ?? null,
                'message'           => $intro->message ?? null,
                'hero_title'        => $intro->hero_title ?? null,
                'hero_subtitle'     => $intro->hero_subtitle ?? null,
                'hero_buttons'      => $this->decodeJson($intro->hero_buttons ?? null, []),
                'intro_title'       => $intro->intro_title ?? null,
                'welcome_title'     => $intro->welcome_title ?? null,
                'welcome_highlight' => $intro->welcome_highlight ?? null,
                'bible_quote'       => $this->decodeJson($intro->bible_quote ?? null, null),
                'directions'        => $this->decodeJson($intro->directions ?? null, []),
            ];

            $pastorTypes = $this->repository->getPastorTypes();
            $typeMap = [];
            foreach ($pastorTypes as $t) { $typeMap[$t->type_no] = $t->value; }

            $headPastor = $this->repository->getHeadPastor($churchId);
            $pastorData = null;
            if ($headPastor) {
                $photo = $headPastor->thumbnail ? $this->repository->getFileByNo((int) $headPastor->thumbnail) : null;
                $pastorData = [
                    'pastor_no'  => (int) $headPastor->pastor_no,
                    'name'       => $headPastor->name,
                    'type_no'    => $headPastor->type_no !== null ? (int) $headPastor->type_no : null,
                    'type_value' => $typeMap[$headPastor->type_no] ?? null,
                    'career'     => $this->decodeJson($headPastor->career ?? null, []),
                    'photo'      => ($photo && $photo->status === 'ALIVE') ? ['file_no' => (int) $photo->file_no, 'url' => $photo->web_path] : null,
                ];
            }

            return ApiResponse::success([
                'intro'        => $introData,
                'pastor'       => $pastorData,
                'pastor_types' => array_map(fn ($t) => ['type_no' => (int) $t->type_no, 'value' => $t->value], $pastorTypes),
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[IntroService] getIntro error: " . $e->getMessage(), "intro");
            return ApiResponse::fail('INTERNAL_ERROR', '교회소개 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateIntro(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $before = $this->repository->getIntro($churchId);
            $data = $input;

            foreach (self::HTML_FIELDS as $f) {
                if (array_key_exists($f, $data)) {
                    $data[$f] = $this->normalizeQuillEmpty($data[$f]);
                }
            }
            foreach (self::HTML_JSON_SUBFIELDS as $jsonField => $subKeys) {
                if (!array_key_exists($jsonField, $data) || !is_array($data[$jsonField])) continue;
                foreach ($subKeys as $sub) {
                    if (array_key_exists($sub, $data[$jsonField])) {
                        $data[$jsonField][$sub] = $this->normalizeQuillEmpty($data[$jsonField][$sub]);
                    }
                }
            }

            foreach (self::JSON_FIELDS as $f) {
                if (array_key_exists($f, $data) && $data[$f] !== null) {
                    $data[$f] = json_encode($data[$f], JSON_UNESCAPED_UNICODE);
                }
            }
            if (empty($data)) {
                return ApiResponse::fail('VALIDATION_FAILED', '수정할 항목이 없습니다.', 400);
            }

            $this->repository->upsertIntro($churchId, $data);

            AuditLogHelper::logUpdate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "교회소개 수정", targetId: (string) $churchId, targetLabel: '교회소개',
                before: (array) $before, after: array_merge((array) $before, $data), compareFields: array_keys($data),
            );

            return ApiResponse::success(['church_no' => $churchId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[IntroService] updateIntro error: " . $e->getMessage(), "intro");
            return ApiResponse::fail('INTERNAL_ERROR', '교회소개 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function updatePastor(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $pastor = $this->repository->getHeadPastor($churchId);
            if ($pastor === null) {
                return ApiResponse::fail('NOT_FOUND', '담임목사가 지정되어 있지 않습니다. 설정 > 교회 프로필에서 먼저 지정해주세요.', 404);
            }

            $data = $input;
            if (array_key_exists('career', $data) && $data['career'] !== null) {
                $data['career'] = json_encode($data['career'], JSON_UNESCAPED_UNICODE);
            }
            if (empty($data)) {
                return ApiResponse::fail('VALIDATION_FAILED', '수정할 항목이 없습니다.', 400);
            }

            $this->repository->updatePastor((int) $pastor->pastor_no, $data);

            AuditLogHelper::logUpdate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "교회소개 목회자 정보 수정: {$pastor->name}", targetId: (string) $pastor->pastor_no, targetLabel: $pastor->name,
                before: (array) $pastor, after: array_merge((array) $pastor, $data), compareFields: array_keys($data),
            );

            return ApiResponse::success(['pastor_no' => (int) $pastor->pastor_no]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[IntroService] updatePastor error: " . $e->getMessage(), "intro");
            return ApiResponse::fail('INTERNAL_ERROR', '목회자 정보 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function uploadPastorPhoto(int $authMemberId, UploadedFile $file): JsonResponse
    {
        $err = S3FileHelper::validate($file, FileUploadConstants::ALLOWED_IMAGE_EXTENSIONS, '목회자 사진');
        if ($err !== null) {
            return ApiResponse::fail('INVALID_FILE_TYPE', $err, 400);
        }

        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $pastor = $this->repository->getHeadPastor($churchId);
            if ($pastor === null) {
                return ApiResponse::fail('NOT_FOUND', '담임목사가 지정되어 있지 않습니다. 설정 > 교회 프로필에서 먼저 지정해주세요.', 404);
            }

            $oldFileNo = $pastor->thumbnail;

            $upload = S3FileHelper::upload($file, 'uploads/church_profile/pastor', "church_{$churchId}_pastor_{$pastor->pastor_no}_" . time());
            $newFileNo = $this->repository->insertFile([
                'file_name'     => basename($upload['s3_key']),
                'file_ext'      => $upload['ext'],
                'uploader_type' => 'A',
                'uploader_no'   => $authMemberId,
                'use_for'       => 'reg_pastor',
                'web_path'      => $upload['s3_url'],
                'file_size'     => intval(round($upload['file_size'] / 1024)),
            ]);
            S3FileHelper::cleanupLocalTemp($upload['local_path']);

            if (!$newFileNo) {
                S3FileHelper::deleteFromS3($upload['s3_key']);
                return ApiResponse::fail('INTERNAL_ERROR', '사진 저장에 실패했습니다.', 500);
            }

            $this->repository->updatePastor((int) $pastor->pastor_no, ['thumbnail' => $newFileNo]);

            if ($oldFileNo) {
                $oldFile = $this->repository->getFileByNo((int) $oldFileNo);
                if ($oldFile) {
                    $this->repository->markFileDeleted((int) $oldFileNo);
                    $oldKey = S3FileHelper::extractS3KeyFromUrl($oldFile->web_path);
                    if ($oldKey) S3FileHelper::deleteFromS3($oldKey);
                }
            }

            AuditLogHelper::logUpdate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "목회자 사진 업로드: {$pastor->name}", targetId: (string) $pastor->pastor_no, targetLabel: $pastor->name,
                compareFields: ['thumbnail'],
            );

            $newFile = $this->repository->getFileByNo($newFileNo);
            return ApiResponse::success(['photo' => ['file_no' => $newFileNo, 'url' => $newFile->web_path]]);
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('UPLOAD_FAILED', '사진 업로드에 실패했습니다.', 500);
        } catch (\Exception $e) {
            LogHelper::logWrite("[IntroService] uploadPastorPhoto error: " . $e->getMessage(), "intro");
            return ApiResponse::fail('INTERNAL_ERROR', '사진 업로드 중 오류가 발생했습니다.', 500);
        }
    }

    public function deletePastorPhoto(int $authMemberId): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $pastor = $this->repository->getHeadPastor($churchId);
            if ($pastor === null || !$pastor->thumbnail) {
                return ApiResponse::fail('NOT_FOUND', '삭제할 사진이 없습니다.', 404);
            }

            $file = $this->repository->getFileByNo((int) $pastor->thumbnail);
            $this->repository->updatePastor((int) $pastor->pastor_no, ['thumbnail' => null]);
            $this->repository->markFileDeleted((int) $pastor->thumbnail);

            if ($file) {
                $key = S3FileHelper::extractS3KeyFromUrl($file->web_path);
                if ($key) S3FileHelper::deleteFromS3($key);
            }

            AuditLogHelper::logUpdate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "목회자 사진 삭제: {$pastor->name}", targetId: (string) $pastor->pastor_no, targetLabel: $pastor->name,
                compareFields: ['thumbnail'],
            );

            return ApiResponse::success(['pastor_no' => (int) $pastor->pastor_no]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[IntroService] deletePastorPhoto error: " . $e->getMessage(), "intro");
            return ApiResponse::fail('INTERNAL_ERROR', '사진 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    private function decodeJson(?string $json, $default)
    {
        if (!$json) return $default;
        $decoded = json_decode($json, true);
        return $decoded ?? $default;
    }

    /** Quill 에디터의 "빈 상태" HTML(<p><br></p> 등)을 null로 정규화 — 02_gh_admin_front의 동일 관례. */
    private function normalizeQuillEmpty(?string $html): ?string
    {
        if ($html === null) return null;
        $stripped = trim(strip_tags($html));
        return $stripped === '' ? null : $html;
    }
}
