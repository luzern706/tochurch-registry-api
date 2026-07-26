-- ================================================================
-- 교인 50명 테스트 데이터
-- DB: gyohyero_db
-- ⚠️  실행 전: SET @church_id = 실제교회ID; 로 교체
-- 비밀번호: 모든 교인 test1234 (bcrypt)
-- ================================================================

USE gyohyero_db;

SET @church_id = 1;  -- ← 실제 church_id 로 교체

-- ================================================================
-- 1. 조직 5개 (이미 있으면 IGNORE)
-- ================================================================
INSERT IGNORE INTO `reg_organizations` (`church_id`, `name`, `description`, `sort_order`, `is_active`)
VALUES (@church_id, '남전도회', '남성 전도 모임',   1, 1);
INSERT IGNORE INTO `reg_organizations` (`church_id`, `name`, `description`, `sort_order`, `is_active`)
VALUES (@church_id, '여전도회', '여성 전도 모임',   2, 1);
INSERT IGNORE INTO `reg_organizations` (`church_id`, `name`, `description`, `sort_order`, `is_active`)
VALUES (@church_id, '청년부',   '청년 예배 모임',   3, 1);
INSERT IGNORE INTO `reg_organizations` (`church_id`, `name`, `description`, `sort_order`, `is_active`)
VALUES (@church_id, '교육부',   '아동·주일학교',   4, 1);
INSERT IGNORE INTO `reg_organizations` (`church_id`, `name`, `description`, `sort_order`, `is_active`)
VALUES (@church_id, '구역1',    '1구역 소그룹',    5, 1);

-- 조직 ID 변수
SET @org_men   = (SELECT id FROM reg_organizations WHERE church_id = @church_id AND name = '남전도회' LIMIT 1);
SET @org_women = (SELECT id FROM reg_organizations WHERE church_id = @church_id AND name = '여전도회' LIMIT 1);
SET @org_youth = (SELECT id FROM reg_organizations WHERE church_id = @church_id AND name = '청년부'   LIMIT 1);
SET @org_edu   = (SELECT id FROM reg_organizations WHERE church_id = @church_id AND name = '교육부'   LIMIT 1);
SET @org_cell  = (SELECT id FROM reg_organizations WHERE church_id = @church_id AND name = '구역1'    LIMIT 1);

-- ================================================================
-- 2. reg_members  50명
--    비밀번호 hash: test1234
-- ================================================================
INSERT IGNORE INTO `reg_members`
    (`church_id`,`member_no`,`email`,`password`,`name`,`gender`,`birth_date`,`status`)
VALUES
-- ── 남전도회 (장로·안수집사 위주, 남성) ──────────────────────────
(@church_id,'M-2026001','m001@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','김정호','M','1958-04-12','active'),
(@church_id,'M-2026002','m002@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','박성근','M','1961-09-25','active'),
(@church_id,'M-2026003','m003@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','이재훈','M','1965-02-18','active'),
(@church_id,'M-2026004','m004@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','최동훈','M','1963-11-30','active'),
(@church_id,'M-2026005','m005@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','강태양','M','1959-07-07','active'),
(@church_id,'M-2026006','m006@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','조민석','M','1972-03-21','active'),
(@church_id,'M-2026007','m007@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','윤성준','M','1968-08-14','active'),
(@church_id,'M-2026008','m008@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','한도현','M','1975-05-03','active'),
(@church_id,'M-2026009','m009@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','서재민','M','1970-12-19','active'),
(@church_id,'M-2026010','m010@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','권현우','M','1978-06-08','active'),
-- ── 여전도회 (권사·서리집사 위주, 여성) ──────────────────────────
(@church_id,'M-2026011','m011@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','김순희','F','1960-01-22','active'),
(@church_id,'M-2026012','m012@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','이영숙','F','1957-03-15','active'),
(@church_id,'M-2026013','m013@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','박명자','F','1964-10-04','active'),
(@church_id,'M-2026014','m014@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','최정숙','F','1962-07-27','active'),
(@church_id,'M-2026015','m015@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','정혜경','F','1969-02-11','active'),
(@church_id,'M-2026016','m016@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','강미숙','F','1966-09-30','active'),
(@church_id,'M-2026017','m017@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','조선희','F','1973-04-17','active'),
(@church_id,'M-2026018','m018@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','윤지연','F','1971-11-05','active'),
(@church_id,'M-2026019','m019@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','한수진','F','1975-08-23','active'),
(@church_id,'M-2026020','m020@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','서은미','F','1968-06-14','active'),
-- ── 청년부 (성도·봉사자, 20·30대) ────────────────────────────────
(@church_id,'M-2026021','m021@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','김준서','M','1997-05-12','active'),
(@church_id,'M-2026022','m022@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','이수빈','F','1999-02-28','active'),
(@church_id,'M-2026023','m023@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','박지훈','M','1995-08-16','active'),
(@church_id,'M-2026024','m024@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','최예린','F','2001-11-03','active'),
(@church_id,'M-2026025','m025@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','정민재','M','1998-04-20','active'),
(@church_id,'M-2026026','m026@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','강채원','F','2000-07-09','active'),
(@church_id,'M-2026027','m027@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','조현석','M','1993-01-25','active'),
(@church_id,'M-2026028','m028@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','윤하람','F','1996-10-17','active'),
(@church_id,'M-2026029','m029@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','임도윤','M','1994-03-08','active'),
(@church_id,'M-2026030','m030@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','한소희','F','2002-06-30','active'),
-- ── 교육부 (교사·봉사자, 30·40대) ────────────────────────────────
(@church_id,'M-2026031','m031@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','신성민','M','1985-09-11','active'),
(@church_id,'M-2026032','m032@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','오지혜','F','1987-12-04','active'),
(@church_id,'M-2026033','m033@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','노민준','M','1982-04-28','active'),
(@church_id,'M-2026034','m034@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','유미래','F','1984-07-15','active'),
(@church_id,'M-2026035','m035@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','홍진우','M','1989-02-22','active'),
(@church_id,'M-2026036','m036@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','장수연','F','1986-10-01','active'),
(@church_id,'M-2026037','m037@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','황재현','M','1983-05-19','active'),
(@church_id,'M-2026038','m038@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','문지영','F','1988-08-07','active'),
(@church_id,'M-2026039','m039@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','변성호','M','1991-11-13','active'),
(@church_id,'M-2026040','m040@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','안지민','F','1990-03-26','active'),
-- ── 구역1 (혼합·성도 위주) ────────────────────────────────────────
(@church_id,'M-2026041','m041@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','구민호','M','1980-06-02','active'),
(@church_id,'M-2026042','m042@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','심혜진','F','1979-01-18','active'),
(@church_id,'M-2026043','m043@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','전태균','M','1976-09-07','active'),
(@church_id,'M-2026044','m044@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','곽선미','F','1983-04-14','active'),
(@church_id,'M-2026045','m045@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','마상훈','M','1988-11-29','active'),
(@church_id,'M-2026046','m046@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','연소영','F','1991-07-21','active'),
(@church_id,'M-2026047','m047@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','엄재원','M','1986-02-06','active'),
(@church_id,'M-2026048','m048@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','채은경','F','1977-10-10','active'),
(@church_id,'M-2026049','m049@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','탁성준','M','1993-05-31','active'),
(@church_id,'M-2026050','m050@test.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','봉미선','F','1982-12-24','active');

-- ================================================================
-- 3. reg_member_profiles  직분 배분
--
--  직분 분포 (50명):
--    장로          4명  (001~004)
--    집사(안수)     6명  (005~010)
--    권사          5명  (011~015)
--    집사(서리)     8명  (016~020, 031~033)
--    봉사자         5명  (021~025)
--    성도          20명  (026~030, 034~050)
--    목사           1명  (staff - 없음, 소규모 교회 가정)
--
--  member_type: 등록교인(기본) / 새가족 일부
-- ================================================================
INSERT IGNORE INTO `reg_member_profiles`
    (`member_id`, `member_type`, `position`, `registered_at`)
SELECT m.id,
       CASE
           WHEN m.member_no IN ('M-2026001','M-2026002','M-2026003','M-2026004') THEN '등록교인'
           ELSE '등록교인'
       END,
       CASE m.member_no
           -- 장로 4
           WHEN 'M-2026001' THEN '장로'
           WHEN 'M-2026002' THEN '장로'
           WHEN 'M-2026003' THEN '장로'
           WHEN 'M-2026004' THEN '장로'
           -- 집사(안수) 6
           WHEN 'M-2026005' THEN '집사(안수)'
           WHEN 'M-2026006' THEN '집사(안수)'
           WHEN 'M-2026007' THEN '집사(안수)'
           WHEN 'M-2026008' THEN '집사(안수)'
           WHEN 'M-2026009' THEN '집사(안수)'
           WHEN 'M-2026010' THEN '집사(안수)'
           -- 권사 5
           WHEN 'M-2026011' THEN '권사'
           WHEN 'M-2026012' THEN '권사'
           WHEN 'M-2026013' THEN '권사'
           WHEN 'M-2026014' THEN '권사'
           WHEN 'M-2026015' THEN '권사'
           -- 집사(서리) 8
           WHEN 'M-2026016' THEN '집사(서리)'
           WHEN 'M-2026017' THEN '집사(서리)'
           WHEN 'M-2026018' THEN '집사(서리)'
           WHEN 'M-2026019' THEN '집사(서리)'
           WHEN 'M-2026020' THEN '집사(서리)'
           WHEN 'M-2026031' THEN '집사(서리)'
           WHEN 'M-2026032' THEN '집사(서리)'
           WHEN 'M-2026033' THEN '집사(서리)'
           -- 봉사자 5
           WHEN 'M-2026021' THEN '봉사자'
           WHEN 'M-2026022' THEN '봉사자'
           WHEN 'M-2026023' THEN '봉사자'
           WHEN 'M-2026024' THEN '봉사자'
           WHEN 'M-2026025' THEN '봉사자'
           -- 성도 나머지 22
           ELSE '성도'
       END,
       '2020-01-01'
FROM reg_members m
WHERE m.church_id = @church_id
  AND m.member_no LIKE 'M-2026%';

-- ================================================================
-- 4. reg_member_organizations  소속 배분
-- ================================================================
INSERT IGNORE INTO `reg_member_organizations`
    (`member_id`, `organization_id`, `is_primary`, `joined_at`)
SELECT m.id,
       CASE
           WHEN m.member_no IN ('M-2026001','M-2026002','M-2026003','M-2026004',
                                'M-2026005','M-2026006','M-2026007','M-2026008',
                                'M-2026009','M-2026010') THEN @org_men
           WHEN m.member_no IN ('M-2026011','M-2026012','M-2026013','M-2026014',
                                'M-2026015','M-2026016','M-2026017','M-2026018',
                                'M-2026019','M-2026020') THEN @org_women
           WHEN m.member_no IN ('M-2026021','M-2026022','M-2026023','M-2026024',
                                'M-2026025','M-2026026','M-2026027','M-2026028',
                                'M-2026029','M-2026030') THEN @org_youth
           WHEN m.member_no IN ('M-2026031','M-2026032','M-2026033','M-2026034',
                                'M-2026035','M-2026036','M-2026037','M-2026038',
                                'M-2026039','M-2026040') THEN @org_edu
           ELSE @org_cell
       END,
       1,
       '2020-01-01'
FROM reg_members m
WHERE m.church_id = @church_id
  AND m.member_no LIKE 'M-2026%';

-- ================================================================
-- 확인 쿼리
-- ================================================================
SELECT
    m.member_no,
    m.name,
    m.gender,
    p.position,
    o.name AS org
FROM reg_members m
LEFT JOIN reg_member_profiles   p ON p.member_id = m.id
LEFT JOIN reg_member_organizations mo ON mo.member_id = m.id
LEFT JOIN reg_organizations     o ON o.id = mo.organization_id
WHERE m.church_id = @church_id
  AND m.member_no LIKE 'M-2026%'
ORDER BY m.member_no;

-- ================================================================
-- 직분별 인원 요약
-- ================================================================
SELECT
    COALESCE(p.position, '(미배정)') AS 직분,
    COUNT(*) AS 인원
FROM reg_members m
LEFT JOIN reg_member_profiles p ON p.member_id = m.id
WHERE m.church_id = @church_id
  AND m.member_no LIKE 'M-2026%'
GROUP BY p.position
ORDER BY 인원 DESC;
