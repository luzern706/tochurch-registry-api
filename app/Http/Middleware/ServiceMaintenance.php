<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Services\MaintenanceState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 서비스 점검 모드 차단 (전역 미들웨어).
 *
 * 관리자가 02 관리자 화면에서 점검을 켜면(gh_service_maintenance.is_on=1),
 * 허용 IP(관리자) 가 아닌 모든 요청을 HTTP 503 + code=MAINTENANCE 로 막는다.
 * 프론트는 이 응답을 받으면 /api/maintenance/status 로 안내 문구를 다시 읽어 점검 화면으로 전환한다.
 *
 * 예외: CORS preflight(OPTIONS), 점검 상태 조회 엔드포인트, 허용 IP.
 * HandleCors 뒤에 두어 503 응답에도 CORS 헤더가 붙는다.
 */
class ServiceMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $state = MaintenanceState::current();
        if (!$state['is_on']) {
            return $next($request);
        }

        if ($request->is(MaintenanceState::STATUS_PATH) || MaintenanceState::isBypass($request->ip(), $state)) {
            return $next($request);
        }

        $message = $state['message'] !== '' ? $state['message'] : $state['title'];

        return ApiResponse::fail('MAINTENANCE', $message, 503)
            ->withHeaders(['Retry-After' => '60', 'Cache-Control' => 'no-store']);
    }
}
