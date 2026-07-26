<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\CodeRepository;
use Illuminate\Http\JsonResponse;

class CodeService
{
    private const FILLABLE = ['code_label', 'description', 'sort_order', 'is_active'];

    protected CodeRepository $codeRepository;

    public function __construct(CodeRepository $codeRepository)
    {
        $this->codeRepository = $codeRepository;
    }

    public function getCodes(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $list = $this->codeRepository->getCodesByGroup($churchId, $filters['group_key'], $filters);
            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[CodeService] getCodes error: " . $e->getMessage(), "code");
            return ApiResponse::fail('INTERNAL_ERROR', '코드 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerCode(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            if ($this->codeRepository->existsCodeValue($churchId, $input['group_key'], $input['code_value'])) {
                return ApiResponse::fail('DUPLICATE_DATA', '이미 등록된 코드값입니다.', 400);
            }

            $data = [
                'church_id'   => $churchId,
                'group_key'   => $input['group_key'],
                'code_value'  => $input['code_value'],
                'code_label'  => $input['code_label'],
                'description' => $input['description'] ?? null,
                'sort_order'  => $input['sort_order'] ?? 0,
                'is_active'   => isset($input['is_active']) ? (int) $input['is_active'] : 1,
                'is_system'   => 0,
            ];

            $newId = $this->codeRepository->insertCode($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::CODE,
                summary:     "코드 추가: [{$input['group_key']}] {$input['code_label']}",
                targetId:    (string) $newId,
                targetLabel: $input['code_label'],
                created:     $data,
            );

            return ApiResponse::success(['code_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[CodeService] registerCode error: " . $e->getMessage(), "code");
            return ApiResponse::fail('INTERNAL_ERROR', '코드 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateCode(int $authMemberId, int $codeId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $code = $this->codeRepository->getCodeById($codeId);
            if ($code === null || (int) $code->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '코드를 찾을 수 없습니다.', 404);
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            if (isset($data['is_active'])) {
                $data['is_active'] = (int) $data['is_active'];
            }

            if (!empty($data)) {
                $this->codeRepository->updateCode($codeId, $data);
            }

            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::CODE,
                summary:       "코드 수정: [{$code->group_key}] {$code->code_label}",
                targetId:      (string) $codeId,
                targetLabel:   $code->code_label,
                before:        (array) $code,
                after:         array_merge((array) $code, $data),
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['code_id' => $codeId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[CodeService] updateCode error: " . $e->getMessage(), "code");
            return ApiResponse::fail('INTERNAL_ERROR', '코드 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteCode(int $authMemberId, int $codeId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $code = $this->codeRepository->getCodeById($codeId);
            if ($code === null || (int) $code->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '코드를 찾을 수 없습니다.', 404);
            }

            if ((int) $code->is_system === 1) {
                return ApiResponse::fail('INSUFFICIENT_PERMISSION', '시스템 기본 코드는 삭제할 수 없습니다.', 403);
            }

            $this->codeRepository->deleteCode($codeId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::CODE,
                summary:     "코드 삭제: [{$code->group_key}] {$code->code_label}",
                targetId:    (string) $codeId,
                targetLabel: $code->code_label,
            );

            return ApiResponse::success(['code_id' => $codeId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[CodeService] deleteCode error: " . $e->getMessage(), "code");
            return ApiResponse::fail('INTERNAL_ERROR', '코드 삭제 중 오류가 발생했습니다.', 500);
        }
    }
}
