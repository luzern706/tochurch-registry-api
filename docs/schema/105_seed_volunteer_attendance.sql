-- ============================================================
-- 105_seed_volunteer_attendance.sql
-- 봉사 참여 출결 테스트 데이터 (reg_service_attendance)
--
-- 대상 church_id : 33632 (104_seed_volunteer.sql 선행 필요 — 팀 8건/매핑 30건)
-- 봉사이력 화면의 "참여 횟수"/"최근 참여일"/"참여 기간" 이 실제 값으로 채워지도록
-- 팀별로 정기 일정(주간)에 맞춰 여러 회차 출결을 기록합니다.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 팀1 주일 주차 안내 (정기·주간, 멤버 16,17,18,19) — 6회 ──────
INSERT IGNORE INTO reg_service_attendance (service_team_id, member_id, service_date, status) VALUES
(1, 16, '2026-01-11', 'present'), (1, 17, '2026-01-11', 'present'), (1, 18, '2026-01-11', 'present'), (1, 19, '2026-01-11', 'present'),
(1, 16, '2026-01-18', 'present'), (1, 17, '2026-01-18', 'present'), (1, 18, '2026-01-18', 'absent'),  (1, 19, '2026-01-18', 'present'),
(1, 16, '2026-01-25', 'present'), (1, 17, '2026-01-25', 'late'),    (1, 18, '2026-01-25', 'present'), (1, 19, '2026-01-25', 'present'),
(1, 16, '2026-02-01', 'present'), (1, 17, '2026-02-01', 'present'), (1, 18, '2026-02-01', 'present'), (1, 19, '2026-02-01', 'absent'),
(1, 16, '2026-02-08', 'present'), (1, 17, '2026-02-08', 'present'), (1, 18, '2026-02-08', 'present'), (1, 19, '2026-02-08', 'present'),
(1, 16, '2026-02-15', 'present'), (1, 17, '2026-02-15', 'present'), (1, 18, '2026-02-15', 'present'), (1, 19, '2026-02-15', 'present');

-- ── 팀2 찬양대 반주 (member 20 활동중 / member 21 해제 — 해제 전 이력 포함) — 5회 ──
INSERT IGNORE INTO reg_service_attendance (service_team_id, member_id, service_date, status) VALUES
(2, 20, '2026-01-11', 'present'), (2, 21, '2026-01-11', 'present'),
(2, 20, '2026-01-18', 'present'), (2, 21, '2026-01-18', 'present'),
(2, 20, '2026-01-25', 'present'), (2, 21, '2026-01-25', 'absent'),
(2, 20, '2026-02-01', 'late'),
(2, 20, '2026-02-08', 'present');

-- ── 팀3 주일 환경 정리 (정기·주간, 멤버 22,23,24) — 4회 ──────────
INSERT IGNORE INTO reg_service_attendance (service_team_id, member_id, service_date, status) VALUES
(3, 22, '2026-06-07', 'present'), (3, 23, '2026-06-07', 'present'), (3, 24, '2026-06-07', 'present'),
(3, 22, '2026-06-14', 'present'), (3, 23, '2026-06-14', 'absent'),  (3, 24, '2026-06-14', 'present'),
(3, 22, '2026-06-21', 'present'), (3, 23, '2026-06-21', 'present'), (3, 24, '2026-06-21', 'late'),
(3, 22, '2026-06-28', 'present'), (3, 23, '2026-06-28', 'present'), (3, 24, '2026-06-28', 'present');

-- ── 팀4 어린이부 간식 준비 (정기·주간, 멤버 25,26,27,28) — 3회 ──
INSERT IGNORE INTO reg_service_attendance (service_team_id, member_id, service_date, status) VALUES
(4, 25, '2026-03-08', 'present'), (4, 26, '2026-03-08', 'present'), (4, 27, '2026-03-08', 'present'), (4, 28, '2026-03-08', 'present'),
(4, 25, '2026-03-15', 'present'), (4, 26, '2026-03-15', 'present'), (4, 27, '2026-03-15', 'present'), (4, 28, '2026-03-15', 'absent'),
(4, 25, '2026-03-22', 'present'), (4, 26, '2026-03-22', 'late'),    (4, 27, '2026-03-22', 'present'), (4, 28, '2026-03-22', 'present');

-- ── 팀5 부활절 특송팀 (일회성, event_date=2026-04-05, 멤버 6,7,29,30,31) — 1회 ──
INSERT IGNORE INTO reg_service_attendance (service_team_id, member_id, service_date, status) VALUES
(5, 6,  '2026-04-05', 'present'),
(5, 7,  '2026-04-05', 'present'),
(5, 29, '2026-04-05', 'present'),
(5, 30, '2026-04-05', 'late'),
(5, 31, '2026-04-05', 'present');

-- ── 팀6 겨울수련회 준비 (참여자 없음 — 출결도 없음, 모집중 상태 그대로) ──
-- (기록 없음)

-- ── 팀7 주일학교 교사 (정기·주간, 멤버 32,33,34,35,13 활동중 / 6 해제) — 8회 ──
INSERT IGNORE INTO reg_service_attendance (service_team_id, member_id, service_date, status) VALUES
(7, 6,  '2024-01-07', 'present'), (7, 6,  '2024-01-14', 'present'), (7, 6, '2024-01-21', 'absent'),
(7, 32, '2026-05-03', 'present'), (7, 33, '2026-05-03', 'present'), (7, 34, '2026-05-03', 'present'), (7, 35, '2026-05-03', 'present'), (7, 13, '2026-05-03', 'present'),
(7, 32, '2026-05-10', 'present'), (7, 33, '2026-05-10', 'late'),    (7, 34, '2026-05-10', 'present'), (7, 35, '2026-05-10', 'present'), (7, 13, '2026-05-10', 'absent'),
(7, 32, '2026-05-17', 'present'), (7, 33, '2026-05-17', 'present'), (7, 34, '2026-05-17', 'present'), (7, 35, '2026-05-17', 'present'), (7, 13, '2026-05-17', 'present'),
(7, 32, '2026-05-24', 'present'), (7, 33, '2026-05-24', 'present'), (7, 34, '2026-05-24', 'absent'),  (7, 35, '2026-05-24', 'present'), (7, 13, '2026-05-24', 'present'),
(7, 32, '2026-05-31', 'present'), (7, 33, '2026-05-31', 'present'), (7, 34, '2026-05-31', 'present'), (7, 35, '2026-05-31', 'present'), (7, 13, '2026-05-31', 'present'),
(7, 32, '2026-06-07', 'present'), (7, 33, '2026-06-07', 'present'), (7, 34, '2026-06-07', 'present'), (7, 35, '2026-06-07', 'late'),    (7, 13, '2026-06-07', 'present'),
(7, 32, '2026-06-14', 'present'), (7, 33, '2026-06-14', 'present'), (7, 34, '2026-06-14', 'present'), (7, 35, '2026-06-14', 'present'), (7, 13, '2026-06-14', 'present');

-- ── 팀8 여름 수련회 준비 (일회성·종료, event_date=2025-08-01, 멤버 4,5,9,10,11,12) — 1회, 전원 출석(완료) ──
INSERT IGNORE INTO reg_service_attendance (service_team_id, member_id, service_date, status) VALUES
(8, 4,  '2025-08-01', 'present'),
(8, 5,  '2025-08-01', 'present'),
(8, 9,  '2025-08-01', 'present'),
(8, 10, '2025-08-01', 'present'),
(8, 11, '2025-08-01', 'present'),
(8, 12, '2025-08-01', 'present');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 확인 쿼리
-- ============================================================
-- SELECT service_team_id, member_id,
--        SUM(status IN ('present','late')) AS 참여횟수,
--        MAX(CASE WHEN status IN ('present','late') THEN service_date END) AS 최근참여일
-- FROM reg_service_attendance
-- GROUP BY service_team_id, member_id
-- ORDER BY service_team_id, member_id;
