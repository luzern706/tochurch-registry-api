-- ============================================================
-- 107_seed_status_codes_dev.sql
-- dev 서버용 — church_id=19715 (행복샘교회 2)
-- local(church_id=33632)에 적용한 107_seed_status_codes.sql과 동일 데이터,
-- church_id만 치환 (106_seed_code_settings_dev.sql과 동일 관례).
-- ============================================================

USE gyohyero;

-- ------------------------------------------------------------
-- 1. 코드 그룹 추가 (기존 7개 다음 sort_order부터)
-- ------------------------------------------------------------
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (19715, 'member_status',    '교인상태', 8, 1);
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (19715, 'education_status',  '교육상태', 9, 1);
INSERT IGNORE INTO `reg_code_groups` (`church_id`, `group_key`, `group_label`, `sort_order`, `is_system`) VALUES (19715, 'service_status',    '봉사상태', 10, 1);

-- ------------------------------------------------------------
-- 2. 교인 상태 (member_status) — '출석'은 시스템 기본값(삭제 불가)
-- ------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'member_status', '출석',     '출석',     '정상 출석 중인 교인', 1, 1, 1);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'member_status', '장기결석', '장기결석', '장기간 결석 중인 교인', 2, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'member_status', '이명',     '이명',     '타 교회로 이명한 교인', 3, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'member_status', '타교회',   '타교회',   '타 교회 소속 교인', 4, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'member_status', '사망',     '사망',     '소천한 교인', 5, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'member_status', '미분류',   '미분류',   '상태 미분류 교인', 6, 1, 0);

-- ------------------------------------------------------------
-- 3. 교육 상태 (education_status)
-- ------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'education_status', '참여',   '참여',   '교육 과정 참여 중', 1, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'education_status', '수료',   '수료',   '교육 과정 수료 완료', 2, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'education_status', '미수료', '미수료', '교육 과정 미수료', 3, 1, 0);

-- ------------------------------------------------------------
-- 4. 봉사 상태 (service_status)
-- ------------------------------------------------------------
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'service_status', '활동', '활동', '봉사 활동 중', 1, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'service_status', '휴식', '휴식', '봉사 휴식 중', 2, 1, 0);
INSERT IGNORE INTO `reg_codes` (`church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`) VALUES (19715, 'service_status', '종료', '종료', '봉사 종료됨', 3, 1, 0);

-- ------------------------------------------------------------
-- 확인 쿼리
-- ------------------------------------------------------------
-- SELECT group_key, COUNT(*) FROM reg_codes WHERE church_id = 19715 AND group_key IN ('member_status','education_status','service_status') GROUP BY group_key;
-- 기대값: member_status=6, education_status=3, service_status=3
