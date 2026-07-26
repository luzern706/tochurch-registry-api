<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\AuthRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected AuthRepository $authRepository;

    public function __construct(AuthRepository $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    /**
     * 관리자 로그인 (gh_church_admin 테이블)
     *
     * admin_type = 'admin'     : 관리자 (관리자 추가/삭제 권한 포함)
     * admin_type = 'pastor'    : 담임목회자
     * admin_type = 'minister'  : 사역자
     * admin_type = 'volunteer' : 봉사자
     */
    public function adminSignIn(array $credentials): JsonResponse
    {
        try {
            $admin = $this->authRepository->findAdminByLoginId($credentials['login_id']);

            if ($admin === null || !Hash::check($credentials['password'], $admin->password)) {
                return ApiResponse::fail(
                    'INVALID_CREDENTIALS',
                    '아이디 또는 비밀번호가 올바르지 않습니다.',
                    401
                );
            }

            $churchId   = (int) $admin->church_no;
            $adminNo    = (int) $admin->admin_no;
            $adminRole  = $admin->admin_type ?? 'admin';
            $churchName = $this->authRepository->getChurchName($churchId) ?? '';
            $token      = JwtHelper::createToken($adminNo, $churchId, $adminRole, $admin->name ?? '', $churchName);

            $this->authRepository->updateLastLoginAt($adminNo);

            AuditLogHelper::logAction(
                memberId:    $adminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::AUTH,
                actionType:  AuditActionType::LOGIN,
                summary:     "관리자 로그인({$adminRole}): {$admin->id}",
                targetId:    (string) $adminNo,
                targetLabel: $admin->id,
            );

            // church_name은 응답 본문에 별도로 담지 않음 — 토큰의 평문 churchName 클레임이
            // 유일한 소스(프론트가 서버에 별도 요청하지 않고 토큰에서 직접 꺼내 쓰도록).
            return ApiResponse::success(['token' => $token]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AuthService] adminSignIn error: " . $e->getMessage(), "auth");
            return ApiResponse::fail('INTERNAL_ERROR', '로그인 중 오류가 발생했습니다.', 500);
        }
    }

    public function signOut(int $adminNo): JsonResponse
    {
        // JWT 는 stateless. 서버 측 토큰 폐기 없이 클라이언트가 토큰을 폐기한다.
        try {
            $admin    = $this->authRepository->findAdminByAdminNo($adminNo);
            $churchId = $admin ? (int) $admin->church_no : JwtHelper::getChurchIdFromRequest() ?? 0;

            AuditLogHelper::logAction(
                memberId:    $adminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::AUTH,
                actionType:  AuditActionType::LOGOUT,
                summary:     '관리자 로그아웃' . ($admin ? ": {$admin->id}" : ''),
                targetId:    (string) $adminNo,
                targetLabel: $admin?->id ?? '',
            );
        } catch (\Exception $e) {
            LogHelper::logWrite("[AuthService] signOut error: " . $e->getMessage(), "auth");
        }

        return ApiResponse::success(['admin_no' => $adminNo]);
    }
}
