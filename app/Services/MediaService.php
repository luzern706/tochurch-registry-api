<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\ChurchBoardNo;
use App\Constants\FileUploadConstants;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Helpers\S3FileHelper;
use App\Repositories\ChurchBoardRepository;
use App\Repositories\SettingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * 관리 > 교회 페이지 "미디어" — gh_new_board_content 재사용.
 * board_no 매핑은 App\Constants\ChurchBoardNo, 필드 매핑은 docs/schema/10_church_board_types.md 참조.
 */
class MediaService
{
    private const SETTING_SERMON_FEATURED_MODE = 'media_sermon_featured_mode';

    protected ChurchBoardRepository $boardRepository;
    protected SettingRepository $settingRepository;

    public function __construct(ChurchBoardRepository $boardRepository, SettingRepository $settingRepository)
    {
        $this->boardRepository = $boardRepository;
        $this->settingRepository = $settingRepository;
    }

    private function churchId(): ?int
    {
        return JwtHelper::getChurchIdFromRequest();
    }

    private function ownedByChurch($row, int $churchId): bool
    {
        return $row !== null && (int) $row->church_no === $churchId;
    }

    // ───────────────────────── 설교 영상 ─────────────────────────

    public function getSermonList(array $filters): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $result = $this->boardRepository->getList(ChurchBoardNo::SERMON, $churchId, $filters);
            $mode = $this->settingRepository->getByKeys($churchId, [self::SETTING_SERMON_FEATURED_MODE]);
            $result['featured_mode'] = $mode[0]->setting_value ?? 'auto';

            return ApiResponse::success($result);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] getSermonList error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', '설교 영상 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function setSermonFeaturedMode(int $authMemberId, string $mode): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $this->settingRepository->upsert($churchId, self::SETTING_SERMON_FEATURED_MODE, $mode);

            AuditLogHelper::logUpdate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "대표 설교 방식 변경: {$mode}", targetId: null, targetLabel: '설교 영상 설정',
                before: [], after: ['featured_mode' => $mode], compareFields: ['featured_mode'],
            );

            return ApiResponse::success(['featured_mode' => $mode]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] setSermonFeaturedMode error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', '설정 저장 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerSermon(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $data = [
                'board_no'   => ChurchBoardNo::SERMON,
                'church_no'  => $churchId,
                'admin_no'   => $authMemberId,
                'title'      => $input['title'],
                'youtubeUrl' => $input['video_url'] ?? null,
                'str_val1'   => $input['speaker'] ?? null,
                'str_val2'   => $input['verse'] ?? null,
                'str_val3'   => $input['series'] ?? null,
                'str_val4'   => isset($input['tags']) ? implode(',', $input['tags']) : null,
                'is_top'     => !empty($input['is_top']) ? 'Y' : 'N',
            ];
            if (!empty($input['date'])) $data['registered'] = $input['date'];

            $newId = $this->boardRepository->insert($data);

            AuditLogHelper::logCreate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "설교 영상 등록: {$input['title']}", targetId: (string) $newId, targetLabel: $input['title'], created: $data,
            );

            return ApiResponse::success(['content_no' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] registerSermon error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', '설교 영상 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateSermon(int $authMemberId, int $contentNo, array $input): JsonResponse
    {
        return $this->genericUpdate($authMemberId, $contentNo, $input,
            ['title', 'video_url', 'speaker', 'verse', 'series', 'tags', 'is_top', 'date'], '설교 영상',
            function ($v) {
                if (isset($v['video_url'])) { $v['youtubeUrl'] = $v['video_url']; unset($v['video_url']); }
                if (isset($v['speaker'])) { $v['str_val1'] = $v['speaker']; unset($v['speaker']); }
                if (isset($v['verse'])) { $v['str_val2'] = $v['verse']; unset($v['verse']); }
                if (isset($v['series'])) { $v['str_val3'] = $v['series']; unset($v['series']); }
                if (isset($v['tags'])) { $v['str_val4'] = implode(',', $v['tags']); unset($v['tags']); }
                if (isset($v['is_top'])) $v['is_top'] = $v['is_top'] ? 'Y' : 'N';
                if (isset($v['date'])) { $v['registered'] = $v['date']; unset($v['date']); }
                return $v;
            });
    }

    public function deleteSermon(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '설교 영상');
    }

    // ───────────────────────── 교회 영상 ─────────────────────────

    public function getChurchVideoList(array $filters): JsonResponse
    {
        return $this->simpleList(ChurchBoardNo::CHURCH_VIDEO, $filters, '교회 영상');
    }

    public function registerChurchVideo(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $data = [
                'board_no'   => ChurchBoardNo::CHURCH_VIDEO,
                'church_no'  => $churchId,
                'admin_no'   => $authMemberId,
                'title'      => $input['title'],
                'content'    => $input['description'] ?? '',
                'youtubeUrl' => $input['video_url'] ?? null,
                'category1'  => $input['category'] ?? null,
            ];
            $newId = $this->boardRepository->insert($data);

            AuditLogHelper::logCreate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "교회 영상 등록: {$input['title']}", targetId: (string) $newId, targetLabel: $input['title'], created: $data,
            );

            return ApiResponse::success(['content_no' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] registerChurchVideo error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', '교회 영상 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateChurchVideo(int $authMemberId, int $contentNo, array $input): JsonResponse
    {
        return $this->genericUpdate($authMemberId, $contentNo, $input, ['title', 'description', 'video_url', 'category'], '교회 영상', function ($v) {
            if (isset($v['description'])) { $v['content'] = $v['description']; unset($v['description']); }
            if (isset($v['video_url'])) { $v['youtubeUrl'] = $v['video_url']; unset($v['video_url']); }
            if (isset($v['category'])) { $v['category1'] = $v['category']; unset($v['category']); }
            return $v;
        });
    }

    public function deleteChurchVideo(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '교회 영상');
    }

    // ───────────────────────── 사진 갤러리 ─────────────────────────

    public function getGalleryList(array $filters): JsonResponse
    {
        return $this->simpleList(ChurchBoardNo::CHURCH_PHOTO, $filters, '사진 갤러리');
    }

    public function registerGallery(int $authMemberId, array $input): JsonResponse
    {
        return $this->registerAlbum(ChurchBoardNo::CHURCH_PHOTO, $authMemberId, $input, '앨범');
    }

    public function updateGallery(int $authMemberId, int $contentNo, array $input): JsonResponse
    {
        return $this->updateAlbum($authMemberId, $contentNo, $input, '앨범');
    }

    public function deleteGallery(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '앨범');
    }

    // ───────────────────────── 사역 & 활동 사진 ─────────────────────────

    public function getMinistryList(array $filters): JsonResponse
    {
        return $this->simpleList(ChurchBoardNo::EVENT_PHOTO, $filters, '사역 활동');
    }

    public function registerMinistry(int $authMemberId, array $input): JsonResponse
    {
        return $this->registerAlbum(ChurchBoardNo::EVENT_PHOTO, $authMemberId, $input, '사역 항목');
    }

    public function updateMinistry(int $authMemberId, int $contentNo, array $input): JsonResponse
    {
        return $this->updateAlbum($authMemberId, $contentNo, $input, '사역 항목');
    }

    public function deleteMinistry(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '사역 항목');
    }

    // ───────────────────────── 이미지 업로드 ─────────────────────────

    public function uploadImage(UploadedFile $file, int $churchNo): JsonResponse
    {
        $err = S3FileHelper::validate($file, FileUploadConstants::ALLOWED_IMAGE_EXTENSIONS, '이미지');
        if ($err !== null) {
            return ApiResponse::fail('INVALID_FILE_TYPE', $err, 400);
        }

        try {
            $upload = S3FileHelper::upload($file, 'uploads/media', "church_{$churchNo}_media_" . time() . '_' . uniqid());
            S3FileHelper::cleanupLocalTemp($upload['local_path']);
            return ApiResponse::success(['url' => $upload['s3_url']]);
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('UPLOAD_FAILED', '이미지 업로드에 실패했습니다.', 500);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] uploadImage error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', '이미지 업로드 중 오류가 발생했습니다.', 500);
        }
    }

    // ───────────────────────── 공통 헬퍼 ─────────────────────────

    private function simpleList(int $boardNo, array $filters, string $label): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $result = $this->boardRepository->getList($boardNo, $churchId, $filters);
            return ApiResponse::success($result);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] simpleList({$label}) error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', "{$label} 조회 중 오류가 발생했습니다.", 500);
        }
    }

    private function registerAlbum(int $boardNo, int $authMemberId, array $input, string $label): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $data = [
                'board_no'  => $boardNo,
                'church_no' => $churchId,
                'admin_no'  => $authMemberId,
                'title'     => $input['title'],
                'content'   => $input['description'] ?? '',
                'rep_img'   => $input['cover_url'] ?? null,
                'files'     => isset($input['photos']) ? json_encode($input['photos'], JSON_UNESCAPED_UNICODE) : null,
                'int_val1'  => !empty($input['is_public']) ? 1 : 0,
            ];
            $newId = $this->boardRepository->insert($data);

            AuditLogHelper::logCreate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "{$label} 등록: {$input['title']}", targetId: (string) $newId, targetLabel: $input['title'], created: $data,
            );

            return ApiResponse::success(['content_no' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] registerAlbum({$label}) error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', "{$label} 등록 중 오류가 발생했습니다.", 500);
        }
    }

    private function updateAlbum(int $authMemberId, int $contentNo, array $input, string $label): JsonResponse
    {
        return $this->genericUpdate($authMemberId, $contentNo, $input,
            ['title', 'description', 'cover_url', 'photos', 'is_public'], $label,
            function ($v) {
                if (isset($v['description'])) { $v['content'] = $v['description']; unset($v['description']); }
                if (isset($v['cover_url'])) { $v['rep_img'] = $v['cover_url']; unset($v['cover_url']); }
                if (isset($v['photos'])) { $v['files'] = json_encode($v['photos'], JSON_UNESCAPED_UNICODE); unset($v['photos']); }
                if (isset($v['is_public'])) { $v['int_val1'] = $v['is_public'] ? 1 : 0; unset($v['is_public']); }
                return $v;
            });
    }

    private function genericUpdate(int $authMemberId, int $contentNo, array $input, array $fillable, string $label, callable $transform): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $row = $this->boardRepository->getById($contentNo);
            if (!$this->ownedByChurch($row, $churchId)) return ApiResponse::fail('NOT_FOUND', "{$label}을(를) 찾을 수 없습니다.", 404);

            $data = array_intersect_key($input, array_flip($fillable));
            $data = $transform($data);
            foreach (['title', 'content'] as $notNullField) {
                if (array_key_exists($notNullField, $data) && $data[$notNullField] === null) {
                    $data[$notNullField] = '';
                }
            }
            if (!empty($data)) {
                $this->boardRepository->update($contentNo, $data);
            }

            $titleLabel = $row->title ?: ($row->content ?? $label);
            AuditLogHelper::logUpdate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "{$label} 수정: {$titleLabel}", targetId: (string) $contentNo, targetLabel: $titleLabel,
                before: (array) $row, after: array_merge((array) $row, $data), compareFields: array_keys($data),
            );

            return ApiResponse::success(['content_no' => $contentNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] genericUpdate({$label}) error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', "{$label} 수정 중 오류가 발생했습니다.", 500);
        }
    }

    private function genericDelete(int $authMemberId, int $contentNo, string $label): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $row = $this->boardRepository->getById($contentNo);
            if (!$this->ownedByChurch($row, $churchId)) return ApiResponse::fail('NOT_FOUND', "{$label}을(를) 찾을 수 없습니다.", 404);

            $this->boardRepository->softDelete($contentNo);

            $titleLabel = $row->title ?: ($row->content ?? $label);
            AuditLogHelper::logDelete(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "{$label} 삭제: {$titleLabel}", targetId: (string) $contentNo, targetLabel: $titleLabel,
            );

            return ApiResponse::success(['content_no' => $contentNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MediaService] genericDelete({$label}) error: " . $e->getMessage(), "media");
            return ApiResponse::fail('INTERNAL_ERROR', "{$label} 삭제 중 오류가 발생했습니다.", 500);
        }
    }
}
