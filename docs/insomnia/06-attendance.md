# 6. 출석 (Attendance)

URL prefix: `{{ base_url }}/v4/attendance`
모두 **인증 필요**

**특징**: 일괄 UPSERT. 입력된 row 만 저장, 미체크 교인은 조회 시 `attendance_id=null` 로 노출.

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/attendance/recordBulk` | 출석 일괄 기록 (UPSERT) |
| 2 | POST | `/v4/attendance/getListByService` | 특정 예배·날짜의 출석부 |
| 3 | POST | `/v4/attendance/getListByMember` | 특정 교인의 기간별 출석 이력 |

---

## 1. POST `/v4/attendance/recordBulk`

### Request Body
```json
{
  "service_id": 1,
  "attend_date": "2026-05-17",
  "records": [
    { "member_id": 1, "status": "present", "note": null },
    { "member_id": 2, "status": "absent",  "note": "병가" },
    { "member_id": 3, "status": "present", "note": null }
  ]
}
```

**파라미터:**
- `service_id` (integer, required, min:1) — 동일 교회 검증
- `attend_date` (date, required)
- `records` (array, required, 1~500개)
  - `records[].member_id` (integer, required, min:1)
  - `records[].status` (string, required, in: present, absent, unknown)
  - `records[].note` (string, optional)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "service_id": 1,
    "attend_date": "2026-05-17",
    "saved_count": 3,
    "skipped_count": 0
  }
}
```
- `skipped_count`: 동일 교회에 속하지 않거나 삭제된 교인 수

---

## 2. POST `/v4/attendance/getListByService`

### Request Body
```json
{
  "service_id": 1,
  "attend_date": "2026-05-17",
  "organization_id": null
}
```

**파라미터:**
- `service_id` (required, integer, min:1)
- `attend_date` (date, required)
- `organization_id` (integer, optional) — 조직 소속 교인만 필터

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "service_id": 1, "attend_date": "2026-05-17",
    "list": [
      { "member_id": 1, "member_no": "M-...", "name": "홍길동",
        "gender": "M", "member_status": "active",
        "attendance_id": 12, "attendance_status": "present",
        "note": null, "attend_date": "2026-05-17", "recorded_by": 1 },
      { "member_id": 4, "member_no": "M-...", "name": "김미체크",
        "gender": "F", "member_status": "active",
        "attendance_id": null, "attendance_status": null,
        "note": null, "attend_date": null, "recorded_by": null }
    ]
  }
}
```
- `attendance_id=null` 인 행 = 아직 미체크

---

## 3. POST `/v4/attendance/getListByMember`

### Request Body
```json
{
  "member_id": 1,
  "from_date": "2026-01-01",
  "to_date": "2026-05-31",
  "service_id": null
}
```

**파라미터:**
- `member_id` (required, integer, min:1)
- `from_date`, `to_date` (date, optional)
- `service_id` (integer, optional)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "member_id": 1,
    "list": [
      { "id": 12, "attend_date": "2026-05-17", "status": "present",
        "note": null, "service_id": 1, "service_name": "주일 1부" },
      { "id": 8, "attend_date": "2026-05-10", "status": "absent",
        "note": "병가", "service_id": 1, "service_name": "주일 1부" }
    ]
  }
}
```
