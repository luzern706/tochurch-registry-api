-- ================================================================
-- 교육 회차별 출결 기록 (reg_education_attendance)
--
-- 연결 구조:
--   reg_education_courses  ← 교육과정
--   reg_education_sessions ← 기수 (session_id)
--   reg_education_records  ← 수강 등록 (session_id + member_id)
--   reg_education_attendance ← 회차별 출결 (session_id + member_id + round_no)
--
-- 설계 원칙:
--   - 출결 행은 "출석/결석" 명시 방식: 행이 없으면 미처리(미입력)
--   - round_no 는 1 부터 reg_education_sessions.total_rounds 까지
--   - session_date 는 실제 수업 날짜 (없을 수 있음)
-- ================================================================

DROP TABLE IF EXISTS `reg_education_attendance`;
CREATE TABLE `reg_education_attendance` (
    `id`           BIGINT       NOT NULL AUTO_INCREMENT,
    `session_id`   BIGINT       NOT NULL COMMENT 'reg_education_sessions.id',
    `member_id`    BIGINT       NOT NULL COMMENT 'reg_members.id',
    `round_no`     SMALLINT     NOT NULL COMMENT '회차 번호 (1~total_rounds)',
    `status`       ENUM('present','absent','late','excused')
                                NOT NULL DEFAULT 'present'
                                COMMENT 'present=출석 absent=결석 late=지각 excused=공결',
    `session_date` DATE         NULL     COMMENT '실제 수업 날짜',
    `note`         VARCHAR(200) NULL     COMMENT '비고',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_session_member_round` (`session_id`, `member_id`, `round_no`),
    KEY `idx_session_id`  (`session_id`),
    KEY `idx_member_id`   (`member_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='교육 회차별 출결 기록';
