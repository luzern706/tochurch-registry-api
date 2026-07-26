<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Helpers\LogHelper;

class JwtHelper
{
    private static $key_secret;
    private static $ttl;

    public static function initialize(): void
    {
        self::$key_secret = config('jwt.secret');
        self::$ttl        = config('jwt.ttl');
        // APP_KEY 는 Crypt 파사드가 자동으로 사용 (별도 설정 불필요)
    }

    /**
     * 토큰 생성
     *
     * 민감한 클레임(sub/churchId/adminRole/name)은 AES-256-CBC(Crypt)로 암호화해
     * HS256 서명 JWT의 data 클레임에 담는다.
     *
     * churchName은 암호화하지 않고 최상위 평문 클레임으로 별도 저장 — 사이드바 교회명처럼
     * 프론트가 서버 왕복 없이 토큰에서 직접 꺼내 쓸 수 있어야 하는 값이라 이렇게 분리.
     * 여전히 JWT 서명(HS256) 범위 안이라 위변조는 불가능(기밀성만 없음, 민감정보 아님).
     *
     * 구조: JWT(HS256 서명) { data: AES-256-CBC(payload JSON), churchName: "..." }
     */
    public static function createToken(
        int    $adminNo,
        int    $churchId   = 0,
        string $adminRole  = 'admin',
        string $name       = '',
        string $churchName = ''
    ): string {
        self::initialize();

        $payload = json_encode([
            'sub'       => $adminNo,
            'churchId'  => $churchId,
            'adminRole' => $adminRole,
            'name'      => $name,
            'iat'       => time(),
            'ttl'       => self::$ttl,
        ]);

        return JWT::encode(
            ['data' => Crypt::encrypt($payload), 'churchName' => $churchName],
            self::$key_secret,
            'HS256'
        );
    }

    /**
     * 토큰 디코딩
     *
     * 1. JWT 서명 검증 (HS256)
     * 2. data 클레임을 AES 복호화
     * 3. 복호화된 payload 반환
     */
    public static function decodeToken(string $token): ?\stdClass
    {
        self::initialize();

        try {
            $outer = JWT::decode($token, new Key(self::$key_secret, 'HS256'));

            if (!isset($outer->data)) {
                return null;
            }

            $inner = Crypt::decrypt($outer->data);

            return json_decode($inner) ?: null;
        } catch (DecryptException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function validate(string $token): bool
    {
        try {
            $payload = self::decodeToken($token);

            if ($payload === null) {
                return false;
            }

            if (!isset($payload->iat) || !isset($payload->ttl)) {
                return false;
            }

            if ((time() - (int) $payload->iat) > (int) $payload->ttl) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Authorization 헤더에서 Bearer 토큰 추출
     */
    public static function extractTokenFromRequest(Request $request): ?string
    {
        $token = $request->bearerToken();
        if (!empty($token)) {
            return $token;
        }

        $authHeader = $request->header('Authorization');
        if (empty($authHeader)) {
            return null;
        }

        return preg_replace('/^Bearer\s+/i', '', trim($authHeader));
    }

    public static function getPayloadFromRequest(Request $request): ?\stdClass
    {
        $token = self::extractTokenFromRequest($request);
        if (empty($token)) {
            return null;
        }

        if (!self::validate($token)) {
            return null;
        }

        $decoded = self::decodeToken($token);

        if (!$decoded || !isset($decoded->sub)) {
            return null;
        }

        if (!is_int($decoded->sub) && !ctype_digit((string) $decoded->sub)) {
            return null;
        }

        $adminNo = (int) $decoded->sub;
        if ($adminNo <= 0) {
            return null;
        }

        $decoded->sub = $adminNo;

        return $decoded;
    }

    public static function getAdminNoFromRequest(Request $request): ?int
    {
        return self::getPayloadFromRequest($request)?->sub;
    }

    /**
     * 현재 요청 JWT에서 adminRole 추출. 실패 시 'admin' 반환.
     */
    public static function getAdminRoleFromRequest(): string
    {
        $request = request();
        if ($request === null) {
            LogHelper::logWrite("[JwtHelper] getAdminRoleFromRequest: request is null", "super_auth");
            return 'admin';
        }

        $token = self::extractTokenFromRequest($request);
        if (empty($token)) {
            LogHelper::logWrite("[JwtHelper] getAdminRoleFromRequest: token is empty", "super_auth");
            return 'admin';
        }

        $decoded = self::decodeToken($token);
        $role    = $decoded?->adminRole ?? 'admin';

        LogHelper::logWrite(
            "[JwtHelper] getAdminRoleFromRequest: decoded=" . ($decoded ? 'OK' : 'NULL')
            . " | adminRole=" . ($decoded->adminRole ?? 'NOT_SET')
            . " | sub=" . ($decoded->sub ?? 'NOT_SET')
            . " | churchId=" . ($decoded->churchId ?? 'NOT_SET'),
            "super_auth"
        );

        return $role;
    }

    /**
     * 현재 요청 JWT에서 churchId 추출. 실패 시 null 반환.
     */
    public static function getChurchIdFromRequest(): ?int
    {
        $request = request();
        if ($request === null) {
            return null;
        }

        $token = self::extractTokenFromRequest($request);
        if (empty($token)) {
            return null;
        }

        if (!self::validate($token)) {
            return null;
        }

        $decoded = self::decodeToken($token);
        if ($decoded === null || !isset($decoded->churchId)) {
            return null;
        }

        $churchId = (int) $decoded->churchId;

        return $churchId > 0 ? $churchId : null;
    }

    /**
     * 현재 요청 JWT에서 churchName 추출. 실패 시 null 반환.
     *
     * churchName은 암호화되지 않은 최상위 클레임이라 data 복호화 없이 JWT 서명만
     * 검증해서 바로 읽는다(decodeToken()은 data 클레임만 복호화해 반환하므로 사용 불가).
     */
    public static function getChurchNameFromRequest(): ?string
    {
        $request = request();
        if ($request === null) {
            return null;
        }

        $token = self::extractTokenFromRequest($request);
        if (empty($token)) {
            return null;
        }

        self::initialize();

        try {
            $outer = JWT::decode($token, new Key(self::$key_secret, 'HS256'));
            return $outer->churchName ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
