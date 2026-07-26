# 8. 봉사 (Volunteer)

URL prefix: `{{ base_url }}/v4/volunteer`
모두 **인증 필요**

테이블: `reg_service_teams` (봉사 팀), `reg_service_members` (참여자 매핑), `reg_service_attendance` (출결)
저장 프로시저: `sp_v4_reg_volunteer_by_team`, `sp_v4_reg_volunteer_teams_by_member`, `sp_v4_reg_volunteer_history`, `sp_v4_reg_volunteer_team_history` — [05_sp_v4_reg_volunteer.sql](../schema/05_sp_v4_reg_volunteer.sql)

> **테스트 데이터**: `church_id=33632` 기준 [104_seed_volunteer.sql](../schema/104_seed_volunteer.sql)(팀 8건 + 참여자 30건) + [105_seed_volunteer_attendance.sql](../schema/105_seed_volunteer_attendance.sql)(출결 105건)이 적재되어 있습니다.
> 아래 예시의 `team_id`/`member_id`는 이 시드 데이터의 실제 값입니다 (team 1=주일 주차 안내, member 6=부활절 특송팀 활동중 + 주일학교 교사 해제됨 두 이력 보유).

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
| 10 | POST | `/v4/volunteer/getHistory` | 교회 전체 봉사 이력 (봉사이력 "교인 중심" 탭 기본 목록) |
| 11 | POST | `/v4/volunteer/getTeamHistory` | 봉사 팀별 이력 요약 (봉사이력 "봉사 중심" 탭 기본 목록) |
| 12 | POST | `/v4/volunteer/attendance/getSheet` | 팀 출결 시트 조회 (체크인 화면용) |
| 13 | POST | `/v4/volunteer/attendance/save` | 특정 날짜 출결 일괄 저장 (UPSERT) |
| 14 | POST | `/v4/volunteer/attendance/getMemberStats` | 팀별 교인 출결 통계 |

---

## 1. POST `/v4/volunteer/getList`

### Request Body
```json
{
  "service_type": "worship",
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
      { "id": 1, "church_id": 33632, "name": "주일 주차 안내", "description": "주일 예배 시간에 교회 주차장 안내를 도와주세요",
        "service_type": "parking", "mode": "regular", "frequency": "weekly",
        "day_of_week": 0, "service_time": "08:30:00",
        "event_date": null, "start_time": null, "end_time": null,
        "required_count": 6, "manager_member_id": 8,
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
  "name": "주일 주차 안내",
  "description": "주일 1부 예배 주차 안내",
  "service_type": "parking",
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

SP: `sp_v4_reg_volunteer_by_team` (reg_service_members + reg_members JOIN)

### Request Body
```json
{
  "team_id": 1,
  "status": "active",
  "page": 1,
  "size": 20
}
```

### Response — 성공 (시드 데이터 기준 실측값)
```json
{
  "status": "success", ..., "data": {
    "total": 4, "page": 1, "size": 20,
    "list": [
      { "mapping_id": 4, "role": "안내", "joined_at": "2026-01-05", "status": "active",
        "member_id": 19, "member_no": "M-2026016", "name": "강미숙", "email": "...", "phone": null },
      { "mapping_id": 1, "role": "안내", "joined_at": "2026-01-05", "status": "active",
        "member_id": 16, "member_no": "M-2026013", "name": "박명자", "email": "...", "phone": null }
    ]
  }
}
```

---

## 9. POST `/v4/volunteer/getTeamsByMember`

SP: `sp_v4_reg_volunteer_teams_by_member` (reg_service_members + reg_service_teams JOIN)

### Request Body
```json
{ "member_id": 6 }
```

### Response — 성공 (시드 데이터 기준 실측값 — 활동중 팀 + 해제된 팀 이력 모두 조회됨)
```json
{
  "status": "success", ..., "data": {
    "member_id": 6,
    "list": [
      { "mapping_id": 14, "role": "찬양", "joined_at": "2026-03-01", "status": "active",
        "team_id": 5, "team_name": "부활절 특송팀",
        "service_type": "special", "mode": "onetime", "team_is_active": 1 },
      { "mapping_id": 24, "role": "교사", "joined_at": "2024-01-01", "status": "inactive",
        "team_id": 7, "team_name": "주일학교 교사",
        "service_type": "worship", "mode": "regular", "team_is_active": 1 }
    ]
  }
}
```

---

## 10. POST `/v4/volunteer/getHistory`

SP: `sp_v4_reg_volunteer_history` (reg_service_members + reg_members + reg_service_teams 3-way JOIN)

`member_id`/`team_id` 둘 다 생략하면 **교회 전체 봉사 이력**을 반환 — 봉사이력 화면(교인 중심/봉사 중심 탭)의 기본 목록으로 사용되며, 검색은 그 결과를 좁히는 용도.

### Request Body — 전체 조회 (기본)
```json
{ "page": 1, "size": 20 }
```

### Request Body — 특정 교인으로 좁힘
```json
{ "member_id": 6, "page": 1, "size": 20 }
```

### Request Body — 특정 봉사로 좁힘
```json
{ "team_id": 1, "status": "active", "page": 1, "size": 20 }
```

**파라미터 (모두 optional):**
- `member_id` (integer) — 특정 교인으로 좁힘
- `team_id` (integer) — 특정 봉사 팀으로 좁힘
- `status` (in: active, inactive)
- `service_type` (string, max:30) — 봉사 종류 필터
- `keyword` (string, max:100) — 교인명/봉사명 LIKE
- `from_date`, `to_date` (date) — 배정일(joined_at) 범위
- `page`, `size`

### Response — 성공 (시드 데이터 기준 실측값, 전체 조회 시 total=30)
```json
{
  "status": "success", ..., "data": {
    "total": 30, "page": 1, "size": 20,
    "list": [
      { "mapping_id": 14, "role": "찬양", "joined_at": "2026-03-01", "status": "active",
        "member_id": 6, "member_no": "M-2026003", "member_name": "이재훈",
        "team_id": 5, "team_name": "부활절 특송팀",
        "service_type": "special", "mode": "onetime", "team_is_active": 1,
        "attend_count": 1, "last_service_date": "2026-04-05" }
    ]
  }
}
```
> `attend_count`/`last_service_date`는 `reg_service_attendance` 기반 실측 출석 통계 (105_seed_volunteer_attendance.sql 적재 후 값).

---

## 11. POST `/v4/volunteer/getTeamHistory`

SP: `sp_v4_reg_volunteer_team_history` (reg_service_teams + reg_service_members + reg_service_attendance 집계)

`team_id` 생략 시 **교회 전체 팀**을 반환 — 봉사이력 화면 "봉사 중심" 탭의 기본 목록으로 사용.

### Request Body — 전체 조회 (기본)
```json
{ "page": 1, "size": 20 }
```

**파라미터 (모두 optional):**
- `team_id` (integer) — 특정 봉사 팀으로 좁힘
- `service_type` (string, max:30), `mode` (in: regular, onetime)
- `keyword` (string, max:100) — 봉사명 LIKE
- `from_date` (date) — 팀 최초 배정일(first_joined_at) 하한
- `to_date` (date) — 최근 참여일(last_service_date) 상한
- `page`, `size`

### Response — 성공 (시드 데이터 기준 실측값, total=8)
```json
{
  "status": "success", ..., "data": {
    "total": 8, "page": 1, "size": 20,
    "list": [
      { "team_id": 1, "team_name": "주일 주차 안내", "service_type": "parking", "mode": "regular",
        "is_active": 1, "event_date": null, "start_time": null, "end_time": null,
        "participant_count": 4, "attend_count": 22,
        "last_service_date": "2026-02-15", "first_joined_at": "2026-01-05" }
    ]
  }
}
```

---

## 12. POST `/v4/volunteer/attendance/getSheet`

봉사 팀의 활동중 참여자 + 기록된 출결 데이터 조회 (체크인 화면용). `round_no` 대신 실제 날짜(`service_date`)를 키로 사용 — 교육과 달리 정기 팀은 고정 회차 수가 없음.

### Request Body
```json
{ "team_id": 1 }
```

### Response — 성공 (시드 데이터 기준 실측값)
```json
{
  "status": "success", ..., "data": {
    "members": [
      { "member_id": 16, "name": "박명자", "phone": null },
      { "member_id": 17, "name": "최정숙", "phone": null }
    ],
    "dates": ["2026-02-15", "2026-02-08", "2026-02-01", "2026-01-25", "2026-01-18", "2026-01-11"],
    "attendance": { "16_2026-02-15": "present", "18_2026-01-18": "absent" }
  }
}
```

---

## 13. POST `/v4/volunteer/attendance/save`

특정 날짜 출결 일괄 UPSERT — 이미 기록된 날짜를 다시 저장하면 상태가 덮어써짐.

### Request Body
```json
{
  "team_id": 1,
  "service_date": "2026-07-19",
  "records": [
    { "member_id": 16, "status": "present" },
    { "member_id": 17, "status": "present" },
    { "member_id": 18, "status": "absent" },
    { "member_id": 19, "status": "late", "note": "5분 지각" }
  ]
}
```

**파라미터:**
- `team_id`, `service_date` (required)
- `records` (required, array, min:1)
- `records[].member_id` (required, integer)
- `records[].status` (required, in: present, absent, late, excused)
- `records[].note` (optional, string, max:200)

### Response — 성공
```json
{ "status": "success", ..., "data": { "team_id": 1, "service_date": "2026-07-19", "saved": 4 } }
```

---

## 14. POST `/v4/volunteer/attendance/getMemberStats`

봉사 팀별 교인 출결 통계.

### Request Body
```json
{ "team_id": 1 }
```

### Response — 성공 (시드 데이터 기준 실측값)
```json
{
  "status": "success", ..., "data": {
    "team_id": 1,
    "list": [
      { "member_id": 16, "name": "박명자", "phone": null,
        "present": "6", "absent": "0", "late": "0", "excused": "0",
        "total": 6, "last_service_date": "2026-02-15" }
    ]
  }
}
```
