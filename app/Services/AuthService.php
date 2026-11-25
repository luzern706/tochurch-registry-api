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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected AuthRepository $authRepository;
    protected NhnEmailService $nhnEmailService;

    public function __construct(AuthRepository $authRepository, NhnEmailService $nhnEmailService)
    {
        $this->authRepository = $authRepository;
        $this->nhnEmailService = $nhnEmailService;
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

    /**
     * 비밀번호 설정 링크 확인 — 토큰이 유효하면 아이디·교회명만 돌려준다.
     * 무효/만료/사용됨/형식오류는 구분하지 않고 동일 응답(토큰 존재 여부 노출 방지).
     */
    public function checkSetupToken(string $token): JsonResponse
    {
        try {
            $row = $this->authRepository->findValidSetupToken(hash('sha256', $token));
            if ($row === null) {
                return ApiResponse::fail('SETUP_TOKEN_INVALID', '링크가 만료되었거나 이미 사용되었습니다.', 400);
            }

            return ApiResponse::success([
                'login_id'    => $row->login_id,
                'church_name' => $row->church_name ?? '',
                'admin_name'  => $row->admin_name ?? '',
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AuthService] checkSetupToken error: " . $e->getMessage(), "auth");
            return ApiResponse::fail('INTERNAL_ERROR', '처리 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 비밀번호 재설정 링크 요청 (로그인 화면 「비밀번호 찾기」, 로그인 전 공개 호출).
     *
     * 가입 여부 노출을 막기 위해 이메일이 있든 없든, 요청 제한에 걸렸든 항상 같은 성공 응답을 준다.
     * 실제 발송은 응답을 보낸 뒤 처리해 응답 시간으로도 가입 여부가 드러나지 않게 한다.
     * 링크는 기존 「비밀번호 설정 링크」와 같은 토큰 테이블·같은 /set-password 화면을 쓴다.
     */
    public function requestPasswordReset(string $email): JsonResponse
    {
        try {
            $normalized = mb_strtolower(trim($email));
            $admins = $this->authRepository->findAliveAdminsByEmail($normalized);

            if (!empty($admins)) {
                app()->terminating(function () use ($admins, $normalized) {
                    $this->sendPasswordResetMails($admins, $normalized);
                });
            }

            return ApiResponse::success(null);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AuthService] requestPasswordReset error: " . $e->getMessage(), "auth");
            return ApiResponse::fail('INTERNAL_ERROR', '처리 중 오류가 발생했습니다.', 500);
        }
    }

    /** 계정마다 요청 제한(연속 간격·시간당 횟수)을 확인하고 토큰 발급 → 메일 발송. 실패는 로그만 남긴다(응답은 이미 나갔다). */
    private function sendPasswordResetMails(array $admins, string $toEmail): void
    {
        try {
            $baseUrl = rtrim((string) config('services.registry_password_reset.front_url'), '/');
            if ($baseUrl === '') {
                LogHelper::logWrite('[AuthService] password reset mail skipped — REGISTRY_SETUP_FRONT_URL 미설정', 'auth');
                return;
            }
            if (!$this->nhnEmailService->isConfigured()) {
                LogHelper::logWrite('[AuthService] password reset mail skipped — NHN_EMAIL_* 미설정', 'auth');
                return;
            }
            if ($this->authRepository->isEmailSuppressed($toEmail)) {
                LogHelper::logWrite('[AuthService] password reset mail skipped — suppressed email', 'auth');
                return;
            }

            $minInterval = (int) config('services.registry_password_reset.min_interval_seconds', 60);
            $maxPerHour = (int) config('services.registry_password_reset.max_per_hour', 5);
            $hours = (int) config('services.registry_password_reset.valid_hours', 24);

            foreach ($admins as $admin) {
                $adminNo = (int) $admin->admin_no;

                if ($this->authRepository->countRecentTokens($adminNo, $minInterval) > 0
                    || $this->authRepository->countRecentTokens($adminNo, 3600) >= $maxPerHour) {
                    LogHelper::logWrite("[AuthService] password reset skipped — rate limited (admin_no {$adminNo})", 'auth');
                    continue;
                }

                $rawToken = bin2hex(random_bytes(32));
                $this->authRepository->issueSetupToken($adminNo, hash('sha256', $rawToken), now()->addHours($hours));

                $result = $this->nhnEmailService->sendRegistryPasswordReset(
                    $toEmail,
                    (string) ($admin->church_name ?? ''),
                    (string) ($admin->admin_name ?? ''),
                    (string) $admin->login_id,
                    $baseUrl . '?token=' . $rawToken,
                    $hours
                );

                // 토큰·링크는 로그에 남기지 않는다
                if (!$result['success']) {
                    LogHelper::logWrite("[AuthService] password reset mail failed (admin_no {$adminNo}): {$result['message']}", 'auth');
                }
            }
        } catch (\Throwable $e) {
            LogHelper::logWrite('[AuthService] sendPasswordResetMails error: ' . $e->getMessage(), 'auth');
        }
    }

    /** 비밀번호 설정 링크로 최초 비밀번호 설정 — 토큰 검증 + 저장 + 토큰 소멸을 한 트랜잭션으로 처리 */
    public function setupPassword(string $token, string $newPassword): JsonResponse
    {
        try {
            $applied = DB::transaction(function () use ($token, $newPassword) {
                $row = $this->authRepository->findValidSetupToken(hash('sha256', $token), true);
                if ($row === null) {
                    return null;
                }

                $this->authRepository->applySetupPassword((int) $row->admin_no, Hash::make($newPassword));
                return $row;
            });

            if ($applied === null) {
                return ApiResponse::fail('SETUP_TOKEN_INVALID', '링크가 만료되었거나 이미 사용되었습니다.', 400);
            }

            // 비밀번호·토큰 원문은 기록하지 않는다
            AuditLogHelper::logAction(
                memberId:    (int) $applied->admin_no,
                churchId:    (int) $applied->church_no,
                menuCode:    AuditMenuCode::AUTH,
                actionType:  AuditActionType::UPDATE,
                summary:     "교적 비밀번호 설정 완료(설정 링크): {$applied->login_id}",
                targetId:    (string) $applied->admin_no,
                targetLabel: $applied->login_id,
            );

            return ApiResponse::success(null);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AuthService] setupPassword error: " . $e->getMessage(), "auth");
            return ApiResponse::fail('INTERNAL_ERROR', '처리 중 오류가 발생했습니다.', 500);
        }
    }
}
