# 1. 인증 (Auth)

URL prefix: `{{ base_url }}/v4/auth`

| # | 메서드 | URL | 인증 | 설명 |
|---|---|---|---|---|
| 1 | POST | `/v4/auth/adminSignIn` | ❌ | 관리자 로그인 (토큰 발급) |
| 2 | POST | `/v4/auth/signOut` | ✅ | 로그아웃 (stateless, 클라이언트 토큰 폐기) |

> **교인 로그인 없음** — 교적 시스템은 관리자 전용. `gh_church_admin` 테이블 계정만 로그인 가능.

---

## 1. POST `/v4/auth/adminSignIn` — 관리자 로그인

**인증**: 불필요

### 설명

`gh_church_admin` 테이블 기준으로 인증.  
로그인 성공 시 JWT 토큰 발급. 이후 모든 API 요청 헤더에 `Authorization: Bearer {token}` 포함.

### Request Body

```json
{
  "login_id": "test111",
  "password": "!q2w3e4r"
}
```

> dev DB 실제 시드 계정(church_no=33632) — 이전 예시(`admin01`/`adminpass1234`)는 실제 계정과 달랐음.

**파라미터:**

| 필드 | 타입 | 필수 | 설명 |
|---|---|:---:|---|
| `login_id` | string | ✅ | 관리자 로그인 아이디 (max:100) |
| `password` | string | ✅ | 비밀번호 — DB에 bcrypt 해시로 저장 (max:255) |

### Response — 성공 (200)

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

> **token 구조**: JWT(HS256 서명) + `data` 클레임만 AES-256-CBC로 암호화(sub/churchId/adminRole/name).
> `churchName`은 암호화하지 않고 평문 최상위 클레임으로 별도 저장 — 여전히 JWT 서명 범위 안이라
> 위변조는 불가능하지만(기밀성만 없음), Base64 디코딩만으로 바로 읽을 수 있다.
> 상세 내용 → [`docs/token-auth.md`](../token-auth.md)
>
> **왜 churchName만 평문인가**: 사이드바 상단 교회명처럼 민감하지 않은 값은 프론트가 매번 서버에
> 요청하지 않고 토큰에서 직접 꺼내 쓰도록 설계 — `AuthContext`가 로그인 시 토큰을 디코딩해
> `churchName`을 `localStorage`에 저장해두고 이후 새로고침에도 재조회 없이 재사용(`registry_church_name` 키).
> 응답 본문에는 `church_name`을 별도로 담지 않는다(토큰이 유일한 소스).

**token 내부 payload (data 클레임 복호화 후 + 최상위 평문 클레임):**

| 클레임 | 위치 | 설명 |
|---|---|---|
| `sub` | 암호화(`data`) | 관리자 번호 (`admin_no`) |
| `churchId` | 암호화(`data`) | 교회 ID (`church_no`) |
| `adminRole` | 암호화(`data`) | 역할 — `super` (슈퍼 관리자) \| `admin` (교회 관리자) |
| `name` | 암호화(`data`) | 관리자 이름 |
| `iat` | 암호화(`data`) | 발급 시각 (Unix timestamp) |
| `ttl` | 암호화(`data`) | 유효 기간 (초, `.env JWT_TTL`) |
| `churchName` | **평문(최상위)** | 교회명 (`gh_church.name`) — `atob()` 등으로 프론트에서 직접 읽을 수 있음 |

### Response — 실패

| 코드 | HTTP | 원인 |
|---|:---:|---|
| `INVALID_CREDENTIALS` | 401 | 아이디/비밀번호 불일치 또는 status=WAIT |
| `VALIDATION_FAILED` | 400 | 입력 형식 오류 |
| `INTERNAL_ERROR` | 500 | 서버 오류 |

### 참고 — 관리자 계정 상태

| status | 로그인 가능 | 설명 |
|---|:---:|---|
| `ALIVE` | ✅ | 정상 활성 계정 |
| `WAIT` | ❌ | 비활성화(정지) — `INVALID_CREDENTIALS` 반환 |

### 참고 — adminRole 별 권한

| adminRole | 교적 관리 | 관리자 계정 관리 (`/v4/admin/*`) |
|---|:---:|:---:|
| `super` | ✅ | ✅ |
| `admin` | ✅ | ❌ (403) |

---

## 2. POST `/v4/auth/signOut` — 로그아웃

**인증**: 필요 (`Authorization: Bearer {token}`)

### 설명

JWT는 stateless 구조로 서버 측 토큰 폐기가 없음.  
로그아웃은 감사 로그 기록 후 클라이언트가 저장된 토큰을 삭제하는 방식으로 처리.

### Request Body

```json
{}
```

(파라미터 없음)

### Response — 성공 (200)

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "admin_no": 10
  }
}
```

### Response — 실패

| 코드 | HTTP | 원인 |
|---|:---:|---|
| `TOKEN_INVALID` | 401 | 토큰 없음 / 만료 / 서명 불일치 |

---

## Insomnia 설정 참고

### Environment Variables

```json
{
  "base_url": "http://localhost:8000/api",
  "token": ""
}
```

### 로그인 후 token 저장 (After Response Script)

```js
const res = insomnia.response.getBody();
const body = JSON.parse(res);
if (body.status === 'success') {
  insomnia.environment.set('token', body.data.token);
}
```
