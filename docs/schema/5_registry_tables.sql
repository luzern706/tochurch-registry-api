-- ================================================================
-- 교적 관리 시스템 테이블 생성 스크립트
-- DB: gyohyero_db
-- prefix: reg_
-- 작성일: 2026-05
-- 주의: 기존 교회로 테이블 수정 없음. 신규 테이블만 추가.
-- ================================================================

USE gyohyero_db;

-- ================================================================
-- 1. 교인 계정 (reg_members)
-- 교회로 회원(users)과 연동 가능 (churchero_user_id)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_members` (
    `id`                  BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`           BIGINT       NOT NULL COMMENT '교회 ID (기존 churches 테이블 참조)',
    `churchero_user_id`   BIGINT       NULL     COMMENT '교회로 회원 연동 ID (users.id)',
    `member_no`           VARCHAR(20)  NOT NULL COMMENT '교인번호 예: M-20240101',
    `email`               VARCHAR(100) NOT NULL COMMENT '로그인 이메일',
    `password`            VARCHAR(255) NOT NULL COMMENT '암호화된 패스워드',
    `name`                VARCHAR(50)  NOT NULL COMMENT '실명',
    `nickname`            VARCHAR(50)  NULL     COMMENT '닉네임',
    `phone`               VARCHAR(20)  NULL     COMMENT '휴대전화',
    `gender`              ENUM('M','F') NULL    COMMENT '성별',
    `birth_date`          DATE         NULL     COMMENT '생년월일',
    `birth_type`          ENUM('solar','lunar') NOT NULL DEFAULT 'solar' COMMENT '양력/음력',
    `address_zip`         VARCHAR(10)  NULL     COMMENT '우편번호',
    `address_main`        VARCHAR(200) NULL     COMMENT '기본주소',
    `address_detail`      VARCHAR(100) NULL     COMMENT '상세주소',
    `profile_image`       VARCHAR(500) NULL     COMMENT '프로필 사진 경로',
    `status`              ENUM('active','inactive','unknown') NOT NULL DEFAULT 'active' COMMENT '출석중/가끔/안함',
    `memo`                TEXT         NULL     COMMENT '특이사항 메모',
    `is_deleted`          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '삭제 여부',
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_member_no` (`member_no`),
    UNIQUE KEY `uq_email_church` (`email`, `church_id`),
    KEY `idx_church_id` (`church_id`),
    KEY `idx_churchero_user_id` (`churchero_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='교인 계정';


-- ================================================================
-- 2. 교인 교적 상세 정보 (reg_member_profiles)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_member_profiles` (
    `id`                  BIGINT       NOT NULL AUTO_INCREMENT,
    `member_id`           BIGINT       NOT NULL COMMENT 'reg_members.id',
    `member_type`         VARCHAR(30)  NOT NULL DEFAULT '새가족' COMMENT '교인구분',
    `member_type_source`  ENUM('system','user') NOT NULL DEFAULT 'system' COMMENT '시스템고정/사용자정의',
    `position`            VARCHAR(30)  NULL     COMMENT '직분 (목사/장로/집사/권사/성도)',
    `baptism_grade`       VARCHAR(20)  NULL     COMMENT '신급 (세례/입교/학습 등)',
    `attendance_grade`    VARCHAR(10)  NULL     COMMENT '출석등급 (우수/보통/미흡)',
    `ordained_at`         DATE         NULL     COMMENT '임직일',
    `ordained_church`     VARCHAR(100) NULL     COMMENT '임직교회',
    `baptism_at`          DATE         NULL     COMMENT '집례일',
    `baptism_church`      VARCHAR(100) NULL     COMMENT '집례교회',
    `registered_at`       DATE         NULL     COMMENT '등록일',
    `welcomed_at`         DATE         NULL     COMMENT '환영일',
    `previous_church`     VARCHAR(100) NULL     COMMENT '이전교회',
    `leader_member_id`    BIGINT       NULL     COMMENT '인도자 교인 ID',
    `marriage_status`     ENUM('single','married','widowed','divorced') NULL COMMENT '결혼상태',
    `is_household_head`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '세대주 여부',
    `household_relation`  VARCHAR(20)  NULL     COMMENT '세대주와의 관계',
    `workplace`           VARCHAR(100) NULL     COMMENT '직장/학교명',
    `custom_field_1`      VARCHAR(200) NULL     COMMENT '자유항목1',
    `custom_field_2`      VARCHAR(200) NULL     COMMENT '자유항목2',
    `custom_field_3`      VARCHAR(200) NULL     COMMENT '자유항목3',
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_member_id` (`member_id`),
    KEY `idx_leader_member_id` (`leader_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='교인 교적 상세 정보';


-- ================================================================
-- 3. 조직 (reg_organizations)
-- 남전도회 > 제1남전도회 처럼 계층구조
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_organizations` (
    `id`          BIGINT      NOT NULL AUTO_INCREMENT,
    `church_id`   BIGINT      NOT NULL COMMENT '교회 ID',
    `parent_id`   BIGINT      NULL     COMMENT '상위 조직 ID (NULL=최상위)',
    `name`        VARCHAR(50) NOT NULL COMMENT '조직명',
    `sort_order`  INT         NOT NULL DEFAULT 0 COMMENT '정렬순서',
    `is_active`   TINYINT(1)  NOT NULL DEFAULT 1 COMMENT '사용여부',
    `created_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_church_id` (`church_id`),
    KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='조직 (남전도회, 청년부 등)';


-- ================================================================
-- 4. 교인-조직 매핑 (reg_member_organizations)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_member_organizations` (
    `id`               BIGINT     NOT NULL AUTO_INCREMENT,
    `member_id`        BIGINT     NOT NULL COMMENT 'reg_members.id',
    `organization_id`  BIGINT     NOT NULL COMMENT 'reg_organizations.id',
    `is_primary`       TINYINT(1) NOT NULL DEFAULT 1 COMMENT '주소속 여부',
    `joined_at`        DATE       NULL     COMMENT '가입일',
    `created_at`       TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_member_org` (`member_id`, `organization_id`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_organization_id` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='교인-조직 매핑';


-- ================================================================
-- 5. 가족 관계 (reg_families)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_families` (
    `id`                  BIGINT       NOT NULL AUTO_INCREMENT,
    `member_id`           BIGINT       NOT NULL COMMENT '교인 ID',
    `related_member_id`   BIGINT       NOT NULL COMMENT '관련 교인 ID',
    `relation_type`       VARCHAR(20)  NOT NULL COMMENT '관계 (배우자/자녀/부모/형제자매/기타)',
    `family_note`         TEXT         NULL     COMMENT '가족 특이사항',
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_family` (`member_id`, `related_member_id`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_related_member_id` (`related_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='가족 관계';


-- ================================================================
-- 6. 예배/모임 종류 (reg_services)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_services` (
    `id`              BIGINT      NOT NULL AUTO_INCREMENT,
    `church_id`       BIGINT      NOT NULL COMMENT '교회 ID',
    `name`            VARCHAR(50) NOT NULL COMMENT '예배명 (주일 1부, 수요예배 등)',
    `day_of_week`     TINYINT     NULL     COMMENT '요일 0=일 ~ 6=토',
    `target_org_id`   BIGINT      NULL     COMMENT '대상 조직 (NULL=전체)',
    `sort_order`      INT         NOT NULL DEFAULT 0,
    `is_active`       TINYINT(1)  NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_church_id` (`church_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='예배/모임 종류';


-- ================================================================
-- 7. 출석 기록 (reg_attendance_records)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_attendance_records` (
    `id`           BIGINT     NOT NULL AUTO_INCREMENT,
    `member_id`    BIGINT     NOT NULL COMMENT 'reg_members.id',
    `service_id`   BIGINT     NOT NULL COMMENT 'reg_services.id',
    `church_id`    BIGINT     NOT NULL,
    `attend_date`  DATE       NOT NULL COMMENT '출석 날짜',
    `status`       ENUM('present','absent','unknown') NOT NULL DEFAULT 'unknown' COMMENT '출석/결석/미체크',
    `note`         TEXT       NULL     COMMENT '특이사항',
    `recorded_by`  BIGINT     NOT NULL COMMENT '입력한 교인 ID',
    `created_at`   TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attendance` (`member_id`, `service_id`, `attend_date`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_church_date` (`church_id`, `attend_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='출석 기록';


-- ================================================================
-- 8. 심방 기록 (reg_visit_records)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_visit_records` (
    `id`                  BIGINT       NOT NULL AUTO_INCREMENT,
    `member_id`           BIGINT       NOT NULL COMMENT '심방 대상 교인',
    `church_id`           BIGINT       NOT NULL,
    `visit_type`          ENUM('annual','event') NOT NULL DEFAULT 'annual' COMMENT '대심방/이벤트심방',
    `event_reason`        VARCHAR(30)  NULL     COMMENT '이벤트 사유 (이사/병문안/생신 등)',
    `visit_date`          DATE         NOT NULL COMMENT '심방일',
    `visitor_member_id`   BIGINT       NOT NULL COMMENT '심방자 교인 ID',
    `manager_member_id`   BIGINT       NULL     COMMENT '담당자 교인 ID',
    `place`               VARCHAR(100) NULL     COMMENT '장소',
    `companion`           VARCHAR(200) NULL     COMMENT '동행자',
    `attendee_count`      INT          NULL     COMMENT '참석인원',
    `content`             TEXT         NOT NULL COMMENT '심방 내용 요약',
    `common_note`         TEXT         NULL     COMMENT '공통심방 내용',
    `private_note`        TEXT         NULL     COMMENT '비공개 사항',
    `add_to_prayer`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '기도목록 추가 여부',
    `created_by`          BIGINT       NOT NULL COMMENT '작성자',
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_church_date` (`church_id`, `visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='심방 기록';


-- ================================================================
-- 9. 기도 목록 (reg_prayer_records)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_prayer_records` (
    `id`           BIGINT     NOT NULL AUTO_INCREMENT,
    `member_id`    BIGINT     NOT NULL COMMENT '대상 교인',
    `church_id`    BIGINT     NOT NULL,
    `content`      TEXT       NOT NULL COMMENT '기도 제목',
    `is_resolved`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '응답 여부',
    `created_by`   BIGINT     NOT NULL COMMENT '작성자',
    `created_at`   TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_church_id` (`church_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='기도 목록';


-- ================================================================
-- 10. 봉사 팀 (reg_service_teams)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_service_teams` (
    `id`                   BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`            BIGINT       NOT NULL,
    `name`                 VARCHAR(100) NOT NULL COMMENT '봉사명',
    `description`          VARCHAR(300) NULL     COMMENT '봉사 설명',
    `service_type`         VARCHAR(30)  NULL     COMMENT '봉사 유형 (예배/주차/식당/행사/특별)',
    `mode`                 ENUM('regular','onetime') NOT NULL DEFAULT 'regular' COMMENT '정기/일회성',
    `frequency`            ENUM('weekly','biweekly','monthly') NULL COMMENT '반복 주기',
    `day_of_week`          TINYINT      NULL     COMMENT '요일 0=일 ~ 6=토',
    `service_time`         TIME         NULL     COMMENT '봉사 시간',
    `event_date`           DATE         NULL     COMMENT '일회성 날짜',
    `start_time`           TIME         NULL     COMMENT '시작 시간',
    `end_time`             TIME         NULL     COMMENT '종료 시간',
    `required_count`       INT          NOT NULL DEFAULT 1 COMMENT '필요 인원',
    `manager_member_id`    BIGINT       NULL     COMMENT '담당자 교인 ID',
    `participation_type`   ENUM('application','assignment') NOT NULL DEFAULT 'application' COMMENT '신청/배정',
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '사용여부',
    `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_church_id` (`church_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='봉사 팀';


-- ================================================================
-- 11. 봉사 참여자 (reg_service_members)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_service_members` (
    `id`               BIGINT      NOT NULL AUTO_INCREMENT,
    `service_team_id`  BIGINT      NOT NULL COMMENT 'reg_service_teams.id',
    `member_id`        BIGINT      NOT NULL COMMENT 'reg_members.id',
    `role`             VARCHAR(50) NULL     COMMENT '역할/직책',
    `joined_at`        DATE        NULL     COMMENT '시작일',
    `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_team_member` (`service_team_id`, `member_id`),
    KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='봉사 참여자';


-- ================================================================
-- 12. 교육 과정 (reg_education_courses)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_education_courses` (
    `id`          BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`   BIGINT       NOT NULL,
    `name`        VARCHAR(100) NOT NULL COMMENT '과정명 (새신자교육/성경통독반 등)',
    `description` TEXT         NULL,
    `start_date`  DATE         NULL     COMMENT '시작일',
    `end_date`    DATE         NULL     COMMENT '종료일',
    `status`      ENUM('upcoming','ongoing','completed') NOT NULL DEFAULT 'upcoming',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_church_id` (`church_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='교육 과정';


-- ================================================================
-- 13. 교육 수강 기록 (reg_education_records)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_education_records` (
    `id`          BIGINT     NOT NULL AUTO_INCREMENT,
    `course_id`   BIGINT     NOT NULL COMMENT 'reg_education_courses.id',
    `member_id`   BIGINT     NOT NULL COMMENT 'reg_members.id',
    `progress`    INT        NOT NULL DEFAULT 0 COMMENT '진도율 0~100',
    `status`      ENUM('ongoing','completed','dropped') NOT NULL DEFAULT 'ongoing',
    `created_at`  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_course_member` (`course_id`, `member_id`),
    KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='교육 수강 기록';


-- ================================================================
-- 14. 헌금 기록 (reg_offering_records)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_offering_records` (
    `id`           BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`    BIGINT       NOT NULL,
    `member_id`    BIGINT       NULL     COMMENT '교인 ID (익명 허용)',
    `offer_date`   DATE         NOT NULL COMMENT '헌금 날짜',
    `category`     VARCHAR(50)  NOT NULL COMMENT '헌금 항목 (주일헌금/감사헌금 등)',
    `amount`       INT          NOT NULL DEFAULT 0 COMMENT '금액',
    `method`       ENUM('cash','transfer') NOT NULL DEFAULT 'cash' COMMENT '현금/이체',
    `ledger`       VARCHAR(100) NULL     COMMENT '원장 (현금/국민은행 등)',
    `recorded_by`  BIGINT       NOT NULL COMMENT '입력자 교인 ID',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_church_date` (`church_id`, `offer_date`),
    KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='헌금 기록';


-- ================================================================
-- 15. 메시지 발송 기록 (reg_messages)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_messages` (
    `id`               BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`        BIGINT       NOT NULL,
    `title`            VARCHAR(200) NOT NULL COMMENT '제목',
    `content`          TEXT         NOT NULL COMMENT '내용',
    `send_type`        ENUM('sms','push','email') NOT NULL DEFAULT 'push' COMMENT '발송 유형',
    `sent_at`          TIMESTAMP    NULL     COMMENT '발송 시각',
    `sent_by`          BIGINT       NOT NULL COMMENT '발송자 교인 ID',
    `recipient_count`  INT          NOT NULL DEFAULT 0 COMMENT '발송 대상 수',
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_church_id` (`church_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='메시지 발송 기록';


-- ================================================================
-- 16. 감사 로그 (reg_audit_logs)
-- 교적 시스템 write 액션 추적용 (관리자 API audit log V4 패턴 동일 적용)
--  - admin_no(INT) → member_id(BIGINT) 로 변경 (reg_members.id 와 타입 일치)
--  - church_id 추가 (멀티테넌트 격리)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_audit_logs` (
    `audit_no`      BIGINT       NOT NULL AUTO_INCREMENT COMMENT '감사 로그 PK',
    `church_id`     BIGINT       NOT NULL COMMENT '교회 ID (멀티테넌트 격리)',
    `member_id`     BIGINT       NOT NULL COMMENT '작업자 reg_members.id',
    `menu_code`     VARCHAR(50)  NOT NULL COMMENT '메뉴 코드: MEMBER, ORGANIZATION, FAMILY, WORSHIP, ATTENDANCE, VISIT, VOLUNTEER, EDUCATION, OFFERING, MESSAGE, AUTH 등',
    `action_type`   VARCHAR(50)  NOT NULL COMMENT '액션 타입: CREATE, UPDATE, DELETE, LOGIN, LOGOUT, SEND, ASSIGN, UNASSIGN, ENROLL, WITHDRAW 등',
    `target_id`     VARCHAR(100) NULL     COMMENT '대상 식별자 (PK 다양성 수용, NULL 허용)',
    `target_label`  VARCHAR(200) NULL     COMMENT '대상 이름 (조회 편의용 비정규화: 교인명, 조직명 등)',
    `summary`       VARCHAR(500) NOT NULL COMMENT '액션 요약 (사람이 읽는 한 줄 설명)',
    `change_detail` LONGTEXT     NULL     COMMENT '간단한 변경사항 JSON. 큰 내용은 NULL' COLLATE utf8mb4_bin,
    `result`        VARCHAR(10)  NOT NULL DEFAULT 'SUCCESS' COMMENT 'SUCCESS, FAIL',
    `ip_address`    VARCHAR(45)  NULL     COMMENT '작업자 IP (IPv6 고려)',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    PRIMARY KEY (`audit_no`),
    KEY `idx_church_member` (`church_id`, `member_id`, `created_at`) COMMENT '교회·작업자별 이력 조회',
    KEY `idx_church_menu`   (`church_id`, `menu_code`, `created_at`) COMMENT '교회·메뉴별 이력 조회',
    KEY `idx_target`        (`menu_code`, `target_id`)               COMMENT '특정 대상의 변경 이력 조회',
    KEY `idx_created`       (`created_at`)                            COMMENT '기간별 조회',
    CONSTRAINT `chk_change_detail_json` CHECK (`change_detail` IS NULL OR JSON_VALID(`change_detail`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='교적 시스템 작업 감사 로그';


-- ================================================================
-- 생성 확인
-- ================================================================
SELECT TABLE_NAME, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'gyohyero_db'
  AND TABLE_NAME LIKE 'reg_%'
ORDER BY TABLE_NAME;
