# DB 반영 이력 (dev 서버 적용 현황)

> 목적: `docs/schema/*.sql`(및 `*_alter.md`) 각 파일이 **리눅스 개발 서버(dev DB)**에 실제로 적용됐는지 추적한다.
> 로컬 PC와 dev 서버는 별도 DB이므로, 로컬에서 개발·테스트가 끝났다고 dev에도 자동으로 반영되는 것이 아니다 — 반드시 이 문서로 dev 적용 여부를 확인하고, 적용 후 체크할 것.
> Laravel 마이그레이션을 쓰지 않으므로 (CLAUDE.md 방침) 모든 DDL/SP/시드는 **HeidiSQL 등으로 직접 실행**한다. 이 문서가 "어디까지 실행했는지"의 유일한 기록이다.

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
| local (로컬 PC) | `33632` | 개발/테스트용 |
| dev (개발 서버) | `19715` | 행복샘교회 2 |

`church_id=33632`로 하드코딩됐던 시드 파일(100, 102~104)은 **dev 전용(`_dev` 접미사) 사본**을 이미 만들어 두었다 — dev에는 원본이 아니라 `_dev` 파일을 실행한다. 103/105는 church_id를 직접 참조하지 않아 데이터 내용은 local과 동일(101이 dev에 먼저 적재되어 있어야 함).
적재 전 확인 쿼리: `SELECT admin_no, church_no FROM gh_church_admin WHERE admin_no = <dev 로그인 관리자 no>;` → 나온 `church_no`가 곧 `@church_id`.

---

## DDL — 테이블 / 프로시저

실행 순서 그대로. SP는 참조 테이블이 나중에 생성돼도 무방(MySQL이 `CREATE PROCEDURE` 시점에 테이블 참조를 검증하지 않음).

| # | 파일 | 설명 | local | dev | dev 적용일 | 비고 |
|---|---|---|:---:|:---:|---|---|
| 1 | [01_registry_tables.sql](01_registry_tables.sql) | `reg_*` 16종 테이블 생성 | ✅ | 🔲 | | |
| 2 | [02_code_tables.sql](02_code_tables.sql) | 코드 관리 테이블 (`reg_code_groups`/`reg_code_values`) | ✅ | 🔲 | | |
| 3 | [03_reg_education_attendance.sql](03_reg_education_attendance.sql) | 교육 회차별 출결 테이블 | ✅ | 🔲 | | |
| 4 | [04_sp_v4_reg_education.sql](04_sp_v4_reg_education.sql) | 교육 저장 프로시저 (`sp_v4_reg_edu_*`) | ✅ | 🔲 | | |
| 5 | [05_sp_v4_reg_volunteer.sql](05_sp_v4_reg_volunteer.sql) | 봉사 저장 프로시저 (`sp_v4_reg_volunteer_*`, 5종) | ✅ | 🔲 | | |
| 6 | [06_reg_service_attendance.sql](06_reg_service_attendance.sql) | 봉사 참여 출결 테이블 | ✅ | 🔲 | | |

## ALTER — 기존 테이블 컬럼 추가

| 파일 | 설명 | local | dev | dev 적용일 | 비고 |
|---|---|:---:|:---:|---|---|
| [gh_church_admin_alter.md](gh_church_admin_alter.md) | `gh_church_admin` 컬럼 추가 이력 | ✅ | 🔲 | | 문서 내 ALTER 구문을 dev DB에서 직접 실행 |
| [reg_members_alter.md](reg_members_alter.md) | `reg_members` 컬럼 추가 이력 | ✅ | 🔲 | | 문서 내 ALTER 구문을 dev DB에서 직접 실행 |

## Seed — 테스트 데이터

local과 dev는 church_id가 다르므로 파일이 분리되어 있다. local 개발 시 원본(church_id=33632)을,
dev 반영 시 `_dev` 접미사 파일(church_id=19715)을 실행한다.

| # | 파일 | 설명 | local | dev | dev 적용일 | 비고 |
|---|---|---|:---:|:---:|---|---|
| 7 | [100_seed_members_50.sql](100_seed_members_50.sql) | 교인 50명 (`reg_members`) | ✅ | — | | 로컬 전용 |
| 7-dev | [100_seed_members_50_dev.sql](100_seed_members_50_dev.sql) | 100번 dev 버전 | — | 🔲 | | 101을 이미 dev에 적재했다면 `member_no` 중복으로 스킵됨 — 보통 불필요, 실행 전 판단할 것 |
| 8 | [101_seed_registry_full.sql](101_seed_registry_full.sql) | 교적 전체 샘플(조직/가족/출석/심방 등, 481건) | ✅ (`@church_id=33632`로 변경 후) | 🔲 | | 기본값이 이미 `@church_id=19715` — dev는 파일 수정 없이 그대로 실행. 상세: [seed_registry_full.README.md](seed_registry_full.README.md) |
| 9 | [102_seed_visit_records.sql](102_seed_visit_records.sql) | 심방 기록 테스트 데이터 | ✅ | — | | `church_id=33632` 하드코딩, 로컬 전용 |
| 9-dev | [102_seed_visit_records_dev.sql](102_seed_visit_records_dev.sql) | 102번 dev 버전 | — | 🔲 | | `church_id=19715`로 치환 완료, 101(dev) 선행 필요 |
| 10 | [103_seed_education_history_statistics.sql](103_seed_education_history_statistics.sql) | 교육 이력/출석 통계 데이터 | ✅ | — | | session_id로만 참조, church_id 무관 |
| 10-dev | [103_seed_education_history_statistics_dev.sql](103_seed_education_history_statistics_dev.sql) | 103번 dev 버전 | — | 🔲 | | 데이터는 local과 동일, 101(dev) 선행 필요 |
| 11 | [104_seed_volunteer.sql](104_seed_volunteer.sql) | 봉사 팀 8건 · 참여자 30건 | ✅ | — | | `church_id=33632` 하드코딩, 로컬 전용 |
| 11-dev | [104_seed_volunteer_dev.sql](104_seed_volunteer_dev.sql) | 104번 dev 버전 | — | 🔲 | | `church_id=19715`로 치환 완료, 101(dev) 선행 필요 |
| 12 | [105_seed_volunteer_attendance.sql](105_seed_volunteer_attendance.sql) | 봉사 참여 출결 105건 | ✅ | — | | service_team_id로만 참조, church_id 무관, 104번 선행 필요 |
| 12-dev | [105_seed_volunteer_attendance_dev.sql](105_seed_volunteer_attendance_dev.sql) | 105번 dev 버전 | — | 🔲 | | 데이터는 local과 동일, 104-dev 선행 필요 |
| 13-dev | [106_seed_code_settings_dev.sql](106_seed_code_settings_dev.sql) | reg_church_settings 7 · reg_code_groups 7 · reg_codes 49 (local 실데이터 이식) | — | 🔲 | | **다른 시드보다 먼저 실행** — 코드/설정 값이 없으면 여러 화면의 드롭다운·집계 로직이 정상 동작하지 않음 |

---

## 사용법

1. dev DB에 파일을 실행한 **직후**, 해당 행의 `dev` 칸을 ✅로, `dev 적용일`을 채운다.
2. 새 스키마/시드 파일이 추가되면 [README.md](README.md)와 이 문서 양쪽에 행을 추가한다 (같은 타이밍에 갱신).
3. 이 문서의 dev 적용 상태와 [../05_menu-checklist.md](../05_menu-checklist.md)의 "Dev 적용" 열은 서로 다른 것을 추적한다 — 메뉴 체크리스트는 화면(코드) 배포, 이 문서는 DB 반영. 예를 들어 봉사 관리는 코드 배포만으로는 동작하지 않고, 이 문서의 5·6번(DDL/SP)까지 dev에 반영되어야 실제로 동작한다.
