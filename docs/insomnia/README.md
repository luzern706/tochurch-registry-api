# 교적 관리 API — Insomnia 테스트 가이드

## 1. 기본 설정

### Base URL
- 로컬 개발: `http://127.0.0.1:8000/api`
- 모든 엔드포인트는 위 URL 뒤에 `/v4/{도메인}/{액션}` 형식으로 붙는다.

### 공통 헤더
| 헤더 | 값 | 비고 |
|---|---|---|
| `Content-Type` | `application/json` | 모든 요청 |
| `Accept` | `application/json` | 모든 요청 |
| `Authorization` | `Bearer {token}` | `v4/auth/signIn` 외 모든 엔드포인트 |

### Insomnia Environment 권장 설정
```json
{
  "base_url": "http://127.0.0.1:8000/api",
  "token": ""
}
```
로그인 응답에서 받은 토큰을 `token` 환경 변수에 저장 후, Authorization 헤더에 `Bearer {{ token }}` 형태로 사용.

---

## 2. 인증 흐름

1. `POST /v4/auth/signIn` 호출 → 응답의 `data.token` 추출
2. 이후 모든 요청 헤더에 `Authorization: Bearer {token}` 추가
3. 토큰 만료 시간은 `.env` 의 `JWT_TTL` (기본 3600초)

---

## 3. 공통 응답 형식

### 성공
```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": { ... }
}
```

### 실패
```json
{
  "status": "fail",
  "code": "ERROR_CODE",
  "message": "한국어 메시지",
  "data": null
}
```

### 공통 에러 코드
| 코드 | HTTP | 의미 |
|---|---|---|
| `TOKEN_INVALID` | 401 | 토큰 없음/만료/위변조 |
| `VALIDATION_FAILED` | 400 | 입력값 유효성 검사 실패 |
| `NOT_FOUND` | 404 | 리소스 없음 |
| `DUPLICATE_DATA` | 400 | 중복 데이터 |
| `HAS_CHILDREN` | 400 | 하위 데이터로 인해 삭제 불가 |
| `INVALID_CREDENTIALS` | 401 | 로그인 실패 (signIn 한정) |
| `INSUFFICIENT_PERMISSION` | 403 | 권한 부족 |
| `INTERNAL_ERROR` | 500 | 서버 오류 |

---

## 4. 도메인별 엔드포인트 문서

| # | 도메인 | URL prefix | 파일 |
|---|---|---|---|
| 1 | 인증 | `v4/auth` | [01-auth.md](01-auth.md) — 교인 로그인 / **관리자 로그인** / 로그아웃 |
| 2 | 교인 | `v4/member` | [02-member.md](02-member.md) |
| 3 | 조직 | `v4/organization` | [03-organization.md](03-organization.md) |
| 4 | 가족 관계 | `v4/family` | [04-family.md](04-family.md) |
| 5 | 예배/모임 | `v4/worship` | [05-worship.md](05-worship.md) |
| 6 | 출석 | `v4/attendance` | [06-attendance.md](06-attendance.md) |
| 7 | 심방 | `v4/visit` | [07-visit.md](07-visit.md) |
| 8 | 봉사 | `v4/volunteer` | [08-volunteer.md](08-volunteer.md) |
| 9 | 교육 | `v4/education` | [09-education.md](09-education.md) |
| 10 | 헌금 | `v4/offering` | [10-offering.md](10-offering.md) |
| 11 | 메시지 발송 | `v4/message` | [11-message.md](11-message.md) |
| 12 | 보고서/통계 | `v4/report` | [12-report.md](12-report.md) |

총 라우트 수: **67개** (모두 `POST`)

---

## 5. 테스트 권장 시나리오

1. **로그인** (`v4/auth/signIn`) → 토큰 획득 후 환경 변수 저장
2. **교인 등록** (`v4/member/register`) → member_id 확보
3. 다른 도메인 (`organization`, `family`, `attendance` 등) 에서 위 member_id 활용
4. **로그아웃** (`v4/auth/signOut`) — 서버 stateless 라 토큰 폐기는 클라이언트 책임

---

## 6. 사전 준비

### DB 시드 데이터 필요 항목
- `users` 테이블 — 교회로 시스템과 연동되는 기본 사용자 (선택)
- `churches` 테이블 — 교회 정보 (기존 교회로 테이블, `church_id` 필수)
- `reg_members` 테이블 — 최소 1명 (로그인 테스트용, 이메일·비밀번호 알아야 함)
  - `password` 컬럼은 `Hash::make()` (bcrypt) 결과여야 함
  - HeidiSQL 등으로 직접 입력 시 PHP `tinker` 로 해시 생성 권장:
    ```
    php artisan tinker
    >>> Hash::make('비밀번호')
    ```

### .env JWT 변수
```
JWT_KEY=any-fixed-string
JWT_SECRET=your-secret-min-32-chars
JWT_TTL=3600
```

---

## 7. 감사 로그 자동 기록

테스트 시 알아둘 사항:

- **모든 write 액션** (register / update / delete / assign / unassign / enroll / withdraw / recordBulk / send / signIn / signOut) 은 `reg_audit_logs` 테이블에 자동 INSERT.
- 직접 호출 가능한 API 는 없음 (내부 인프라).
- audit 행 확인: `SELECT * FROM reg_audit_logs WHERE church_id = ? ORDER BY created_at DESC`
- **사전 준비**: 테스트 전 `reg_audit_logs` 테이블을 HeidiSQL 로 먼저 생성. 없으면 audit INSERT 가 실패하며 `web_log/audit_*.log` 파일에만 기록됨 (메인 비즈니스 흐름은 정상 동작).
- audit 행 내용:
  - `summary`: 사람이 읽는 한 줄 설명 (예: "교인 등록: 홍길동 (M-20260518-0001)")
  - `change_detail`: 변경 필드 JSON (예: `{"changes":{"name":{"before":"홍길동","after":"홍변경"}}}`)
  - `ip_address`: 클라이언트 IP 자동 추출
