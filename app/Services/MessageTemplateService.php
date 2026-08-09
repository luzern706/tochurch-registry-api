<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MessageTemplateRepository;
use Illuminate\Http\JsonResponse;

class MessageTemplateService
{
    private const FILLABLE = [
        'name', 'category', 'msg_type', 'scope', 'content', 'unsub_enabled', 'unsub_text', 'is_active',
    ];

    protected MessageTemplateRepository $repository;

    public function __construct(MessageTemplateRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            return ApiResponse::success($this->repository->getList($churchId, $filters));
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageTemplateService] getList error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '템플릿 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getActiveList(int $authMemberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            return ApiResponse::success(['list' => $this->repository->getActiveList($churchId)]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageTemplateService] getActiveList error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '템플릿 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getDetail(int $authMemberId, int $id): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $template = $this->repository->getById($id);
            if ($template === null || (int) $template->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '템플릿을 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['template' => $template]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageTemplateService] getDetail error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '템플릿 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function register(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $data = $this->pickFillable($input);
            $data['church_id']  = $churchId;
            $data['created_by'] = $authMemberId;

            $newId = $this->repository->insert($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MESSAGE,
                summary:     "템플릿 등록: {$data['name']}",
                targetId:    (string) $newId,
                targetLabel: $data['name'],
                created:     $data,
            );

            return ApiResponse::success(['id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageTemplateService] register error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '템플릿 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function update(int $authMemberId, int $id, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $template = $this->repository->getById($id);
            if ($template === null || (int) $template->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '템플릿을 찾을 수 없습니다.', 404);
            }
            if ($template->scope === 'public') {
                return ApiResponse::fail('INSUFFICIENT_PERMISSION', '공용 템플릿은 직접 수정할 수 없습니다. 복제 후 수정해주세요.', 403);
            }

            $data = $this->pickFillable($input);
            $this->repository->update($id, $data);

            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::MESSAGE,
                summary:       "템플릿 수정: {$template->name}",
                targetId:      (string) $id,
                targetLabel:   $template->name,
                before:        (array) $template,
                after:         array_merge((array) $template, $data),
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['id' => $id]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageTemplateService] update error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '템플릿 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function duplicate(int $authMemberId, int $id): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $template = $this->repository->getById($id);
            if ($template === null || (int) $template->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '템플릿을 찾을 수 없습니다.', 404);
            }

            $data = [
                'church_id'     => $churchId,
                'name'          => $template->name . ' (복제본)',
                'category'      => $template->category,
                'msg_type'      => $template->msg_type,
                'scope'         => 'private',
                'content'       => $template->content,
                'unsub_enabled' => $template->unsub_enabled,
                'unsub_text'    => $template->unsub_text,
                'is_active'     => 1,
                'created_by'    => $authMemberId,
            ];
            $newId = $this->repository->insert($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MESSAGE,
                summary:     "템플릿 복제: {$template->name} → {$data['name']}",
                targetId:    (string) $newId,
                targetLabel: $data['name'],
                created:     $data,
            );

            return ApiResponse::success(['id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageTemplateService] duplicate error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '템플릿 복제 중 오류가 발생했습니다.', 500);
        }
    }

    public function toggleActive(int $authMemberId, int $id, bool $isActive): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $template = $this->repository->getById($id);
            if ($template === null || (int) $template->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '템플릿을 찾을 수 없습니다.', 404);
            }

            $this->repository->update($id, ['is_active' => $isActive ? 1 : 0]);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MESSAGE,
                actionType:  AuditActionType::UPDATE,
                summary:     ($isActive ? "템플릿 사용 재개: " : "템플릿 비활성화: ") . $template->name,
                targetId:    (string) $id,
                targetLabel: $template->name,
                detail:      ['is_active' => $isActive],
            );

            return ApiResponse::success(['id' => $id, 'is_active' => $isActive]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageTemplateService] toggleActive error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '처리 중 오류가 발생했습니다.', 500);
        }
    }

    private function pickFillable(array $input): array
    {
        return array_intersect_key($input, array_flip(self::FILLABLE));
    }
}
