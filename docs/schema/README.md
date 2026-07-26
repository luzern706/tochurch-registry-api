# docs/schema — SQL 파일 목록 및 설명

교적 관리 시스템(`gyohyero_db`)에서 사용하는 SQL 파일 목록입니다.
파일명 앞 번호 기준으로 구분되며, **00번대**는 DDL(테이블/프로시저), **100번대**는 시드 데이터입니다.

---

## 📐 DDL — 테이블 및 프로시저 정의

| 파일 | 설명 |
|---|---|
| [01_registry_tables.sql](01_registry_tables.sql) | 교적 관리 전체 테이블 생성 스크립트 (`reg_*` prefix 테이블 16종) |
| [02_code_tables.sql](02_code_tables.sql) | 범용 코드 관리 테이블 생성 스크립트 (`reg_code_groups`, `reg_code_values`) |
| [03_reg_education_attendance.sql](03_reg_education_attendance.sql) | 교육 회차별 출결 테이블 생성 스크립트 (`reg_education_attendance`) |
| [04_sp_v4_reg_education.sql](04_sp_v4_reg_education.sql) | 교육 관리 저장 프로시저 (`sp_v4_reg_edu_*`) |
| [05_sp_v4_reg_volunteer.sql](05_sp_v4_reg_volunteer.sql) | 봉사 관리 저장 프로시저 (`sp_v4_reg_volunteer_*`, 봉사 이력·팀별 이력 요약 SP는 `reg_service_attendance` 참조 — 06번 파일과 생성 순서 무관, MySQL은 CREATE PROCEDURE 시점에 테이블 참조를 검증하지 않음) |
| [06_reg_service_attendance.sql](06_reg_service_attendance.sql) | 봉사 참여 출결 테이블 생성 스크립트 (`reg_service_attendance`) |

---

## 🌱 Seed — 테스트 데이터 삽입

| 파일 | 설명 |
|---|---|
| [100_seed_members_50.sql](100_seed_members_50.sql) | 교인 50명 테스트 계정 삽입 (`reg_members`, local church_id=1 placeholder) |
| [100_seed_members_50_dev.sql](100_seed_members_50_dev.sql) | 100번 dev 버전 (church_id=19715) — ⚠️ 101을 이미 dev에 적재했다면 `member_no` 중복으로 대부분 스킵됨, 보통 불필요 |
| [101_seed_registry_full.sql](101_seed_registry_full.sql) | 교적 전체 샘플 데이터 (이미 `@church_id=19715`로 설정되어 dev-ready, local 재적용 시에만 33632로 변경) |
| [102_seed_visit_records.sql](102_seed_visit_records.sql) | 심방 기록 테스트 데이터 (`reg_visit_records`, church_id=33632, local 전용) |
| [102_seed_visit_records_dev.sql](102_seed_visit_records_dev.sql) | 102번 dev 버전 (church_id=19715) |
| [103_seed_education_history_statistics.sql](103_seed_education_history_statistics.sql) | 교육 이력·출석 통계 테스트 데이터 (`reg_education_records`, `reg_education_attendance`, session_id로만 참조 — church_id 무관) |
| [103_seed_education_history_statistics_dev.sql](103_seed_education_history_statistics_dev.sql) | 103번 dev 버전 — 데이터는 local과 동일(직접 church_id 참조 없음), 101이 dev에 먼저 적재되어 있어야 함 |
| [104_seed_volunteer.sql](104_seed_volunteer.sql) | 봉사 팀·참여자 테스트 데이터 (`reg_service_teams` 8건, `reg_service_members` 30건, church_id=33632, local 전용) |
| [104_seed_volunteer_dev.sql](104_seed_volunteer_dev.sql) | 104번 dev 버전 (church_id=19715) |
| [105_seed_volunteer_attendance.sql](105_seed_volunteer_attendance.sql) | 봉사 참여 출결 테스트 데이터 (`reg_service_attendance` 105건, service_team_id로만 참조 — church_id 무관, 104번 선행 필요) |
| [105_seed_volunteer_attendance_dev.sql](105_seed_volunteer_attendance_dev.sql) | 105번 dev 버전 — 데이터는 local과 동일, 104_dev가 먼저 적재되어 있어야 함 |
| [106_seed_code_settings_dev.sql](106_seed_code_settings_dev.sql) | **dev 전용** — local DB의 `reg_church_settings`(7건)/`reg_code_groups`(7건)/`reg_codes`(49건) 실 데이터를 church_id=19715로 치환해 그대로 이식 (id 보존). 테스트 시드가 아니라 앱 필수 참조/설정 데이터라 local 원본 파일은 따로 없음 |

---

## 📄 참고 문서 (Markdown)

| 파일 | 설명 |
|---|---|
| [gh_church_admin_alter.md](gh_church_admin_alter.md) | `gh_church_admin` 테이블 컬럼 추가 이력 및 ALTER 구문 |
| [reg_members_alter.md](reg_members_alter.md) | `reg_members` 테이블 컬럼 추가 이력 및 ALTER 구문 |
| [seed_registry_full.README.md](seed_registry_full.README.md) | `101_seed_registry_full.sql` 사용 방법 및 포함 데이터 상세 설명 |
| [db-update-log.md](db-update-log.md) | **dev 서버 DB 반영 이력** — 각 DDL/ALTER/시드 파일이 dev DB에 적용됐는지 추적. DB 반영 시 이 문서를 갱신할 것 |

---

## 실행 순서 가이드

### 신규 환경 구성

```
1. 01_registry_tables.sql       — 기본 테이블 생성
2. 02_code_tables.sql           — 코드 관리 테이블 생성
3. 03_reg_education_attendance.sql  — 교육 출결 테이블 생성
4. 04_sp_v4_reg_education.sql   — 교육 저장 프로시저 생성
5. 05_sp_v4_reg_volunteer.sql   — 봉사 저장 프로시저 생성
6. 06_reg_service_attendance.sql — 봉사 출결 테이블 생성
```

### 테스트 데이터 투입 — local (church_id=33632)

```
7. 100_seed_members_50.sql                  — 교인 50명 등록
8. 101_seed_registry_full.sql               — 전체 샘플 데이터 (조직/가족/출석/심방 등, @church_id를 33632로 변경 후 실행)
9. 102_seed_visit_records.sql               — 심방 기록 추가
10. 103_seed_education_history_statistics.sql — 교육 이력 및 출석 통계 데이터 추가
11. 104_seed_volunteer.sql                   — 봉사 팀·참여자 데이터 추가
12. 105_seed_volunteer_attendance.sql        — 봉사 출결 데이터 추가
```

### 테스트 데이터 투입 — dev (church_id=19715, 행복샘교회 2)

```
6-1. 106_seed_code_settings_dev.sql          — 코드/설정 참조 데이터 (필수 — 다른 화면들의 드롭다운/설정값이 이 데이터에 의존)
7. 101_seed_registry_full.sql               — 전체 샘플 데이터 (기본값이 이미 @church_id=19715, 그대로 실행)
8. 102_seed_visit_records_dev.sql           — 심방 기록 추가
9. 103_seed_education_history_statistics_dev.sql — 교육 이력 및 출석 통계 데이터 추가
10. 104_seed_volunteer_dev.sql               — 봉사 팀·참여자 데이터 추가
11. 105_seed_volunteer_attendance_dev.sql    — 봉사 출결 데이터 추가
```

> 100_seed_members_50(_dev).sql은 dev 목록에서 제외 — 101이 이미 실제 교인 53명을 제공하고,
> `member_no`가 전역 UNIQUE라 101과 겹치면 대부분 스킵된다. 완전히 빈 교회로 최소 데이터만
> 채우고 싶은 특수한 경우에만 사용할 것.
>
> **주의**: 어느 환경이든 실행 전 `@church_id` 값이 맞는지 한 번 더 확인할 것.
> - local 테스트: `33632`
> - dev 환경: `19715` (행복샘교회 2)

---

## 테이블 목록 (`reg_*`)

| 테이블 | 역할 |
|---|---|
| `reg_members` | 교인 계정 |
| `reg_member_profiles` | 교인 교적 상세 정보 |
| `reg_organizations` | 조직 (남전도회, 청년부 등) |
| `reg_member_organizations` | 교인-조직 매핑 |
| `reg_families` | 가족 관계 |
| `reg_services` | 예배/모임 종류 |
| `reg_attendance_records` | 출석 기록 |
| `reg_visit_records` | 심방 기록 |
| `reg_prayer_records` | 기도 목록 |
| `reg_service_teams` | 봉사 팀 |
| `reg_service_members` | 봉사 참여자 |
| `reg_service_attendance` | 봉사 참여 출결 기록 |
| `reg_education_courses` | 교육 과정 |
| `reg_education_sessions` | 교육 기수 |
| `reg_education_records` | 교육 수강 기록 |
| `reg_education_attendance` | 교육 회차별 출결 기록 |
| `reg_offering_records` | 헌금 기록 |
| `reg_messages` | 메시지 발송 기록 |
| `reg_audit_logs` | 감사 로그 |
| `reg_code_groups` | 코드 그룹 |
| `reg_code_values` | 코드 값 |
