# Insomnia API 테스트 체크리스트

> API 서버 베이스 URL: `http://localhost:8000/api`  
> 모든 요청: `POST` 방식 / Content-Type: `application/json`  
> 인증 필요 항목: `Authorization: Bearer {token}` 헤더 포함  
> 총 엔드포인트: **68개**

---

## 환경 변수 (Insomnia Environment)

```json
{
  "base_url": "http://localhost:8000/api",
  "token": "로그인 후 발급된 JWT 토큰",
  "member_id": 1,
  "church_id": 1
}
```

---

## 1. 인증 (Auth)

### 1-1. 관리자 로그인
- [ ] **POST** `/v4/auth/adminSignIn` — 인증 불필요

```json
{
  "login_id": "admin01",
  "password": "password1234"
}
```

**성공 응답 예시**
```json
{
  "status": "success",
  "data": {
    "token": "eyJ...",
    "admin_role": "super",
    "admin_no": 1,
    "login_id": "admin01",
    "church_id": 1
  }
}
```

---

### 1-2. 로그아웃
- [ ] **POST** `/v4/auth/signOut` — 인증 필요

```json
{}
```

---

## 2. 관리자 계정 관리 (Admin) — 슈퍼 관리자 전용

### 2-1. 관리자 목록 조회
- [ ] **POST** `/v4/admin/getList`

```json
{
  "page": 1,
  "size": 20
}
```

### 2-2. 관리자 상세 조회
- [ ] **POST** `/v4/admin/getDetail`

```json
{
  "admin_no": 1
}
```

### 2-3. 관리자 등록
- [ ] **POST** `/v4/admin/register`

```json
{
  "login_id": "admin02",
  "password": "password1234",
  "admin_type": "admin"
}
```
> `admin_type`: `super` | `admin` / `church_id`는 JWT에서 자동 취득

### 2-4. 관리자 수정
- [ ] **POST** `/v4/admin/update`

```json
{
  "admin_no": 2,
  "status": "ALIVE",
  "admin_type": "admin"
}
```
> `status`: `ALIVE` | `WAIT`

### 2-5. 관리자 비활성화 (삭제)
- [ ] **POST** `/v4/admin/delete`

```json
{
  "admin_no": 2
}
```

---

## 3. 교인 관리 (Member)

### 3-1. 교인 목록 조회
- [ ] **POST** `/v4/member/getList`

```json
{
  "keyword": "홍길동",
  "status": "active",
  "page": 1,
  "size": 20
}
```
> `status`: `active` | `inactive` | `unknown`

### 3-2. 교인 상세 조회
- [ ] **POST** `/v4/member/getDetail`

```json
{
  "member_id": 1
}
```

### 3-3. 교인 등록
- [ ] **POST** `/v4/member/register`

```json
{
  "email": "hong@example.com",
  "password": "1234",
  "name": "홍길동",
  "nickname": "길동",
  "phone": "010-1234-5678",
  "gender": "M",
  "birth_date": "1990-01-15",
  "birth_type": "solar",
  "address_zip": "12345",
  "address_main": "서울시 강남구",
  "address_detail": "101호",
  "status": "active",
  "member_type": "정교인",
  "position": "집사",
  "baptism_grade": "세례",
  "attendance_grade": "A",
  "marriage_status": "married",
  "is_household_head": true
}
```
> `gender`: `M` | `F` / `birth_type`: `solar` | `lunar`  
> `marriage_status`: `single` | `married` | `widowed` | `divorced`

### 3-4. 교인 수정
- [ ] **POST** `/v4/member/update`

```json
{
  "member_id": 1,
  "phone": "010-9999-8888",
  "position": "권사"
}
```

### 3-5. 교인 삭제 (소프트)
- [ ] **POST** `/v4/member/delete`

```json
{
  "member_id": 1
}
```

---

## 4. 조직 관리 (Organization)

### 4-1. 조직 목록 조회
- [ ] **POST** `/v4/organization/getList`

```json
{
  "is_active": true,
  "keyword": "청년"
}
```

### 4-2. 조직 상세 조회
- [ ] **POST** `/v4/organization/getDetail`

```json
{
  "organization_id": 1
}
```

### 4-3. 조직 등록
- [ ] **POST** `/v4/organization/register`

```json
{
  "name": "청년부",
  "parent_id": null,
  "sort_order": 1
}
```

### 4-4. 조직 수정
- [ ] **POST** `/v4/organization/update`

```json
{
  "organization_id": 1,
  "name": "청년1부",
  "is_active": true
}
```

### 4-5. 조직 삭제
- [ ] **POST** `/v4/organization/delete`

```json
{
  "organization_id": 1
}
```

### 4-6. 교인 조직 배정
- [ ] **POST** `/v4/organization/assignMember`

```json
{
  "member_id": 1,
  "organization_id": 1,
  "is_primary": true,
  "joined_at": "2024-01-01"
}
```

### 4-7. 교인 조직 해제
- [ ] **POST** `/v4/organization/unassignMember`

```json
{
  "member_id": 1,
  "organization_id": 1
}
```

### 4-8. 조직별 교인 목록
- [ ] **POST** `/v4/organization/getMembersByOrg`

```json
{
  "organization_id": 1,
  "page": 1,
  "size": 20
}
```

---

## 5. 가족 관계 (Family)

### 5-1. 가족 목록 조회
- [ ] **POST** `/v4/family/getList`

```json
{
  "member_id": 1
}
```

### 5-2. 가족 관계 등록
- [ ] **POST** `/v4/family/register`

```json
{
  "member_id": 1,
  "related_member_id": 2,
  "relation_type": "배우자",
  "family_note": ""
}
```
> `relation_type`: `배우자` | `부모` | `자녀` | `형제자매` | `기타`  
> 양방향 자동 등록됨 (배우자 등록 시 상대방도 배우자로 등록)

### 5-3. 가족 관계 수정
- [ ] **POST** `/v4/family/update`

```json
{
  "member_id": 1,
  "related_member_id": 2,
  "relation_type": "부모"
}
```

### 5-4. 가족 관계 삭제
- [ ] **POST** `/v4/family/delete`

```json
{
  "member_id": 1,
  "related_member_id": 2
}
```

---

## 6. 예배/모임 정의 (Worship)

### 6-1. 예배 목록 조회
- [ ] **POST** `/v4/worship/getList`

```json
{
  "is_active": true
}
```

### 6-2. 예배 상세 조회
- [ ] **POST** `/v4/worship/getDetail`

```json
{
  "service_id": 1
}
```

### 6-3. 예배 등록
- [ ] **POST** `/v4/worship/register`

```json
{
  "name": "주일 1부 예배",
  "day_of_week": 0,
  "sort_order": 1,
  "is_active": true
}
```
> `day_of_week`: 0=일 1=월 2=화 3=수 4=목 5=금 6=토

### 6-4. 예배 수정
- [ ] **POST** `/v4/worship/update`

```json
{
  "service_id": 1,
  "name": "주일 1부 예배 (수정)",
  "is_active": true
}
```

### 6-5. 예배 삭제
- [ ] **POST** `/v4/worship/delete`

```json
{
  "service_id": 1
}
```

---

## 7. 출석 관리 (Attendance)

### 7-1. 출석 일괄 기록
- [ ] **POST** `/v4/attendance/recordBulk`

```json
{
  "service_id": 1,
  "attend_date": "2026-06-22",
  "records": [
    { "member_id": 1, "status": "present", "note": "" },
    { "member_id": 2, "status": "absent",  "note": "여행" },
    { "member_id": 3, "status": "unknown", "note": "" }
  ]
}
```
> `status`: `present` | `absent` | `unknown`

### 7-2. 예배별 출석 조회
- [ ] **POST** `/v4/attendance/getListByService`

```json
{
  "service_id": 1,
  "attend_date": "2026-06-22"
}
```

### 7-3. 교인별 출석 조회
- [ ] **POST** `/v4/attendance/getListByMember`

```json
{
  "member_id": 1,
  "from_date": "2026-01-01",
  "to_date": "2026-06-30"
}
```

---

## 8. 심방 관리 (Visit)

### 8-1. 심방 목록 조회
- [ ] **POST** `/v4/visit/getList`

```json
{
  "from_date": "2026-01-01",
  "to_date": "2026-06-30",
  "page": 1,
  "size": 20
}
```
> `visit_type`: `annual` | `event`

### 8-2. 심방 상세 조회
- [ ] **POST** `/v4/visit/getDetail`

```json
{
  "visit_id": 1
}
```

### 8-3. 심방 등록
- [ ] **POST** `/v4/visit/register`

```json
{
  "member_id": 1,
  "visit_type": "annual",
  "visit_date": "2026-06-20",
  "visitor_member_id": 2,
  "place": "자택",
  "content": "가정 심방 진행. 건강 양호.",
  "add_to_prayer": false
}
```

### 8-4. 심방 수정
- [ ] **POST** `/v4/visit/update`

```json
{
  "visit_id": 1,
  "content": "수정된 내용",
  "common_note": "메모"
}
```

### 8-5. 심방 삭제
- [ ] **POST** `/v4/visit/delete`

```json
{
  "visit_id": 1
}
```

---

## 9. 봉사 관리 (Volunteer)

### 9-1. 봉사팀 목록 조회
- [ ] **POST** `/v4/volunteer/getList`

```json
{
  "is_active": true,
  "page": 1,
  "size": 20
}
```

### 9-2. 봉사팀 상세 조회
- [ ] **POST** `/v4/volunteer/getDetail`

```json
{
  "team_id": 1
}
```

### 9-3. 봉사팀 등록
- [ ] **POST** `/v4/volunteer/register`

```json
{
  "name": "주차 봉사팀",
  "service_type": "주차",
  "mode": "regular",
  "frequency": "weekly",
  "day_of_week": 0,
  "is_active": true
}
```
> `mode`: `regular` | `onetime`  
> `frequency`: `weekly` | `biweekly` | `monthly`

### 9-4. 봉사팀 수정
- [ ] **POST** `/v4/volunteer/update`

```json
{
  "team_id": 1,
  "name": "주차 봉사팀 (수정)"
}
```

### 9-5. 봉사팀 삭제
- [ ] **POST** `/v4/volunteer/delete`

```json
{
  "team_id": 1
}
```

### 9-6. 봉사자 배정
- [ ] **POST** `/v4/volunteer/assignVolunteer`

```json
{
  "team_id": 1,
  "member_id": 1,
  "role": "팀장",
  "joined_at": "2026-01-01",
  "status": "active"
}
```

### 9-7. 봉사자 해제
- [ ] **POST** `/v4/volunteer/unassignVolunteer`

```json
{
  "team_id": 1,
  "member_id": 1
}
```

### 9-8. 봉사팀별 봉사자 목록
- [ ] **POST** `/v4/volunteer/getVolunteersByTeam`

```json
{
  "team_id": 1,
  "status": "active"
}
```

### 9-9. 교인별 봉사팀 목록
- [ ] **POST** `/v4/volunteer/getTeamsByMember`

```json
{
  "member_id": 1
}
```

---

## 10. 교육 관리 (Education)

### 10-1. 교육 과정 목록 조회
- [ ] **POST** `/v4/education/getList`

```json
{
  "status": "ongoing",
  "page": 1,
  "size": 20
}
```
> `status`: `upcoming` | `ongoing` | `completed`

### 10-2. 교육 과정 상세 조회
- [ ] **POST** `/v4/education/getDetail`

```json
{
  "course_id": 1
}
```

### 10-3. 교육 과정 등록
- [ ] **POST** `/v4/education/register`

```json
{
  "name": "새신자반",
  "description": "새로 등록한 교인을 위한 기초 교육",
  "start_date": "2026-07-01",
  "end_date": "2026-07-31",
  "status": "upcoming"
}
```

### 10-4. 교육 과정 수정
- [ ] **POST** `/v4/education/update`

```json
{
  "course_id": 1,
  "status": "ongoing"
}
```

### 10-5. 교육 과정 삭제
- [ ] **POST** `/v4/education/delete`

```json
{
  "course_id": 1
}
```

### 10-6. 수강 등록
- [ ] **POST** `/v4/education/enroll`

```json
{
  "course_id": 1,
  "member_id": 1
}
```

### 10-7. 수강 진도 수정
- [ ] **POST** `/v4/education/updateProgress`

```json
{
  "course_id": 1,
  "member_id": 1,
  "progress": 80,
  "status": "ongoing"
}
```
> `progress`: 0~100 / `status`: `ongoing` | `completed` | `dropped`

### 10-8. 수강 철회
- [ ] **POST** `/v4/education/withdraw`

```json
{
  "course_id": 1,
  "member_id": 1
}
```

### 10-9. 과정별 수강자 목록
- [ ] **POST** `/v4/education/getEnrollmentsByCourse`

```json
{
  "course_id": 1,
  "status": "ongoing"
}
```

### 10-10. 교인별 수강 과정 목록
- [ ] **POST** `/v4/education/getCoursesByMember`

```json
{
  "member_id": 1
}
```

---

## 11. 헌금 관리 (Offering)

### 11-1. 헌금 목록 조회
- [ ] **POST** `/v4/offering/getList`

```json
{
  "from_date": "2026-01-01",
  "to_date": "2026-06-30",
  "category": "십일조",
  "page": 1,
  "size": 20
}
```
> `method`: `cash` | `transfer`

### 11-2. 헌금 상세 조회
- [ ] **POST** `/v4/offering/getDetail`

```json
{
  "offering_id": 1
}
```

### 11-3. 헌금 등록
- [ ] **POST** `/v4/offering/register`

```json
{
  "member_id": 1,
  "offer_date": "2026-06-22",
  "category": "십일조",
  "amount": 100000,
  "method": "transfer",
  "ledger": "2026년 2분기"
}
```

### 11-4. 헌금 수정
- [ ] **POST** `/v4/offering/update`

```json
{
  "offering_id": 1,
  "amount": 150000,
  "ledger": "2026년 2분기 (수정)"
}
```

### 11-5. 헌금 삭제
- [ ] **POST** `/v4/offering/delete`

```json
{
  "offering_id": 1
}
```

### 11-6. 교인별 헌금 목록
- [ ] **POST** `/v4/offering/getListByMember`

```json
{
  "member_id": 1,
  "from_date": "2026-01-01",
  "to_date": "2026-06-30"
}
```

---

## 12. 메시지 발송 (Message)

### 12-1. 메시지 목록 조회
- [ ] **POST** `/v4/message/getList`

```json
{
  "send_type": "sms",
  "from_date": "2026-06-01",
  "to_date": "2026-06-30",
  "page": 1,
  "size": 20
}
```
> `send_type`: `sms` | `push` | `email`

### 12-2. 메시지 상세 조회
- [ ] **POST** `/v4/message/getDetail`

```json
{
  "message_id": 1
}
```

### 12-3. 메시지 발송
- [ ] **POST** `/v4/message/send`

```json
{
  "title": "주일 예배 안내",
  "content": "이번 주 주일 예배는 오전 11시입니다.",
  "send_type": "sms",
  "target_type": "all"
}
```

조직 대상 발송:
```json
{
  "title": "청년부 모임 안내",
  "content": "이번 주 금요일 저녁 7시 모임이 있습니다.",
  "send_type": "sms",
  "target_type": "organization",
  "organization_id": 1
}
```

특정 교인 대상 발송:
```json
{
  "title": "개인 안내",
  "content": "목사님이 연락 드립니다.",
  "send_type": "sms",
  "target_type": "members",
  "member_ids": [1, 2, 3]
}
```
> `target_type`: `all` | `organization` | `members`  
> ⚠️ 실제 SMS/Push/Email 게이트웨이 연동은 미구현 — 발송 기록만 저장됨

### 12-4. 메시지 삭제
- [ ] **POST** `/v4/message/delete`

```json
{
  "message_id": 1
}
```

---

## 13. 보고서·통계 (Report)

### 13-1. 교인 현황 통계
- [ ] **POST** `/v4/report/getMemberStats`

```json
{}
```

### 13-2. 출석 통계
- [ ] **POST** `/v4/report/getAttendanceStats`

```json
{
  "from_date": "2026-01-01",
  "to_date": "2026-06-30",
  "service_id": 1
}
```

### 13-3. 헌금 통계
- [ ] **POST** `/v4/report/getOfferingStats`

```json
{
  "from_date": "2026-01-01",
  "to_date": "2026-06-30",
  "category": "십일조"
}
```

### 13-4. 심방 현황 통계
- [ ] **POST** `/v4/report/getVisitStats`

```json
{
  "from_date": "2026-01-01",
  "to_date": "2026-06-30"
}
```

### 13-5. 대시보드
- [ ] **POST** `/v4/report/getDashboard`

```json
{}
```

---

## 진행 현황 요약

| 그룹 | 총 항목 | 완료 |
|---|:---:|:---:|
| 인증 | 2 | 0 |
| 관리자 계정 | 5 | 0 |
| 교인 관리 | 5 | 0 |
| 조직 관리 | 8 | 0 |
| 가족 관계 | 4 | 0 |
| 예배 정의 | 5 | 0 |
| 출석 관리 | 3 | 0 |
| 심방 관리 | 5 | 0 |
| 봉사 관리 | 9 | 0 |
| 교육 관리 | 10 | 0 |
| 헌금 관리 | 6 | 0 |
| 메시지 발송 | 4 | 0 |
| 보고서·통계 | 5 | 0 |
| **합계** | **71** | **0** |

> 테스트 완료 시 `- [ ]` → `- [x]` 로 변경

---

*최종 수정: 2026-06-20*
