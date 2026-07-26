-- ============================================================
-- 106_seed_code_settings_dev.sql
-- dev 서버용 — church_id=19715 (행복샘교회 2)
--
-- local DB(church_id=33632)의 reg_church_settings / reg_code_groups / reg_codes
-- 실 데이터를 그대로 덤프하여 church_id만 19715로 치환한 것 (id 보존, mysqldump 기반).
-- 이 3개 테이블은 "테스트 시드"가 아니라 앱이 정상 동작하기 위한 필수 참조/설정
-- 데이터(코드 그룹·코드값·교회별 KPI/자동화 설정)이므로, local 전용 원본 파일은 없음 —
-- 이 파일이 곧 dev 반영용 소스.
--
-- 포함 데이터: reg_church_settings 7건, reg_code_groups 7건, reg_codes 49건
-- 실행 순서: reg_church_settings/reg_code_groups → reg_codes (그룹이 코드보다 먼저,
--   group_key 로만 연결되는 논리적 FK라 순서가 깨져도 INSERT 자체는 실패하지 않지만
--   맞춰서 실행할 것)
--
-- ⚠️ id 값을 원본 그대로 보존함 — dev DB의 해당 테이블에 이미 같은 id 가 있으면
--    INSERT IGNORE 로 조용히 스킵된다. 재적재가 필요하면 아래 CLEAN 블록을 먼저 실행할 것.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 0. CLEAN (재적재 시에만 주석 해제)
-- ------------------------------------------------------------
-- DELETE FROM reg_codes WHERE church_id = 19715;
-- DELETE FROM reg_code_groups WHERE church_id = 19715;
-- DELETE FROM reg_church_settings WHERE church_id = 19715;

-- ============================================================
-- 1. reg_church_settings (7건)
-- ============================================================
INSERT IGNORE INTO `reg_church_settings` (`id`, `church_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES (1,19715,'kpi_attendance_calc','weekly','2026-06-28 14:30:27','2026-06-28 05:30:46');
INSERT IGNORE INTO `reg_church_settings` (`id`, `church_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES (2,19715,'kpi_education_calc','completion','2026-06-28 14:30:27','2026-06-28 05:30:46');
INSERT IGNORE INTO `reg_church_settings` (`id`, `church_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES (3,19715,'kpi_service_calc','all','2026-06-28 14:30:27','2026-06-28 05:30:46');
INSERT IGNORE INTO `reg_church_settings` (`id`, `church_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES (4,19715,'auto_new_member','{\"enabled\":true,\"days\":90}','2026-06-28 14:31:24','2026-06-28 05:33:50');
INSERT IGNORE INTO `reg_church_settings` (`id`, `church_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES (5,19715,'auto_long_absent','{\"enabled\":true,\"weeks\":4,\"auto_status\":true,\"create_visit\":true}','2026-06-28 14:31:24','2026-06-28 05:33:50');
INSERT IGNORE INTO `reg_church_settings` (`id`, `church_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES (6,19715,'auto_education','{\"enabled\":true,\"courses\":[\"새가족교육\",\"기초신앙\"]}','2026-06-28 14:31:24','2026-06-28 05:33:50');
INSERT IGNORE INTO `reg_church_settings` (`id`, `church_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES (7,19715,'auto_visit_target','{\"enabled\":true,\"triggers\":[\"장기결석\",\"환우\",\"경조사\"]}','2026-06-28 14:31:24','2026-06-28 05:33:50');

-- ============================================================
-- 2. reg_code_groups (7건)
-- ============================================================
INSERT IGNORE INTO `reg_code_groups` (`id`, `church_id`, `group_key`, `group_label`, `sort_order`, `is_system`, `created_at`) VALUES (1,19715,'member_type','교인구분',1,1,'2026-06-28 13:37:29');
INSERT IGNORE INTO `reg_code_groups` (`id`, `church_id`, `group_key`, `group_label`, `sort_order`, `is_system`, `created_at`) VALUES (2,19715,'position','직분유형',2,1,'2026-06-28 13:37:29');
INSERT IGNORE INTO `reg_code_groups` (`id`, `church_id`, `group_key`, `group_label`, `sort_order`, `is_system`, `created_at`) VALUES (3,19715,'relation','관계유형',3,1,'2026-06-28 13:37:29');
INSERT IGNORE INTO `reg_code_groups` (`id`, `church_id`, `group_key`, `group_label`, `sort_order`, `is_system`, `created_at`) VALUES (4,19715,'education_type','교육기준',4,1,'2026-06-28 13:37:29');
INSERT IGNORE INTO `reg_code_groups` (`id`, `church_id`, `group_key`, `group_label`, `sort_order`, `is_system`, `created_at`) VALUES (5,19715,'service_type','봉사기준',5,1,'2026-06-28 13:37:29');
INSERT IGNORE INTO `reg_code_groups` (`id`, `church_id`, `group_key`, `group_label`, `sort_order`, `is_system`, `created_at`) VALUES (6,19715,'visit_type','심방기준',6,1,'2026-06-28 13:37:29');
INSERT IGNORE INTO `reg_code_groups` (`id`, `church_id`, `group_key`, `group_label`, `sort_order`, `is_system`, `created_at`) VALUES (7,19715,'attendance_status','출석상태',7,1,'2026-06-28 13:37:29');

-- ============================================================
-- 3. reg_codes (49건)
-- ============================================================
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (1,19715,'member_type','새가족','새가족','첫 방문 및 등록 대기 교인',1,1,1,'2026-06-28 13:38:21','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (2,19715,'member_type','등록교인','등록교인','정식 등록된 교인',2,1,1,'2026-06-28 13:38:21','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (3,19715,'member_type','세례교인','세례교인','세례를 받은 교인',3,1,1,'2026-06-28 13:38:21','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (4,19715,'member_type','입교인','입교인','입교한 교인',4,1,1,'2026-06-28 13:38:21','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (5,19715,'member_type','학습교인','학습교인','학습 중인 교인',5,1,1,'2026-06-28 13:38:21','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (6,19715,'member_type','유아세례','유아세례','유아세례를 받은 교인',6,1,1,'2026-06-28 13:38:21','2026-06-28 13:47:11');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (7,19715,'member_type','협동교인','협동교인','협동 교인',7,0,0,'2026-06-28 13:38:21','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (8,19715,'member_type','온라인교인','온라인교인','온라인으로 참여하는 교인',8,0,0,'2026-06-28 13:38:21','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (9,19715,'member_type','기타','기타','기타 구분',9,1,0,'2026-06-28 13:38:21','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (10,19715,'position','성도','성도','일반 성도',1,1,1,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (11,19715,'position','집사(서리)','집사(서리)','서리집사',2,1,1,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (12,19715,'position','집사(안수)','집사(안수)','안수집사',3,1,1,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (13,19715,'position','권사','권사','권사직',4,1,1,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (14,19715,'position','장로','장로','장로직',5,1,1,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (15,19715,'position','목사','목사','목사',6,1,1,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (16,19715,'position','부목사','부목사','부목사',7,1,0,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (17,19715,'position','교역자','교역자','교역자',8,1,0,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (18,19715,'position','사역자','사역자','사역자',9,1,0,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (19,19715,'position','봉사자','봉사자','봉사자',10,1,0,'2026-06-28 13:38:30','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (20,19715,'relation','세대주','세대주','가족의 대표 세대주',1,1,1,'2026-06-28 13:38:39','2026-06-28 13:54:01');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (21,19715,'relation','배우자','배우자','세대주의 배우자',2,1,1,'2026-06-28 13:38:39','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (22,19715,'relation','자녀','자녀','세대주의 자녀',3,1,1,'2026-06-28 13:38:39','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (23,19715,'relation','부','부','세대주의 아버지',4,1,1,'2026-06-28 13:38:39','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (24,19715,'relation','모','모','세대주의 어머니',5,1,1,'2026-06-28 13:38:39','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (25,19715,'relation','형제','형제','세대주의 형제',6,1,0,'2026-06-28 13:38:39','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (26,19715,'relation','자매','자매','세대주의 자매',7,1,0,'2026-06-28 13:38:39','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (27,19715,'relation','기타','기타','기타 관계',8,1,0,'2026-06-28 13:38:39','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (28,19715,'education_type','새가족교육','새가족 교육','새가족을 위한 기초 교육',1,1,1,'2026-06-28 13:39:06','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (29,19715,'education_type','기초신앙','기초신앙','기초 신앙 교육 과정',2,1,1,'2026-06-28 13:39:06','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (30,19715,'education_type','제자훈련','제자훈련','제자 양육 훈련 과정',3,1,1,'2026-06-28 13:39:06','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (31,19715,'education_type','성경공부','성경공부','성경 공부 및 연구',4,1,1,'2026-06-28 13:39:06','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (32,19715,'education_type','리더교육','리더교육','리더 양성 교육',5,1,0,'2026-06-28 13:39:06','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (33,19715,'service_type','찬양팀','찬양팀','예배 찬양 봉사',1,1,1,'2026-06-28 13:39:18','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (34,19715,'service_type','안내','안내','예배 안내 봉사',2,1,1,'2026-06-28 13:39:18','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (35,19715,'service_type','미디어','미디어','영상 및 음향 봉사',3,1,1,'2026-06-28 13:39:18','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (36,19715,'service_type','차량','차량','교회 차량 봉사',4,1,1,'2026-06-28 13:39:18','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (37,19715,'service_type','교사','교사','교육부 교사 봉사',5,1,1,'2026-06-28 13:39:18','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (38,19715,'service_type','식당봉사','식당봉사','식사 준비 및 배식 봉사',6,1,0,'2026-06-28 13:39:18','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (39,19715,'visit_type','전화심방','전화심방','전화를 통한 심방',1,1,1,'2026-06-28 13:39:26','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (40,19715,'visit_type','대심방','대심방','가정 방문 심방',2,1,1,'2026-06-28 13:39:26','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (41,19715,'visit_type','환우','환우','병원 환자 심방',3,1,1,'2026-06-28 13:39:26','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (42,19715,'visit_type','취업','취업','취업 축하 심방',4,1,0,'2026-06-28 13:39:26','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (43,19715,'visit_type','출산','출산','출산 축하 심방',5,1,0,'2026-06-28 13:39:26','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (44,19715,'visit_type','개업','개업','개업 축하 심방',6,1,0,'2026-06-28 13:39:26','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (45,19715,'attendance_status','present','출석','정상 출석',1,1,1,'2026-06-28 13:39:35','2026-06-28 14:06:35');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (46,19715,'attendance_status','late','지각','예배 중간 입장',2,1,1,'2026-06-28 13:39:35','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (47,19715,'attendance_status','absent','결석','미출석',3,1,1,'2026-06-28 13:39:35','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (48,19715,'attendance_status','excused','병결','질병으로 인한 결석',4,1,0,'2026-06-28 13:39:35','2026-06-28 13:44:59');
INSERT IGNORE INTO `reg_codes` (`id`, `church_id`, `group_key`, `code_value`, `code_label`, `description`, `sort_order`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES (49,19715,'attendance_status','other','기타','기타 사유',5,1,0,'2026-06-28 13:39:35','2026-06-28 13:44:59');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 확인 쿼리
-- ============================================================
-- SELECT 'reg_church_settings' t, COUNT(*) c FROM reg_church_settings WHERE church_id = 19715
-- UNION ALL SELECT 'reg_code_groups', COUNT(*) FROM reg_code_groups WHERE church_id = 19715
-- UNION ALL SELECT 'reg_codes', COUNT(*) FROM reg_codes WHERE church_id = 19715;
-- 기대값: reg_church_settings=7, reg_code_groups=7, reg_codes=49
