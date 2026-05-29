# 5. 예배/모임 (Worship)

URL prefix: `{{ base_url }}/v4/worship`
모두 **인증 필요**

테이블: `reg_services` (도메인명을 Worship 으로 통일 — 서비스 레이어와 명명 충돌 회피)

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/worship/getList` | 예배 목록 |
| 2 | POST | `/v4/worship/getDetail` | 예배 상세 |
| 3 | POST | `/v4/worship/register` | 예배 등록 |
| 4 | POST | `/v4/worship/update` | 예배 수정 |
| 5 | POST | `/v4/worship/delete` | 예배 삭제 (soft, is_active=0) |

---

## 1. POST `/v4/worship/getList`

### Request Body
```json
{
  "is_active": true,
  "day_of_week": 0,
  "keyword": "주일"
}
```

**파라미터 (모두 optional):**
- `is_active` (boolean)
- `day_of_week` (integer, 0~6, 0=일요일)
- `keyword` (string, max:50) — name LIKE

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "list": [
      { "id": 1, "church_id": 1, "name": "주일 1부",
        "day_of_week": 0, "target_org_id": null,
        "sort_order": 0, "is_active": 1, ... }
    ]
  }
}
```

---

## 2. POST `/v4/worship/getDetail`

### Request Body
```json
{ "service_id": 1 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "worship": { ... } } }
```

---

## 3. POST `/v4/worship/register`

### Request Body
```json
{
  "name": "수요예배",
  "day_of_week": 3,
  "target_org_id": null,
  "sort_order": 5,
  "is_active": true
}
```

**파라미터:**
- `name` (string, required, max:50)
- `day_of_week` (integer, 0~6, optional)
- `target_org_id` (integer, optional, 동일 교회 검증)
- `sort_order` (integer, optional)
- `is_active` (boolean, optional, default true)

### Response — 성공
```json
{ "status": "success", ..., "data": { "service_id": 4 } }
```

---

## 4. POST `/v4/worship/update`

### Request Body
```json
{
  "service_id": 4,
  "name": "수요 저녁예배",
  "sort_order": 3
}
```

**파라미터:**
- `service_id` (required)
- 그 외 sometimes

### Response — 성공
```json
{ "status": "success", ..., "data": { "service_id": 4 } }
```

---

## 5. POST `/v4/worship/delete`

### Request Body
```json
{ "service_id": 4 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "service_id": 4 } }
```
