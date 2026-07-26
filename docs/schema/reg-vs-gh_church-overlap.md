# reg_ 테이블 ↔ gh_church_ / gh_account 중복 조사

> 2026-07-17 조사. `reg_` 테이블 전체를 `gh_church_*`/`gh_account`와 컬럼 단위로 비교.

---

## 1. reg_members / reg_member_profiles / reg_families ↔ gh_account

**중복이지만 이미 설계상 연결되어 있음 — 조치 불필요.**

| | 내용 |
|---|---|
| gh_account | 교회로 앱 계정 전반 — name/email/phone/birthday/address/gender + 가족관계(`headAccountNo`/`familyRelationSeq`/`bringAccountNO1`/`bringAccountNO2`) + 교회소속(`church_no`/`churchDepartment`/`churchGrade`) 등 70+ 컬럼 |
| reg_members | 교적부 교인 명부 — name/email/phone/birth_date/address 등. **`churchero_user_id` 컬럼이 이미 `gh_account.account_no`를 참조** (최초 설계 문서에 명시됨) |

**결론**: 앱 계정이 없는 교인(아동/고령자)도 교적부엔 있어야 해서 별도 테이블이 필요하고, 연결 컬럼이 이미 있어 앱 계정 보유 교인은 대조 가능. 정상 설계.

---

## 2. reg_services ↔ gh_church_timetable

**해결됨 (2026-07-17)** — `gh_church_timetable`을 단일 소스로 통합. 상세: [gh_church_alter.md](gh_church_alter.md). 애플리케이션 코드(`WorshipRepository`/`AttendanceRepository`/`VisitRepository`/`ReportRepository`)는 이제 전부 `gh_church_timetable`을 사용. `reg_services`/`reg_attendance_records` 테이블 자체는 하위 호환을 위해 남겨두되 더 이상 쓰지 않음. **HeidiSQL에서 ALTER + 데이터 이관 SQL 실행 필요** (아직 미실행).

<details>
<summary>원래 조사 내용</summary>

**진짜 중복, 연결 없음.**

| | reg_services | gh_church_timetable |
|---|---|---|
| 용도 | 교적 출결 기록용 (구조화) | 공개 홈페이지 예배안내 표시용 (자유 텍스트) |
| 컬럼 | `day_of_week`(tinyint) / `start_time`(time) / `category` / `target_org_id` / `sort_order` / `is_active` | `name` / `place` / `timetext`(자유 텍스트, 구조화 안 됨) |
| 참조받는 곳 | `reg_attendance_records.service_id` (FK, 241건) | (공개 앱/관리자 페이지) |
| 현재 데이터 (church_no=33632) | 8건 | 1건 ("주일1부예배") |

같은 "이 교회 예배가 언제인지"를 두 곳에 따로 입력. `gh_church_timetable`엔 구조화된 시간 컬럼이 없어 교적의 출결 로직(요일/시간 기준 계산)엔 그대로 못 씀.

</details>

---

## 3. reg_codes (group_key=position) ↔ gh_church_code (group_code=1)

**표면적으로는 비슷하지만 실제 데이터를 보면 다른 개념 — 강제 통합 시 데이터 유실 위험.**

| | reg_codes (position) | gh_church_code (group_code=1) |
|---|---|---|
| 값 | 성도, 집사(서리), 집사(안수), 권사, 장로, 목사, 부목사, 교역자, 사역자, 봉사자 | 담임목사, 부목사, 전도사, 장로, 간사, 행정직원, 기타 |
| 성격 | 교인 개개인의 **신앙적 직분** (평신도 포함 전체 교인 대상) | 교회 **조직 내 역할/직위** (간사·행정직원 등 사역자·행정직 중심) |
| 실사용 값 (reg_member_profiles.position 샘플) | 성도/집사/장로/집사(안수)/권사/집사(서리)/봉사자 — 7개 중 "장로"만 gh_church_code에도 존재 | — |
| 참조 방식 | `reg_member_profiles.position`은 **문자열 저장**(FK 아님) — 값 자체가 안 맞으면 매핑 불가 | — |

**gh_church_code 기준으로 강제 통합하면**: 현재 교인 기록의 "성도"/"집사"/"권사"/"봉사자" 등 대부분의 값이 새 목록에 없는 값이 되어버림 — 동일 개념의 두 목록이 아니라 **범위가 다른 두 목록**으로 보임.

---

## 4. reg_messages ↔ gh_church_push_record

**개념적 중복, 낮은 우선순위.**

`reg_messages`는 발송 기록만 저장하고 실제 게이트웨이 연동은 아직 TODO(CLAUDE.md에 이미 기록됨) — 실제 발송 인프라가 `gh_church_push_record` 뒤에 있을 가능성. 게이트웨이 연동 시점에 재검토.
