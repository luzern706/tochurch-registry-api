<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * 서비스 점검 모드 상태 (gh_service_maintenance, 단일 행 id=1).
 *
 * 관리자(02_gh_admin_api)가 저장하고, 앱웹(03)·교적(04) API 가 같은 DB 에서 읽는다.
 * 매 요청마다 DB 를 치지 않도록 CACHE_SECONDS 동안 캐시한다 → 토글 후 최대 이 시간 안에 반영.
 * 조회에 실패하면(테이블 미생성·DB 장애) 점검 아님으로 간주한다(fail-open) — 점검 기능 때문에 서비스가 멎으면 안 된다.
 */
class MaintenanceState
{
    private const CACHE_KEY = 'service_maintenance_state';
    private const CACHE_SECONDS = 5;

    /** 점검 중에도 통과시킬 경로 (프론트가 점검 여부를 확인하는 공개 엔드포인트) */
    public const STATUS_PATH = 'api/maintenance/status';

    /** @return array{is_on: bool, title: string, message: string, end_at: ?string, allowed_ips: array<int,string>} */
    public static function current(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static function (): array {
            $state = [
                'is_on' => false,
                'title' => '서비스 점검 중입니다',
                'message' => '',
                'end_at' => null,
                'allowed_ips' => [],
            ];

            try {
                $row = DB::table('gh_service_maintenance')->where('id', 1)->first();
            } catch (\Throwable $e) {
                return $state;
            }

            if ($row) {
                $state['is_on'] = (int) $row->is_on === 1;
                $state['title'] = (string) ($row->title ?: $state['title']);
                $state['message'] = (string) ($row->message ?? '');
                $state['end_at'] = $row->end_at ? substr((string) $row->end_at, 0, 16) : null;
                $state['allowed_ips'] = array_values(array_filter(
                    array_map('trim', preg_split('/[\r\n]+/', (string) ($row->allowed_ips ?? '')))
                ));
            }

            return $state;
        });
    }

    /** 점검 중에도 접속이 허용된 IP(관리자) 인지 */
    public static function isBypass(?string $ip, array $state): bool
    {
        if ($ip === null || $ip === '' || empty($state['allowed_ips'])) {
            return false;
        }

        return IpUtils::checkIp($ip, $state['allowed_ips']);
    }

    /** 프론트가 읽는 공개 정보 (허용 IP 목록은 절대 노출하지 않는다) */
    public static function publicPayload(array $state, bool $maintenance, bool $bypass): array
    {
        return [
            'maintenance' => $maintenance,
            'bypass' => $bypass,
            'title' => $state['title'],
            'message' => $state['message'],
            'end_at' => $state['end_at'],
        ];
    }
}
