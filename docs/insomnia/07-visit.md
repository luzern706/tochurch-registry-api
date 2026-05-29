# 7. 심방 (Visit)

URL prefix: `{{ base_url }}/v4/visit`
모두 **인증 필요**

**특징**: `add_to_prayer=true` 로 등록 시 `reg_prayer_records` 자동 생성. 업데이트 시 0→1 전환만 새 기도 record 생성.

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/visit/getList` | 심방 목록 |
| 2 | POST | `/v4/visit/getDetail` | 심방 상세 |
| 3 | POST | `/v4/visit/register` | 심방 등록 |
| 4 | POST | `/v4/visit/update` | 심방 수정 |
| 5 | POST | `/v4/visit/delete` | 심방 삭제 (hard DELETE) |

---

## 1. POST `/v4/visit/getList`

### Request Body
```json
{
  "member_id": null,
  "visitor_member_id": null,
  "visit_type": "annual",
  "from_date": "2026-01-01",
  "to_date": "2026-05-31",
  "keyword": "병문안",
  "page": 1,
  "size": 20
}
```

**파라미터 (모두 optional):**
- `member_id` (integer, min:1) — 대상 교인
- `visitor_member_id` (integer, min:1) — 심방자
- `visit_type` (in: annual, event)
- `from_date`, `to_date` (date)
- `keyword` (string, max:100) — content/common_note/대상이름 LIKE
- `page`, `size`

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 10, "page": 1, "size": 20,
    "list": [
      { "id": 3, "member_id": 1, "church_id": 1,
        "visit_type": "annual", "event_reason": null,
        "visit_date": "2026-05-10", "visitor_member_id": 2,
        "manager_member_id": null, "place": "교회",
        "companion": null, "attendee_count": 3,
        "content": "...", "common_note": null,
        "private_note": null, "add_to_prayer": 0,
        "created_by": 1, "created_at": "...", "updated_at": "...",
        "member_name": "홍길동", "member_member_no": "M-...",
        "visitor_name": "김심방" }
    ]
  }
}
```

---

## 2. POST `/v4/visit/getDetail`

### Request Body
```json
{ "visit_id": 3 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "visit": { ... } } }
```

---

## 3. POST `/v4/visit/register`

### Request Body
```json
{
  "member_id": 1,
  "visit_type": "annual",
  "event_reason": null,
  "visit_date": "2026-05-17",
  "visitor_member_id": 2,
  "manager_member_id": null,
  "place": "성도 자택",
  "companion": "김장로, 박권사",
  "attendee_count": 3,
  "content": "신앙 상담 진행, 가정 안부 확인.",
  "common_note": "...",
  "private_note": "개인 비공개 메모",
  "add_to_prayer": true
}
```

**파라미터:**
- `member_id` (integer, required, min:1) — 대상 교인, 동일 교회 검증
- `visit_date` (date, required)
- `visitor_member_id` (integer, required, min:1) — 동일 교회 검증
- `content` (string, required)
- `visit_type` (in: annual, event, optional, default 미지정 시 DB default)
- `event_reason` (string, max:30, optional)
- `manager_member_id` (integer, optional, 동일 교회 검증)
- `place`, `companion`, `attendee_count`, `common_note`, `private_note` (optional)
- `add_to_prayer` (boolean, optional, default false) — true 시 `reg_prayer_records` 자동 생성

### Response — 성공
```json
{ "status": "success", ..., "data": { "visit_id": 5 } }
```

### Response — 실패
- `NOT_FOUND` (404) — member_id/visitor/manager 중 다른 교회/존재하지 않음

---

## 4. POST `/v4/visit/update`

### Request Body
```json
{
  "visit_id": 5,
  "content": "내용 수정",
  "add_to_prayer": true
}
```

**파라미터:**
- `visit_id` (integer, required, min:1)
- 다른 모든 필드 sometimes
- `add_to_prayer` 0 → 1 전환 시에만 새 기도 record 생성

### Response — 성공
```json
{ "status": "success", ..., "data": { "visit_id": 5 } }
```

---

## 5. POST `/v4/visit/delete`

### Request Body
```json
{ "visit_id": 5 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "visit_id": 5 } }
```

**주의**: 연결된 `reg_prayer_records` 는 삭제되지 않음 (독립 라이프사이클).
