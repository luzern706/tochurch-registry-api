# 8. 봉사 (Volunteer)

URL prefix: `{{ base_url }}/v4/volunteer`
모두 **인증 필요**

테이블: `reg_service_teams` (봉사 팀), `reg_service_members` (참여자 매핑)

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/volunteer/getList` | 봉사 팀 목록 |
| 2 | POST | `/v4/volunteer/getDetail` | 봉사 팀 상세 |
| 3 | POST | `/v4/volunteer/register` | 봉사 팀 등록 |
| 4 | POST | `/v4/volunteer/update` | 봉사 팀 수정 |
| 5 | POST | `/v4/volunteer/delete` | 봉사 팀 삭제 (soft, is_active=0) |
| 6 | POST | `/v4/volunteer/assignVolunteer` | 봉사자 배정 (UPSERT) |
| 7 | POST | `/v4/volunteer/unassignVolunteer` | 봉사자 해제 (status=inactive) |
| 8 | POST | `/v4/volunteer/getVolunteersByTeam` | 팀 소속 봉사자 목록 |
| 9 | POST | `/v4/volunteer/getTeamsByMember` | 특정 교인의 참여 팀 목록 |

---

## 1. POST `/v4/volunteer/getList`

### Request Body
```json
{
  "service_type": "예배",
  "mode": "regular",
  "is_active": true,
  "manager_member_id": null,
  "keyword": "주차",
  "page": 1,
  "size": 20
}
```

**파라미터 (모두 optional):**
- `service_type` (string, max:30) — 봉사 유형
- `mode` (in: regular, onetime)
- `is_active` (boolean)
- `manager_member_id` (integer)
- `keyword` (string, max:100) — name/description LIKE
- `page`, `size`

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 8, "page": 1, "size": 20,
    "list": [
      { "id": 1, "church_id": 1, "name": "주일 주차안내", "description": "...",
        "service_type": "예배", "mode": "regular", "frequency": "weekly",
        "day_of_week": 0, "service_time": "09:00:00",
        "event_date": null, "start_time": null, "end_time": null,
        "required_count": 4, "manager_member_id": 2,
        "participation_type": "assignment", "is_active": 1, ... }
    ]
  }
}
```

---

## 2. POST `/v4/volunteer/getDetail`

### Request Body
```json
{ "team_id": 1 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "team": { ... } } }
```

---

## 3. POST `/v4/volunteer/register`

### Request Body — 정기 (regular)
```json
{
  "name": "주일 주차안내",
  "description": "주일 1부 예배 주차 안내",
  "service_type": "예배",
  "mode": "regular",
  "frequency": "weekly",
  "day_of_week": 0,
  "service_time": "09:00:00",
  "required_count": 4,
  "manager_member_id": 2,
  "participation_type": "assignment",
  "is_active": true
}
```

### Request Body — 일회성 (onetime)
```json
{
  "name": "체육대회 운영",
  "mode": "onetime",
  "event_date": "2026-06-15",
  "start_time": "09:00:00",
  "end_time": "17:00:00",
  "required_count": 10,
  "manager_member_id": 2,
  "participation_type": "application"
}
```

**파라미터:**
- `name` (string, required, max:100)
- `description` (string, max:300, optional)
- `service_type` (string, max:30, optional)
- `mode` (in: regular, onetime, optional)
- `frequency` (in: weekly, biweekly, monthly, optional) — regular 시
- `day_of_week` (integer, 0~6, optional)
- `service_time` (HH:MM:SS, optional)
- `event_date` (date, optional) — onetime 시
- `start_time`, `end_time` (HH:MM:SS, optional)
- `required_count` (integer, min:1, optional)
- `manager_member_id` (integer, min:1, optional, 동일 교회 검증)
- `participation_type` (in: application, assignment, optional)
- `is_active` (boolean, optional, default true)

### Response — 성공
```json
{ "status": "success", ..., "data": { "team_id": 6 } }
```

---

## 4. POST `/v4/volunteer/update`

### Request Body
```json
{
  "team_id": 6,
  "required_count": 6,
  "is_active": true
}
```

**파라미터:**
- `team_id` (required)
- 그 외 sometimes (`name` 등 모두)

### Response — 성공
```json
{ "status": "success", ..., "data": { "team_id": 6 } }
```

---

## 5. POST `/v4/volunteer/delete`

### Request Body
```json
{ "team_id": 6 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "team_id": 6 } }
```

---

## 6. POST `/v4/volunteer/assignVolunteer`

### Request Body
```json
{
  "team_id": 1,
  "member_id": 5,
  "role": "리더",
  "joined_at": "2026-01-15",
  "status": "active"
}
```

**파라미터:**
- `team_id`, `member_id` (required, integer, min:1) — 동일 교회 검증
- `role` (string, max:50, optional)
- `joined_at` (date, optional)
- `status` (in: active, inactive, optional, default active)

### Response — 성공
```json
{ "status": "success", ..., "data": { "mapping_id": 10, "team_id": 1, "member_id": 5 } }
```

---

## 7. POST `/v4/volunteer/unassignVolunteer`

### Request Body
```json
{ "team_id": 1, "member_id": 5 }
```

### Response — 성공
```json
{ "status": "success", ..., "data": { "mapping_id": 10, "team_id": 1, "member_id": 5 } }
```
- `status='inactive'` 로 soft 해제 (이력 보존)

---

## 8. POST `/v4/volunteer/getVolunteersByTeam`

### Request Body
```json
{
  "team_id": 1,
  "status": "active",
  "page": 1,
  "size": 20
}
```

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 4, "page": 1, "size": 20,
    "list": [
      { "mapping_id": 10, "role": "리더", "joined_at": "2026-01-15", "status": "active",
        "member_id": 5, "member_no": "M-...", "name": "...", "email": "...", "phone": "..." }
    ]
  }
}
```

---

## 9. POST `/v4/volunteer/getTeamsByMember`

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
      { "mapping_id": 10, "role": "리더", "joined_at": "...", "status": "active",
        "team_id": 1, "team_name": "주일 주차안내",
        "service_type": "예배", "mode": "regular", "team_is_active": 1 }
    ]
  }
}
```
