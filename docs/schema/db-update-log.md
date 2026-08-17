# DB 반영 이력 (dev 서버 적용 현황)

> 목적: `docs/schema/*.sql`(및 `*_alter.md`) 각 파일이 **원격 dev/staging 서버**에 실제로 적용됐는지 추적한다.
> 로컬 PC와 dev/staging 서버는 별도 DB 인스턴스이므로, 로컬에서 개발·테스트가 끝났다고 dev에도 자동으로 반영되는 것이 아니다.
> Laravel 마이그레이션을 쓰지 않으므로 (CLAUDE.md 방침) 모든 DDL/SP/시드는 **HeidiSQL 등으로 직접 실행**한다.

> ⚠️ **2026-08-17 갱신**: 이 문서가 2026-07-26 이후 갱신되지 않아(파일 07~16번, `gh_church_alter.md`/`gh_church_admin_alter.md` 등 다수 항목 누락) 실제 상태와 크게 어긋나 있었다.
> 세션 로그를 믿는 대신 **원격 dev/staging DB(`3.35.24.97:13306`)에 직접 접속해 테이블/컬럼/인덱스/프로시저 존재 여부를 라이브로 조회**해 이 문서를 다시 작성함(접속 정보는 별도 메모리 참고, 조회 전용). 아래 "dev 적용일"이 빈 항목은 정확한 실행 시점을 알 수 없지만(과거 세션에서 이미 실행됐고 이 문서에만 기록이 누락됐던 것으로 추정), 2026-08-17 시점 실제 존재는 확인된 것이다.
> 참고로 운영 서버(`3.36.50.113:13306`)도 표본 점검한 신규 테이블(권한/가입승인/메시지템플릿/지출/예산/계좌관리/푸시)이 전부 존재해, dev/staging과 스키마가 동일하게 유지되고 있는 것으로 보인다(전수 점검은 아님).
> 앞으로도 이 문서와 실제 DB가 어긋날 수 있으니, **중요한 판단(예: "이 기능이 dev에서 동작하는가?") 전에는 이 문서만 믿지 말고 라이브 조회로 재확인할 것.**

---

## 범례

| 아이콘 | 의미 |
|:---:|---|
| ✅ | 적용 완료 |
| 🔲 | 미적용 |
| — | 해당 없음 (dev에 적용할 대상이 아님 — 로컬 전용 테스트 데이터 등) |

## church_id 주의

| 환경 | church_id | 비고 |
|---|---|---|
| local (로컬 PC) | `33632` | 개발/테스트용, host=localhost:3306 |
| dev/staging (원격 서버) | `19715` | 행복샘교회 2, host=3.35.24.97:13306 |

`church_id=33632`로 하드코딩됐던 시드 파일(100, 102~104)은 **dev 전용(`_dev` 접미사) 사본**을 이미 만들어 두었다 — dev에는 원본이 아니라 `_dev` 파일을 실행한다. 103/105는 church_id를 직접 참조하지 않아 데이터 내용은 local과 동일(101이 dev에 먼저 적재되어 있어야 함).
적재 전 확인 쿼리: `SELECT admin_no, church_no FROM gh_church_admin WHERE admin_no = <dev 로그인 관리자 no>;` → 나온 `church_no`가 곧 `@church_id`.

---

## DDL — 테이블 / 프로시저 (01~06번)

실행 순서 그대로. SP는 참조 테이블이 나중에 생성돼도 무방(MySQL이 `CREATE PROCEDURE` 시점에 테이블 참조를 검증하지 않음).

| # | 파일 | 설명 | local | dev | dev 적용일 | 비고 |
|---|---|---|:---:|:---:|---|---|
| 1 | [01_registry_tables.sql](01_registry_tables.sql) | `reg_*` 16종 테이블 생성 | ✅ | ✅ | (기록 없음, 2026-08-17 라이브조회로 재확인) | |
| 2 | [02_code_tables.sql](02_code_tables.sql) | 코드 관리 테이블 | ✅ | ✅ | (기록 없음, 2026-08-17 라이브조회로 재확인) | ⚠️ 실제 테이블명은 `reg_code_groups`/`reg_codes` — 이 파일·`README.md`가 언급하는 `reg_code_values`는 실존하지 않음(문서 표기 오류로 추정, 코드는 `reg_codes`를 정상 사용 중) |
| 3 | [03_reg_education_attendance.sql](03_reg_education_attendance.sql) | 교육 회차별 출결 테이블 | ✅ | ✅ | (기록 없음, 2026-08-17 라이브조회로 재확인) | |
| 4 | [04_sp_v4_reg_education.sql](04_sp_v4_reg_education.sql) | 교육 저장 프로시저 (`sp_v4_reg_edu_*`) | ✅ | ✅ | (기록 없음, 2026-08-17 라이브조회로 재확인) | `sp_v4_reg_edu_course_list`/`sessions_by_member`/`session_detail`/`session_list` 4종 존재 확인 |
| 5 | [05_sp_v4_reg_volunteer.sql](05_sp_v4_reg_volunteer.sql) | 봉사 저장 프로시저 (`sp_v4_reg_volunteer_*`, 5종) | ✅ | ✅ | (기록 없음, 2026-08-17 라이브조회로 재확인) | `by_team`/`history`/`list`/`teams_by_member`/`team_history` 5종 존재 확인 |
| 6 | [06_reg_service_attendance.sql](06_reg_service_attendance.sql) | 봉사 참여 출결 테이블 | ✅ | ✅ | (기록 없음, 2026-08-17 라이브조회로 재확인) | |

## 확장 테이블 — 권한 / 가입승인 / 메시지 / 재정 (09~16번)

이전 버전 문서에 아예 누락돼 있던 항목들. 2026-08-17 라이브조회로 local·dev 모두 확인.

| # | 파일 | 설명 | local | dev | 비고 |
|---|---|---|:---:|:---:|---|
| 9 | [09_reg_role_permissions_v2.sql](09_reg_role_permissions_v2.sql) | 권한 매트릭스(`reg_role_permissions`, page_code/can_access 구조) | ✅ | ✅ | 구버전 `08_reg_role_permissions.sql`(menu_code/can_view/can_manage, deprecated)이 아니라 v2 스키마로 확인됨 |
| 10 | [10_reg_member_join_requests.sql](10_reg_member_join_requests.sql) | 가입 승인 보류/반려 기록(`reg_member_join_requests`) | ✅ | ✅ | |
| 11 | [11_reg_message_templates.sql](11_reg_message_templates.sql) | 문자 템플릿(`reg_message_templates`) | ✅ | ✅ | |
| 13 | [13_reg_expense_records.sql](13_reg_expense_records.sql) | 재정 지출(`reg_expense_records`) | ✅ | ✅ | |
| 14 | [14_reg_budgets.sql](14_reg_budgets.sql) | 재정 예산(`reg_budgets`) | ✅ | ✅ | |
| 15 | [15_reg_finance_accounts.sql](15_reg_finance_accounts.sql) | 재정 계좌관리(`reg_finance_accounts`) | ✅ | ✅ | |
| 16 | [16_reg_push_recipients.sql](16_reg_push_recipients.sql) | 푸시 발송 대상 스냅샷(`reg_push_recipients`) + `reg_messages.deep_link`/`resend_of` 컬럼 | ✅ | ✅ | |

> 12번은 SQL이 아니라 제안 문서([12_reg_offering_records_alter.md](12_reg_offering_records_alter.md)) — 아래 ALTER 섹션 참고, **의도적으로 미실행 상태**.

## ALTER — 기존 테이블 컬럼 추가

| 파일 | 설명 | local | dev | 비고 |
|---|---|:---:|:---:|---|
| [gh_church_admin_alter.md](gh_church_admin_alter.md) | `gh_church_admin`에 email/phone/admin_type/name/last_login_at 컬럼 | ✅ | ✅ | |
| [reg_members_alter.md](reg_members_alter.md) | `reg_members`에 idx_name/idx_birth_date 인덱스 | ✅ | ✅ | |
| [gh_church_alter.md](gh_church_alter.md) — ① `gh_church.logo_file_no` | 로고 파일 참조 컬럼 | ✅ | ✅ | |
| [gh_church_alter.md](gh_church_alter.md) — ② `gh_church_location.address_detail`/`postcode` | 상세주소/우편번호 컬럼 | ✅ | ✅ | |
| [gh_church_alter.md](gh_church_alter.md) — ③ `gh_church_pastor.is_head`/`career` | 담임여부/약력 컬럼 | ✅ | ✅ | |
| [gh_church_alter.md](gh_church_alter.md) — ④ `gh_church_timetable` 컬럼 추가(category/day_of_week/start_time/target_org_id/sort_order/is_active/description/note) | 예배 정의 구조화 | ✅ | ✅ | |
| [gh_church_alter.md](gh_church_alter.md) — ⑤ `reg_services` → `gh_church_timetable` 데이터 이관 + `reg_attendance_records.service_id` 재매핑 | | ✅ | ✅ | 이관 자체는 양쪽 다 완료(`reg_attendance_records` 값 재매핑됨) |
| [gh_church_alter.md](gh_church_alter.md) — ⑥ `reg_services` `DROP TABLE`(이관 확인 후 정리) | | ✅ (삭제됨) | 🔲 (테이블 남아있음) | dev에는 아직 미사용 상태로 남아있음 — 애플리케이션 코드가 더 이상 참조하지 않아 기능상 문제는 없으나, 정리하려면 dev에서도 `DROP TABLE reg_services;` 실행 필요 |
| [gh_church_alter.md](gh_church_alter.md) — ⑦ `gh_church_intro`(hero_title 등 8개 컬럼 + vision TEXT 확장) | 교회소개 필드 | ✅ | ✅ | |
| [12_reg_offering_records_alter.md](12_reg_offering_records_alter.md) | `reg_offering_records`에 service_id/note/status 컬럼 제안 | 🔲 (의도적 미실행) | 🔲 (의도적 미실행) | 우선순위 낮아 제안만 하고 보류 중 — 화면은 해당 항목 "데이터 없음"으로 표시. 실행 대상 아님 |

## Seed — 테스트 데이터

local과 dev는 church_id가 다르므로 파일이 분리되어 있다. local 개발 시 원본(church_id=33632)을,
dev 반영 시 `_dev` 접미사 파일(church_id=19715)을 실행한다.

| # | 파일 | 설명 | local | dev | 비고 |
|---|---|---|:---:|:---:|---|
| 7 | [100_seed_members_50.sql](100_seed_members_50.sql) | 교인 50명 (`reg_members`) | ✅ | — | 로컬 전용 |
| 7-dev | [100_seed_members_50_dev.sql](100_seed_members_50_dev.sql) | 100번 dev 버전 | — | — | 101이 이미 dev에 적재되어 사실상 불필요(member_no 중복 스킵) |
| 8 | [101_seed_registry_full.sql](101_seed_registry_full.sql) | 교적 전체 샘플(조직/가족/출석/심방 등) | ✅ | ✅ | dev 라이브조회로 확인: reg_members 53건, reg_organizations 11건, reg_attendance_records 241건 등 |
| 9 | [102_seed_visit_records.sql](102_seed_visit_records.sql) | 심방 기록 테스트 데이터 | ✅ | — | `church_id=33632` 하드코딩, 로컬 전용 |
| 9-dev | [102_seed_visit_records_dev.sql](102_seed_visit_records_dev.sql) | 102번 dev 버전 | — | ✅ | dev reg_visit_records 31건 확인 |
| 10 | [103_seed_education_history_statistics.sql](103_seed_education_history_statistics.sql) | 교육 이력/출석 통계 데이터 | ✅ | — | session_id로만 참조, church_id 무관 |
| 10-dev | [103_seed_education_history_statistics_dev.sql](103_seed_education_history_statistics_dev.sql) | 103번 dev 버전 | — | ✅ | dev reg_education_sessions 51건 · reg_education_attendance 540건 확인 |
| 11 | [104_seed_volunteer.sql](104_seed_volunteer.sql) | 봉사 팀 8건 · 참여자 30건 | ✅ | — | `church_id=33632` 하드코딩, 로컬 전용 |
| 11-dev | [104_seed_volunteer_dev.sql](104_seed_volunteer_dev.sql) | 104번 dev 버전 | — | ✅ | dev reg_service_teams 8건 확인 |
| 12 | [105_seed_volunteer_attendance.sql](105_seed_volunteer_attendance.sql) | 봉사 참여 출결 105건 | ✅ | — | service_team_id로만 참조, church_id 무관, 104번 선행 필요 |
| 12-dev | [105_seed_volunteer_attendance_dev.sql](105_seed_volunteer_attendance_dev.sql) | 105번 dev 버전 | — | ✅ | dev reg_service_attendance 105건 확인 |
| 13-dev | [106_seed_code_settings_dev.sql](106_seed_code_settings_dev.sql) | reg_church_settings 7 · reg_code_groups 7 · reg_codes 49 (local 실데이터 이식) | — | ✅ | dev reg_church_settings 7건 확인 |
| 14 | [107_seed_status_codes.sql](107_seed_status_codes.sql) | 교인/교육/봉사 상태 코드(member_status/education_status/service_status) | ✅ | — | local 전용 |
| 14-dev | [107_seed_status_codes_dev.sql](107_seed_status_codes_dev.sql) | 107번 dev 버전 | — | ✅ | dev reg_code_groups에 member_status/education_status/service_status group_key 존재 확인 |

---

## 사용법

1. dev DB에 파일을 실행한 **직후**, 해당 행의 `dev` 칸을 ✅로 갱신한다 — 미루면 이번처럼 문서가 통째로 stale해진다.
2. 새 스키마/시드 파일이 추가되면 [README.md](README.md)와 이 문서 양쪽에 행을 추가한다 (같은 타이밍에 갱신).
3. 이 문서의 dev 적용 상태와 `docs/06_페이지별_기능_현황.md`(프론트 저장소)의 화면 연동 여부는 서로 다른 것을 추적한다 — 화면 코드가 배포돼 있어도 이 문서의 DDL/ALTER가 dev에 반영되지 않으면 실제로는 500 에러가 난다.
4. 이 문서를 갱신하지 못한 채로 세션이 끝나는 경우가 반복되고 있다 — 확신이 안 서면 문서보다 dev/staging(3.35.24.97:13306) DB에 직접 접속해 라이브 조회로 확인할 것 (SELECT는 자유, 쓰기는 사용자 확인 필수. 접속 정보는 세션 메모리 `reference-production-db-access` 참고).
