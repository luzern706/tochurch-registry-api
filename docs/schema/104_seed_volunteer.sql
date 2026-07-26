-- ============================================================
-- 104_seed_volunteer.sql
-- 봉사 관리 (reg_service_teams, reg_service_members) 테스트 데이터
--
-- 대상 church_id : 33632
-- 사용 교인 ID   : 4 ~ 35 (reg_members 기존 등록 교인, 101_seed_registry_full.sql 선행 필요)
--
-- 커버하는 화면/상태 시나리오
--   ServiceStatus (봉사현황) : 부족(팀1,5) / 충족(팀3,4,7) / 모집중·미배정(팀6) / 종료(팀8)
--   ServiceRegister (배정탭) : 활동중 봉사자 + 해제된(inactive) 봉사자 혼재(팀2,7)
--   ServiceHistory  (교인중심): member_id=6 이 팀5(활동중)·팀7(해제됨) 두 곳에 이력 보유
--   ServiceHistory  (봉사중심): 팀8(종료) 참여자 이력 조회
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 0. CLEAN (재적재 시에만 주석 해제)
-- ------------------------------------------------------------
-- DELETE FROM reg_service_members WHERE service_team_id IN (SELECT id FROM reg_service_teams WHERE church_id = 33632);
-- DELETE FROM reg_service_teams WHERE church_id = 33632;

-- ============================================================
-- 1. reg_service_teams (봉사 팀 8개)
-- ============================================================
INSERT IGNORE INTO reg_service_teams
    (id, church_id, name, description, service_type, mode, frequency, day_of_week, service_time,
     event_date, start_time, end_time, required_count, manager_member_id, participation_type, is_active)
VALUES
(1, 33632, '주일 주차 안내',     '주일 예배 시간에 교회 주차장 안내를 도와주세요',   'parking', 'regular', 'weekly',   0, '08:30:00', NULL,         NULL,       NULL,       6,  8,  'assignment',  1),
(2, 33632, '찬양대 반주',         '주일 예배 찬양대 반주 봉사',                        'worship', 'regular', 'weekly',   0, '10:00:00', NULL,         NULL,       NULL,       2,  5,  'application', 1),
(3, 33632, '주일 환경 정리',     '예배 후 본당·로비 환경 정리',                       'worship', 'regular', 'weekly',   0, '13:00:00', NULL,         NULL,       NULL,       3,  9,  'assignment',  1),
(4, 33632, '어린이부 간식 준비', '유초등부 예배 간식 준비 및 배분',                   'dining',  'regular', 'weekly',   0, '09:00:00', NULL,         NULL,       NULL,       4,  14, 'application', 1),
(5, 33632, '부활절 특송팀',       '부활절 연합예배 특별 찬양 준비',                   'special', 'onetime', NULL,       NULL, NULL,     '2026-04-05', '10:00:00', '11:30:00', 8,  10, 'assignment',  1),
(6, 33632, '겨울수련회 준비',     '겨울 수련회 준비 및 진행 지원',                     'event',   'onetime', NULL,       NULL, NULL,     '2026-01-20', '09:00:00', '18:00:00', 10, 11, 'application', 1),
(7, 33632, '주일학교 교사',       '유초등부 주일학교 반 교사',                         'worship', 'regular', 'weekly',   0, '09:30:00', NULL,         NULL,       NULL,       5,  15, 'assignment',  1),
(8, 33632, '여름 수련회 준비',     '2025년 여름 수련회 준비 (종료)',                   'event',   'onetime', NULL,       NULL, NULL,     '2025-08-01', '09:00:00', '17:00:00', 6,  12, 'application', 0);

-- ============================================================
-- 2. reg_service_members (참여자 매핑 30건)
-- ============================================================
INSERT IGNORE INTO reg_service_members
    (id, service_team_id, member_id, role, joined_at, status)
VALUES
-- ── 팀1 주일 주차 안내 (4/6명, 부족 2명) ─────────────────────
(1,  1, 16, '안내', '2026-01-05', 'active'),
(2,  1, 17, '안내', '2026-01-05', 'active'),
(3,  1, 18, '안내', '2026-01-05', 'active'),
(4,  1, 19, '안내', '2026-01-05', 'active'),

-- ── 팀2 찬양대 반주 (1/2명, 활동중 1 + 해제 1 — 배정탭 해제 이력 테스트) ─
(5,  2, 20, '피아노', '2026-01-05', 'active'),
(6,  2, 21, '드럼',   '2025-06-01', 'inactive'),

-- ── 팀3 주일 환경 정리 (3/3명, 충족) ─────────────────────────
(7,  3, 22, NULL, '2025-06-01', 'active'),
(8,  3, 23, NULL, '2025-06-01', 'active'),
(9,  3, 24, NULL, '2025-06-01', 'active'),

-- ── 팀4 어린이부 간식 준비 (4/4명, 충족) ─────────────────────
(10, 4, 25, '간식', '2025-03-01', 'active'),
(11, 4, 26, '간식', '2025-03-01', 'active'),
(12, 4, 27, '간식', '2025-03-01', 'active'),
(13, 4, 28, '간식', '2025-03-01', 'active'),

-- ── 팀5 부활절 특송팀 (5/8명, 부족 3명) — member 6 활동중 ────
(14, 5, 6,  '찬양', '2026-03-01', 'active'),
(15, 5, 7,  '찬양', '2026-03-01', 'active'),
(16, 5, 29, '찬양', '2026-03-01', 'active'),
(17, 5, 30, '찬양', '2026-03-01', 'active'),
(18, 5, 31, '찬양', '2026-03-01', 'active'),

-- ── 팀6 겨울수련회 준비 (0/10명, 미배정 — 모집중 상태 테스트) ─
-- (참여자 없음)

-- ── 팀7 주일학교 교사 (5/5명 충족, member 6 이 과거 교사로 해제 이력) ─
(19, 7, 32, '교사', '2025-09-01', 'active'),
(20, 7, 33, '교사', '2025-09-01', 'active'),
(21, 7, 34, '교사', '2025-09-01', 'active'),
(22, 7, 35, '교사', '2025-09-01', 'active'),
(23, 7, 13, '교사', '2025-09-01', 'active'),
(24, 7, 6,  '교사', '2024-01-01', 'inactive'),

-- ── 팀8 여름 수련회 준비 (종료된 팀, 참여 이력 6명) ──────────
(25, 8, 4,  NULL, '2025-07-01', 'active'),
(26, 8, 5,  NULL, '2025-07-01', 'active'),
(27, 8, 9,  NULL, '2025-07-01', 'active'),
(28, 8, 10, NULL, '2025-07-01', 'active'),
(29, 8, 11, NULL, '2025-07-01', 'active'),
(30, 8, 12, NULL, '2025-07-01', 'active');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 확인 쿼리
-- ============================================================
-- SELECT t.id, t.name, t.required_count, t.is_active,
--        COUNT(CASE WHEN sm.status='active' THEN 1 END) AS 활동중인원
-- FROM reg_service_teams t
-- LEFT JOIN reg_service_members sm ON sm.service_team_id = t.id
-- WHERE t.church_id = 33632
-- GROUP BY t.id ORDER BY t.id;

-- SELECT sm.member_id, m.name, COUNT(*) AS 참여봉사수
-- FROM reg_service_members sm JOIN reg_members m ON m.id = sm.member_id
-- GROUP BY sm.member_id ORDER BY sm.member_id;
