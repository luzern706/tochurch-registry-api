<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class JwtHelper
{
    private static $key; // .env의 JWT_KEY 값을 저장
    private static $key_secret; // .env의 JWT_SECRET 값을 저장
    private static $ttl; // .env의 JWT_TTL 값을 저장

    // 초기화 메서드
    public static function initialize(): void
    {
        // 설정 파일에서 읽기
        self::$key_secret = config('jwt.secret');
        self::$key = config('jwt.key');
        self::$ttl = config('jwt.ttl');

    }

    public static function createToken(
        int    $adminNo,
        string $adminId,
        int    $churchId   = 0,
        string $actorType  = 'member'
    ): string {
        self::initialize();

        $token = [
            'consumerKey' => self::$key,
            'sub'         => $adminNo,
            'userId'      => $adminId,
            'churchId'    => $churchId,
            'actorType'   => $actorType,
            'iat'         => time(),
            'ttl'         => self::$ttl,
        ];

        return JWT::encode($token, self::$key_secret, 'HS256');
    }

    public static function decodeToken($token): ?\stdClass
    {
        self::initialize(); // 초기화

        try {

            // JWT 디코딩
            $decoded = JWT::decode($token, new Key(self::$key_secret, 'HS256'));

            return $decoded;
        } catch (\Exception $e) {
            $error_message = "[JwtHelper] decodeToken() Exception: " . $e->getMessage();
            //LogHelper::logWrite($error_message, "token");

            return null; // Invalid token
        }
    }

    public static function validate($token): bool
    {
        self::initialize();

        try {
            $decodedToken = self::decodeToken($token);

            if ($decodedToken === null) {
                return false;
            }

            // 발행 시간 (iat) 및 만료 시간 (ttl) 검증
            if (!isset($decodedToken->iat) || !isset($decodedToken->ttl)) {
                return false;
            }

            $iat = (int) $decodedToken->iat;
            $ttl = (int) $decodedToken->ttl;
            $currentTime = time();

            $timeDifference = $currentTime - $iat;

            if ($timeDifference > $ttl) {
                throw new \Exception('Token expired');
            }

            return true;
        } catch (\Exception $e) {
            //LogHelper::logWrite("[JwtHelper] validate() Exception: " . $e->getMessage(), "token");
            return false;
        }
    }

    // ───────────────────────── 신규 추가 (v4용) ─────────────────────────

    /**
     * Authorization 헤더에서 Bearer 토큰만 추출
     *
     * 'Bearer xxx' 또는 'bearer xxx' 형식에서 prefix 제거 후 토큰만 반환.
     * prefix 없이 raw 토큰만 들어와도 그대로 반환 (v1 호환).
     *
     * @param Request $request
     * @return string|null  토큰 문자열 또는 헤더 없을 시 null
     */
    public static function extractTokenFromRequest(Request $request): ?string
    {
        // Laravel 표준 메서드 우선 (Bearer prefix 자동 제거)
        $token = $request->bearerToken();
        if (!empty($token)) {
            return $token;
        }

        // bearerToken()이 못 잡는 경우 — 헤더에 prefix 없이 raw 토큰만 있는 경우 (v1 호환)
        $authHeader = $request->header('Authorization');
        if (empty($authHeader)) {
            return null;
        }

        // 혹시 'Bearer ' prefix가 붙어 있다면 한 번 더 제거 (방어 코드)
        return preg_replace('/^Bearer\s+/i', '', trim($authHeader));
    }

    public static function getPayloadFromRequest(Request $request): ?\stdClass
    {
        // 1. 헤더에서 토큰 추출
        $token = self::extractTokenFromRequest($request);
        if (empty($token)) {
            return null;
        }

        // 2. 만료/위변조 검증
        if (!self::validate($token)) {
            return null;
        }

        // 3. 디코드
        $decoded = self::decodeToken($token);

        if (!$decoded || !isset($decoded->sub)) {
            return null;
        }

        // 4. sub 가드 — 정수가 아니거나 숫자 문자열이 아니면 거부
        //    (잘못 발급된 v1/legacy 토큰: 문자열 sub 차단)
        if (!is_int($decoded->sub) && !ctype_digit((string) $decoded->sub)) {
            return null;
        }

        // 5. 0 이하 차단
        $adminNo = (int) $decoded->sub;
        if ($adminNo <= 0) {
            return null;
        }

        // 6. 정규화 — sub을 검증된 정수로 덮어씀 (호출자 편의)
        $decoded->sub = $adminNo;

        return $decoded;
    }

    public static function getUserIdFromRequest(Request $request): ?int
    {
        $payload = self::getPayloadFromRequest($request);
        return $payload?->userId;
    }

    public static function getAdminNoFromRequest(Request $request): ?int
    {
        $payload = self::getPayloadFromRequest($request);

        return $payload?->sub;
    }

    /**
     * 현재 요청의 JWT payload 에서 churchId 를 추출.
     *
     * Service 레이어에서 request() 헬퍼를 통해 직접 호출 가능.
     * 토큰이 없거나 churchId 클레임이 없으면 null 반환.
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
     * 현재 요청의 JWT payload 에서 actorType 을 추출.
     * 'member' | 'admin' 중 하나. 없으면 'member' 기본값 반환.
     */
    public static function getActorTypeFromRequest(): string
    {
        $request = request();
        if ($request === null) {
            return 'member';
        }

        $token = self::extractTokenFromRequest($request);
        if (empty($token)) {
            return 'member';
        }

        $decoded = self::decodeToken($token);

        return $decoded?->actorType ?? 'member';
    }

}
