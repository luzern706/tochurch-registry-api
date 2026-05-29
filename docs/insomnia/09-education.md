# 9. 교육 (Education)

URL prefix: `{{ base_url }}/v4/education`
모두 **인증 필요**

테이블: `reg_education_courses` (교육 과정), `reg_education_records` (수강 기록)

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/education/getList` | 교육 과정 목록 |
| 2 | POST | `/v4/education/getDetail` | 과정 상세 |
| 3 | POST | `/v4/education/register` | 과정 등록 |
| 4 | POST | `/v4/education/update` | 과정 수정 |
| 5 | POST | `/v4/education/delete` | 과정 삭제 (수강 record 있으면 거부) |
| 6 | POST | `/v4/education/enroll` | 수강 등록 (UPSERT, dropped 복구) |
| 7 | POST | `/v4/education/updateProgress` | 진도 갱신 (100 → 자동 completed) |
| 8 | POST | `/v4/education/withdraw` | 수강 취소 (status=dropped) |
| 9 | POST | `/v4/education/getEnrollmentsByCourse` | 과정 수강자 목록 |
| 10 | POST | `/v4/education/getCoursesByMember` | 교인 수강 이력 |

---

## 1. POST `/v4/education/getList`

### Request Body
```json
{
  "status": "ongoing",
  "from_date": "2026-01-01",
  "to_date": "2026-12-31",
  "keyword": "성경",
  "page": 1,
  "size": 20
}
```

**파라미터 (모두 optional):**
- `status` (in: upcoming, ongoing, completed)
- `from_date`, `to_date` (date)
- `keyword` (string, max:100) — name/description LIKE
- `page`, `size`

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 5, "page": 1, "size": 20,
    "list": [
      { "id": 1, "church_id": 1, "name": "성경통독반", "description": "...",
        "start_date": "2026-03-01", "end_date": "2026-08-31",
        "status": "ongoing", "created_at": "...", "updated_at": "..." }
    ]
  }
}
```

---

## 2. POST `/v4/education/getDetail`

### Request Body
```json
{ "course_id": 1 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "course": { ... } } }
```

---

## 3. POST `/v4/education/register`

### Request Body
```json
{
  "name": "새신자 교육 (5월)",
  "description": "5월 신규 등록자 대상 8주 과정",
  "start_date": "2026-05-01",
  "end_date": "2026-06-30",
  "status": "upcoming"
}
```

**파라미터:**
- `name` (string, required, max:100)
- `description` (string, optional)
- `start_date`, `end_date` (date, optional, end_date >= start_date)
- `status` (in: upcoming, ongoing, completed, optional, default 'upcoming')

### Response — 성공
```json
{ "status": "success", ..., "data": { "course_id": 6 } }
```

---

## 4. POST `/v4/education/update`

### Request Body
```json
{
  "course_id": 6,
  "status": "ongoing",
  "end_date": "2026-07-31"
}
```

**파라미터:**
- `course_id` (required, min:1)
- 그 외 sometimes

### Response — 성공
```json
{ "status": "success", ..., "data": { "course_id": 6 } }
```

---

## 5. POST `/v4/education/delete`

### Request Body
```json
{ "course_id": 6 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "course_id": 6 } }
```

### Response — 실패
- `HAS_CHILDREN` (400) — 수강 기록 존재 시 거부

---

## 6. POST `/v4/education/enroll`

### Request Body
```json
{ "course_id": 1, "member_id": 5 }
```

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "enrollment_id": 12, "course_id": 1, "member_id": 5
  }
}
```

**동작:**
- 신규: `progress=0, status='ongoing'` 으로 INSERT
- 기존 `dropped`: `status='ongoing'` 으로 되살림
- 기존 `completed`: 유지 (재수강 방지)
- 기존 `ongoing`: 변경 없이 ID 반환

---

## 7. POST `/v4/education/updateProgress`

### Request Body
```json
{
  "course_id": 1,
  "member_id": 5,
  "progress": 50,
  "status": null
}
```

**파라미터:**
- `course_id`, `member_id` (required, min:1)
- `progress` (integer, required, 0~100)
- `status` (in: ongoing, completed, dropped, optional)
  - 미지정 시 `progress=100 & 현재 ongoing` 이면 자동 `completed` 전환

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "enrollment_id": 12,
    "course_id": 1,
    "member_id": 5,
    "progress": 50,
    "status": "ongoing"
  }
}
```

---

## 8. POST `/v4/education/withdraw`

### Request Body
```json
{ "course_id": 1, "member_id": 5 }
```

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "enrollment_id": 12, "course_id": 1, "member_id": 5
  }
}
```
- `status='dropped'` 로 변경

---

## 9. POST `/v4/education/getEnrollmentsByCourse`

### Request Body
```json
{
  "course_id": 1,
  "status": "ongoing",
  "page": 1,
  "size": 20
}
```

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 12, "page": 1, "size": 20,
    "list": [
      { "enrollment_id": 12, "progress": 50, "status": "ongoing",
        "created_at": "...", "updated_at": "...",
        "member_id": 5, "member_no": "M-...", "name": "홍길동",
        "email": "...", "phone": "..." }
    ]
  }
}
```

---

## 10. POST `/v4/education/getCoursesByMember`

### Request Body
```json
{ "member_id": 5 }
```

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "member_id": 5,
    "list": [
      { "enrollment_id": 12, "progress": 50, "enrollment_status": "ongoing",
        "course_id": 1, "course_name": "성경통독반",
        "description": "...", "start_date": "2026-03-01",
        "end_date": "2026-08-31", "course_status": "ongoing" }
    ]
  }
}
```
