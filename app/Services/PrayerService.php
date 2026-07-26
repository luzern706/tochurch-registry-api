<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\LogHelper;
use App\Repositories\PrayerRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrayerService
{
    protected PrayerRepository $prayerRepository;

    public function __construct(PrayerRepository $prayerRepository)
    {
        $this->prayerRepository = $prayerRepository;
    }

    public function getList(array $filters, int $churchId): JsonResponse
    {
        try {
            $filters['church_id'] = $churchId;
            $data = $this->prayerRepository->getPrayerList($filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            return ApiResponse::fail('INTERNAL_ERROR', '목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getDetail(int $id, int $churchId): JsonResponse
    {
        try {
            $prayer = $this->prayerRepository->getPrayerById($id, $churchId);
            if (!$prayer) {
                return ApiResponse::fail('NOT_FOUND', '기도 제목을 찾을 수 없습니다.', 404);
            }
            return ApiResponse::success($prayer);
        } catch (\Exception $e) {
            return ApiResponse::fail('INTERNAL_ERROR', '상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function register(array $data, int $churchId, int $authMemberId): JsonResponse
    {
        try {
            $insertData = [
                'church_id'          => $churchId,
                'member_id'          => $data['member_id'] ?? null,
                'non_member_name'    => $data['non_member_name'] ?? null,
                'title'              => $data['title'],
                'content'            => $data['content'],
                'visibility'         => $data['visibility'] ?? 'leaders',
                'status'             => 'active',
                'manager_member_id'  => $data['manager_member_id'] ?? null,
                'visit_record_id'    => $data['visit_record_id'] ?? null,
                'is_resolved'        => 0,
                'created_by'         => $authMemberId,
            ];

            $newId = $this->prayerRepository->createPrayer($insertData);

            $targetLabel = !empty($data['member_id']) ? null : ($data['non_member_name'] ?? '비교인');

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::PRAYER,
                summary:     "기도 등록: {$data['title']}",
                targetId:    (string) $newId,
                targetLabel: $data['title'],
                created:     $insertData,
            );

            return ApiResponse::success(['id' => $newId]);
        } catch (\Throwable $e) {
            LogHelper::logWrite("[PrayerService] register error: " . $e->getMessage(), "prayer");
            return ApiResponse::fail('INTERNAL_ERROR', '기도 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function update(int $id, array $data, int $churchId, int $authMemberId): JsonResponse
    {
        try {
            $before = $this->prayerRepository->getPrayerById($id, $churchId);
            if (!$before) {
                return ApiResponse::fail('NOT_FOUND', '기도 제목을 찾을 수 없습니다.', 404);
            }

            $updateData = array_filter([
                'member_id'         => $data['member_id'] ?? null,
                'non_member_name'   => $data['non_member_name'] ?? null,
                'title'             => $data['title'] ?? null,
                'content'           => $data['content'] ?? null,
                'visibility'        => $data['visibility'] ?? null,
                'manager_member_id' => $data['manager_member_id'] ?? null,
                'visit_record_id'   => $data['visit_record_id'] ?? null,
            ], fn($v) => $v !== null);

            $this->prayerRepository->updatePrayer($id, $updateData);

            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::PRAYER,
                summary:       "기도 수정: {$before->title}",
                targetId:      (string) $id,
                targetLabel:   $before->title,
                before:        (array) $before,
                after:         array_merge((array) $before, $updateData),
                compareFields: array_keys($updateData),
            );

            return ApiResponse::success(['id' => $id]);
        } catch (\Exception $e) {
            return ApiResponse::fail('INTERNAL_ERROR', '기도 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function delete(int $id, int $churchId, int $authMemberId): JsonResponse
    {
        try {
            $prayer = $this->prayerRepository->getPrayerById($id, $churchId);
            if (!$prayer) {
                return ApiResponse::fail('NOT_FOUND', '기도 제목을 찾을 수 없습니다.', 404);
            }

            $this->prayerRepository->deletePrayer($id);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::PRAYER,
                summary:     "기도 삭제: {$prayer->title}",
                targetId:    (string) $id,
                targetLabel: $prayer->title,
                deleted:     (array) $prayer,
            );

            return ApiResponse::success(null);
        } catch (\Exception $e) {
            return ApiResponse::fail('INTERNAL_ERROR', '기도 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateStatus(int $id, array $data, int $churchId, int $authMemberId): JsonResponse
    {
        try {
            $prayer = $this->prayerRepository->getPrayerById($id, $churchId);
            if (!$prayer) {
                return ApiResponse::fail('NOT_FOUND', '기도 제목을 찾을 수 없습니다.', 404);
            }

            $status = $data['status'];
            $this->prayerRepository->updateStatus($id, $status);

            $actionLabel = $status === 'completed' ? '응답' : ($status === 'cancelled' ? '취소' : '재활성화');

            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::PRAYER,
                summary:       "기도 상태 변경({$actionLabel}): {$prayer->title}",
                targetId:      (string) $id,
                targetLabel:   $prayer->title,
                before:        ['status' => $prayer->status],
                after:         ['status' => $status],
                compareFields: ['status'],
            );

            return ApiResponse::success(['id' => $id, 'status' => $status]);
        } catch (\Exception $e) {
            return ApiResponse::fail('INTERNAL_ERROR', '상태 변경 중 오류가 발생했습니다.', 500);
        }
    }

    public function getStats(int $churchId): JsonResponse
    {
        try {
            $stats = $this->prayerRepository->getStats($churchId);
            return ApiResponse::success($stats);
        } catch (\Exception $e) {
            return ApiResponse::fail('INTERNAL_ERROR', '통계 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
