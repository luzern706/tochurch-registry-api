<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MaintenanceState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 서비스 점검 상태 조회 (공개, 인증 불필요) — GET /api/maintenance/status
 *
 * 프론트(SPA)가 앱 시작 시·주기적으로 호출해 점검 화면 전환 여부를 판단한다.
 * - maintenance=true  : 이 접속자는 차단 대상 → 점검 화면 표시
 * - bypass=true       : 점검 중이지만 허용 IP(관리자) → 정상 사용 (안내 배너만 표시)
 *
 * 단순 상태 조회라 Service/Repository 계층 없이 MaintenanceState(캐시 포함)를 직접 사용한다.
 */
class MaintenanceController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $state = MaintenanceState::current();
        $bypass = $state['is_on'] && MaintenanceState::isBypass($request->ip(), $state);
        $blocked = $state['is_on'] && !$bypass;

        return ApiResponse::success(MaintenanceState::publicPayload($state, $blocked, $bypass))
            ->withHeaders(['Cache-Control' => 'no-store']);
    }
}
