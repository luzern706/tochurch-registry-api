<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

/**
 * API 표준 응답 헬퍼
 *
 * 응답 골격:
 * {
 *   "status":  "success" | "fail",
 *   "code":    "" | "ERROR_CODE",
 *   "message": "" | "사용자용 메시지",
 *   "data":    실제 데이터 | null
 * }
 *
 * 규칙:
 * - HTTP 상태 코드는 의미에 맞게 (2xx / 4xx / 5xx)
 * - 성공 시 code, message는 항상 빈 문자열
 * - 실패 시 data는 항상 null
 */
class ApiResponse
{
    /**
     * 성공 응답
     *
     * @param mixed $data 응답 데이터
     * @return JsonResponse
     */
    public static function success(mixed $data = null): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'code'    => '',
            'message' => '',
            'data'    => $data,
        ], 200);
    }

    /**
     * 실패 응답
     *
     * @param string $code        식별 코드 (예: 'INVALID_CREDENTIALS')
     * @param string $message     사용자용 한국어 메시지
     * @param int    $httpStatus  HTTP 상태 코드 (기본 400)
     * @return JsonResponse
     */
    public static function fail(
        string $code,
        string $message,
        int $httpStatus = 400
    ): JsonResponse {
        return response()->json([
            'status'  => 'fail',
            'code'    => $code,
            'message' => $message,
            'data'    => null,
        ], $httpStatus);
    }
}
