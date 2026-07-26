-- ================================================================
-- 봉사 관리 저장 프로시저 (sp_v4_reg_volunteer.sql)
-- prefix: sp_v4_reg_volunteer_
-- 복잡한 JOIN 쿼리만 SP로 처리 (04_sp_v4_reg_education.sql과 동일 원칙)
-- 단순 CRUD(reg_service_teams 목록/상세/등록/수정/삭제, 매핑 등록/수정)는
-- Laravel Repository에서 직접 쿼리 사용
-- ================================================================

DELIMITER $$

-- ================================================================
-- 0. 봉사 팀 목록 (봉사현황 화면 기본 목록)
--    reg_service_teams + 참여인원 집계 + 담당자명 JOIN, 페이징
--    - 기존에는 목록 조회 후 프론트에서 팀마다 getVolunteersByTeam(참여인원) /
--      manager_member_id마다 getMemberDetail(담당자명)을 별도 호출해
--      팀 20개 기준 최대 ~40회 추가 요청이 발생 → 분당 요청 제한(60/min)에
--      쉽게 걸리는 문제가 있어, 참여인원/담당자명을 이 SP에서 한 번에 반환하도록 변경
--    count는 PHP에서 별도 처리, SP는 데이터 조회만 담당
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_volunteer_list$$
CREATE PROCEDURE sp_v4_reg_volunteer_list(
    IN p_church_id         BIGINT,
    IN p_service_type      VARCHAR(30),
    IN p_mode              VARCHAR(20),
    IN p_is_active         TINYINT,
    IN p_manager_member_id BIGINT,
    IN p_keyword           VARCHAR(100),
    IN p_page              INT,
    IN p_size              INT
)
BEGIN
    DECLARE v_offset INT DEFAULT (p_page - 1) * p_size;

    SELECT
        t.id, t.church_id, t.name, t.description, t.service_type, t.mode, t.frequency,
        t.day_of_week, t.service_time, t.event_date, t.start_time, t.end_time,
        t.required_count, t.manager_member_id, t.participation_type, t.is_active,
        t.created_at, t.updated_at,
        COALESCE(pc.participant_count, 0) AS participant_count,
        mgr.name AS manager_name
    FROM reg_service_teams t
    LEFT JOIN (
        SELECT service_team_id, COUNT(*) AS participant_count
        FROM reg_service_members
        WHERE status = 'active'
        GROUP BY service_team_id
    ) pc ON pc.service_team_id = t.id
    LEFT JOIN reg_members mgr ON mgr.id = t.manager_member_id
    WHERE t.church_id = p_church_id
      AND (p_service_type      IS NULL OR t.service_type = p_service_type)
      AND (p_mode              IS NULL OR t.mode = p_mode)
      AND (p_is_active         IS NULL OR t.is_active = p_is_active)
      AND (p_manager_member_id IS NULL OR t.manager_member_id = p_manager_member_id)
      AND (p_keyword           IS NULL OR t.name LIKE CONCAT('%', p_keyword, '%')
                                       OR t.description LIKE CONCAT('%', p_keyword, '%'))
    ORDER BY t.id DESC
    LIMIT p_size OFFSET v_offset;
END$$


-- ================================================================
-- 1. 봉사 팀 소속 봉사자 목록
--    reg_service_members + reg_members JOIN, 페이징
--    count는 PHP에서 별도 처리, SP는 데이터 조회만 담당
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_volunteer_by_team$$
CREATE PROCEDURE sp_v4_reg_volunteer_by_team(
    IN p_team_id BIGINT,
    IN p_status  VARCHAR(20),
    IN p_page    INT,
    IN p_size    INT
)
BEGIN
    DECLARE v_offset INT DEFAULT (p_page - 1) * p_size;

    SELECT
        sm.id       AS mapping_id,
        sm.role,
        sm.joined_at,
        sm.status,
        m.id        AS member_id,
        m.member_no,
        m.name,
        m.email,
        m.phone
    FROM reg_service_members sm
    JOIN reg_members m ON m.id = sm.member_id
    WHERE sm.service_team_id = p_team_id
      AND m.is_deleted = 0
      AND (p_status IS NULL OR sm.status = p_status)
    ORDER BY sm.status ASC, m.name ASC
    LIMIT p_size OFFSET v_offset;
END$$


-- ================================================================
-- 2. 교인별 참여 봉사 팀 목록
--    reg_service_members + reg_service_teams JOIN (페이징 없음)
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_volunteer_teams_by_member$$
CREATE PROCEDURE sp_v4_reg_volunteer_teams_by_member(
    IN p_member_id BIGINT
)
BEGIN
    SELECT
        sm.id            AS mapping_id,
        sm.role,
        sm.joined_at,
        sm.status,
        t.id             AS team_id,
        t.name           AS team_name,
        t.service_type,
        t.mode,
        t.is_active      AS team_is_active
    FROM reg_service_members sm
    JOIN reg_service_teams t ON t.id = sm.service_team_id
    WHERE sm.member_id = p_member_id
    ORDER BY sm.status ASC, t.name ASC;
END$$


-- ================================================================
-- 3. 교회 전체 봉사 이력 (봉사이력 화면 "교인 중심" 탭 기본 목록)
--    reg_service_members + reg_members + reg_service_teams JOIN
--    + reg_service_attendance 집계(attend_count/last_service_date) LEFT JOIN
--    member_id/team_id 미지정 시 교회 전체, 지정 시 해당 대상으로 좁힘
--    count는 PHP에서 별도 처리, SP는 데이터 조회만 담당
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_volunteer_history$$
CREATE PROCEDURE sp_v4_reg_volunteer_history(
    IN p_church_id    BIGINT,
    IN p_member_id    BIGINT,
    IN p_team_id      BIGINT,
    IN p_status       VARCHAR(20),
    IN p_service_type VARCHAR(30),
    IN p_keyword      VARCHAR(100),
    IN p_from_date    DATE,
    IN p_to_date      DATE,
    IN p_page         INT,
    IN p_size         INT
)
BEGIN
    DECLARE v_offset INT DEFAULT (p_page - 1) * p_size;

    SELECT
        sm.id       AS mapping_id,
        sm.role,
        sm.joined_at,
        sm.status,
        m.id        AS member_id,
        m.member_no,
        m.name      AS member_name,
        t.id        AS team_id,
        t.name      AS team_name,
        t.service_type,
        t.mode,
        t.is_active AS team_is_active,
        COALESCE(att.attend_count, 0) AS attend_count,
        att.last_service_date
    FROM reg_service_members sm
    JOIN reg_members m       ON m.id = sm.member_id
    JOIN reg_service_teams t ON t.id = sm.service_team_id
    LEFT JOIN (
        SELECT service_team_id, member_id,
               SUM(status IN ('present','late')) AS attend_count,
               MAX(CASE WHEN status IN ('present','late') THEN service_date END) AS last_service_date
        FROM reg_service_attendance
        GROUP BY service_team_id, member_id
    ) att ON att.service_team_id = sm.service_team_id AND att.member_id = sm.member_id
    WHERE t.church_id = p_church_id
      AND m.is_deleted = 0
      AND (p_member_id    IS NULL OR sm.member_id = p_member_id)
      AND (p_team_id      IS NULL OR sm.service_team_id = p_team_id)
      AND (p_status       IS NULL OR sm.status = p_status)
      AND (p_service_type IS NULL OR t.service_type = p_service_type)
      AND (p_from_date    IS NULL OR sm.joined_at >= p_from_date)
      AND (p_to_date      IS NULL OR sm.joined_at <= p_to_date)
      AND (p_keyword      IS NULL OR m.name LIKE CONCAT('%', p_keyword, '%')
                                  OR t.name LIKE CONCAT('%', p_keyword, '%'))
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT p_size OFFSET v_offset;
END$$


-- ================================================================
-- 4. 봉사 팀별 이력 요약 (봉사이력 화면 "봉사 중심" 탭 기본 목록)
--    reg_service_teams + reg_service_members(참여인원) + reg_service_attendance(참여횟수/최근참여일) 집계
--    team_id 미지정 시 교회 전체 팀, 지정 시 해당 팀으로 좁힘
--    count는 PHP에서 별도 처리, SP는 데이터 조회만 담당
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_volunteer_team_history$$
CREATE PROCEDURE sp_v4_reg_volunteer_team_history(
    IN p_church_id    BIGINT,
    IN p_team_id      BIGINT,
    IN p_service_type VARCHAR(30),
    IN p_mode         VARCHAR(20),
    IN p_keyword      VARCHAR(100),
    IN p_from_date    DATE,
    IN p_to_date      DATE,
    IN p_page         INT,
    IN p_size         INT
)
BEGIN
    DECLARE v_offset INT DEFAULT (p_page - 1) * p_size;

    SELECT
        t.id           AS team_id,
        t.name         AS team_name,
        t.service_type,
        t.mode,
        t.is_active,
        t.event_date,
        t.start_time,
        t.end_time,
        COALESCE(pc.participant_count, 0) AS participant_count,
        COALESCE(att.attend_count, 0)     AS attend_count,
        att.last_service_date,
        fj.first_joined_at
    FROM reg_service_teams t
    LEFT JOIN (
        SELECT service_team_id, COUNT(*) AS participant_count
        FROM reg_service_members
        WHERE status = 'active'
        GROUP BY service_team_id
    ) pc ON pc.service_team_id = t.id
    LEFT JOIN (
        SELECT service_team_id,
               SUM(status IN ('present','late')) AS attend_count,
               MAX(CASE WHEN status IN ('present','late') THEN service_date END) AS last_service_date
        FROM reg_service_attendance
        GROUP BY service_team_id
    ) att ON att.service_team_id = t.id
    LEFT JOIN (
        SELECT service_team_id, MIN(joined_at) AS first_joined_at
        FROM reg_service_members
        GROUP BY service_team_id
    ) fj ON fj.service_team_id = t.id
    WHERE t.church_id = p_church_id
      AND (p_team_id      IS NULL OR t.id = p_team_id)
      AND (p_service_type IS NULL OR t.service_type = p_service_type)
      AND (p_mode         IS NULL OR t.mode = p_mode)
      AND (p_keyword      IS NULL OR t.name LIKE CONCAT('%', p_keyword, '%'))
      AND (p_from_date    IS NULL OR fj.first_joined_at IS NULL OR fj.first_joined_at >= p_from_date)
      AND (p_to_date      IS NULL OR att.last_service_date IS NULL OR att.last_service_date <= p_to_date)
    ORDER BY t.id DESC
    LIMIT p_size OFFSET v_offset;
END$$

DELIMITER ;
