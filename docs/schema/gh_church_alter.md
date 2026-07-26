# gh_church / gh_church_location / gh_church_pastor / gh_church_timetable 테이블 변경 이력

> HeidiSQL에서 직접 실행. Laravel 마이그레이션 사용 금지.
> 이 테이블들은 gh_church_admin/gh_admin 등 관리자 페이지와 공개 교회로 앱/홈페이지가
> 공통으로 사용하는 공유 테이블 — 교적 시스템(`reg_` prefix) 전용이 아니므로,
> 교적 "기본정보" 화면에서 다루는 교회 프로필 정보(로고/상세주소/담임목사 지정)는
> 이 공유 테이블에 컬럼을 추가해 다른 시스템에서도 동일하게 조회 가능하도록 한다.

---

## [2026-07-17] gh_church: logo_file_no 컬럼 추가 (교적 "기본정보" 기능)

기존 `thumbnail` 컬럼은 교회 목록 등 다른 화면에서 이미 "대표 사진" 용도로 쓰이고 있어
그대로 재사용하고, "로고"는 별도 신규 컬럼으로 분리 (gh_file.file_no 참조).

```sql
ALTER TABLE `gh_church`
  ADD COLUMN `logo_file_no` BIGINT(20) NULL DEFAULT NULL COMMENT '교회 로고 — gh_file.file_no'
  AFTER `thumbnail`;
```

---

## [2026-07-17] gh_church_location: address_detail 컬럼 추가 (교적 "기본정보" 기능)

기존 `address` 컬럼은 기본 주소만 저장 — 상세 주소(동/층/호 등)를 위한 컬럼 추가.

```sql
ALTER TABLE `gh_church_location`
  ADD COLUMN `address_detail` VARCHAR(200) NULL DEFAULT NULL COMMENT '상세 주소 (동/층/호 등)'
  AFTER `address`;
```

---

## [2026-07-17] gh_church_pastor: is_head 컬럼 추가 (교적 "기본정보" 기능)

교회당 여러 목회자 레코드를 가질 수 있는데(목사/전도사/강도사) "담임목사"를 구분하는
플래그가 없었음. 공개 홈페이지에서도 담임목사 표시에 사용할 수 있도록 공유 테이블에 추가.
church_no당 최대 1건만 1이어야 함 — DB 제약이 아닌 애플리케이션 레벨에서 보장
(담임목사 지정 시 같은 church_no의 기존 is_head=1 행을 먼저 0으로 내림).

```sql
ALTER TABLE `gh_church_pastor`
  ADD COLUMN `is_head` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '담임목사 여부 (교회당 최대 1명)'
  AFTER `type_no`;
```

---

## [2026-07-17] gh_church_timetable: 예배 정의 컬럼 추가 + reg_services 데이터 이관

`reg_services`(교적 출결용, 구조화된 요일/시간)와 `gh_church_timetable`(공개 홈페이지
예배안내용, 자유 텍스트)가 같은 정보를 별도로 관리하고 있었음
([reg-vs-gh_church-overlap.md](reg-vs-gh_church-overlap.md) 참조).
`gh_church_timetable`을 단일 소스로 통합 — 구조화된 컬럼을 추가하고, 기존
`reg_services` 데이터(church_no=33632, 8건)를 이관하면서 `reg_attendance_records`가
참조하던 `service_id`(241건)도 새 ID로 재매핑한다. `reg_services`/`reg_attendance_records`
스키마 자체는 바뀌지 않음 — 애플리케이션 코드가 이제 `gh_church_timetable`을 바라볼 뿐.

`target_org_id`는 교적 전용(어떤 예배가 특정 조직 대상인지) — 다른 시스템은 이 컬럼을
쓰지 않으므로 무시해도 무방.

⚠️ **참고**: 33632번 교회는 이미 `gh_church_timetable`에 별도로 입력된 행이 1건 있음
(`timetable_no=267171`, "주일1부예배") — 이름이 정확히 일치하지 않아 자동 병합하지 않고
그대로 둠. 아래 이관 데이터의 "주일 1부"와 중복일 가능성이 있으니 확인 후 필요하면
수동 정리 권장.

### 1) 컬럼 추가

```sql
ALTER TABLE `gh_church_timetable`
  ADD COLUMN `category`      VARCHAR(20) NOT NULL DEFAULT 'regular' COMMENT '예배 분류 (regular=정기예배, other=기타예배)' AFTER `name`,
  ADD COLUMN `day_of_week`   TINYINT(4)  NULL DEFAULT NULL COMMENT '요일 (0=일~6=토, NULL=매일)' AFTER `place`,
  ADD COLUMN `start_time`    TIME        NULL DEFAULT NULL COMMENT '예배 시작 시간' AFTER `day_of_week`,
  ADD COLUMN `target_org_id` BIGINT(20)  NULL DEFAULT NULL COMMENT '대상 조직 — reg_organizations.id (교적 전용, 다른 시스템은 무시)' AFTER `start_time`,
  ADD COLUMN `sort_order`    INT(11)     NOT NULL DEFAULT 0 COMMENT '표시 순서' AFTER `target_org_id`,
  ADD COLUMN `is_active`     TINYINT(1)  NOT NULL DEFAULT 1 COMMENT '활성 여부' AFTER `sort_order`;
```

### 2) 데이터 이관 (church_no=33632 — reg_services에 데이터가 있는 유일한 교회)

HeidiSQL에서 한 세션으로 순서대로 실행 (세션 변수로 새 ID를 즉시 재사용).

```sql
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (33632, '주일 1부', '', '매주 일요일', 'regular', 0, NULL, NULL, 1, 1, NOW(), NOW());
SET @tt_1 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_1 WHERE service_id = 1 AND church_id = 33632;

INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (33632, '주일 2부', '', '매주 일요일', 'regular', 0, NULL, NULL, 2, 1, NOW(), NOW());
SET @tt_2 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_2 WHERE service_id = 2 AND church_id = 33632;

INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (33632, '수요예배', '', '매주 수요일', 'regular', 3, NULL, NULL, 3, 1, NOW(), NOW());
SET @tt_3 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_3 WHERE service_id = 3 AND church_id = 33632;

INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (33632, '금요기도', '', '매주 금요일', 'regular', 5, NULL, NULL, 4, 1, NOW(), NOW());
SET @tt_4 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_4 WHERE service_id = 4 AND church_id = 33632;

INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (33632, '새벽기도', '', '매일', 'regular', NULL, NULL, NULL, 5, 1, NOW(), NOW());
SET @tt_5 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_5 WHERE service_id = 5 AND church_id = 33632;

INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (33632, '청년예배', '', '매주 일요일', 'regular', 0, NULL, NULL, 6, 1, NOW(), NOW());
SET @tt_6 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_6 WHERE service_id = 6 AND church_id = 33632;

INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (33632, '교육부예배', '', '매주 일요일', 'regular', 0, NULL, NULL, 7, 1, NOW(), NOW());
SET @tt_7 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_7 WHERE service_id = 7 AND church_id = 33632;

INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (33632, '헌신예배', '', '매주 수요일 19:30', 'other', 3, '19:30:00', NULL, 0, 1, NOW(), NOW());
SET @tt_8 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_8 WHERE service_id = 8 AND church_id = 33632;
```

### 3) 확인 쿼리

```sql
SELECT timetable_no, church_no, name, category, day_of_week, start_time, sort_order, is_active
FROM gh_church_timetable WHERE church_no = 33632 ORDER BY sort_order;
-- 기대값: 8건(+기존 267171행 별도) — 자동 이관된 8건 category/day_of_week 확인

SELECT service_id, COUNT(*) FROM reg_attendance_records WHERE church_id = 33632 GROUP BY service_id;
-- 기대값: service_id가 전부 새 timetable_no 값으로 바뀌어 있어야 함 (1~8 같은 옛 값이 남아있으면 안 됨)
```

이관 완료 후 애플리케이션 코드는 더 이상 `reg_services`를 읽거나 쓰지 않는다
(`reg_attendance_records`는 계속 정상 사용 — `service_id` 값만 재매핑됨).

### 4) reg_services 삭제 (이관 확인 후)

이관·재매핑이 끝나면 `reg_services`는 완전히 미사용 상태 — DB 레벨 FK 제약도 없고
(`reg_attendance_records.service_id`는 애플리케이션 레벨 참조), 이 테이블을 참조하는
프로시저/뷰도 없음(확인 완료). 삭제해도 안전.

```sql
DROP TABLE `reg_services`;
```

### 5) dev 환경 적용 (church_no=19715, 행복샘교회 2)

⚠️ **1)의 컬럼 추가 ALTER는 local과 별개 DB 인스턴스라 dev에서도 동일하게 다시 실행해야 함**
(스키마는 DB끼리 자동으로 따라가지 않음). 4)의 `DROP TABLE`은 이관·검증 끝난 뒤 마지막에.

2)의 데이터 이관은 dev의 `reg_services`가 실제로 어떤 `id` 값을 쓰고 있는지 이 세션에서
확인할 수 없어서, **id를 하드코딩하지 않고 이름으로 조회**하도록 작성함 — dev의
auto_increment 상태가 local(1~8)과 다르더라도 안전하게 동작. `reg_services`에 해당
이름의 행이 없으면(church_id=19715에 애초에 데이터가 없는 경우) `@old_id`가 NULL이 되고
UPDATE는 그냥 0건 매칭으로 조용히 넘어감 — 먼저 아래 확인 쿼리로 데이터 존재 여부부터
확인 권장.

```sql
-- 사전 확인: dev(church_id=19715)에 reg_services 데이터가 있는지
SELECT id, name, category, day_of_week, start_time, sort_order, is_active
FROM reg_services WHERE church_id = 19715 ORDER BY sort_order;
```

```sql
SET @old_id = (SELECT id FROM reg_services WHERE church_id = 19715 AND name = '주일 1부' LIMIT 1);
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (19715, '주일 1부', '', '매주 일요일', 'regular', 0, NULL, NULL, 1, 1, NOW(), NOW());
SET @tt_1 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_1 WHERE service_id = @old_id AND church_id = 19715;

SET @old_id = (SELECT id FROM reg_services WHERE church_id = 19715 AND name = '주일 2부' LIMIT 1);
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (19715, '주일 2부', '', '매주 일요일', 'regular', 0, NULL, NULL, 2, 1, NOW(), NOW());
SET @tt_2 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_2 WHERE service_id = @old_id AND church_id = 19715;

SET @old_id = (SELECT id FROM reg_services WHERE church_id = 19715 AND name = '수요예배' LIMIT 1);
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (19715, '수요예배', '', '매주 수요일', 'regular', 3, NULL, NULL, 3, 1, NOW(), NOW());
SET @tt_3 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_3 WHERE service_id = @old_id AND church_id = 19715;

SET @old_id = (SELECT id FROM reg_services WHERE church_id = 19715 AND name = '금요기도' LIMIT 1);
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (19715, '금요기도', '', '매주 금요일', 'regular', 5, NULL, NULL, 4, 1, NOW(), NOW());
SET @tt_4 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_4 WHERE service_id = @old_id AND church_id = 19715;

SET @old_id = (SELECT id FROM reg_services WHERE church_id = 19715 AND name = '새벽기도' LIMIT 1);
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (19715, '새벽기도', '', '매일', 'regular', NULL, NULL, NULL, 5, 1, NOW(), NOW());
SET @tt_5 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_5 WHERE service_id = @old_id AND church_id = 19715;

SET @old_id = (SELECT id FROM reg_services WHERE church_id = 19715 AND name = '청년예배' LIMIT 1);
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (19715, '청년예배', '', '매주 일요일', 'regular', 0, NULL, NULL, 6, 1, NOW(), NOW());
SET @tt_6 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_6 WHERE service_id = @old_id AND church_id = 19715;

SET @old_id = (SELECT id FROM reg_services WHERE church_id = 19715 AND name = '교육부예배' LIMIT 1);
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (19715, '교육부예배', '', '매주 일요일', 'regular', 0, NULL, NULL, 7, 1, NOW(), NOW());
SET @tt_7 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_7 WHERE service_id = @old_id AND church_id = 19715;

SET @old_id = (SELECT id FROM reg_services WHERE church_id = 19715 AND name = '헌신예배' LIMIT 1);
INSERT INTO gh_church_timetable (church_no, name, place, timetext, category, day_of_week, start_time, target_org_id, sort_order, is_active, registered, updated)
VALUES (19715, '헌신예배', '', '매주 수요일 19:30', 'other', 3, '19:30:00', NULL, 0, 1, NOW(), NOW());
SET @tt_8 = LAST_INSERT_ID();
UPDATE reg_attendance_records SET service_id = @tt_8 WHERE service_id = @old_id AND church_id = 19715;
```

이관 후 확인(3번 항목의 확인 쿼리를 church_no=19715로 바꿔서 재사용):

```sql
SELECT timetable_no, church_no, name, category, day_of_week, start_time, sort_order, is_active
FROM gh_church_timetable WHERE church_no = 19715 ORDER BY sort_order;

SELECT service_id, COUNT(*) FROM reg_attendance_records WHERE church_id = 19715 GROUP BY service_id;
```

---

## [2026-07-17] gh_church_location: postcode 컬럼 추가 (교적 "기본정보" 주소 검색 기능)

교회 프로필의 주소 입력을 자유 입력 텍스트에서 다음(카카오) 주소 검색 팝업(`AddressSearchModal`,
교인등록 페이지와 동일 컴포넌트)으로 교체하면서 우편번호도 함께 저장하도록 컬럼 추가.
컬럼명은 `zip`(미국식 용어) 대신 `postcode`로 — `AddressSearchModal.jsx`의 `POSTCODE_URL`,
`daum.Postcode`가 이미 쓰고 있는 다음 우편번호 위젯의 공식 명칭과 동일하게 맞춤.
`VARCHAR(10)`은 `reg_members.address_zip`과 동일한 정의 — 우편번호는 5자리 숫자이나 여유를 둠.

```sql
ALTER TABLE `gh_church_location`
  ADD COLUMN `postcode` VARCHAR(10) NULL DEFAULT NULL COMMENT '우편번호'
  AFTER `address_detail`;
```

확인 쿼리:

```sql
SHOW COLUMNS FROM gh_church_location LIKE 'postcode';
```

---

## [2026-07-17] gh_church_timetable: description / note 컬럼 추가 (관리 > 교회 페이지 "예배·모임 안내" 기능)

✅ **local/dev(이 세션에서 연결된 gyohyero DB, church_no 33632·19715 데이터 모두 존재) 적용 완료** —
이 세션의 DB 연결로 직접 실행함(사용자 승인). 이 Laravel 앱이 연결하는 DB 인스턴스가 이것 하나뿐이라
별도의 원격 dev 서버가 있다면 거기에도 동일 SQL을 실행해야 함.

공개 홈페이지 예배안내 화면(`WorshipMeeting.jsx`)의 카드마다 "설명"(한 줄 소개)과 "비고"(추가 안내사항)
입력란이 있는데, 기존 `gh_church_timetable`엔 이 두 필드가 없음(교직설정 "예배 기준" 탭은 name/day_of_week/
start_time만 사용해 문제 없었음). `desc`는 예약어라 `description`으로 명명.

```sql
ALTER TABLE `gh_church_timetable`
  ADD COLUMN `description` VARCHAR(200) NULL DEFAULT NULL COMMENT '예배 한 줄 설명 (공개 홈페이지 노출)' AFTER `name`,
  ADD COLUMN `note`        VARCHAR(200) NULL DEFAULT NULL COMMENT '비고 (공개 홈페이지 노출)' AFTER `is_active`;
```

확인 쿼리:

```sql
SHOW COLUMNS FROM gh_church_timetable LIKE 'description';
SHOW COLUMNS FROM gh_church_timetable LIKE 'note';
```

---

## [2026-07-17] gh_church_intro / gh_church_pastor / gh_church: "교회소개" 기능 컬럼 추가

✅ **local/dev(이 세션에서 연결된 gyohyero DB) 적용 완료** — 이 세션의 DB 연결로 직접 실행함.

`Intro.jsx`(관리 > 교회 페이지 > 교회소개)는 기존 `gh_church_intro`(slogan/vision/message)보다 훨씬
많은 필드를 다룬다. 값이 짧고 단순한 것은 개별 컬럼으로, 여러 개가 묶여 있는 것(버튼 2개,
성경구절, 오시는 길 안내)은 JSON 컬럼 하나로 — 공유 테이블에 컬럼을 과도하게 늘리지 않으면서도
02_gh_admin_api 등 다른 시스템은 이 컬럼들을 몰라도 무방(참조 안 하면 그냥 무시됨).

기존 `slogan`(표어) / `vision`(비전) / `message`(전하는 말 본문) 컬럼은 그대로 재사용 — 이름이
그대로 맞아떨어짐. `images`/`videos`/`rep_images`/`man_images`/`event_images`는 건드리지 않음
(그 필드들은 미디어 화면에서 이미 `gh_new_board_content` 게시판 시스템으로 대체해 사용 중이라
혼선 방지를 위해 손대지 않음).

```sql
ALTER TABLE `gh_church_intro`
  ADD COLUMN `hero_title`       VARCHAR(50)  NULL DEFAULT NULL COMMENT '교회소개 요약 타이틀' AFTER `vision`,
  ADD COLUMN `hero_subtitle`    VARCHAR(100) NULL DEFAULT NULL COMMENT '교회소개 요약 타이틀 보조' AFTER `hero_title`,
  ADD COLUMN `hero_buttons`     TEXT         NULL DEFAULT NULL COMMENT '요약 섹션 버튼 2개(JSON: [{label,url}, {label,url}])' AFTER `hero_subtitle`,
  ADD COLUMN `intro_title`      VARCHAR(200) NULL DEFAULT NULL COMMENT '교회소개 제목(기본정보 섹션)' AFTER `hero_buttons`,
  ADD COLUMN `welcome_title`    VARCHAR(100) NULL DEFAULT NULL COMMENT '전하는 말 제목' AFTER `intro_title`,
  ADD COLUMN `welcome_highlight` VARCHAR(200) NULL DEFAULT NULL COMMENT '전하는 말 강조 문구' AFTER `welcome_title`,
  ADD COLUMN `bible_quote`      TEXT         NULL DEFAULT NULL COMMENT '전하는 말 성경구절(JSON: {mode, book, chapter, verse, manual_text})' AFTER `welcome_highlight`,
  ADD COLUMN `directions`       TEXT         NULL DEFAULT NULL COMMENT '오시는 길(JSON: {location_detail, contact_note, transit_subway, transit_car, transit_navi, parking_info})' AFTER `bible_quote`;

ALTER TABLE `gh_church_pastor`
  ADD COLUMN `career` TEXT NULL DEFAULT NULL COMMENT '주요 약력 다건(JSON 문자열 배열, 교회소개 화면 전용)' AFTER `pastor_intro`;
```

`gh_church.church_create_date`는 이미 존재하는 컬럼(02_gh_admin_api 교회 등록 시 사용)인데
`ChurchProfileService`가 아직 노출하지 않고 있었음 — "창립 년도" 필드는 신규 컬럼 없이 이 필드를
`ChurchProfileService`에 추가 노출해서 재사용(주소/연락처/이메일/홈페이지와 동일하게 Intro.jsx가
`churchProfileService`를 통해 조회·저장).

확인 쿼리:

```sql
SHOW COLUMNS FROM gh_church_intro LIKE 'hero_title';
SHOW COLUMNS FROM gh_church_pastor LIKE 'career';
```

---

## [2026-07-17] gh_church_intro: vision 컬럼 VARCHAR(300) → TEXT 확장

✅ **local/dev(이 세션에서 연결된 gyohyero DB) 적용 완료**.

교회소개 화면의 textarea 입력란에 관리자 페이지(02_gh_admin_front)와 동일한 Quill 리치 텍스트
에디터를 적용하면서, `비전` 필드도 HTML(Quill 출력)을 저장하게 됨. `message` 컬럼은 원래부터
TEXT라 문제없지만 `vision`은 VARCHAR(300)이라 HTML 태그가 붙으면 쉽게 넘침 — TEXT로 확장.

```sql
ALTER TABLE `gh_church_intro` MODIFY COLUMN `vision` TEXT NULL DEFAULT NULL;
```

확인 쿼리:

```sql
SHOW COLUMNS FROM gh_church_intro LIKE 'vision';
```
