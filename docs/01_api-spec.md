# 교적 관리 API 명세

> **base_url**: `http://127.0.0.1:8098/api`
> **인증**: `Authorization: Bearer {token}` (로그인 엔드포인트 제외 모든 요청 필수)
> **모든 요청**: `POST`, `Content-Type: application/json`

---

## 공통 응답 구조

```json
{
  "status":  "success" | "fail",
  "code":    "" | "ERROR_CODE",
  "message": "" | "사용자용 메시지",
  "data":    실제데이터 | null
}
```

### 공통 에러 코드

| 코드 | HTTP | 설명 |
|---|---|---|
| `TOKEN_INVALID` | 401 | 인증 실패 (토큰 없음 또는 만료) |
| `VALIDATION_FAILED` | 400 | 유효성 검사 실패 |
| `NOT_FOUND` | 404 | 데이터 없음 |
| `INSUFFICIENT_PERMISSION` | 403 | 권한 없음 |
| `DUPLICATE_DATA` | 400 | 중복 데이터 |
| `INTERNAL_ERROR` | 500 | 서버 내부 오류 |
| `HAS_CHILDREN` | 400 | 하위 데이터 존재로 삭제 불가 |
| `INVALID_CREDENTIALS` | 401 | 아이디/비밀번호 불일치 |

---

## 1. 인증 (Auth)

### POST /v4/auth/adminSignIn

관리자 로그인. `gh_church_admin` 테이블 대상. JWT 발급. 로그인 성공 시 `last_login_at` 자동 업데이트.

- **인증 필요**: 아니오

> `ALIVE` 상태 계정만 로그인 가능. `INACTIVE` · `DELETE` 상태는 `INVALID_CREDENTIALS(401)` 반환.

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `login_id` | string(100) | Y | 관리자 아이디 |
| `password` | string(255) | Y | 비밀번호 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "token": "eyJ..."
  }
}
```

> JWT payload 클레임: `sub`(admin_no), `churchId`, `adminRole`(`admin_type` 값), `iat`, `ttl`

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 아이디/비밀번호 불일치 또는 비활성·삭제 계정 | `INVALID_CREDENTIALS` | 401 |
| 필수 파라미터 누락 | `VALIDATION_FAILED` | 400 |

---

### POST /v4/auth/signOut

관리자 로그아웃. JWT는 stateless이므로 서버 측 폐기 없음. 클라이언트가 토큰 파기.

- **인증 필요**: 예

**Request**

없음 (토큰에서 관리자 번호 추출)

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "admin_no": 1
  }
}
```

---

## 2. 관리자 계정 관리 (Admin)

> **관리자(admin) 전용** (`admin_type = 'admin'`). `jwt.auth` + `super.auth` 미들웨어 적용.
>
> **상태(status) 값**: `ALIVE`(활성) / `INACTIVE`(비활성) / `DELETE`(소프트 삭제)
>
> **admin_type 값**: `admin`(관리자) / `pastor`(담임목회자) / `minister`(사역자) / `volunteer`(봉사자)

### POST /v4/admin/getList

관리자 목록 조회 (소속 교회 기준). **ALIVE · INACTIVE · DELETE 모든 상태 포함** 출력.

- **인증 필요**: 예 (admin)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `search_type` | string | N | 검색 컬럼 선택: `id` \| `name` (`search_value` 입력 시 필수) |
| `search_value` | string(100) | N | 검색어 부분 일치 (`search_type` 선택 시 필수) |
| `status` | string | N | 상태 필터: `ALIVE` \| `INACTIVE` \| `DELETE` |
| `admin_type` | string | N | 유형 필터: `admin` \| `pastor` \| `minister` \| `volunteer` |
| `page` | integer | N | 페이지 번호 (기본값: 1) |
| `size` | integer | N | 페이지당 개수 (기본값: 20, 최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 5,
    "list": [
      {
        "admin_no": 1,
        "church_no": 10,
        "id": "admin01",
        "name": "홍길동",
        "status": "ALIVE",
        "admin_type": "admin",
        "last_login_at": "2026-06-27T10:00:00.000000Z",
        "registered": "2026-01-01T00:00:00.000000Z"
      }
    ]
  }
}
```

---

### POST /v4/admin/getDetail

관리자 상세 조회. DELETE 상태는 조회 불가.

- **인증 필요**: 예 (admin)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `admin_no` | integer | Y | 관리자 번호 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "admin_no": 1,
    "church_no": 10,
    "id": "admin01",
    "name": "홍길동",
    "status": "ALIVE",
    "admin_type": "admin",
    "last_login_at": "2026-06-27T10:00:00.000000Z",
    "registered": "2026-01-01T00:00:00.000000Z"
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 관리자 없음 또는 DELETE 상태 | `NOT_FOUND` | 404 |

---

### POST /v4/admin/register

신규 관리자 등록. 등록 즉시 `ALIVE` 상태.

- **인증 필요**: 예 (admin)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `login_id` | string(100) | Y | 로그인 아이디 |
| `name` | string(50) | Y | 이름 |
| `password` | string | Y | 비밀번호 (최소 8자) |
| `admin_type` | string | Y | `admin` \| `pastor` \| `minister` \| `volunteer` |

**Response (성공)**

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

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 아이디 중복 | `DUPLICATE_DATA` | 400 |

---

### POST /v4/admin/update

관리자 정보 수정 (이름 / 비밀번호 / 유형 변경). DELETE 상태 계정은 수정 불가.

- **인증 필요**: 예 (admin)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `admin_no` | integer | Y | 관리자 번호 |
| `name` | string(50) | N | 이름 |
| `password` | string | N | 새 비밀번호 (최소 8자) |
| `admin_type` | string | N | `admin` \| `pastor` \| `minister` \| `volunteer` |

**Response (성공)**

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

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 수정할 항목 없음 | `VALIDATION_FAILED` | 400 |
| 관리자 없음 또는 DELETE 상태 | `NOT_FOUND` | 404 |

---

### POST /v4/admin/suspend

관리자 계정 비활성화 (status → `INACTIVE`). ALIVE · DELETE 상태 모두 적용 가능 (DELETE 상태 복원 포함).

- **인증 필요**: 예 (admin)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `admin_no` | integer | Y | 관리자 번호 |

**Response (성공)**

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

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 자기 자신 비활성화 시도 | `INSUFFICIENT_PERMISSION` | 403 |
| 이미 INACTIVE 상태 | `DUPLICATE_DATA` | 400 |

---

### POST /v4/admin/activate

관리자 계정 활성화 (status → `ALIVE`). INACTIVE · DELETE 상태 모두 적용 가능 (DELETE 상태 복원 포함).

- **인증 필요**: 예 (admin)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `admin_no` | integer | Y | 관리자 번호 |

**Response (성공)**

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

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 이미 ALIVE 상태 | `DUPLICATE_DATA` | 400 |

---

### POST /v4/admin/delete

관리자 소프트 삭제 (status → `DELETE`). 목록(getList)에는 계속 표시됨. `activate` / `suspend`로 복원 가능.

- **인증 필요**: 예 (admin)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `admin_no` | integer | Y | 관리자 번호 |

**Response (성공)**

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

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 자기 자신 삭제 시도 | `INSUFFICIENT_PERMISSION` | 403 |
| 관리자 없음 또는 타 교회 | `NOT_FOUND` | 404 |

---

### POST /v4/admin/purge

소프트 삭제(`DELETE` 상태)된 관리자 DB 영구 삭제. 복구 불가.

- **인증 필요**: 예 (admin)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `admin_no` | integer | Y | 관리자 번호 (DELETE 상태인 것만 가능) |

**Response (성공)**

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

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| DELETE 상태가 아닌 관리자 | `NOT_FOUND` | 404 |
| 자기 자신 삭제 시도 | `INSUFFICIENT_PERMISSION` | 403 |

---

## 3. 교인 (Member)

### POST /v4/member/getList

교인 목록 조회 (교회 기준, 페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `keyword` | string(100) | N | 이름/이메일 검색 키워드 |
| `status` | string | N | `active` \| `inactive` \| `unknown` |
| `page` | integer | N | 페이지 번호 (최소: 1) |
| `size` | integer | N | 페이지당 개수 (최소: 1, 최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 100,
    "list": [...]
  }
}
```

---

### POST /v4/member/getDetail

교인 상세 조회 (계정 정보 + 교적 프로필).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID (최소: 1) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member": {
      "id": 1,
      "member_no": "M-20260626-0001",
      "church_id": 10,
      "email": "hong@example.com",
      "name": "홍길동",
      "nickname": "길동",
      "phone": "010-1234-5678",
      "gender": "M",
      "birth_date": "1990-01-01",
      "birth_type": "solar",
      "address_zip": "12345",
      "address_main": "서울시 강남구",
      "address_detail": "101동 101호",
      "profile_image": null,
      "status": "active",
      "memo": null,
      "churchero_user_id": null,
      "created_at": "2026-06-26T00:00:00.000000Z"
    },
    "profile": {
      "member_id": 1,
      "member_type": "성도",
      "position": "집사",
      "baptism_grade": "세례",
      "attendance_grade": "A",
      "marriage_status": "married",
      "is_household_head": true,
      "registered_at": "2020-03-01"
    }
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 교인 없음 또는 다른 교회 | `NOT_FOUND` | 404 |

---

### POST /v4/member/register

신규 교인 등록 (`reg_members` + `reg_member_profiles` 트랜잭션).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `email` | string(100) | Y | 이메일 (교회 내 중복 불가) |
| `password` | string(100) | Y | 비밀번호 (최소 4자) |
| `name` | string(50) | Y | 이름 |
| `nickname` | string(50) | N | 별명 |
| `phone` | string(20) | N | 휴대전화 |
| `gender` | string | N | `M` \| `F` |
| `birth_date` | date | N | 생년월일 |
| `birth_type` | string | N | `solar` \| `lunar` |
| `address_zip` | string(10) | N | 우편번호 |
| `address_main` | string(200) | N | 주소 |
| `address_detail` | string(100) | N | 상세주소 |
| `profile_image` | string(500) | N | 프로필 이미지 URL |
| `status` | string | N | `active` \| `inactive` \| `unknown` |
| `memo` | string | N | 메모 |
| `churchero_user_id` | integer | N | 교회로 연동 user ID |
| `member_type` | string(30) | N | 교인 유형 |
| `member_type_source` | string | N | `system` \| `user` |
| `position` | string(30) | N | 직분 |
| `baptism_grade` | string(20) | N | 세례 구분 |
| `attendance_grade` | string(10) | N | 출석 등급 |
| `ordained_at` | date | N | 안수일 |
| `ordained_church` | string(100) | N | 안수 교회 |
| `baptism_at` | date | N | 세례일 |
| `baptism_church` | string(100) | N | 세례 교회 |
| `registered_at` | date | N | 등록일 |
| `welcomed_at` | date | N | 환영 주일 |
| `previous_church` | string(100) | N | 이전 교회 |
| `leader_member_id` | integer | N | 담당 목회자/리더 교인 ID |
| `marriage_status` | string | N | `single` \| `married` \| `widowed` \| `divorced` |
| `is_household_head` | boolean | N | 세대주 여부 |
| `household_relation` | string(20) | N | 세대주와의 관계 |
| `workplace` | string(100) | N | 직장 |
| `custom_field_1` | string(200) | N | 사용자 정의 필드 1 |
| `custom_field_2` | string(200) | N | 사용자 정의 필드 2 |
| `custom_field_3` | string(200) | N | 사용자 정의 필드 3 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "member_no": "M-20260626-0001"
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 이메일 중복 | `DUPLICATE_DATA` | 400 |

---

### POST /v4/member/update

교인 정보 수정 (부분 수정 가능).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |
| (register의 나머지 필드) | - | N | 변경할 항목만 전송 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 교인 없음 | `NOT_FOUND` | 404 |
| 이메일 중복 | `DUPLICATE_DATA` | 400 |

---

### POST /v4/member/delete

교인 소프트 삭제.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 교인 없음 | `NOT_FOUND` | 404 |
| 본인 계정 삭제 시도 | `INSUFFICIENT_PERMISSION` | 403 |

---

## 4. 조직 관리 (Organization)

### POST /v4/organization/getList

조직 목록 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `parent_id` | integer | N | 상위 조직 ID (null이면 최상위만) |
| `is_active` | boolean | N | 활성화 여부 필터 |
| `keyword` | string(50) | N | 조직명 검색 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "list": [...]
  }
}
```

---

### POST /v4/organization/getDetail

조직 상세 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `organization_id` | integer | Y | 조직 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "organization": {
      "id": 1,
      "church_id": 10,
      "parent_id": null,
      "name": "남전도회",
      "sort_order": 0,
      "is_active": 1
    }
  }
}
```

---

### POST /v4/organization/register

조직 등록.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `name` | string(50) | Y | 조직명 |
| `parent_id` | integer | N | 상위 조직 ID |
| `sort_order` | integer | N | 정렬 순서 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "organization_id": 5
  }
}
```

---

### POST /v4/organization/update

조직 정보 수정.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `organization_id` | integer | Y | 조직 ID |
| `name` | string(50) | N | 조직명 |
| `parent_id` | integer\|null | N | 상위 조직 ID (null로 보내면 최상위로 변경) |
| `sort_order` | integer | N | 정렬 순서 |
| `is_active` | boolean | N | 활성화 여부 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "organization_id": 5
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 자기 자신을 상위 조직으로 지정 | `VALIDATION_FAILED` | 400 |
| 하위 조직을 상위로 지정 (순환참조) | `VALIDATION_FAILED` | 400 |

---

### POST /v4/organization/delete

조직 삭제 (소프트, 매핑 정리). 하위 조직이 있으면 삭제 불가.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `organization_id` | integer | Y | 조직 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "organization_id": 5
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 하위 조직 존재 | `HAS_CHILDREN` | 400 |

---

### POST /v4/organization/assignMember

교인을 조직에 배정 (이미 매핑 있으면 갱신, `is_primary=1` 시 기존 주소속 해제 후 설정).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |
| `organization_id` | integer | Y | 조직 ID |
| `is_primary` | boolean | N | 주소속 여부 (기본: false) |
| `joined_at` | date | N | 가입일 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "organization_id": 5
  }
}
```

---

### POST /v4/organization/unassignMember

교인-조직 매핑 해제.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |
| `organization_id` | integer | Y | 조직 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "organization_id": 5
  }
}
```

---

### POST /v4/organization/getMembersByOrg

조직 소속 교인 목록 조회 (페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `organization_id` | integer | Y | 조직 ID |
| `keyword` | string(100) | N | 이름 검색 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 30,
    "list": [...]
  }
}
```

---

## 5. 가족 관계 (Family)

> 양방향 자동 동기화: A→B 등록 시 B→A 역방향도 자동 생성.
> 역방향 관계 변환: 부모↔자녀, 배우자↔배우자, 형제자매↔형제자매, 기타↔기타

### POST /v4/family/getList

특정 교인의 가족 목록 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "list": [
      {
        "related_member_id": 2,
        "relation_type": "배우자",
        "family_note": null
      }
    ]
  }
}
```

---

### POST /v4/family/register

가족 관계 등록 (양방향 자동 생성).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |
| `related_member_id` | integer | Y | 관련 교인 ID (member_id와 달라야 함) |
| `relation_type` | string | Y | `배우자` \| `부모` \| `자녀` \| `형제자매` \| `기타` |
| `family_note` | string | N | 가족 특이사항 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "related_member_id": 2,
    "relation_type": "배우자"
  }
}
```

---

### POST /v4/family/update

가족 관계 수정 (양방향 동기화).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |
| `related_member_id` | integer | Y | 관련 교인 ID |
| `relation_type` | string | N | `배우자` \| `부모` \| `자녀` \| `형제자매` \| `기타` |
| `family_note` | string\|null | N | 가족 특이사항 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "related_member_id": 2,
    "relation_type": "부모"
  }
}
```

---

### POST /v4/family/delete

가족 관계 삭제 (양방향).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |
| `related_member_id` | integer | Y | 관련 교인 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "related_member_id": 2
  }
}
```

---

## 6. 예배/모임 정의 (Worship)

> `reg_services` 테이블 관리. 실제 출석 기록의 기준이 되는 예배/모임 정의.

### POST /v4/worship/getList

예배 목록 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `is_active` | boolean | N | 활성화 여부 필터 |
| `day_of_week` | integer | N | 요일 (0=일, 1=월, ..., 6=토) |
| `keyword` | string(50) | N | 예배명 검색 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "list": [
      {
        "id": 1,
        "church_id": 10,
        "name": "주일 1부 예배",
        "day_of_week": 0,
        "target_org_id": null,
        "sort_order": 1,
        "is_active": 1
      }
    ]
  }
}
```

> `day_of_week`: 0=일, 1=월, 2=화, 3=수, 4=목, 5=금, 6=토 / null이면 미정

---

### POST /v4/worship/getDetail

예배 상세 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `service_id` | integer | Y | 예배 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "worship": {
      "id": 1,
      "church_id": 10,
      "name": "주일 2부 예배",
      "day_of_week": 0,
      "target_org_id": null,
      "sort_order": 2,
      "is_active": 1
    }
  }
}
```

---

### POST /v4/worship/register

예배 등록.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `name` | string(50) | Y | 예배명 |
| `day_of_week` | integer | N | 요일 (0~6) |
| `target_org_id` | integer | N | 대상 조직 ID |
| `sort_order` | integer | N | 정렬 순서 |
| `is_active` | boolean | N | 활성화 여부 (기본: true) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "service_id": 3
  }
}
```

---

### POST /v4/worship/update

예배 수정.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `service_id` | integer | Y | 예배 ID |
| `name` | string(50) | N | 예배명 |
| `day_of_week` | integer\|null | N | 요일 (0~6) |
| `target_org_id` | integer\|null | N | 대상 조직 ID |
| `sort_order` | integer | N | 정렬 순서 |
| `is_active` | boolean | N | 활성화 여부 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "service_id": 3
  }
}
```

---

### POST /v4/worship/delete

예배 삭제 (소프트, is_active → 0).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `service_id` | integer | Y | 예배 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "service_id": 3
  }
}
```

---

## 7. 출석 기록 (Attendance)

> `reg_attendance_records` 테이블. UPSERT 방식으로 중복 등록 시 갱신.

### POST /v4/attendance/recordBulk

출석 일괄 등록 (최대 500건, 유효하지 않은 교인 ID는 스킵).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `service_id` | integer | Y | 예배 ID |
| `attend_date` | date | Y | 출석 날짜 |
| `records` | array | Y | 출석 목록 (최소 1건, 최대 500건) |
| `records[].member_id` | integer | Y | 교인 ID |
| `records[].status` | string | Y | `present` \| `absent` \| `unknown` |
| `records[].note` | string | N | 비고 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "service_id": 1,
    "attend_date": "2026-06-22",
    "saved_count": 50,
    "skipped_count": 2
  }
}
```

---

### POST /v4/attendance/getListByService

특정 예배·날짜의 출석부 조회 (활성 교인 전체, 미체크 포함).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `service_id` | integer | Y | 예배 ID |
| `attend_date` | date | Y | 출석 날짜 |
| `organization_id` | integer | N | 조직 필터 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "service_id": 1,
    "attend_date": "2026-06-22",
    "list": [
      {
        "member_id": 1,
        "name": "홍길동",
        "status": "present",
        "note": null
      }
    ]
  }
}
```

---

### POST /v4/attendance/getListByMember

특정 교인의 기간별 출석 이력 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |
| `from_date` | date | N | 시작일 |
| `to_date` | date | N | 종료일 (from_date 이후) |
| `service_id` | integer | N | 예배 필터 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "list": [...]
  }
}
```

---

## 8. 심방 기록 (Visit)

> `reg_visit_records` 테이블. `add_to_prayer=true` 시 `reg_prayer_records` 자동 생성.

### POST /v4/visit/getList

심방 목록 조회 (페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | N | 대상 교인 ID 필터 |
| `visitor_member_id` | integer | N | 심방자 교인 ID 필터 |
| `visit_type` | string | N | `annual` \| `event` |
| `from_date` | date | N | 시작일 |
| `to_date` | date | N | 종료일 (from_date 이후) |
| `keyword` | string(100) | N | 내용 검색 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 20,
    "list": [...]
  }
}
```

---

### POST /v4/visit/getDetail

심방 상세 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `visit_id` | integer | Y | 심방 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "visit": {
      "id": 1,
      "church_id": 10,
      "member_id": 5,
      "visit_type": "annual",
      "visit_date": "2026-06-20",
      "visitor_member_id": 2,
      "place": "교인 자택",
      "content": "심방 내용...",
      "add_to_prayer": 0
    }
  }
}
```

---

### POST /v4/visit/register

심방 기록 등록.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 대상 교인 ID |
| `visit_date` | date | Y | 심방일 |
| `visitor_member_id` | integer | Y | 심방자 교인 ID |
| `content` | string | Y | 심방 내용 |
| `visit_type` | string | N | `annual` \| `event` |
| `event_reason` | string(30) | N | 이벤트 심방 사유 (병문안 등) |
| `manager_member_id` | integer | N | 담당자 교인 ID |
| `place` | string(100) | N | 심방 장소 |
| `companion` | string(200) | N | 동행자 |
| `attendee_count` | integer | N | 참석 인원 수 |
| `common_note` | string | N | 공개 메모 |
| `private_note` | string | N | 비공개 메모 |
| `add_to_prayer` | boolean | N | 기도 목록 자동 연동 여부 (기본: false) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "visit_id": 10
  }
}
```

---

### POST /v4/visit/update

심방 기록 수정. `add_to_prayer` 0→1 전환 시 기도 기록 신규 추가 (1→0은 기존 기도 기록 유지).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `visit_id` | integer | Y | 심방 ID |
| (register의 나머지 필드) | - | N | 변경할 항목만 전송 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "visit_id": 10
  }
}
```

---

### POST /v4/visit/delete

심방 기록 삭제 (하드 삭제). 연결된 기도 기록은 유지.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `visit_id` | integer | Y | 심방 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "visit_id": 10
  }
}
```

---

## 9. 봉사 관리 (Volunteer)

> `reg_service_teams` (팀) + `reg_service_members` (참여자 매핑).

### POST /v4/volunteer/getList

봉사 팀 목록 조회 (페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `service_type` | string(30) | N | 봉사 종류 필터 |
| `mode` | string | N | `regular` \| `onetime` |
| `is_active` | boolean | N | 활성화 여부 |
| `manager_member_id` | integer | N | 담당자 교인 ID |
| `keyword` | string(100) | N | 봉사명 검색 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 10,
    "list": [...]
  }
}
```

---

### POST /v4/volunteer/getDetail

봉사 팀 상세 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "team": { ...team object... }
  }
}
```

---

### POST /v4/volunteer/register

봉사 팀 등록.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `name` | string(100) | Y | 봉사명 |
| `description` | string(300) | N | 설명 |
| `service_type` | string(30) | N | 봉사 종류 |
| `mode` | string | N | `regular` \| `onetime` |
| `frequency` | string | N | `weekly` \| `biweekly` \| `monthly` |
| `day_of_week` | integer | N | 요일 (0~6) |
| `service_time` | string | N | 봉사 시간 (HH:mm:ss) |
| `event_date` | date | N | 행사일 (onetime) |
| `start_time` | string | N | 시작 시간 (HH:mm:ss) |
| `end_time` | string | N | 종료 시간 (HH:mm:ss) |
| `required_count` | integer | N | 필요 인원 |
| `manager_member_id` | integer | N | 담당자 교인 ID |
| `participation_type` | string | N | `application` \| `assignment` |
| `is_active` | boolean | N | 활성화 여부 (기본: true) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "team_id": 3
  }
}
```

---

### POST /v4/volunteer/update

봉사 팀 수정.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |
| (register의 나머지 필드) | - | N | 변경할 항목만 전송 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "team_id": 3
  }
}
```

---

### POST /v4/volunteer/delete

봉사 팀 삭제 (소프트, is_active → 0).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "team_id": 3
  }
}
```

---

### POST /v4/volunteer/assignVolunteer

봉사자 배정 (이미 매핑 있으면 갱신).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |
| `member_id` | integer | Y | 교인 ID |
| `role` | string(50) | N | 역할 |
| `joined_at` | date | N | 시작일 |
| `status` | string | N | `active` \| `inactive` (기본: `active`) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "mapping_id": 15,
    "team_id": 3,
    "member_id": 1
  }
}
```

---

### POST /v4/volunteer/unassignVolunteer

봉사자 해제 (소프트, status → inactive).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |
| `member_id` | integer | Y | 교인 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "mapping_id": 15,
    "team_id": 3,
    "member_id": 1
  }
}
```

---

### POST /v4/volunteer/getVolunteersByTeam

봉사 팀 소속 봉사자 목록 조회 (페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |
| `status` | string | N | `active` \| `inactive` |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 10,
    "list": [...]
  }
}
```

---

### POST /v4/volunteer/getTeamsByMember

특정 교인이 참여 중인 봉사 팀 목록 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "list": [...]
  }
}
```

---

### POST /v4/volunteer/getHistory

교회 전체 봉사 이력 조회 (페이징). `member_id`/`team_id` 미지정 시 교회 전체, 지정 시 해당 대상으로 좁힘 — 봉사이력 화면 "교인 중심" 탭의 기본 목록으로 사용. `attend_count`/`last_service_date`는 `reg_service_attendance` 기반 실측 출석 통계.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | N | 특정 교인으로 좁힘 |
| `team_id` | integer | N | 특정 봉사 팀으로 좁힘 |
| `status` | string | N | `active` \| `inactive` |
| `service_type` | string(30) | N | 봉사 종류 필터 |
| `keyword` | string(100) | N | 교인명/봉사명 검색 |
| `from_date` | date | N | 배정일(joined_at) 시작 범위 |
| `to_date` | date | N | 배정일(joined_at) 종료 범위 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 30,
    "page": 1,
    "size": 20,
    "list": [
      { "mapping_id": 14, "role": "찬양", "joined_at": "2026-03-01", "status": "active",
        "member_id": 6, "member_no": "M-2026003", "member_name": "이재훈",
        "team_id": 5, "team_name": "부활절 특송팀",
        "service_type": "special", "mode": "onetime", "team_is_active": 1,
        "attend_count": 1, "last_service_date": "2026-04-05" }
    ]
  }
}
```

---

### POST /v4/volunteer/getTeamHistory

봉사 팀별 이력 요약 조회 (페이징). `team_id` 미지정 시 교회 전체 팀, 지정 시 해당 팀으로 좁힘 — 봉사이력 화면 "봉사 중심" 탭의 기본 목록으로 사용. `participant_count`는 활동중 배정 인원, `attend_count`/`last_service_date`는 `reg_service_attendance` 기반 실측 누적 출석 통계, `first_joined_at`은 팀 내 최초 배정일.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | N | 특정 봉사 팀으로 좁힘 |
| `service_type` | string(30) | N | 봉사 종류 필터 |
| `mode` | string | N | `regular` \| `onetime` |
| `keyword` | string(100) | N | 봉사명 검색 |
| `from_date` | date | N | 팀 최초 배정일(first_joined_at) 하한 |
| `to_date` | date | N | 최근 참여일(last_service_date) 상한 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 8,
    "page": 1,
    "size": 20,
    "list": [
      { "team_id": 1, "team_name": "주일 주차 안내", "service_type": "parking", "mode": "regular",
        "is_active": 1, "event_date": null, "start_time": null, "end_time": null,
        "participant_count": 4, "attend_count": 22,
        "last_service_date": "2026-02-15", "first_joined_at": "2026-01-05" }
    ]
  }
}
```

---

### POST /v4/volunteer/attendance/getSheet

봉사 팀의 활동중 참여자 + 기록된 출결 데이터 조회 (출석 체크 화면용). 교육(`v4/education/attendance/getSheet`)과 달리 `round_no` 대신 실제 날짜(`service_date`)를 키로 사용 — 봉사 팀은 정기(무기한 반복)/일회성이라 고정된 회차 수가 없기 때문.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "members": [{ "member_id": 16, "name": "박명자", "phone": null }],
    "dates": ["2026-02-15", "2026-02-08", "2026-02-01"],
    "attendance": { "16_2026-02-15": "present", "16_2026-02-08": "absent" }
  }
}
```

---

### POST /v4/volunteer/attendance/save

특정 날짜 출결 일괄 저장 (UPSERT). 이미 기록된 날짜를 다시 저장하면 상태가 덮어써짐.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |
| `service_date` | date | Y | 실제 봉사 날짜 |
| `records` | array | Y | `[{ member_id, status, note? }]` |
| `records[].member_id` | integer | Y | 교인 ID |
| `records[].status` | string | Y | `present` \| `absent` \| `late` \| `excused` |
| `records[].note` | string(200) | N | 비고 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": { "team_id": 1, "service_date": "2026-07-19", "saved": 4 }
}
```

---

### POST /v4/volunteer/attendance/getMemberStats

봉사 팀별 교인 출결 통계 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `team_id` | integer | Y | 봉사 팀 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "team_id": 1,
    "list": [
      { "member_id": 16, "name": "박명자", "phone": null,
        "present": "7", "absent": "0", "late": "0", "excused": "0",
        "total": 7, "last_service_date": "2026-02-15" }
    ]
  }
}
```

---

## 10. 교육 관리 (Education)

> `reg_education_courses` (과정) + `reg_education_records` (수강 기록).
> progress=100 도달 시 status 자동 `completed` 처리.

### POST /v4/education/getList

교육 과정 목록 조회 (페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `status` | string | N | `upcoming` \| `ongoing` \| `completed` |
| `from_date` | date | N | 시작일 이후 필터 |
| `to_date` | date | N | 종료일 이전 필터 |
| `keyword` | string(100) | N | 과정명 검색 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 5,
    "list": [...]
  }
}
```

---

### POST /v4/education/getDetail

교육 과정 상세 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `course_id` | integer | Y | 교육 과정 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "course": {
      "id": 1,
      "church_id": 10,
      "name": "새신자 교육",
      "description": null,
      "start_date": "2026-03-01",
      "end_date": "2026-03-31",
      "status": "completed"
    }
  }
}
```

---

### POST /v4/education/register

교육 과정 등록.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `name` | string(100) | Y | 과정명 |
| `description` | string | N | 과정 설명 |
| `start_date` | date | N | 시작일 |
| `end_date` | date | N | 종료일 (start_date 이후) |
| `status` | string | N | `upcoming` \| `ongoing` \| `completed` (기본: `upcoming`) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "course_id": 5
  }
}
```

---

### POST /v4/education/update

교육 과정 수정.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `course_id` | integer | Y | 교육 과정 ID |
| `name` | string(100) | N | 과정명 |
| `description` | string\|null | N | 과정 설명 |
| `start_date` | date\|null | N | 시작일 |
| `end_date` | date\|null | N | 종료일 |
| `status` | string | N | `upcoming` \| `ongoing` \| `completed` |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "course_id": 5
  }
}
```

---

### POST /v4/education/delete

교육 과정 삭제 (하드 삭제). 수강 기록이 있으면 삭제 불가.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `course_id` | integer | Y | 교육 과정 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "course_id": 5
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 수강 기록 존재 | `HAS_CHILDREN` | 400 |

---

### POST /v4/education/enroll

교인 수강 등록 (dropped 상태였다면 ongoing으로 복구).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `course_id` | integer | Y | 교육 과정 ID |
| `member_id` | integer | Y | 교인 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "enrollment_id": 20,
    "course_id": 5,
    "member_id": 1
  }
}
```

---

### POST /v4/education/updateProgress

수강 진도 갱신. progress=100 달성 시 status 자동 `completed` 처리.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `course_id` | integer | Y | 교육 과정 ID |
| `member_id` | integer | Y | 교인 ID |
| `progress` | integer | Y | 진도율 (0~100) |
| `status` | string | N | `ongoing` \| `completed` \| `dropped` (명시 시 우선 적용) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "enrollment_id": 20,
    "course_id": 5,
    "member_id": 1,
    "progress": 75,
    "status": "ongoing"
  }
}
```

---

### POST /v4/education/withdraw

수강 취소 (status → dropped).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `course_id` | integer | Y | 교육 과정 ID |
| `member_id` | integer | Y | 교인 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "enrollment_id": 20,
    "course_id": 5,
    "member_id": 1
  }
}
```

---

### POST /v4/education/getEnrollmentsByCourse

과정별 수강자 목록 조회 (페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `course_id` | integer | Y | 교육 과정 ID |
| `status` | string | N | `ongoing` \| `completed` \| `dropped` |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 30,
    "list": [...]
  }
}
```

---

### POST /v4/education/getCoursesByMember

특정 교인의 수강 이력 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "list": [...]
  }
}
```

---

## 11. 헌금 관리 (Offering)

> `reg_offering_records` 테이블. `member_id` 없이 익명 헌금 등록 가능.

### POST /v4/offering/getList

헌금 목록 조회 (페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | N | 교인 ID 필터 |
| `category` | string(50) | N | 헌금 항목 필터 |
| `method` | string | N | `cash` \| `transfer` |
| `ledger` | string(100) | N | 회계 장부 필터 |
| `from_date` | date | N | 시작일 |
| `to_date` | date | N | 종료일 |
| `min_amount` | integer | N | 최소 금액 |
| `max_amount` | integer | N | 최대 금액 |
| `keyword` | string(100) | N | 검색 키워드 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 200,
    "list": [...]
  }
}
```

---

### POST /v4/offering/getDetail

헌금 상세 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `offering_id` | integer | Y | 헌금 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "offering": {
      "id": 1,
      "church_id": 10,
      "member_id": 5,
      "offer_date": "2026-06-22",
      "category": "십일조",
      "amount": 100000,
      "method": "transfer",
      "ledger": null
    }
  }
}
```

---

### POST /v4/offering/register

헌금 등록.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `offer_date` | date | Y | 헌금 날짜 |
| `category` | string(50) | Y | 헌금 항목 (십일조, 감사헌금 등) |
| `amount` | integer | Y | 금액 (0 이상) |
| `member_id` | integer | N | 교인 ID (없으면 익명) |
| `method` | string | N | `cash` \| `transfer` |
| `ledger` | string(100) | N | 회계 장부 분류 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "offering_id": 50
  }
}
```

---

### POST /v4/offering/update

헌금 기록 수정.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `offering_id` | integer | Y | 헌금 ID |
| `member_id` | integer\|null | N | 교인 ID |
| `offer_date` | date | N | 헌금 날짜 |
| `category` | string(50) | N | 헌금 항목 |
| `amount` | integer | N | 금액 |
| `method` | string | N | `cash` \| `transfer` |
| `ledger` | string\|null | N | 회계 장부 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "offering_id": 50
  }
}
```

---

### POST /v4/offering/delete

헌금 기록 삭제 (하드 삭제).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `offering_id` | integer | Y | 헌금 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "offering_id": 50
  }
}
```

---

### POST /v4/offering/getListByMember

특정 교인의 헌금 이력 조회 (getList와 동일 필터, member_id 필수).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `member_id` | integer | Y | 교인 ID |
| `category` | string(50) | N | 헌금 항목 필터 |
| `method` | string | N | `cash` \| `transfer` |
| `ledger` | string(100) | N | 회계 장부 필터 |
| `from_date` | date | N | 시작일 |
| `to_date` | date | N | 종료일 |
| `min_amount` | integer | N | 최소 금액 |
| `max_amount` | integer | N | 최대 금액 |
| `keyword` | string(100) | N | 검색 키워드 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "member_id": 1,
    "total": 12,
    "list": [...]
  }
}
```

---

## 12. 메시지 발송 (Message)

> `reg_messages` 테이블. 실제 SMS/Push/Email 게이트웨이 연동 미구현 (발송 기록만 저장).

### POST /v4/message/getList

메시지 발송 기록 목록 조회 (페이징).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `send_type` | string | N | `sms` \| `push` \| `email` |
| `from_date` | date | N | 시작일 |
| `to_date` | date | N | 종료일 |
| `keyword` | string(100) | N | 제목 검색 |
| `page` | integer | N | 페이지 번호 |
| `size` | integer | N | 페이지당 개수 (최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 50,
    "list": [...]
  }
}
```

---

### POST /v4/message/getDetail

메시지 발송 기록 상세 조회.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `message_id` | integer | Y | 메시지 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "message": {
      "id": 1,
      "church_id": 10,
      "title": "주일 예배 안내",
      "content": "이번 주 주일 예배는...",
      "send_type": "push",
      "sent_at": "2026-06-22T09:00:00.000000Z",
      "recipient_count": 150
    }
  }
}
```

---

### POST /v4/message/send

메시지 발송 (수신 대상 산출 후 발송 기록 저장).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `title` | string(200) | Y | 제목 |
| `content` | string | Y | 내용 |
| `target_type` | string | Y | `all` \| `organization` \| `members` |
| `send_type` | string | N | `sms` \| `push` \| `email` (기본: `push`) |
| `organization_id` | integer | N | target_type=organization 시 필수 |
| `member_ids` | integer[] | N | target_type=members 시 필수 (최대 1000건) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "message_id": 30,
    "recipient_count": 150
  }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| 발송 대상 조직 없음 | `NOT_FOUND` | 404 |
| 발송 대상 0명 | `VALIDATION_FAILED` | 400 |

---

### POST /v4/message/delete

메시지 발송 기록 삭제 (하드 삭제).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `message_id` | integer | Y | 메시지 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "message_id": 30
  }
}
```

---

## 13. 보고서/통계 (Report)

> 조회 전용 (감사 로그 미기록). 헌금 통계 등 민감 정보 포함.

### POST /v4/report/getMemberStats

교인 통계 (성별, 연령대, 직분, 출석등급, 상태별 집계).

- **인증 필요**: 예

**Request**

없음 (JWT의 churchId 사용)

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 300,
    "by_gender": [...],
    "by_age_group": [...],
    "by_position": [...],
    "by_attendance_grade": [...],
    "by_status": [...]
  }
}
```

---

### POST /v4/report/getAttendanceStats

출석 통계 (기간, 예배, 조직 기준). 출석률 자동 계산.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `from_date` | date | Y | 시작일 |
| `to_date` | date | Y | 종료일 (from_date 이후) |
| `service_id` | integer | N | 예배 필터 |
| `organization_id` | integer | N | 조직 필터 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "from_date": "2026-06-01",
    "to_date": "2026-06-30",
    "service_id": null,
    "organization_id": null,
    "by_status": [...],
    "attendance_rate": 87.5,
    "present_count": 700,
    "absent_count": 100,
    "daily_trend": [...]
  }
}
```

---

### POST /v4/report/getAttendanceStatsByOrg

조직별 출석 통계. 전체 조직 집계 또는 특정 조직+하위 조직 필터. raw SQL 집계.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `from_date` | date | Y | 시작일 |
| `to_date` | date | Y | 종료일 |
| `service_id` | integer | N | 예배 필터 |
| `organization_id` | integer | N | 조직 필터 (해당 조직 + 하위 조직) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "list": [
      {
        "org_id": 1,
        "org_name": "청년부",
        "present_count": 120,
        "absent_count": 20,
        "member_count": 35,
        "date_count": 4,
        "avg_rate": 85.7,
        "avg_present": 30.0
      }
    ]
  }
}
```

- `avg_rate`: `present / (present + absent) * 100`. 0이면 `null`.
- `avg_present`: `present_count / date_count`. 0이면 `null`.
- `org_name`: 상위 조직 있을 시 `"상위 > 하위"` 형식.

---

### POST /v4/report/getStatsByService

예배별 출석 통계. 기간 내 각 예배의 평균 출석 인원과 출석률 집계.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `from_date` | date | Y | 시작일 |
| `to_date` | date | Y | 종료일 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "list": [
      {
        "service_id": 1,
        "service_name": "주일 1부",
        "avg_present": 142.5,
        "rate": 89.1
      }
    ]
  }
}
```

- `avg_present`: `present_count / date_count`. 날짜 없으면 `null`.
- `rate`: `present / (present + absent) * 100`. 분모 0이면 `null`.

---

### POST /v4/report/getMemberRateDistribution

개인 출석률 분포. 기간 내 교인별 출석률을 5구간 버킷으로 집계.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `from_date` | date | Y | 시작일 |
| `to_date` | date | Y | 종료일 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 156,
    "list": [
      { "bucket": "90_plus",  "count": 60, "percent": 38.5 },
      { "bucket": "80_89",    "count": 30, "percent": 19.2 },
      { "bucket": "70_79",    "count": 20, "percent": 12.8 },
      { "bucket": "60_69",    "count": 15, "percent": 9.6  },
      { "bucket": "under_60", "count": 20, "percent": 12.8 },
      { "bucket": "no_record","count": 11, "percent": 7.1  }
    ]
  }
}
```

- `total`: 전체 활성 교인 수 (삭제 제외)
- `bucket`: `90_plus`(90%↑) / `80_89` / `70_79` / `60_69` / `under_60`(60%↓) / `no_record`(기록 없음)
- `percent`: `count / total * 100`

---

### POST /v4/report/getOfferingStats

헌금 통계 (기간, 항목 기준). 항목별/월별 집계 포함.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `from_date` | date | Y | 시작일 |
| `to_date` | date | Y | 종료일 |
| `category` | string(50) | N | 헌금 항목 필터 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "from_date": "2026-06-01",
    "to_date": "2026-06-30",
    "category": null,
    "count": 250,
    "total_amount": 15000000,
    "by_category": [...],
    "monthly_trend": [...]
  }
}
```

---

### POST /v4/report/getVisitStats

심방 통계 (기간, 심방자 기준). 심방자별 Top 10 포함.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `from_date` | date | Y | 시작일 |
| `to_date` | date | Y | 종료일 |
| `visitor_member_id` | integer | N | 심방자 교인 ID 필터 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "from_date": "2026-06-01",
    "to_date": "2026-06-30",
    "visitor_member_id": null,
    "count": 45,
    "top_visitors": [...],
    "by_type": [...]
  }
}
```

---

### POST /v4/report/getDashboard

대시보드 요약 (교인 총원, 이번 주 출석률, 이번 달 헌금/심방 건수).

- **인증 필요**: 예

**Request**

없음 (JWT의 churchId 사용)

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "as_of_date": "2026-06-26",
    "member_total": 300,
    "this_week": {
      "from": "2026-06-22",
      "to": "2026-06-26",
      "attendance_rate": 87.5,
      "present_count": 175,
      "absent_count": 25
    },
    "this_month": {
      "from": "2026-06-01",
      "to": "2026-06-30",
      "offering_total": 15000000,
      "offering_count": 250,
      "visit_count": 45
    }
  }
}
```

---

## 14. 코드 관리 (Code)

> `reg_code_groups` + `reg_codes` 테이블. 교인구분·직분·관계·출결상태 등 선택 목록을 동적으로 관리합니다.

### 그룹 키 상수 (`CODE_GROUPS`)

| group_key | 용도 |
|---|---|
| `member_type` | 교인구분 |
| `position` | 직분 유형 |
| `relation` | 관계 유형 |
| `attendance_status` | 출결 상태 |
| `education_type` | 교육 유형 |
| `service_type` | 봉사 유형 |
| `visit_type` | 심방 유형 |

### POST /v4/code/getCodes

특정 그룹의 코드 목록 조회 (활성 코드만 반환).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `group_key` | string | Y | 코드 그룹 키 |
| `church_id` | integer | N | 교회 ID (미입력 시 JWT의 churchId 사용) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "group_key": "position",
    "list": [
      { "id": 1, "code": "elder", "label": "장로", "sort_order": 1, "is_active": 1, "is_system": 1 },
      { "id": 2, "code": "deacon", "label": "집사", "sort_order": 2, "is_active": 1, "is_system": 1 }
    ]
  }
}
```

---

### POST /v4/code/registerCode

코드 등록.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `group_key` | string | Y | 코드 그룹 키 |
| `code` | string(50) | Y | 코드 값 (영문, 소문자/숫자/언더스코어) |
| `label` | string(100) | Y | 표시 라벨 |
| `sort_order` | integer | N | 정렬 순서 (기본: 0) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": { "id": 10 }
}
```

**에러 코드**

| 코드 | HTTP | 설명 |
|---|---|---|
| `DUPLICATE_DATA` | 400 | 동일 group_key+code 중복 |

---

### POST /v4/code/updateCode

코드 수정.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `id` | integer | Y | 코드 ID |
| `label` | string(100) | N | 표시 라벨 |
| `sort_order` | integer | N | 정렬 순서 |
| `is_active` | boolean | N | 활성 여부 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": { "id": 10 }
}
```

---

### POST /v4/code/deleteCode

코드 삭제. `is_system=1`인 코드는 삭제 불가.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `id` | integer | Y | 코드 ID |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": { "id": 10 }
}
```

**에러 코드**

| 코드 | HTTP | 설명 |
|---|---|---|
| `INSUFFICIENT_PERMISSION` | 403 | 시스템 코드 삭제 시도 |

---

## 15. 교적 설정 (Setting)

> `reg_church_settings` 테이블. KPI 기준·자동화 규칙 등 교회 단위 key-value 설정을 저장합니다.

### 설정 키 상수 (`SETTING_KEYS`)

| key | 타입 | 설명 |
|---|---|---|
| `kpi_attendance_calc` | string | 출석률 계산 방식: `service` \| `weekly` \| `monthly` |
| `kpi_education_calc` | string | 교육 참여율 기준: `completion` \| `participation` |
| `kpi_service_calc` | string | 봉사 참여율 기준: `active` \| `all` |
| `auto_new_member` | JSON string | 새가족 자동 등록: `{"enabled":true,"days":90}` |
| `auto_long_absent` | JSON string | 장기결석 처리: `{"enabled":true,"weeks":4,"auto_status":true,"create_visit":true}` |
| `auto_education` | JSON string | 교육 미참여 알림: `{"enabled":true,"courses":["새가족교육"]}` |
| `auto_visit_target` | JSON string | 심방 대상 생성: `{"enabled":true,"triggers":["장기결석","환우"]}` |

### POST /v4/setting/getSettings

설정값 일괄 조회. 없는 키는 `null` 반환.

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `keys` | string[] | Y | 조회할 설정 키 배열 (min:1) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "settings": {
      "kpi_attendance_calc": "weekly",
      "kpi_education_calc": "completion",
      "kpi_service_calc": null
    }
  }
}
```

---

### POST /v4/setting/saveSettings

설정값 일괄 저장 (UPSERT).

- **인증 필요**: 예

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `settings` | object | Y | `{ setting_key: setting_value }` 맵 (min:1) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": { "saved_count": 3 }
}
```

---

## 16. 권한 관리 (Permission)

> `reg_role_permissions` 테이블 (church_id, role, page_code 당 1행, `can_access` 단일 플래그). 조회/관리 구분 없음 — 소메뉴(화면) 단위 접근 여부만 관리.
>
> **역할 4종**: `admin`(관리자, 항상 전체 접근 — DB에 저장하지 않고 하드코딩 바이패스) / `pastor`(담임목회자) / `minister`(사역자) / `volunteer`(봉사자). `admin`은 커스터마이즈 불가.
>
> DB에 오버라이드가 없으면 `App\Constants\PermissionDefaults`의 역할별 기본 정책으로 폴백. 메뉴 트리는 `App\Constants\PermissionMenuTree`(대메뉴 > 중메뉴 > 소메뉴) 참조.

### POST /v4/permission/getMatrix

전체 역할 × 전체 소메뉴 권한 매트릭스 조회. 권한 설정 화면(관리 > 시스템설정 > 권한 관리)용.

- **인증 필요**: 예 (admin, `super.auth`)

**Request**

없음

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "roles": [
      { "key": "admin", "label": "관리자", "user_count": 1 },
      { "key": "pastor", "label": "담임목회자", "user_count": 1 },
      { "key": "minister", "label": "사역자", "user_count": 2 },
      { "key": "volunteer", "label": "봉사자", "user_count": 3 }
    ],
    "groups": [
      {
        "key": "member", "label": "교적",
        "menus": [
          {
            "code": "MEMBER", "label": "교인",
            "pages": [
              { "code": "MEMBER_LIST", "label": "교인목록" },
              { "code": "MEMBER_APPROVAL", "label": "가입승인" }
            ]
          }
        ]
      }
    ],
    "matrix": {
      "admin": { "MEMBER_LIST": true, "MEMBER_APPROVAL": true },
      "pastor": { "MEMBER_LIST": true, "MEMBER_APPROVAL": true },
      "minister": { "MEMBER_LIST": true, "MEMBER_APPROVAL": false },
      "volunteer": { "MEMBER_LIST": false, "MEMBER_APPROVAL": false }
    }
  }
}
```

---

### POST /v4/permission/saveRole

역할 1개의 소메뉴별 접근 권한 일괄 저장 (UPSERT). `admin`/`pastor`/`minister`/`volunteer` 중 `pastor`·`minister`·`volunteer`만 저장 가능.

- **인증 필요**: 예 (admin, `super.auth`)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `role` | string | Y | `pastor` \| `minister` \| `volunteer` |
| `permissions` | array | Y | `[{ page_code, can_access }]` (min:1) |
| `permissions.*.page_code` | string | Y | 소메뉴 코드 (`PermissionMenuTree` 기준) |
| `permissions.*.can_access` | boolean | Y | 접근 허용 여부 |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": { "saved_count": 36 }
}
```

**주요 에러 케이스**

| 상황 | code | HTTP |
|---|---|---|
| `role`이 `admin` 등 커스터마이즈 불가 값 | `VALIDATION_FAILED` | 400 |
| `page_code`가 `PermissionMenuTree`에 없는 값 | `VALIDATION_FAILED` | 400 |

---

### POST /v4/permission/getMyPermissions

로그인한 본인 역할 기준 권한만 반환 — 프론트 GNB 메뉴 필터링/페이지 가드용. 매트릭스 조회(`getMatrix`)와 달리 `super.auth` 불필요.

- **인증 필요**: 예 (jwt.auth만)

**Request**

없음 (토큰의 `adminRole` 클레임 사용)

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "role": "minister",
    "permissions": {
      "MEMBER_LIST": true,
      "MEMBER_APPROVAL": true,
      "SETTING_CHURCH_PROFILE": false
    }
  }
}
```

> 프론트는 `permissions[pageCode]` 단일 boolean으로 접근 가능 여부만 판단(조회/관리 구분 없음).

---

## 17. 시스템 설정 — 전체 현황 / 활동 로그 (System)

> 관리 > 시스템설정 > 전체 현황·활동 로그 화면 전용. 관리자 계정 관리(`Admin`)·권한 관리(`Permission`)와 동일하게 `jwt.auth` + `super.auth` 적용.

### POST /v4/system/getOverview

시스템 설정 대시보드 요약 — 전체 사용자 수(활성/비활성), 정의된 역할(고정 4종), 최근 로그인 수(7일 기준), 최근 감사 로그 10건.

- **인증 필요**: 예 (admin, `super.auth`)

**Request**

없음

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "users": { "total": 4, "active": 4, "inactive": 0 },
    "roles": { "total": 4, "labels": ["관리자", "담임", "사역자", "봉사자"] },
    "recent_logins": { "count": 1, "days": 7 },
    "activities": [
      {
        "date": "2026-07-17 17:59:25",
        "user": "교적 관리자",
        "menu_code": "AUTH",
        "menu_label": "인증",
        "action_type": "LOGIN",
        "action_label": "로그인",
        "target": "test111"
      }
    ]
  }
}
```

---

### POST /v4/system/getAuditLogs

`reg_audit_logs` 전체 조회 — 검색/필터/페이지네이션 지원(관리 > 시스템설정 > 활동 로그 화면).

- **인증 필요**: 예 (admin, `super.auth`)

**Request**

| 파라미터 | 타입 | 필수 | 설명 |
|---|---|---|---|
| `menu_code` | string | N | 메뉴 코드 필터 (`AuditMenuCode` 값 중 하나) |
| `action_type` | string | N | 액션 타입 필터 (`AuditActionType` 값 중 하나) |
| `admin_no` | integer | N | 작업자(관리자 번호) 필터 |
| `search_type` | string | N | 검색 대상 컬럼: `user`(사용자명) \| `target`(대상) \| `summary`(요약) (`keyword` 입력 시 필수) |
| `keyword` | string(100) | N | 검색어 부분 일치 (`search_type` 선택 시 필수) |
| `date_from` | date | N | 조회 시작일 (`YYYY-MM-DD`) |
| `date_to` | date | N | 조회 종료일 (`YYYY-MM-DD`) |
| `page` | integer | N | 페이지 번호 (기본값: 1) |
| `size` | integer | N | 페이지당 개수 (기본값: 20, 최대: 100) |

**Response (성공)**

```json
{
  "status": "success",
  "code": "",
  "message": "",
  "data": {
    "total": 270,
    "list": [
      {
        "audit_no": 270,
        "date": "2026-07-17 19:08:22",
        "user": "교적 관리자",
        "menu_code": "CHURCH_PROFILE",
        "menu_label": "교회 기본정보",
        "action_type": "UPDATE",
        "action_label": "수정",
        "target": "교회 로고",
        "summary": "교회 정보 수정: 로고"
      }
    ]
  }
}
```

> `search_type` 없이 `keyword`만 있는 경우(레거시 호출)는 `summary`/`target_label`/사용자명 3개 컬럼을 OR 검색.

---

*이 문서는 실제 Controller, Request, Service 코드 기반으로 자동 생성되었습니다.*
*갱신일: 2026-07-17*
