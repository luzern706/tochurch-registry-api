-- ================================================================
-- 교육 관리 저장 프로시저 (sp_v4_reg_education.sql)
-- prefix: sp_v4_reg_edu_
-- 복잡한 JOIN / 집계 쿼리만 SP로 처리
-- 단순 CRUD는 Laravel Repository에서 직접 쿼리 사용
-- ================================================================

DELIMITER $$

-- ================================================================
-- 1. 교육 과정 목록
--    최신 기수 서브쿼리 + 기수수/수강자수 집계 JOIN
--    결과셋 1: SELECT total AS total
--    결과셋 2: 과정 목록
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_edu_course_list$$
CREATE PROCEDURE sp_v4_reg_edu_course_list(
    IN p_church_id  BIGINT,
    IN p_keyword    VARCHAR(100),
    IN p_status     VARCHAR(20),
    IN p_target     VARCHAR(50),
    IN p_edu_type   VARCHAR(50),
    IN p_page       INT,
    IN p_size       INT
)
BEGIN
    DECLARE v_offset INT DEFAULT (p_page - 1) * p_size;

    -- count는 PHP에서 별도 처리, SP는 데이터 조회만 담당

    -- 결과셋: 페이징된 목록
    SELECT
        c.id              AS course_id,
        c.name,
        c.description,
        c.target,
        c.edu_type,
        c.total_weeks,
        c.total_rounds,
        c.use_attendance,
        c.completion_rate,
        c.created_at,
        COALESCE(sc.session_count,    0) AS session_count,
        COALESCE(ec.enrollment_count, 0) AS enrollment_count,
        ls.latest_session_id,
        ls.generation AS latest_generation,
        ls.instructor AS latest_instructor,
        ls.start_date AS latest_start_date,
        ls.end_date   AS latest_end_date,
        ls.status     AS latest_status,
        ls.memo       AS latest_memo
    FROM reg_education_courses c
    LEFT JOIN (
        SELECT si.course_id,
               si.id AS latest_session_id,
               si.generation, si.instructor,
               si.start_date, si.end_date, si.status, si.memo
        FROM reg_education_sessions si
        INNER JOIN (
            SELECT course_id, MAX(id) AS max_id
            FROM reg_education_sessions
            GROUP BY course_id
        ) sm ON si.course_id = sm.course_id AND si.id = sm.max_id
    ) ls ON ls.course_id = c.id
    LEFT JOIN (
        SELECT course_id, COUNT(*) AS session_count
        FROM reg_education_sessions
        GROUP BY course_id
    ) sc ON sc.course_id = c.id
    LEFT JOIN (
        SELECT session_id, COUNT(*) AS enrollment_count
        FROM reg_education_records
        GROUP BY session_id
    ) ec ON ec.session_id = ls.latest_session_id
    WHERE c.church_id = p_church_id
      AND (p_keyword  IS NULL OR c.name       LIKE CONCAT('%', p_keyword, '%')
                              OR ls.instructor LIKE CONCAT('%', p_keyword, '%'))
      AND (p_status   IS NULL OR ls.status  = p_status)
      AND (p_target   IS NULL OR c.target   = p_target)
      AND (p_edu_type IS NULL OR c.edu_type = p_edu_type)
    ORDER BY c.id DESC
    LIMIT p_size OFFSET v_offset;
END$$


-- ================================================================
-- 2. 교육 기수 목록
--    과정명 JOIN + 수강 통계 집계
--    결과셋 1: total
--    결과셋 2: 기수 목록
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_edu_session_list$$
CREATE PROCEDURE sp_v4_reg_edu_session_list(
    IN p_church_id  BIGINT,
    IN p_course_id  BIGINT,
    IN p_status     VARCHAR(20),
    IN p_from_date  DATE,
    IN p_to_date    DATE,
    IN p_keyword    VARCHAR(100),
    IN p_page       INT,
    IN p_size       INT
)
BEGIN
    DECLARE v_offset INT DEFAULT (p_page - 1) * p_size;

    -- count는 PHP에서 별도 처리, SP는 데이터 조회만 담당
    SELECT
        s.id           AS session_id,
        s.course_id,
        s.generation,
        s.instructor,
        s.start_date,
        s.end_date,
        s.capacity,
        s.total_rounds,
        s.status,
        s.memo,
        s.created_at,
        s.updated_at,
        c.name            AS course_name,
        c.description     AS course_description,
        c.edu_type,
        c.target,
        c.use_attendance,
        c.completion_rate,
        COALESCE(ec.enrollment_count, 0) AS enrollment_count,
        COALESCE(ec.avg_progress,     0) AS avg_progress
    FROM reg_education_sessions s
    JOIN reg_education_courses c ON c.id = s.course_id
    LEFT JOIN (
        SELECT session_id,
               COUNT(*)              AS enrollment_count,
               ROUND(AVG(progress))  AS avg_progress
        FROM reg_education_records
        GROUP BY session_id
    ) ec ON ec.session_id = s.id
    WHERE s.church_id = p_church_id
      AND (p_course_id IS NULL OR s.course_id  = p_course_id)
      AND (p_status    IS NULL OR s.status     = p_status)
      AND (p_from_date IS NULL OR s.start_date >= p_from_date)
      AND (p_to_date   IS NULL OR s.end_date   <= p_to_date)
      AND (p_keyword   IS NULL OR s.generation  LIKE CONCAT('%', p_keyword, '%')
                               OR s.instructor   LIKE CONCAT('%', p_keyword, '%')
                               OR c.name         LIKE CONCAT('%', p_keyword, '%'))
    ORDER BY s.start_date DESC, s.id DESC
    LIMIT p_size OFFSET v_offset;
END$$


-- ================================================================
-- 3. 교육 기수 상세
--    과정명 JOIN + 수강 통계 집계 (단일 행이지만 JOIN 복잡도 있음)
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_edu_session_detail$$
CREATE PROCEDURE sp_v4_reg_edu_session_detail(
    IN p_session_id BIGINT
)
BEGIN
    SELECT
        s.id           AS session_id,
        s.church_id,
        s.course_id,
        s.generation,
        s.instructor,
        s.start_date,
        s.end_date,
        s.capacity,
        s.total_rounds,
        s.status,
        s.memo,
        s.created_at,
        s.updated_at,
        c.name            AS course_name,
        c.description     AS course_description,
        c.edu_type,
        c.target,
        c.use_attendance,
        c.completion_rate,
        COALESCE(ec.enrollment_count, 0) AS enrollment_count,
        COALESCE(ec.avg_progress,     0) AS avg_progress
    FROM reg_education_sessions s
    JOIN reg_education_courses c ON c.id = s.course_id
    LEFT JOIN (
        SELECT session_id,
               COUNT(*)              AS enrollment_count,
               ROUND(AVG(progress))  AS avg_progress
        FROM reg_education_records
        GROUP BY session_id
    ) ec ON ec.session_id = s.id
    WHERE s.id = p_session_id;
END$$


-- ================================================================
-- 4. 교인별 수강 이력
--    3개 테이블 JOIN
-- ================================================================
DROP PROCEDURE IF EXISTS sp_v4_reg_edu_sessions_by_member$$
CREATE PROCEDURE sp_v4_reg_edu_sessions_by_member(
    IN p_member_id BIGINT
)
BEGIN
    SELECT
        r.id                AS enrollment_id,
        r.progress,
        r.status            AS enrollment_status,
        s.id                AS session_id,
        s.generation,
        s.instructor,
        s.start_date,
        s.end_date,
        s.status            AS session_status,
        c.id                AS course_id,
        c.name              AS course_name
    FROM reg_education_records r
    JOIN reg_education_sessions s ON s.id = r.session_id
    JOIN reg_education_courses  c ON c.id = s.course_id
    WHERE r.member_id = p_member_id
    ORDER BY s.start_date DESC;
END$$

DELIMITER ;
