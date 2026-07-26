<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // JWT 인증이라 $request->user()는 항상 null → 사실상 IP 기준으로 스코핑됨.
            // 같은 사무실 네트워크(NAT)에서 여러 관리자가 동시 접속하고, 목록 화면 하나가
            // 여러 건의 병렬 요청(목록+KPI 등)을 보내는 구조라 60/min은 너무 낮아 정상 사용
            // 중에도 429가 발생했음(봉사현황 화면에서 재현 확인) — 300/min으로 상향.
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
