<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\AuthRepository;
use App\Repositories\MemberRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected AuthRepository $authRepository;
    protected MemberRepository $memberRepository;

    public function __construct(
        AuthRepository $authRepository,
        MemberRepository $memberRepository
    ) {
        $this->authRepository  = $authRepository;
        $this->memberRepository = $memberRepository;
    }

    public function signIn(array $credentials): JsonResponse
    {
        try {
            $member = $this->authRepository->findActiveByEmail($credentials['email']);

            if ($member === null || !Hash::check($credentials['password'], $member->password)) {
                return ApiResponse::fail(
                    'INVALID_CREDENTIALS',
                    '이메일 또는 비밀번호가 올바르지 않습니다.',
                    401
                );
            }

            $churchId = (int) $member->church_id;
            $token    = JwtHelper::createToken((int) $member->id, $member->email, $churchId, 'member');

            AuditLogHelper::logAction(
                memberId:    (int) $member->id,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::AUTH,
                actionType:  AuditActionType::LOGIN,
                summary:     '교인 로그인',
                targetId:    (string) $member->id,
                targetLabel: $member->name,
            );

            return ApiResponse::success([
                'token'      => $token,
                'actor_type' => 'member',
                'member_id'  => (int) $member->id,
                'member_no'  => $member->member_no,
                'name'       => $member->name,
                'email'      => $member->email,
                'church_id'  => $churchId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AuthService] signIn error: " . $e->getMessage(), "auth");
            return ApiResponse::fail('INTERNAL_ERROR', '로그인 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 교회 관리자 로그인 (gh_church_admin 테이블 기반)
     *
     * 교인 로그인과 별도 엔드포인트로 처리 — 로그인 ID 중복 가능성으로 인해
     * 단일 엔드포인트 순차 조회 대신 화면 분리 방식 채택.
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

            $churchId = (int) $admin->church_no;
            $adminNo  = (int) $admin->admin_no;
            $token    = JwtHelper::createToken($adminNo, $admin->id, $churchId, 'admin');

            AuditLogHelper::logAction(
                memberId:    $adminNo,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::AUTH,
                actionType:  AuditActionType::LOGIN,
                summary:     "관리자 로그인: {$admin->id}",
                targetId:    (string) $adminNo,
                targetLabel: $admin->id,
            );

            return ApiResponse::success([
                'token'      => $token,
                'actor_type' => 'admin',
                'admin_no'   => $adminNo,
                'login_id'   => $admin->id,
                'church_id'  => $churchId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AuthService] adminSignIn error: " . $e->getMessage(), "auth");
            return ApiResponse::fail('INTERNAL_ERROR', '관리자 로그인 중 오류가 발생했습니다.', 500);
        }
    }

    public function signOut(int $memberId): JsonResponse
    {
        // JWT 는 stateless. 서버 측 토큰 폐기 없이 클라이언트가 토큰을 폐기한다.
        // 추후 토큰 블랙리스트 도입 시 본 메서드에서 처리.
        $member = $this->memberRepository->getMemberById($memberId);
        if ($member !== null) {
            AuditLogHelper::logAction(
                memberId:    $memberId,
                churchId:    (int) $member->church_id,
                menuCode:    AuditMenuCode::AUTH,
                actionType:  AuditActionType::LOGOUT,
                summary:     '로그아웃',
                targetId:    (string) $memberId,
                targetLabel: $member->name,
            );
        }

        return ApiResponse::success(['member_id' => $memberId]);
    }
}
