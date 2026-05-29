# 11. 메시지 발송 (Message)

URL prefix: `{{ base_url }}/v4/message`
모두 **인증 필요**

**주의**: 실제 SMS/Push/Email 게이트웨이 연동은 미구현. 본 API 는 발송 기록 row 만 저장하며 `recipient_count` 를 계산한다.

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/message/getList` | 발송 이력 목록 |
| 2 | POST | `/v4/message/getDetail` | 발송 상세 |
| 3 | POST | `/v4/message/send` | 메시지 발송 (대상 산출 → 기록 저장) |
| 4 | POST | `/v4/message/delete` | 발송 기록 삭제 (hard DELETE) |

---

## 1. POST `/v4/message/getList`

### Request Body
```json
{
  "send_type": "push",
  "from_date": "2026-05-01 00:00:00",
  "to_date": "2026-05-31 23:59:59",
  "keyword": "공지",
  "page": 1,
  "size": 20
}
```

**파라미터 (모두 optional):**
- `send_type` (in: sms, push, email)
- `from_date`, `to_date` (datetime)
- `keyword` (string, max:100) — title/content LIKE
- `page`, `size`

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 7, "page": 1, "size": 20,
    "list": [
      { "id": 3, "church_id": 1, "title": "5월 주일 안내",
        "content": "이번 주 주일 1부는 ...",
        "send_type": "push", "sent_at": "2026-05-17 09:00:00",
        "sent_by": 1, "recipient_count": 42, "created_at": "..." }
    ]
  }
}
```

---

## 2. POST `/v4/message/getDetail`

### Request Body
```json
{ "message_id": 3 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "message": { ... } } }
```

---

## 3. POST `/v4/message/send`

### Request Body — 전체 발송
```json
{
  "title": "5월 주일 안내",
  "content": "이번 주 주일 1부는 9시에 시작합니다.",
  "send_type": "push",
  "target_type": "all"
}
```

### Request Body — 조직 지정
```json
{
  "title": "청년부 모임 안내",
  "content": "토요일 7시 청년부 ...",
  "send_type": "sms",
  "target_type": "organization",
  "organization_id": 7
}
```

### Request Body — 개별 교인 지정
```json
{
  "title": "심방 안내",
  "content": "내일 오후 2시 방문 예정입니다.",
  "send_type": "email",
  "target_type": "members",
  "member_ids": [1, 2, 5, 10]
}
```

**파라미터:**
- `title` (string, required, max:200)
- `content` (string, required)
- `send_type` (in: sms, push, email, optional, default 'push')
- `target_type` (required, in: all, organization, members)
  - `organization`: `organization_id` (integer, required) — 동일 교회 검증
  - `members`: `member_ids` (array, required, 1~1000개, 각 integer min:1) — 동일 교회 + 미삭제 교인만 추림

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "message_id": 8,
    "recipient_count": 42
  }
}
```

### Response — 실패
- `VALIDATION_FAILED` (400) — 대상이 0명 (id 모두 다른 교회/삭제됨 등)
- `NOT_FOUND` (404) — target_type=organization 인데 organization_id 가 다른 교회

---

## 4. POST `/v4/message/delete`

### Request Body
```json
{ "message_id": 8 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "message_id": 8 } }
```
