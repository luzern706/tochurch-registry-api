# 2. 교인 (Member)

URL prefix: `{{ base_url }}/v4/member`
모두 **인증 필요** (`Authorization: Bearer {token}`)

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/member/getList` | 교인 목록 (페이지네이션) |
| 2 | POST | `/v4/member/getDetail` | 교인 상세 (프로필 포함) |
| 3 | POST | `/v4/member/register` | 교인 등록 (reg_members + reg_member_profiles 동시 INSERT) |
| 4 | POST | `/v4/member/update` | 교인 정보 수정 |
| 5 | POST | `/v4/member/delete` | 교인 삭제 (soft, is_deleted=1) |

---

## 1. POST `/v4/member/getList` — 교인 목록

### Request Body
```json
{
  "keyword": "홍길동",
  "status": "active",
  "page": 1,
  "size": 20
}
```

**파라미터 (모두 optional):**
- `keyword` (string, max:100) — 이름/이메일/전화/교인번호 LIKE 검색
- `status` (in: active, inactive, unknown)
- `page` (integer, min:1, default 1)
- `size` (integer, 1~100, default 20)

### Response — 성공
```json
{
  "status": "success",
  "code": "", "message": "",
  "data": {
    "total": 42,
    "page": 1,
    "size": 20,
    "list": [
      {
        "id": 1, "church_id": 1, "member_no": "M-20260518-0001",
        "email": "...", "name": "...", "nickname": null,
        "phone": "010-...", "gender": "M", "birth_date": "1990-01-01",
        "birth_type": "solar", "status": "active", "created_at": "..."
      }
    ]
  }
}
```

---

## 2. POST `/v4/member/getDetail` — 교인 상세

### Request Body
```json
{ "member_id": 1 }
```

**파라미터:**
- `member_id` (integer, required, min:1)

### Response — 성공
```json
{
  "status": "success", "code": "", "message": "",
  "data": {
    "member": {
      "id": 1, "church_id": 1, "member_no": "M-20260518-0001",
      "email": "test@example.com", "name": "홍길동",
      "phone": "010-...", "gender": "M", "birth_date": "1990-01-01",
      "status": "active", "is_deleted": 0,
      "created_at": "...", "updated_at": "..."
    },
    "profile": {
      "id": 1, "member_id": 1, "member_type": "새가족",
      "position": "성도", "baptism_grade": "세례",
      "attendance_grade": "우수", "is_household_head": 1,
      ...
    }
  }
}
```

### Response — 실패
- `NOT_FOUND` (404) — 교인 없음 또는 다른 교회 소속

---

## 3. POST `/v4/member/register` — 교인 등록

### Request Body (필수 항목만)
```json
{
  "email": "newuser@example.com",
  "password": "123456",
  "name": "김새신자"
}
```

### Request Body (전체 항목)
```json
{
  "email": "newuser@example.com",
  "password": "123456",
  "name": "김새신자",
  "nickname": "새벽",
  "phone": "010-1234-5678",
  "gender": "M",
  "birth_date": "1995-03-15",
  "birth_type": "solar",
  "address_zip": "12345",
  "address_main": "서울시 강남구 ...",
  "address_detail": "101동 202호",
  "profile_image": null,
  "status": "active",
  "memo": "특이사항",
  "churchero_user_id": null,

  "member_type": "새가족",
  "member_type_source": "system",
  "position": "성도",
  "baptism_grade": "학습",
  "attendance_grade": "보통",
  "ordained_at": null,
  "ordained_church": null,
  "baptism_at": null,
  "baptism_church": null,
  "registered_at": "2026-01-01",
  "welcomed_at": null,
  "previous_church": null,
  "leader_member_id": 1,
  "marriage_status": "single",
  "is_household_head": 0,
  "household_relation": "자녀",
  "workplace": "삼성전자",
  "custom_field_1": null,
  "custom_field_2": null,
  "custom_field_3": null
}
```

**필수 파라미터:**
- `email` (email, max:100)
- `password` (string, 4~100자)
- `name` (string, max:50)

**선택 파라미터 (reg_members):**
- `nickname`, `phone`, `gender`(M/F), `birth_date`(date), `birth_type`(solar/lunar)
- `address_zip`, `address_main`, `address_detail`, `profile_image`, `status`(active/inactive/unknown)
- `memo`, `churchero_user_id`(integer)

**선택 파라미터 (reg_member_profiles):**
- `member_type` (default '새가족'), `member_type_source` (system/user)
- `position`, `baptism_grade`, `attendance_grade`
- `ordained_at`, `ordained_church`, `baptism_at`, `baptism_church`
- `registered_at`, `welcomed_at`, `previous_church`, `leader_member_id`
- `marriage_status` (single/married/widowed/divorced)
- `is_household_head` (boolean), `household_relation`, `workplace`
- `custom_field_1~3`

### Response — 성공
```json
{
  "status": "success", "code": "", "message": "",
  "data": {
    "member_id": 5,
    "member_no": "M-20260518-0002"
  }
}
```

### Response — 실패
- `DUPLICATE_DATA` (400) — 동일 church_id 내 이메일 중복
- `VALIDATION_FAILED` (400)

---

## 4. POST `/v4/member/update` — 교인 수정

### Request Body
```json
{
  "member_id": 5,
  "name": "김변경",
  "phone": "010-9999-8888",
  "position": "집사"
}
```

**파라미터:**
- `member_id` (integer, required, min:1)
- 그 외 모든 필드는 `register` 와 동일하나 `sometimes` (생략 가능). `password` 도 선택. `email` 변경 시 중복 검사.

### Response — 성공
```json
{ "status": "success", ..., "data": { "member_id": 5 } }
```

### Response — 실패
- `NOT_FOUND` (404) — 교인 없음 또는 다른 교회
- `DUPLICATE_DATA` (400) — 이메일 변경 시 중복

---

## 5. POST `/v4/member/delete` — 교인 삭제

### Request Body
```json
{ "member_id": 5 }
```

**파라미터:**
- `member_id` (integer, required, min:1)

### Response — 성공
```json
{ "status": "success", ..., "data": { "member_id": 5 } }
```

### Response — 실패
- `NOT_FOUND` (404)
- `INSUFFICIENT_PERMISSION` (403) — 본인 계정 삭제 시도
