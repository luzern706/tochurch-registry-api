-- dev 서버용 — church_id=19715 (행복샘교회 2). local 버전은 102_seed_visit_records.sql(church_id=33632) 참고.
SET @church_id = 19715;
SET @visitor   = 1;
SET @manager   = 2;

INSERT INTO reg_visit_records
(church_id, member_id, visit_type, event_reason, visit_date, visitor_member_id, manager_member_id, place, content, status, add_to_prayer, created_by)
VALUES

-- ── 완료 (2026-06-25 ~ 2026-06-27) ──────────────────────────
(19715, 3,  'annual', NULL,         '2026-06-25', 1, 2, '교인 자택',     '가정예배 드림. 건강하고 신앙생활 잘하고 있음.',           'completed', 0, 1),
(19715, 4,  'event',  'hospital',   '2026-06-25', 1, 2, '한양대병원',    '허리 수술 후 회복 중. 말씀 전하고 기도함.',               'completed', 1, 1),
(19715, 5,  'annual', NULL,         '2026-06-26', 1, 2, '교인 자택',     '부부 함께 만남. 가정 화목하고 신앙 잘 유지하고 있음.',     'completed', 0, 1),
(19715, 6,  'event',  'new_member', '2026-06-26', 1, 2, '교회 카페',     '새신자 방문. 교회 적응 잘하고 있음. 소그룹 참여 권유.',    'completed', 0, 1),
(19715, 7,  'event',  'birthday',   '2026-06-27', 1, 2, '교인 자택',     '생신 축하 방문. 케이크 전달. 기쁘게 맞이하심.',            'completed', 0, 1),
(19715, 8,  'annual', NULL,         '2026-06-27', 1, 2, '교인 자택',     '노부부 만남. 건강 상태 확인. 노인 돌봄 프로그램 안내.',     'completed', 0, 1),

-- ── 미확인 (2026-06-28 ~ 2026-06-29) ─────────────────────────
(19715, 9,  'event',  'moving',     '2026-06-28', 1, 2, '새 거주지',     '이사 축하 방문. 새 환경에 잘 적응하고 있음.',              'scheduled', 0, 1),
(19715, 10, 'annual', NULL,         '2026-06-28', 1, 2, '교인 자택',     '가족 전체 만남. 자녀 진로 고민 상담. 말씀으로 위로.',      'scheduled', 0, 1),
(19715, 3,  'event',  'condolence', '2026-06-29', 1, 2, '장례식장',      '모친상 조문. 위로 말씀 전함. 장례 기간 교회 지원 안내.',   'scheduled', 1, 1),
(19715, 4,  'annual', NULL,         '2026-06-29', 1, 2, '교인 자택',     '독거 노인 방문. 생활 상태 점검. 반찬 나눔 봉사 연결 예정.','scheduled', 0, 1),

-- ── 진행중 (2026-06-30 오늘) ──────────────────────────────────
(19715, 5,  'annual', NULL,         '2026-06-30', 1, 2, '교인 자택',     '오늘 방문 예정. 가정예배 준비 중.',                        'in_progress', 0, 1),
(19715, 6,  'event',  'hospital',   '2026-06-30', 1, 2, '서울아산병원',  '암 투병 중 방문. 기도와 위로 전할 예정.',                  'in_progress', 1, 1),

-- ── 예정 (2026-07-01 ~ 2026-07-10) ───────────────────────────
(19715, 7,  'annual', NULL,         '2026-07-01', 1, 2, '교인 자택',     '7월 대심방 1차 일정.',                                     'scheduled', 0, 1),
(19715, 8,  'event',  'business',   '2026-07-02', 1, 2, '개업 매장',     '새 사업장 개업 축하 방문 예정.',                           'scheduled', 0, 1),
(19715, 9,  'annual', NULL,         '2026-07-03', 1, 2, '교인 자택',     '7월 대심방 2차 일정.',                                     'scheduled', 0, 1),
(19715, 10, 'event',  'new_member', '2026-07-05', 1, 2, '교회 카페',     '새신자 2차 방문 예정. 등록 권유.',                         'scheduled', 0, 1),
(19715, 3,  'event',  'birthday',   '2026-07-07', 1, 2, '교인 자택',     '생신 방문 예정.',                                          'scheduled', 0, 1),
(19715, 4,  'annual', NULL,         '2026-07-10', 1, 2, '교인 자택',     '7월 대심방 마지막 일정.',                                  'scheduled', 0, 1);

-- 결과 확인
SELECT id, member_id, visit_type, event_reason, visit_date, status
FROM reg_visit_records
WHERE church_id = 19715
  AND visit_date BETWEEN '2026-06-25' AND '2026-07-10'
ORDER BY visit_date;
