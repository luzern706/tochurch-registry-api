# 토큰 인증 & 암복호화 가이드

> 적용 범위: `04_gh_registry_api` (Laravel 10 / PHP 8.1)  
> 인증 방식: JWT (HS256 서명) + AES-256-CBC 페이로드 암호화

---

## 1. 전체 구조 개요

```
┌──────────────────────────────────────────────────────┐
│                   발급된 토큰 (문자열)                    │
│                                                      │
│  JWT Wrapper (HS256 서명)                             │
│  ┌────────────────────────────────────────────────┐  │
│  │  { "data": "eyJpdiI6Oi..." }                   │  │
│  │           └─ AES-256-CBC 암호화된 payload       │  │
│  │              ┌──────────────────────────────┐  │  │
│  │              │ {                            │  │  │
│  │              │   "sub":       33535,        │  │  │
│  │              │   "churchId":  33632,        │  │  │
│  │              │   "adminRole": "admin",      │  │  │
│  │              │   "iat":       1782460600,   │  │  │
│  │              │   "ttl":       86400         │  │  │
│  │              │ }                            │  │  │
│  │              └──────────────────────────────┘  │  │
│  └────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────┘
```

### 보안 레이어 2중 구조

| 레이어 | 기술 | 키 | 목적 |
|---|---|---|---|
| 1 (안쪽) | AES-256-CBC | `APP_KEY` | payload 내용 은닉 |
| 2 (바깥) | HMAC-SHA256 | `JWT_SECRET` | 토큰 위변조 방지 |

---

## 2. 사용 키 정리

### 2-1. APP_KEY (AES 암호화 키)

```env
# .env
APP_KEY=base64:Xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
```

- Laravel 기본 제공 키 (`php artisan key:generate` 로 자동 생성)
- `Crypt::encrypt()` / `Crypt::decrypt()` 가 내부적으로 사용
- **AES-256-CBC** 알고리즘 적용
- 암호화 시마다 랜덤 IV(초기화 벡터) 생성 → 동일 payload도 매번 다른 암호문 생성
- 무결성 검증(HMAC) 내장 → 복호화 시 자동으로 변조 여부 확인

### 2-2. JWT_SECRET (JWT 서명 키)

```env
# .env
JWT_SECRET=your-jwt-secret-must-be-at-least-32-chars!
JWT_TTL=86400
```

- `firebase/php-jwt` 가 사용
- **HS256 (HMAC-SHA256)** 알고리즘 적용
- **최소 32자 (256비트) 이상 필수** — 미달 시 라이브러리가 예외 발생
- `config/jwt.php` 를 통해 로드

```php
// config/jwt.php
return [
    'secret' => env('JWT_SECRET', ''),
    'ttl'    => env('JWT_TTL', 3600),
];
```

---

## 3. 토큰 생성 흐름

```
관리자 로그인 성공
       │
       ▼
① payload 구성 (PHP 배열)
   { sub, churchId, adminRole, iat, ttl }
       │
       ▼
② Crypt::encrypt(json_encode($payload))
   → APP_KEY 로 AES-256-CBC 암호화
   → 랜덤 IV 적용 → 매번 다른 암호문 생성
   → HMAC 무결성 태그 포함
       │
       ▼
③ JWT::encode(['data' => 암호문], JWT_SECRET, 'HS256')
   → JWT_SECRET 으로 HS256 서명
   → Header.Payload.Signature 구조의 JWT 완성
       │
       ▼
④ 클라이언트에 token 전달
```

**코드 위치:** [`app/Helpers/JwtHelper.php`](../app/Helpers/JwtHelper.php) — `createToken()`

```php
public static function createToken(
    int    $adminNo,
    int    $churchId  = 0,
    string $adminRole = 'admin'
): string {
    self::initialize();

    $payload = json_encode([
        'sub'       => $adminNo,
        'churchId'  => $churchId,
        'adminRole' => $adminRole,
        'iat'       => time(),
        'ttl'       => self::$ttl,
    ]);

    return JWT::encode(
        ['data' => Crypt::encrypt($payload)],
        self::$key_secret,
        'HS256'
    );
}
```

---

## 4. 토큰 검증 & 복호화 흐름

```
클라이언트 요청 (Authorization: Bearer {token})
       │
       ▼
① JWT 서명 검증
   JWT::decode(token, JWT_SECRET, 'HS256')
   → 서명 불일치 시 즉시 거부 (401)
       │
       ▼
② AES 복호화
   Crypt::decrypt(outer->data)
   → APP_KEY 불일치 또는 변조 시 DecryptException → 거부
       │
       ▼
③ 만료 시간 검증
   현재시간 - iat > ttl 이면 만료 → 거부
       │
       ▼
④ sub (admin_no) 유효성 검사
   정수 & 양수 여부 확인
       │
       ▼
⑤ payload 반환
   { sub, churchId, adminRole, iat, ttl }
```

**코드 위치:** `JwtHelper::decodeToken()` → `validate()` → `getPayloadFromRequest()`

```php
// JWT 서명 검증 + AES 복호화
public static function decodeToken(string $token): ?\stdClass
{
    $outer = JWT::decode($token, new Key(self::$key_secret, 'HS256')); // ① 서명 검증
    $inner = Crypt::decrypt($outer->data);                             // ② AES 복호화
    return json_decode($inner);
}

// 만료 검증
public static function validate(string $token): bool
{
    $payload = self::decodeToken($token);
    return (time() - $payload->iat) <= $payload->ttl;                 // ③ 만료 확인
}
```

---

## 5. 공격 시나리오별 방어

| 공격 | 시도 | 방어 결과 |
|---|---|---|
| payload 열람 | Base64 디코딩 | `data` 클레임이 AES 암호문 → 내용 불가 |
| payload 변조 | 암호문 수정 후 재조립 | Crypt HMAC 검증 실패 → `DecryptException` |
| 서명 위조 | JWT_SECRET 없이 재서명 | HS256 검증 실패 → 즉시 거부 |
| 토큰 재사용 | 만료된 토큰 제출 | `iat + ttl < 현재시간` → 거부 |
| 키 탈취 시도 | `.env` 접근 | 서버 파일 권한 문제 (인프라 영역) |

---

## 6. payload 클레임 설명

| 클레임 | 타입 | 설명 |
|---|---|---|
| `sub` | int | 관리자 번호 (`gh_church_admin.admin_no`) |
| `churchId` | int | 교회 ID (`gh_church_admin.church_no`) |
| `adminRole` | string | 관리자 역할 — `super` 또는 `admin` |
| `iat` | int | 발급 시각 (Unix timestamp) |
| `ttl` | int | 유효 기간 (초) — `.env` 의 `JWT_TTL` 값 |

> **민감 정보 제외 원칙:** 비밀번호, 주민번호 등은 절대 payload에 포함하지 않는다.

---

## 7. 클라이언트 사용 방법

### 7-1. 로그인 후 토큰 저장

```js
const res = await api.post('/v4/auth/adminSignIn', { login_id, password });
localStorage.setItem('token', res.data.data.token);
```

### 7-2. 이후 모든 요청에 헤더 첨부

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...
```

Axios 인터셉터 예시:
```js
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
```

### 7-3. adminRole 읽기 (JWT 직접 파싱)

`adminRole` 은 응답에 포함되지 않으므로 클라이언트가 JWT payload를 직접 파싱한다.  
JWT payload 는 AES 암호화되어 있으나, **JWT의 두 번째 파트(outer payload)는 Base64이므로 클라이언트에서 읽을 수 있다.**

단, outer payload 의 `data` 클레임은 AES 암호문이므로 실제 값은 읽을 수 없다.

```
※ 클라이언트에서 adminRole 을 얻는 방법
   → 로그인 후 별도 API 호출: POST /v4/auth/me (추가 구현 필요 시)
   → 또는 로그인 응답에 admin_role 포함 (현재 미포함)
```

> **현재 구현:** 로그인 응답에 `token` 만 반환.  
> 프론트에서 `adminRole` 이 필요하면 `POST /v4/auth/me` 엔드포인트 추가를 검토한다.

---

## 8. 키 관리 가이드

### 8-1. 최초 세팅

```bash
# APP_KEY 생성 (Laravel 자동 생성)
php artisan key:generate
# → .env 의 APP_KEY 에 자동으로 base64: 형식으로 저장됨

# JWT_SECRET 은 직접 설정 (32자 이상 랜덤 문자열)
# 예시: openssl rand -base64 32
```

### 8-2. .env 예시

```env
APP_KEY=base64:Xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=

JWT_SECRET=AbCdEfGhIjKlMnOpQrStUvWxYz0123456789!!
JWT_TTL=86400
```

### 8-3. 운영/개발 환경 분리 원칙

| 규칙 | 이유 |
|---|---|
| 로컬과 운영 서버의 키는 **다르게** 설정 | 로컬 키 노출 시 운영 토큰에 영향 없음 |
| `.env` 파일은 절대 git 커밋 금지 | `.gitignore` 에 `.env` 포함 확인 |
| `.env.example` 에는 키 이름만, 값은 빈칸 | 팀원 참조용 |
| 키 변경 시 **기존 토큰 전체 무효화** | APP_KEY 또는 JWT_SECRET 변경 시 재로그인 필요 |

### 8-4. 키 변경이 필요한 경우

```bash
# APP_KEY 재생성 (기존 Crypt 암호화 데이터 전체 무효화)
php artisan key:generate

# JWT_SECRET 변경 → .env 에서 직접 수정
# → 변경 즉시 기존 JWT 토큰 모두 검증 실패 (전체 재로그인)
```

---

## 9. 관련 파일

| 파일 | 역할 |
|---|---|
| [`app/Helpers/JwtHelper.php`](../app/Helpers/JwtHelper.php) | 토큰 생성·검증·복호화 전담 헬퍼 |
| [`config/jwt.php`](../config/jwt.php) | JWT 설정 (secret, ttl) |
| [`app/Http/Middleware/JwtAuthMiddleware.php`](../app/Http/Middleware/JwtAuthMiddleware.php) | 라우트 보호 미들웨어 (`jwt.auth`) |
| [`app/Http/Controllers/Api/AuthController.php`](../app/Http/Controllers/Api/AuthController.php) | 로그인·로그아웃 엔드포인트 |
| [`app/Services/AuthService.php`](../app/Services/AuthService.php) | 로그인 비즈니스 로직, 토큰 발급 |

---

*최종 수정: 2026-06-20*
