# 10. 헌금 (Offering)

URL prefix: `{{ base_url }}/v4/offering`
모두 **인증 필요**

**특징**: 익명 헌금 허용 (`member_id=null`). 응답에 `total_amount` 합계 포함.

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/offering/getList` | 헌금 목록 (전체 필터) |
| 2 | POST | `/v4/offering/getDetail` | 헌금 상세 |
| 3 | POST | `/v4/offering/register` | 헌금 등록 |
| 4 | POST | `/v4/offering/update` | 헌금 수정 |
| 5 | POST | `/v4/offering/delete` | 헌금 삭제 (hard DELETE) |
| 6 | POST | `/v4/offering/getListByMember` | 특정 교인의 헌금 이력 |

---

## 1. POST `/v4/offering/getList`

### Request Body
```json
{
  "member_id": null,
  "category": "주일헌금",
  "method": "cash",
  "ledger": "현금",
  "from_date": "2026-05-01",
  "to_date": "2026-05-31",
  "min_amount": 10000,
  "max_amount": 1000000,
  "keyword": null,
  "page": 1,
  "size": 20
}
```

**파라미터 (모두 optional):**
- `member_id` (integer, min:1) — 익명만 보고 싶으면 미지정
- `category` (string, max:50) — 헌금 항목 (주일헌금, 감사헌금 등)
- `method` (in: cash, transfer)
- `ledger` (string, max:100)
- `from_date`, `to_date` (date)
- `min_amount`, `max_amount` (integer, min:0)
- `keyword` (string, max:100) — category/ledger/교인이름 LIKE
- `page`, `size`

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 35,
    "total_amount": 12500000,
    "page": 1, "size": 20,
    "list": [
      { "id": 1, "church_id": 1, "member_id": 1,
        "offer_date": "2026-05-17", "category": "주일헌금",
        "amount": 50000, "method": "cash", "ledger": "현금",
        "recorded_by": 1, "created_at": "...", "updated_at": "...",
        "member_no": "M-...", "member_name": "홍길동" }
    ]
  }
}
```

---

## 2. POST `/v4/offering/getDetail`

### Request Body
```json
{ "offering_id": 1 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "offering": { ... } } }
```

---

## 3. POST `/v4/offering/register`

### Request Body
```json
{
  "member_id": 1,
  "offer_date": "2026-05-17",
  "category": "주일헌금",
  "amount": 50000,
  "method": "cash",
  "ledger": "현금"
}
```

### Request Body (익명)
```json
{
  "member_id": null,
  "offer_date": "2026-05-17",
  "category": "감사헌금",
  "amount": 30000,
  "method": "cash"
}
```

**파라미터:**
- `offer_date` (date, required)
- `category` (string, required, max:50)
- `amount` (integer, required, min:0) — 0 허용
- `member_id` (integer, optional, null 허용 = 익명, 동일 교회 검증)
- `method` (in: cash, transfer, optional)
- `ledger` (string, max:100, optional)

### Response — 성공
```json
{ "status": "success", ..., "data": { "offering_id": 36 } }
```

---

## 4. POST `/v4/offering/update`

### Request Body
```json
{
  "offering_id": 36,
  "amount": 60000,
  "ledger": "국민은행"
}
```

**파라미터:**
- `offering_id` (required, min:1)
- 그 외 모두 sometimes

### Response — 성공
```json
{ "status": "success", ..., "data": { "offering_id": 36 } }
```

---

## 5. POST `/v4/offering/delete`

### Request Body
```json
{ "offering_id": 36 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "offering_id": 36 } }
```

---

## 6. POST `/v4/offering/getListByMember`

### Request Body
```json
{
  "member_id": 1,
  "from_date": "2026-01-01",
  "to_date": "2026-05-31",
  "category": null,
  "page": 1,
  "size": 20
}
```

**파라미터:**
- `member_id` (required, integer, min:1)
- 나머지 필터는 `getList` 와 동일 (`member_id` 만 위에서 받음)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "member_id": 1,
    "total": 12,
    "total_amount": 600000,
    "page": 1, "size": 20,
    "list": [ ... ]
  }
}
```
