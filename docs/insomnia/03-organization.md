# 3. 조직 (Organization)

URL prefix: `{{ base_url }}/v4/organization`
모두 **인증 필요**

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/organization/getList` | 조직 목록 (flat, parent_id 포함) |
| 2 | POST | `/v4/organization/getDetail` | 조직 상세 |
| 3 | POST | `/v4/organization/register` | 조직 등록 |
| 4 | POST | `/v4/organization/update` | 조직 수정 (parent 변경 시 순환 차단) |
| 5 | POST | `/v4/organization/delete` | 조직 삭제 (hasChildren 거부, is_active=0) |
| 6 | POST | `/v4/organization/assignMember` | 교인-조직 매핑 (is_primary 자동 처리) |
| 7 | POST | `/v4/organization/unassignMember` | 매핑 해제 |
| 8 | POST | `/v4/organization/getMembersByOrg` | 조직 소속 교인 목록 |

---

## 1. POST `/v4/organization/getList`

### Request Body
```json
{
  "parent_id": null,
  "is_active": true,
  "keyword": "전도회"
}
```
**파라미터 (모두 optional):**
- `parent_id` (integer/null) — null 명시 시 최상위만, 미지정 시 전체
- `is_active` (boolean)
- `keyword` (string, max:50) — 조직명 LIKE

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "list": [
      { "id": 1, "church_id": 1, "parent_id": null, "name": "남전도회", "sort_order": 0, "is_active": 1, ... }
    ]
  }
}
```

---

## 2. POST `/v4/organization/getDetail`

### Request Body
```json
{ "organization_id": 1 }
```
**파라미터:**
- `organization_id` (integer, required, min:1)

### Response — 성공
```json
{ "status": "success", ..., "data": { "organization": { ... } } }
```

---

## 3. POST `/v4/organization/register`

### Request Body
```json
{
  "name": "청년부",
  "parent_id": null,
  "sort_order": 10
}
```
**파라미터:**
- `name` (string, required, max:50)
- `parent_id` (integer/null, optional, 동일 교회 검증)
- `sort_order` (integer, optional)

### Response — 성공
```json
{ "status": "success", ..., "data": { "organization_id": 7 } }
```

### Response — 실패
- `NOT_FOUND` (404) — parent_id 가 다른 교회/존재하지 않음

---

## 4. POST `/v4/organization/update`

### Request Body
```json
{
  "organization_id": 7,
  "name": "청년1부",
  "parent_id": 1,
  "sort_order": 5,
  "is_active": true
}
```
**파라미터:**
- `organization_id` (integer, required, min:1)
- 그 외 필드 모두 sometimes (`name`, `parent_id`, `sort_order`, `is_active`)

### Response — 성공
```json
{ "status": "success", ..., "data": { "organization_id": 7 } }
```

### Response — 실패
- `VALIDATION_FAILED` (400) — 자기 자신 또는 자손을 parent 로 지정 시
- `NOT_FOUND` (404) — parent 조직이 다른 교회

---

## 5. POST `/v4/organization/delete`

### Request Body
```json
{ "organization_id": 7 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "organization_id": 7 } }
```

### Response — 실패
- `HAS_CHILDREN` (400) — 활성 하위 조직이 있음
- `NOT_FOUND` (404)

---

## 6. POST `/v4/organization/assignMember`

### Request Body
```json
{
  "member_id": 1,
  "organization_id": 7,
  "is_primary": true,
  "joined_at": "2026-01-15"
}
```
**파라미터:**
- `member_id` (integer, required, min:1) — 동일 교회 검증
- `organization_id` (integer, required, min:1) — 동일 교회 검증
- `is_primary` (boolean, optional, default false) — true 시 해당 교인의 다른 매핑들은 자동으로 is_primary=false
- `joined_at` (date, optional)

### Response — 성공
```json
{ "status": "success", ..., "data": { "member_id": 1, "organization_id": 7 } }
```

---

## 7. POST `/v4/organization/unassignMember`

### Request Body
```json
{ "member_id": 1, "organization_id": 7 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "member_id": 1, "organization_id": 7 } }
```

### Response — 실패
- `NOT_FOUND` (404) — 매핑 없음

---

## 8. POST `/v4/organization/getMembersByOrg`

### Request Body
```json
{
  "organization_id": 7,
  "keyword": "홍",
  "page": 1,
  "size": 20
}
```

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 5, "page": 1, "size": 20,
    "list": [
      { "id": 1, "member_no": "M-...", "name": "...", "email": "...",
        "phone": "...", "gender": "M", "status": "active",
        "is_primary": 1, "joined_at": "2026-01-15" }
    ]
  }
}
```
