<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    /**
     * JWT 인증 미들웨어
     *
     * Authorization 헤더의 Bearer 토큰을 추출/검증하여 통과시킨다.
     * 실패 시 ApiResponse 표준 포맷으로 401 응답을 반환한다.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = JwtHelper::extractTokenFromRequest($request);

        if (empty($token)) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        if (!JwtHelper::validate($token)) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        if (JwtHelper::getAdminNoFromRequest($request) === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $next($request);
    }
}
