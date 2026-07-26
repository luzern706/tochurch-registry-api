<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $role  = JwtHelper::getAdminRoleFromRequest();
        $token = $request->bearerToken();

        LogHelper::logWrite(
            "[SuperAdminMiddleware] role={$role} | token=" . ($token ? substr($token, 0, 30) . '...' : 'EMPTY'),
            "super_auth"
        );

        if ($role !== 'admin') {
            return ApiResponse::fail('INSUFFICIENT_PERMISSION', '관리자 권한이 필요합니다.', 403);
        }

        return $next($request);
    }
}
