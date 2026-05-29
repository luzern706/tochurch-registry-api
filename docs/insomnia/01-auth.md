# 1. 인증 (Auth)

URL prefix: `{{ base_url }}/v4/auth`

| # | 메서드 | URL | 인증 | 설명 |
|---|---|---|---|---|
| 1 | POST | `/v4/auth/signIn` | ❌ | 교인 로그인 (토큰 발급) |
| 2 | POST | `/v4/auth/adminSignIn` | ❌ | 관리자 로그인 (gh_church_admin) |
| 3 | POST | `/v4/auth/signOut` | ✅ | 로그아웃 (stateless, 클라이언트 토큰 폐기) |

---

## 1. POST `/v4/auth/signIn` — 교인 로그인

**인증**: 불필요

> **변경 사항 (2026-05-19)**: 응답에 `actor_type: "member"` 필드 추가.
> JWT payload에 `churchId`, `actorType` 클레임이 포함됨.
> **기존 토큰은 재로그인 필요** (churchId 클레임 없는 이전 토큰은 만료 전까지 API 호출 시 TOKEN_INVALID 반환).

### Request Body
```json
{
  "email": "test@example.com",
  "password": "123456"
}
```

**파라미터:**
- `email` (string, required, max:100) — 로그인 이메일
- `password` (string, required, max:255) — 비밀번호

### Response — 성공 (200)
```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "actor_type": "member",
    "member_id": 1,
    "member_no": "M-20260518-0001",
    "name": "홍길동",
    "email": "test@example.com",
    "church_id": 1
  }
}
```

### Response — 실패
- `INVALID_CREDENTIALS` (401) — 이메일/비밀번호 불일치
- `VALIDATION_FAILED` (400) — 입력 형식 오류
- `INTERNAL_ERROR` (500) — 서버 오류

---

## 2. POST `/v4/auth/adminSignIn` — 관리자 로그인

**인증**: 불필요

> 교회 관리자 전용 로그인. `gh_church_admin` 테이블 기준으로 인증.
> 교인 로그인(`signIn`)과 아이디가 중복될 수 있으므로 별도 엔드포인트로 분리.
> 로그인 성공 후 발급된 토큰으로 모든 교적 API (v4/member, v4/attendance 등) 동일하게 호출 가능.

### Request Body
```json
{
  "login_id": "admin01",
  "password": "adminpass"
}
```

**파라미터:**
- `login_id` (string, required, max:100) — gh_church_admin.id 값 (관리자 로그인 아이디)
- `password` (string, required, max:255) — 비밀번호 (bcrypt 해시)

### Response — 성공 (200)
```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "actor_type": "admin",
    "admin_no": 10,
    "login_id": "admin01",
    "church_id": 1
  }
}
```

### Response — 실패
- `INVALID_CREDENTIALS` (401) — 아이디/비밀번호 불일치 또는 상태 WAIT
- `VALIDATION_FAILED` (400) — 입력 형식 오류
- `INTERNAL_ERROR` (500) — 서버 오류

### 참고 — 관리자 계정 상태
| status | 로그인 가능 |
|---|---|
| ALIVE | ✅ |
| WAIT | ❌ (INVALID_CREDENTIALS 반환) |

---

## 3. POST `/v4/auth/signOut` — 로그아웃

**인증**: 필요 (`Authorization: Bearer {token}`)

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
  "data": { "member_id": 1 }
}
```

### Response — 실패
- `TOKEN_INVALID` (401) — 토큰 없음/만료
