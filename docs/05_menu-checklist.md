# 메뉴별 개발 체크리스트

> API: `04_gh_registry_api` / 프론트: `04_gh_registry_front`
> 최종 수정: 2026-07-17

---

## 범례

| 아이콘 | 의미 |
|:---:|---|
| ✅ | 완료 |
| 🚧 | 작업 중 |
| 🔲 | 미착수 / 미적용 |
| — | 해당 없음 (API 불필요, 프론트 전용, 또는 아직 개발되지 않아 배포 대상이 아님) |

> **화면**: JSX 파일 및 UI 구현 여부 (로컬 기준)
> **API 연동**: 실제 백엔드 호출 연결 여부 (로컬 기준)
> **Dev 적용**: 리눅스 개발 서버(dev)에 코드/DB가 실제로 반영되어 해당 화면이 dev 서버에서도 정상 동작하는지 여부.
> 로컬에서 화면/API 연동이 ✅여도 dev 서버에 배포하기 전까지는 **Dev 적용 = 🔲**로 둔다.
> 화면·API 연동이 아직 🔲인 항목은 배포 대상 자체가 없으므로 Dev 적용은 **—**로 표기한다.
> 배포(코드 push/pull + 필요 시 DB 반영, [07_API_로컬테스트_및_배포가이드.md](07_API_로컬테스트_및_배포가이드.md) · [../../04_gh_registry_front/docs/02-00_로컬테스트_및_배포가이드.md](../../04_gh_registry_front/docs/02-00_로컬테스트_및_배포가이드.md) 참고) 직후 이 문서의 해당 행을 ✅로 바꾼다.
> DB 스키마/시드 파일 자체의 dev 적용 여부는 이 문서가 아니라 [schema/db-update-log.md](schema/db-update-log.md)에서 관리한다.

---

## 공통 기반

| 항목 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|:---:|:---:|:---:|---|
| 공통 API 클라이언트 | — | ✅ | 🔲 | `src/services/api.js` |
| 에러 코드 상수 | — | ✅ | 🔲 | `src/constants/errorCodes.js` |
| JWT 인증 컨텍스트 | ✅ | ✅ | 🔲 | `src/context/AuthContext.jsx` |
| 인증 가드 (PrivateRoute) | ✅ | ✅ | 🔲 | `src/components/PrivateRoute.jsx` |
| GNB (상단 메뉴) | ✅ | — | 🔲 | `src/layouts/HeaderGnb.jsx` |

---

## 🔐 인증

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 로그인 | `/login` | ✅ | ✅ | 🔲 | `POST /v4/auth/adminSignIn` |
| 로그아웃 | GNB 내 | ✅ | ✅ | 🔲 | `POST /v4/auth/signOut` |

---

## 📋 교적 (탑메뉴: 교적)

### 교인 (cate01-01)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| 교인목록 | `/member/list` | ✅ | ✅ | 🔲 | `POST /v4/member/getList` |
| 교인 상세 | `/member/detail?id=` | ✅ | ✅ | 🔲 | `POST /v4/member/getDetail` |
| 교인 등록 | `/member/list/register` | ✅ | ✅ | 🔲 | `POST /v4/member/register` |
| 교인 수정 | (상태변경) | ✅ | ✅ | 🔲 | `POST /v4/member/update` |
| 교인 삭제 | (버튼) | ✅ | ✅ | 🔲 | `POST /v4/member/delete` |
| 가입승인 | `/member/approvals` | 🔲 | 🔲 | — | ⚠️ 선결 과제 있음 (하단 참고) |

> **가입승인 선결 과제 (개발 전 확인 필요)**
>
> 교회로 앱에서 교회 가입 요청 시 어느 테이블에 신청 데이터가 저장되는지 확인 필요.
> - 신청 테이블 확인 → API 설계 → 화면 개발 순서로 진행
> - 관련 저장소: `교회로 앱` / `02_gh_admin_api` 등 확인

> **교인목록 미완료 항목 (화면은 있으나 기능 미구현)**
>
> | 기능 | 위치 | 비고 |
> |---|---|---|
> | 문자발송 버튼 | 목록 상단 | 체크박스 선택 후 `/member/messaging` 으로 이동 또는 모달 발송 — `POST /v4/message/send` 연동 필요 |
> | Export 버튼 | 목록 상단 | 현재 목록 데이터 CSV/Excel 다운로드 — 별도 API 또는 프론트 변환 처리 필요 |
> | 연동 액션 (초대/해제) | 목록 행 우측 | 교회로 앱 가입 교인 연동 — churchero_user_id 매핑 API 미개발 |

### 출석 (cate01-02)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| 출석 대시보드 | `/member/attendance` | ✅ | ✅ | 🔲 | `getDashboard` + `getAttendanceStats` + `getStatsByService` |
| 출석입력 | `/member/attendance/input` | ✅ | ✅ | 🔲 | `POST /v4/worship/getList` + `POST /v4/attendance/recordBulk` |
| 출석현황 (개인별) | `/member/attendance/status` | ✅ | ✅ | 🔲 | `POST /v4/attendance/getListByMember` |
| 출석현황 (기간별) | `/member/attendance/status` | ✅ | ✅ | 🔲 | `POST /v4/report/getAttendanceStats` |
| 출석현황 (조직별) | `/member/attendance/status` | ✅ | ✅ | 🔲 | `POST /v4/report/getAttendanceStatsByOrg` |
| 출석통계 | `/member/attendance/statistics` | ✅ | ✅ | 🔲 | `getAttendanceStats` + `getStatsByService` + `getAttendanceStatsByOrg` + `getMemberRateDistribution` |

### 심방 (cate01-03)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| 심방목록 | `/member/visit/history` | ✅ | ✅ | 🔲 | `POST /v4/visit/getList` |
| 심방등록 | `/member/visit/register` | ✅ | ✅ | 🔲 | `POST /v4/visit/register`, `update` |
| 미심방목록 | `/member/visit/targets` | ✅ | ✅ | 🔲 | `POST /v4/visit/getUnvisitedList`, `bulkCompleteVisit` |
| 심방대상관리 | `/member/visit/auto-targets` | ✅ | ✅ | 🔲 | `POST /v4/visit/getAbsenceTargetList` |
| 기도목록 | `/member/visit/prayer` | ✅ | ✅ | 🔲 | `POST /v4/prayer/getList`, `updateStatus`, `getStats` |
| 기도등록 | `/member/visit/prayer-register` | ✅ | ✅ | 🔲 | `POST /v4/prayer/register`, `update` |

### 교육 (cate01-04)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| 교육과정 목록 | `/member/education/curriculum` | ✅ | ✅ | 🔲 | `POST /v4/education/getCourseList`, `deleteCourse` |
| 교육과정 등록 | `/member/education/register` | ✅ | ✅ | 🔲 | `POST /v4/education/registerCourse`, `updateCourse` |
| 진행중 교육 | `/member/education/ongoing` | ✅ | ✅ | 🔲 | `POST /v4/education/session/getList` (status=ongoing) |
| 교육 이력 | `/member/education/history` | ✅ | ✅ | 🔲 | `POST /v4/education/getSessionsByMember`, `getCourseList` |
| 교육출석통계 | `/member/education/attendance-statistics` | ✅ | ✅ | 🔲 | `POST /v4/education/getCourseList` 기반 KPI/차트 |

### 봉사 (cate01-05)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| 봉사현황 | `/member/service/status` | ✅ | ✅ | 🔲 | `POST /v4/volunteer/getList` (팀 목록 + KPI 3종 집계, `participant_count`/`required_count` 포함), `delete` |
| 봉사등록 | `/member/service/register` | ✅ | ✅ | 🔲 | `POST /v4/volunteer/register`, `update`, `getDetail`, `assignVolunteer`, `unassignVolunteer`, `getVolunteersByTeam` |
| 봉사이력 | `/member/service/history` | ✅ | ✅ | 🔲 | `POST /v4/volunteer/getHistory` (교인중심 기본 목록), `getTeamHistory` (봉사중심 기본 목록) |
| 봉사출석 | `/member/service/attendance` (봉사현황 행 액션에서 진입, 별도 메뉴 없음) | ✅ | ✅ | 🔲 | `POST /v4/volunteer/attendance/getSheet`, `attendance/save`, `getTeamDetail` |

### 보고 (cate01-06)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| 주간보고 | `/member/reports/church-overview` | ✅ | 🔲 | — | `POST /v4/report/getDashboard` (백엔드 완료, 프론트 미연동 — 서비스 파일 없음) |
| 종합보고 | `/member/reports/comprehensive` | ✅ | 🔲 | — | `POST /v4/report/getMemberStats` 외 (백엔드 완료, 프론트 미연동) |

### 문자 (cate01-07)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 푸시알림 | `/member/push-notification` | 🔲 | 🔲 | — | 게이트웨이 연동 TODO |
| 문자발송 | `/member/messaging` | ✅ | 🔲 | — | `POST /v4/message/send` (백엔드 완료 — 발송 기록 저장만, 실제 게이트웨이 미연동. 프론트 서비스 파일도 없음) |
| 발송로그 | `/member/messaging/log` | ✅ | 🔲 | — | `POST /v4/message/getList` (백엔드 완료, 프론트 미연동) |

### 게시판 (cate01-08)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 게시판 | `/platform/notices` | ✅ | 🔲 | — | 별도 검토 필요 (백엔드 API 자체 미개발) |

### 설정 (cate01-09)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 기본정보 | `/member/settings/church-profile` | ✅ | ✅ | 🔲 | `POST /v4/churchProfile/*` 신규. 교회명/교단/연락처/납세번호 등은 기존 교회로 공유 테이블(`gh_church`/`gh_church_location`/`gh_church_pastor`/`gh_church_group`)에서 읽고 씀. 로고·담임목사지정·상세주소는 관리자 페이지/공개 홈페이지와도 공유해야 해서 신규 테이블 대신 그 공유 테이블에 컬럼 추가 — [gh_church_alter.md](schema/gh_church_alter.md), **HeidiSQL에서 ALTER 실행 필요**. 소속증명서/문서머리글/기부금영수증안내문(교적 문서생성 전용, 공개 사이트 무관)은 기존 `reg_church_settings` 재사용(신규 테이블 없음) |
| 교직설정 | `/member/settings` | ✅ | ✅ | 🔲 | 교인/직분/관계/예배/출결/교육/조직/봉사/심방 기준 + KPI/자동화규칙 대부분 이미 실연동 상태였음(체크리스트가 오래된 정보였음). 이번에 조직기준 활성화 토글 연동, 자동분류·장기결석기준·집계방식 중복 패널 제거(자동화규칙/KPI 탭으로 일원화), 직분 추가옵션·관계 가족관리옵션 신규 설정키 연동, 교인/교육/봉사 상태를 정적 목록에서 실제 코드그룹(`member_status`/`education_status`/`service_status`)으로 전환 — 시드 SQL: [107_seed_status_codes.sql](schema/107_seed_status_codes.sql), local dev DB 적용 완료. 전수 조사 결과 11개 탭 모두 실제 API 연동 확인, 잔여 하드코딩 없음 |
| 문자설정 | `/member/messaging/settings` | ✅ | 🔲 | — | — |
| 템플릿 | `/member/messaging/templates` | ✅ | 🔲 | — | — |
| ~~권한설정~~ | ~~`/member/settings/permissions`~~ | — | — | — | **관리 > 시스템설정 > 권한 관리로 이전** — 상세는 그쪽 섹션 참조. 이 경로는 리다이렉트만 유지 |
| **권한 기반 프론트 UX** (신규, 별도 화면 없음) | GNB 메뉴 필터링 + 페이지 가드 | ✅ | ✅ | 🔲 | `POST /v4/permission/getMyPermissions`(jwt.auth만, 본인 역할 권한만) 신규 추가, 응답은 `{pageCode: boolean}` 평면 맵. 프론트: `PermissionContext`(로그인 시 본인 권한 fetch, `can(pageCode)` 단일 인자로 접근 여부만 반환) + `HeaderGnb`가 교적(cate01) 서브메뉴 각각을 정확한 소메뉴 코드로 접근 불가 시 숨김(재정·관리는 백엔드 미구현이라 필터링 제외, 대메뉴 자신은 하위 중 하나라도 보이면 표시) + `PermissionGuard`가 `/member/*` 라우트를 소메뉴 단위로 정밀하게 감싸 접근 권한 없으면 `NoPermission.jsx`("권한 없음") 표시 — `App.jsx`의 `guard('PAGE_CODE', <Page/>)` 헬퍼로 적용(대응 소메뉴가 없는 라우트는 가장 가까운 소메뉴로 매핑, 예: `/member/education/session*` → `EDUCATION_CURRICULUM`). 권한 관리 페이지 자체는 소메뉴가 아니라 `adminOnly`(서버가 복호화한 `role==='admin'`)로 별도 가드(백엔드가 `super.auth`로 고정 차단하는 것과 동일 기준). 백엔드 순수 로직은 tinker로 admin/pastor/minister/volunteer 4개 역할 전부 기대값과 일치 확인; DB 의존 경로 및 실제 브라우저 로그인 테스트는 `09_reg_role_permissions_v2.sql` 실행 전이라 미실시 — 사용자가 직접 실행 후 로그인해 확인 필요 |

---

## 💰 재정 (탑메뉴: 재정)

### 헌금 (cate02-01)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| 전체 내역 | `/finance/donation?tab=all` | ✅ | 🔲 | — | `POST /v4/offering/getList` (백엔드 완료, 프론트 미연동 — 서비스 파일 없음) |
| 교인별 조회 | `/finance/donation?tab=member` | ✅ | 🔲 | — | `POST /v4/offering/getListByMember` (백엔드 완료, 프론트 미연동) |
| 기간별 조회 | `/finance/donation?tab=period` | ✅ | 🔲 | — | `POST /v4/offering/getList` (날짜 필터, 백엔드 완료, 프론트 미연동) |

### 헌금(입) (cate02-02)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| 헌금 입력 | `/finance/donation/input` | ✅ | 🔲 | — | `POST /v4/offering/register` (백엔드 완료, 프론트 미연동) |

### 지출 (cate02-03)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 전체 내역 | `/finance/expense?tab=all` | ✅ | 🔲 | — | 지출 API 자체 미개발 |
| 항목별 조회 | `/finance/expense?tab=category` | ✅ | 🔲 | — | — |
| 증빙 관리 | `/finance/expense?tab=receipt` | ✅ | 🔲 | — | — |

### 지출(입) (cate02-04)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 지출 입력 | `/finance/expense/input` | ✅ | 🔲 | — | 지출 API 자체 미개발 |

### 통계 (cate02-05)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 사용 API |
|---|---|:---:|:---:|:---:|---|
| Overview | `/finance/statistics?tab=overview` | ✅ | 🔲 | — | `POST /v4/report/getOfferingStats` (백엔드 완료, 프론트 미연동) |
| 수입통계 | `/finance/statistics?tab=income` | ✅ | 🔲 | — | — |
| 지출통계 | `/finance/statistics?tab=expense` | ✅ | 🔲 | — | 지출 API 자체 미개발 |
| 손익분석 | `/finance/statistics?tab=profit` | ✅ | 🔲 | — | 지출 API 자체 미개발 |
| 기간 비교 | `/finance/statistics?tab=comparison` | ✅ | 🔲 | — | — |

### 예산 (cate02-06)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 예산 | `/finance/budget` | ✅ | 🔲 | — | 예산 API 자체 미개발 |

### 보고 (cate02-07)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 재정 보고 | `/finance/report` | ✅ | 🔲 | — | — |

### 설정 (cate02-08)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 항목 설정 | `/finance/settings/categories` | ✅ | 🔲 | — | — |
| 권한 관리 | `/finance/settings/permissions` | ✅ | 🔲 | — | — |

---

## ⚙️ 관리 (탑메뉴: 관리)

### 교회 페이지 (cate03-01)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 교회 소개 | `/management/website/intro` | ✅ | 🔲 | — | 백엔드 API 자체 미개발 |
| 예배안내 | `/management/website/worship-meeting` | ✅ | 🔲 | — | `gh_church_timetable` 테이블(공개 홈페이지 예배안내용) 사용 예상 — 교적의 예배 기준(`POST /v4/worship/*`)이 2026-07-17부로 이 테이블을 함께 쓰도록 통합됨(schema/gh_church_alter.md), 이 화면 연동 시 참고 |
| 소식·공지 | `/management/website/news` | ✅ | 🔲 | — | — |
| 미디어 | `/management/website/media` | ✅ | 🔲 | — | — |

### 시스템 설정 (cate03-02)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 전체 현황 | `/management/system` | ✅ | ✅ | 🔲 | `POST /v4/system/getOverview`(super.auth) 신규 — 전체 사용자(활성/비활성), 정의된 역할(고정 4종), 최근 로그인(7일), 최근 감사로그 10건. `AdminRepository::getUserStats/getRecentLoginCount` + `AuditLogRepository::getRecentLogs` 조합. KPI 카드·최근 활동 테이블 실데이터 연동, "활동 로그"/"시스템 점검" 빠른관리 카드는 각각 활동 로그 페이지로 연결 / 준비중 안내 모달(`ModalBox`)로 처리 |
| 권한 관리 | `/management/system/permissions` | ✅ | ✅ | 🔲 | 원래 `/member/settings/permissions`(교적 > 설정)에 만들었다가 이전 — `사용자 관리`(바로 아래 행)가 이미 여기서 실제 admin 계정을 관리하고 있고 그 페이지 자체가 "역할별 권한은 권한 관리에서 설정합니다"로 이 경로를 가리키고 있어, 권한 관리가 원래 있어야 할 자리가 여기라고 판단해 이동. `POST /v4/permission/*`(super.auth), 4개 고정 역할(admin/pastor/minister/volunteer) x 소메뉴(화면) 단위 36개 단일 접근 권한(조회/관리 구분 폐지) 매트릭스 — 구현 상세는 CLAUDE.md 진행 현황의 "권한 기반 프론트 UX"/"권한 모델 전면 재설계" 항목 참조. `SystemPermissions.jsx` 목업(고정 4역할×9권한, 저장 안 됨)을 실제 컴포넌트로 교체, 좌측 미니 사이드바는 제거(이 섹션의 실제 페이지인 `사용자 관리`와 동일하게 브레드크럼만 사용) |
| 사용자 관리 | `/management/system/users` | ✅ | ✅ | 🔲 | `POST /v4/admin/*` 연동 완료 |
| 활동 로그 (신규) | `/management/system/audit-logs` | ✅ | ✅ | 🔲 | `POST /v4/system/getAuditLogs`(super.auth) 신규 — `reg_audit_logs` 전체 조회, 메뉴/액션타입/검색대상(사용자명·대상·요약)+키워드/기간 필터, 페이지네이션(봉사현황 `Paging` 컴포넌트 패턴 재사용). 전체 현황의 "활동 로그" 빠른관리 카드 및 GNB·좌측 사이드바에서 진입. `adminOnly` 프론트 가드(권한 관리와 동일 기준) |

---

## 🧑 내정보 (GNB 우측 상단 — 로그인한 관리자 본인 계정)

| 페이지 | 경로 | 화면 | API 연동 | Dev 적용 | 비고 |
|---|---|:---:|:---:|:---:|---|
| 헤더 정보 표시 | GNB 우측 상단 드롭다운 | ✅ | ✅ | 0 | `HeaderGnb.jsx` — `UserDropdowns`가 마운트 시 `getMyProfile` 호출해 이름/이메일/역할 표시, 로딩 중에는 `AuthContext.adminInfo`(JWT 디코딩값)로 폴백 |
| 프로필 설정 | `/settings/profile` | ✅ | ✅ | 0 | `Profile.jsx` — `getMyProfile`로 로드, `updateMyProfile`로 저장. 저장 성공 시 `AuthContext.updateAdminInfo`로 헤더 이름 즉시 반영 |
| 비밀번호 변경 | `/settings/password` | ✅ | ✅ | 0 | `Password.jsx` — 현재/새/확인 클라이언트 검증 후 `changePassword` 호출 (현재 비밀번호 불일치는 서버가 `INVALID_CREDENTIALS`로 반환) |

> **백엔드**: `ProfileController`(`POST /v4/profile/getMyProfile`, `updateMyProfile`, `changePassword`) 신규 추가 — `jwt.auth`만 요구(`super.auth` 불필요), JWT의 `admin_no`로 본인 행만 조회/수정. `AdminController`(`/v4/admin/*`, `super.auth` 필수)는 여전히 *다른* 관리자를 관리하는 용도로 별개 유지.
> **JWT에 `name` 클레임 추가**: 로그인(`adminSignIn`) 시 `JwtHelper::createToken`이 `name`도 함께 인코딩 — 헤더가 별도 API 응답 없이도 즉시 폴백 표시 가능. 기존 발급된 토큰에는 `name`이 없으므로 재로그인 전까지는 `getMyProfile` 응답이 우선 사용됨.
> **스키마 변경**: `gh_church_admin`에 `email`/`phone` 컬럼 추가 필요([gh_church_admin_alter.md](schema/gh_church_admin_alter.md) 참조, HeidiSQL에서 직접 실행).

---

## 진행 현황 요약

> 화면/API 연동은 로컬 개발 기준. Dev 적용은 [이번 배포](#) 전 기준 스냅샷 — 배포 직후 이 표를 갱신할 것.

| 섹션 | 전체 페이지 수 | 화면+API 연동 완료 | 완료율 | Dev 적용 완료 |
|---|:---:|:---:|:---:|:---:|
| 공통 기반 | 5 | 5 | 100% | 0 |
| 인증 | 2 | 2 | 100% | 0 |
| 교인 | 6 | 5 | 83% | 0 |
| 출석 | 6 | 6 | 100% | 0 |
| 심방 | 6 | 6 | 100% | 0 |
| 교육 | 5 | 5 | 100% | 0 |
| 봉사 | 4 | 4 | 100% | 0 |
| 교적 · 보고/문자/게시판/설정 | 11 | 3 | 27% | 0 |
| 재정 (전체) | 16 | 0 | 0% | — |
| 관리 (전체) | 8 | 4 | 50% | 0 |
| 내정보 (전체) | 3 | 3 | 100% | 0 |
| **전체** | **72** | **43** | **60%** | **0** |

> 백엔드 API만 완료되고 프론트 연동이 안 된 항목(보고/문자/헌금 일부)은 "화면+API 연동 완료"에서 제외 — 화면 자체는 있으나 실제 데이터 연동이 없어 사용자가 쓸 수 없는 상태이기 때문.
