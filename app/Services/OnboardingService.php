<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\ServiceAccessPolicy;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\LogHelper;
use App\Repositories\AuthRepository;
use App\Repositories\OnboardingRepository;
use Illuminate\Http\JsonResponse;

/**
 * 교적·재정 서비스 사용 권한 확인 + 신청 접수.
 *
 * 판정 규칙은 ServiceAccessPolicy 참고(권한 소스: gh_church_admin_role.perm_registry / perm_finance).
 */
class OnboardingService
{
    protected OnboardingRepository $onboardingRepository;
    protected AuthRepository $authRepository;

    public function __construct(OnboardingRepository $onboardingRepository, AuthRepository $authRepository)
    {
        $this->onboardingRepository = $onboardingRepository;
        $this->authRepository = $authRepository;
    }

    public function getStatus(int $adminNo): JsonResponse
    {
        try {
            $admin = $this->onboardingRepository->findAdmin($adminNo);
            if ($admin === null) {
                return ApiResponse::fail('NOT_FOUND', '관리자 계정을 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success($this->buildStatus($admin));
        } catch (\Exception $e) {
            LogHelper::logWrite("[OnboardingService] getStatus error: " . $e->getMessage(), "onboarding");
            return ApiResponse::fail('INTERNAL_ERROR', '서비스 이용 상태 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function applyService(int $adminNo): JsonResponse
    {
        try {
            $admin = $this->onboardingRepository->findAdmin($adminNo);
            if ($admin === null) {
                return ApiResponse::fail('NOT_FOUND', '관리자 계정을 찾을 수 없습니다.', 404);
            }

            $status = $this->buildStatus($admin);
            if ($status['has_access']) {
                return ApiResponse::fail('VALIDATION_FAILED', '이미 교적·재정 서비스를 사용할 수 있는 계정입니다.', 400);
            }
            if ($status['application'] !== null && $status['application']['status'] === 'received') {
                return ApiResponse::fail('DUPLICATE_DATA', '이미 신청이 접수되었습니다. 담당자가 이메일로 안내드립니다.', 400);
            }

            $churchId = (int) $admin->church_no;
            $email    = trim((string) ($admin->email ?: $admin->id));
            $applicationId = $this->onboardingRepository->insertApplication([
                'church_id'       => $churchId,
                'admin_no'        => $adminNo,
                'applicant_name'  => (string) $admin->name,
                'applicant_email' => $email,
                'status'          => 'received',
            ]);

            AuditLogHelper::logCreate(
                memberId:    $adminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::SERVICE_APPLICATION,
                summary:     "교적·재정 서비스 신청: {$status['church_name']}",
                targetId:    (string) $applicationId,
                targetLabel: $status['church_name'],
                created:     ['applicant_email' => $email],
            );

            $application = $this->onboardingRepository->findLatestApplication($churchId);

            return ApiResponse::success($this->formatApplication($application));
        } catch (\Exception $e) {
            LogHelper::logWrite("[OnboardingService] applyService error: " . $e->getMessage(), "onboarding");
            return ApiResponse::fail('INTERNAL_ERROR', '서비스 신청 중 오류가 발생했습니다.', 500);
        }
    }

    private function buildStatus(object $admin): array
    {
        $roles = $this->onboardingRepository->getActiveRoles($admin);

        $registry = false;
        $finance  = false;
        foreach ($roles as $role) {
            $registry = $registry || (int) $role->perm_registry === 1;
            $finance  = $finance || (int) $role->perm_finance === 1;
        }

        $hasAccess = ServiceAccessPolicy::hasAccess($roles);

        $churchId    = (int) $admin->church_no;
        $application = $this->onboardingRepository->findLatestApplication($churchId);

        return [
            'has_access'    => $hasAccess,
            'perm_registry' => $registry,
            'perm_finance'  => $finance,
            'church_name'   => $this->authRepository->getChurchName($churchId) ?? '',
            'application'   => $application ? $this->formatApplication($application) : null,
        ];
    }

    private function formatApplication(object $application): array
    {
        return [
            'application_id' => (int) $application->application_id,
            'status'         => $application->status,
            'requested_at'   => $application->requested_at,
        ];
    }
}
