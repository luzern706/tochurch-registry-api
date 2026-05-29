# 4. 가족 관계 (Family)

URL prefix: `{{ base_url }}/v4/family`
모두 **인증 필요**

**특징**: 양방향 자동 동기화. 한쪽 등록/수정/삭제 시 반대 방향도 함께 처리.

**역방향 변환표:**
- 배우자 ↔ 배우자
- 부모 ↔ 자녀
- 자녀 ↔ 부모
- 형제자매 ↔ 형제자매
- 기타 ↔ 기타

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/family/getList` | 특정 교인의 가족 목록 |
| 2 | POST | `/v4/family/register` | 가족 등록 (양방향) |
| 3 | POST | `/v4/family/update` | 가족 관계 수정 (양방향 동기화) |
| 4 | POST | `/v4/family/delete` | 가족 삭제 (양방향) |

---

## 1. POST `/v4/family/getList`

### Request Body
```json
{ "member_id": 1 }
```

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "member_id": 1,
    "list": [
      {
        "id": 5, "member_id": 1, "related_member_id": 2,
        "relation_type": "배우자", "family_note": null,
        "created_at": "...",
        "member_no": "M-20260518-0002",
        "related_name": "김아내", "related_gender": "F",
        "related_birth_date": "1992-05-10"
      }
    ]
  }
}
```

---

## 2. POST `/v4/family/register`

### Request Body
```json
{
  "member_id": 1,
  "related_member_id": 2,
  "relation_type": "배우자",
  "family_note": "결혼 2020년"
}
```

**파라미터:**
- `member_id` (integer, required, min:1) — 동일 교회 검증
- `related_member_id` (integer, required, min:1, different from member_id) — 동일 교회 검증
- `relation_type` (string, required, in: 배우자, 부모, 자녀, 형제자매, 기타)
- `family_note` (string, optional)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "member_id": 1, "related_member_id": 2, "relation_type": "배우자"
  }
}
```

### Response — 실패
- `VALIDATION_FAILED` (400) — 자기 자신 등록 또는 허용되지 않은 관계
- `NOT_FOUND` (404) — 두 교인 중 한쪽이 다른 교회/존재하지 않음

---

## 3. POST `/v4/family/update`

### Request Body
```json
{
  "member_id": 1,
  "related_member_id": 2,
  "relation_type": "기타",
  "family_note": "수정된 메모"
}
```

**파라미터:**
- `member_id`, `related_member_id` (required)
- `relation_type` (sometimes), `family_note` (sometimes)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "member_id": 1, "related_member_id": 2, "relation_type": "기타"
  }
}
```

### Response — 실패
- `NOT_FOUND` (404) — 양쪽 row 없음

---

## 4. POST `/v4/family/delete`

### Request Body
```json
{ "member_id": 1, "related_member_id": 2 }
```

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "member_id": 1, "related_member_id": 2
  }
}
```

### Response — 실패
- `NOT_FOUND` (404)
