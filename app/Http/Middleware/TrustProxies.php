<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    public function __construct()
    {
        // 서버가 리버스 프록시(Apache)/ELB 뒤에 있어 $request->ip()가 127.0.0.1로 잡힘(점검 모드 관리자 IP 허용 판정에도 필요).
        // 기본은 전체 신뢰('*' = 직전 접속 IP 만 프록시로 신뢰). 프록시 IP를 알면 .env의 TRUSTED_PROXIES(쉼표 구분)로 좁힐 것 — config/app.php 'trusted_proxies' 경유.
        $env = config('app.trusted_proxies');
        if (!empty($env)) {
            $this->proxies = $env === '*' ? '*' : array_map('trim', explode(',', $env));
        }
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
