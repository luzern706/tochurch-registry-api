<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\LogHelper;
use App\Repositories\AdminRepository;
use App\Repositories\AuditLogRepository;
use Illuminate\Http\JsonResponse;

class SystemService
{
    protected AdminRepository $adminRepository;
    protected AuditLogRepository $auditLogRepository;

    protected const ROLE_LABELS = [
        'admin'     => '관리자',
        'pastor'    => '담임',
        'minister'  => '사역자',
        'volunteer' => '봉사자',
    ];

    protected const RECENT_LOGIN_DAYS = 7;
    protected const RECENT_ACTIVITY_LIMIT = 10;

    public function __construct(AdminRepository $adminRepository, AuditLogRepository $auditLogRepository)
    {
        $this->adminRepository    = $adminRepository;
        $this->auditLogRepository = $auditLogRepository;
    }

    public function getOverview(int $churchId): JsonResponse
    {
        try {
            $userStats = $this->adminRepository->getUserStats($churchId);
            $logs      = $this->auditLogRepository->getRecentLogs($churchId, self::RECENT_ACTIVITY_LIMIT);

            $activities = array_map(function ($log) {
                return [
                    'date'         => $log->created_at,
                    'user'         => $log->admin_name ?? '(탈퇴한 사용자)',
                    'menu_code'    => $log->menu_code,
                    'menu_label'   => AuditMenuCode::label($log->menu_code),
                    'action_type'  => $log->action_type,
                    'action_label' => AuditActionType::label($log->action_type),
                    'target'       => $log->target_label ?: $log->summary,
                ];
            }, $logs);

            return ApiResponse::success([
                'users' => $userStats,
                'roles' => [
                    'total'  => count(self::ROLE_LABELS),
                    'labels' => array_values(self::ROLE_LABELS),
                ],
                'recent_logins' => [
                    'count' => $this->adminRepository->getRecentLoginCount($churchId, self::RECENT_LOGIN_DAYS),
                    'days'  => self::RECENT_LOGIN_DAYS,
                ],
                'activities' => $activities,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[SystemService] getOverview error: " . $e->getMessage(), "system");
            return ApiResponse::fail('INTERNAL_ERROR', '현황 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getAuditLogs(array $filters, int $churchId): JsonResponse
    {
        try {
            $page = (int) ($filters['page'] ?? 1);
            $size = (int) ($filters['size'] ?? 20);

            $data = $this->auditLogRepository->getLogList($churchId, $filters, $page, $size);
            $data['list'] = array_map(function ($log) {
                return [
                    'audit_no'     => $log->audit_no,
                    'date'         => $log->created_at,
                    'user'         => $log->admin_name ?? '(탈퇴한 사용자)',
                    'menu_code'    => $log->menu_code,
                    'menu_label'   => AuditMenuCode::label($log->menu_code),
                    'action_type'  => $log->action_type,
                    'action_label' => AuditActionType::label($log->action_type),
                    'target'       => $log->target_label ?: $log->summary,
                    'summary'      => $log->summary,
                ];
            }, $data['list']->all());

            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[SystemService] getAuditLogs error: " . $e->getMessage(), "system");
            return ApiResponse::fail('INTERNAL_ERROR', '활동 로그 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
