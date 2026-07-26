-- ================================================================
-- 코드 관리 테이블 (범용 코드 그룹 + 코드값)
-- DB: gyohyero_db
-- 작성일: 2026-06
-- 용도: 교인구분, 직분, 관계, 교육기준, 봉사기준, 심방기준 등
--       교회별로 커스터마이즈 가능한 선택 목록 관리
-- ================================================================

USE gyohyero_db;

-- ================================================================
-- 1. 코드 그룹 (reg_code_groups)
-- 어떤 종류의 코드인지 정의 (교인구분, 직분, 관계 등)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_code_groups` (
    `id`          BIGINT      NOT NULL AUTO_INCREMENT,
    `church_id`   BIGINT      NOT NULL COMMENT '교회 ID',
    `group_key`   VARCHAR(50) NOT NULL COMMENT '그룹 키 (member_type / position / relation 등)',
    `group_label` VARCHAR(50) NOT NULL COMMENT '화면 표시명 (교인구분 / 직분 / 관계 등)',
    `sort_order`  INT         NOT NULL DEFAULT 0,
    `is_system`   TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '시스템 기본 그룹 여부 (1=삭제 불가)',
    `created_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_church_group_key` (`church_id`, `group_key`),
    KEY `idx_church_id` (`church_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='코드 그룹 (교인구분/직분/관계 등 선택 목록 종류)';


-- ================================================================
-- 2. 코드값 (reg_codes)
-- 각 그룹에 속하는 실제 선택 항목 목록
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_codes` (
    `id`          BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`   BIGINT       NOT NULL COMMENT '교회 ID',
    `group_key`   VARCHAR(50)  NOT NULL COMMENT 'reg_code_groups.group_key 참조',
    `code_value`  VARCHAR(50)  NOT NULL COMMENT '실제 저장 값 (member_type에 들어가는 값)',
    `code_label`  VARCHAR(50)  NOT NULL COMMENT '화면 표시명',
    `description` VARCHAR(200) NULL     COMMENT '설명',
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '활성 여부 (0=숨김)',
    `is_system`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '시스템 기본값 여부 (1=삭제 불가)',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_church_group_value` (`church_id`, `group_key`, `code_value`),
    KEY `idx_church_group` (`church_id`, `group_key`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='코드값 (교인구분/직분/관계 등 선택 목록 항목)';


-- ================================================================
-- 기본 데이터 INSERT
-- ⚠️  church_id = 1 부분을 실제 교회 ID로 교체 후 실행
-- ================================================================

-- ----------------------------------------------------------------
-- 코드 그룹 기본값
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (1, 'member_type', '교인구분', 1, 1);
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (1, 'position', '직분유형', 2, 1);
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (1, 'relation', '관계유형', 3, 1);
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (1, 'education_type', '교육기준', 4, 1);
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (1, 'service_type', '봉사기준', 5, 1);
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (1, 'visit_type', '심방기준', 6, 1);
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (1, 'attendance_status', '출석상태', 7, 1);

-- ----------------------------------------------------------------
-- 교인구분
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '새가족', '새가족', '첫 방문 및 등록 대기 교인', 1, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '등록교인', '등록교인', '정식 등록된 교인', 2, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '세례교인', '세례교인', '세례를 받은 교인', 3, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '입교인', '입교인', '입교한 교인', 4, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '학습교인', '학습교인', '학습 중인 교인', 5, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '유아세례', '유아세례', '유아세례를 받은 교인', 6, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '협동교인', '협동교인', '협동 교인', 7, 0, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '온라인교인', '온라인교인', '온라인으로 참여하는 교인', 8, 0, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'member_type', '기타', '기타', '기타 구분', 9, 1, 0);

-- ----------------------------------------------------------------
-- 직분
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '성도', '성도', '일반 성도', 1, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '집사(서리)', '집사(서리)', '서리집사', 2, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '집사(안수)', '집사(안수)', '안수집사', 3, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '권사', '권사', '권사직', 4, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '장로', '장로', '장로직', 5, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '목사', '목사', '목사', 6, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '부목사', '부목사', '부목사', 7, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '교역자', '교역자', '교역자', 8, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '사역자', '사역자', '사역자', 9, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'position', '봉사자', '봉사자', '봉사자', 10, 1, 0);

-- ----------------------------------------------------------------
-- 관계유형
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'relation', '세대주', '세대주', '가족의 대표 세대주', 1, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'relation', '배우자', '배우자', '세대주의 배우자', 2, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'relation', '자녀', '자녀', '세대주의 자녀', 3, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'relation', '부', '부', '세대주의 아버지', 4, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'relation', '모', '모', '세대주의 어머니', 5, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'relation', '형제', '형제', '세대주의 형제', 6, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'relation', '자매', '자매', '세대주의 자매', 7, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'relation', '기타', '기타', '기타 관계', 8, 1, 0);

-- ----------------------------------------------------------------
-- 교육기준
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'education_type', '새가족교육', '새가족 교육', '새가족을 위한 기초 교육', 1, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'education_type', '기초신앙', '기초신앙', '기초 신앙 교육 과정', 2, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'education_type', '제자훈련', '제자훈련', '제자 양육 훈련 과정', 3, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'education_type', '성경공부', '성경공부', '성경 공부 및 연구', 4, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'education_type', '리더교육', '리더교육', '리더 양성 교육', 5, 1, 0);

-- ----------------------------------------------------------------
-- 봉사기준
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'service_type', '찬양팀', '찬양팀', '예배 찬양 봉사', 1, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'service_type', '안내', '안내', '예배 안내 봉사', 2, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'service_type', '미디어', '미디어', '영상 및 음향 봉사', 3, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'service_type', '차량', '차량', '교회 차량 봉사', 4, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'service_type', '교사', '교사', '교육부 교사 봉사', 5, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'service_type', '식당봉사', '식당봉사', '식사 준비 및 배식 봉사', 6, 1, 0);

-- ----------------------------------------------------------------
-- 심방기준
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'visit_type', '전화심방', '전화심방', '전화를 통한 심방', 1, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'visit_type', '대심방', '대심방', '가정 방문 심방', 2, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'visit_type', '환우', '환우', '병원 환자 심방', 3, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'visit_type', '취업', '취업', '취업 축하 심방', 4, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'visit_type', '출산', '출산', '출산 축하 심방', 5, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'visit_type', '개업', '개업', '개업 축하 심방', 6, 1, 0);

-- ----------------------------------------------------------------
-- 출석상태
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'attendance_status', 'present', '출석', '정상 출석', 1, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'attendance_status', 'late', '지각', '예배 중간 입장', 2, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'attendance_status', 'absent', '결석', '미출석', 3, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'attendance_status', 'excused', '병결', '질병으로 인한 결석', 4, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (1, 'attendance_status', 'other', '기타', '기타 사유', 5, 1, 0);

-- ----------------------------------------------------------------
-- 예배/모임 기본값 (reg_services)
-- ⚠️  church_id = 1 부분을 실제 교회 ID로 교체 후 실행
-- day_of_week: 0=일, 1=월, 2=화, 3=수, 4=목, 5=금, 6=토, NULL=매일
-- ----------------------------------------------------------------
INSERT IGNORE INTO `reg_services` (`church_id`, `name`, `day_of_week`, `sort_order`, `is_active`) VALUES (1, '주일 1부', 0, 1, 1);
INSERT IGNORE INTO `reg_services` (`church_id`, `name`, `day_of_week`, `sort_order`, `is_active`) VALUES (1, '주일 2부', 0, 2, 1);
INSERT IGNORE INTO `reg_services` (`church_id`, `name`, `day_of_week`, `sort_order`, `is_active`) VALUES (1, '수요예배', 3, 3, 1);
INSERT IGNORE INTO `reg_services` (`church_id`, `name`, `day_of_week`, `sort_order`, `is_active`) VALUES (1, '금요기도', 5, 4, 1);
INSERT IGNORE INTO `reg_services` (`church_id`, `name`, `day_of_week`, `sort_order`, `is_active`) VALUES (1, '새벽기도', NULL, 5, 1);
INSERT IGNORE INTO `reg_services` (`church_id`, `name`, `day_of_week`, `sort_order`, `is_active`) VALUES (1, '청년예배', 0, 6, 1);
INSERT IGNORE INTO `reg_services` (`church_id`, `name`, `day_of_week`, `sort_order`, `is_active`) VALUES (1, '교육부예배', 0, 7, 1);

-- ================================================================
-- 3. 교회 설정 (reg_church_settings)
-- KPI 기준, 자동화 규칙 등 교회 단위 설정값 저장 (key-value JSON)
-- ================================================================
CREATE TABLE IF NOT EXISTS `reg_church_settings` (
    `id`            BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`     BIGINT       NOT NULL COMMENT '교회 ID',
    `setting_key`   VARCHAR(100) NOT NULL COMMENT '설정 키 (kpi_attendance_calc 등)',
    `setting_value` TEXT         NULL     COMMENT '설정값 (문자열 또는 JSON)',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_church_setting` (`church_id`, `setting_key`),
    KEY `idx_church_id` (`church_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='교회 단위 설정값 (KPI 기준, 자동화 규칙 등)';

-- ----------------------------------------------------------------
-- reg_organizations 에 description 컬럼 추가
-- ⚠️  이미 컬럼이 있으면 오류가 나므로 한 번만 실행할 것
-- ----------------------------------------------------------------
ALTER TABLE `reg_organizations`
    ADD COLUMN `description` VARCHAR(200) NULL COMMENT '조직 설명' AFTER `name`;

-- ----------------------------------------------------------------
-- reg_services 에 category / start_time 컬럼 추가
-- ⚠️  이미 컬럼이 있으면 오류가 나므로 한 번만 실행할 것
-- ----------------------------------------------------------------
ALTER TABLE `reg_services`
    ADD COLUMN `category`   VARCHAR(20) NOT NULL DEFAULT 'regular' COMMENT '예배 분류 (regular=정기예배, other=기타예배)' AFTER `name`,
    ADD COLUMN `start_time` TIME        NULL     COMMENT '예배 시작 시간' AFTER `day_of_week`;

-- 기존 INSERT된 정기예배 데이터에 시작시간 업데이트 (church_id 교체 필요)
UPDATE `reg_services` SET `start_time` = '09:00' WHERE `church_id` = 1 AND `name` = '주일 1부';
UPDATE `reg_services` SET `start_time` = '11:00' WHERE `church_id` = 1 AND `name` = '주일 2부';
UPDATE `reg_services` SET `start_time` = '19:30' WHERE `church_id` = 1 AND `name` = '수요예배';
UPDATE `reg_services` SET `start_time` = '20:00' WHERE `church_id` = 1 AND `name` = '금요기도';
UPDATE `reg_services` SET `start_time` = '05:30' WHERE `church_id` = 1 AND `name` = '새벽기도';
UPDATE `reg_services` SET `start_time` = '14:00' WHERE `church_id` = 1 AND `name` = '청년예배';
UPDATE `reg_services` SET `start_time` = '11:00' WHERE `church_id` = 1 AND `name` = '교육부예배';
