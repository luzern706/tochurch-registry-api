-- ================================================================
-- 봉사 참여 출결 기록 (reg_service_attendance)
--
-- 연결 구조:
--   reg_service_teams   ← 봉사 팀
--   reg_service_members ← 참여자 매핑 (service_team_id + member_id)
--   reg_service_attendance ← 실제 봉사일별 출결 (service_team_id + member_id + service_date)
--
-- 설계 원칙:
--   - 교육(reg_education_attendance)은 기수에 total_rounds 가 정해져 있어 round_no 로 회차를 구분하지만,
--     봉사 팀(정기: 매주/격주/매월 무기한 반복, 일회성: 단일 event_date)은 고정된 회차 수가 없음
--     → round_no 대신 실제 날짜(service_date)를 직접 키로 사용
--   - 출결 행은 "출석/결석" 명시 방식: 행이 없으면 미처리(미입력)
--   - reg_service_members(배정 여부)와 별개 — 배정되어 있어도 그 날 실제 봉사했는지는 이 테이블로 기록
-- ================================================================

DROP TABLE IF EXISTS `reg_service_attendance`;
CREATE TABLE `reg_service_attendance` (
    `id`               BIGINT       NOT NULL AUTO_INCREMENT,
    `service_team_id`  BIGINT       NOT NULL COMMENT 'reg_service_teams.id',
    `member_id`        BIGINT       NOT NULL COMMENT 'reg_members.id',
    `service_date`     DATE         NOT NULL COMMENT '실제 봉사 날짜',
    `status`           ENUM('present','absent','late','excused')
                                    NOT NULL DEFAULT 'present'
                                    COMMENT 'present=출석 absent=결석 late=지각 excused=공결',
    `note`             VARCHAR(200) NULL     COMMENT '비고',
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_team_member_date` (`service_team_id`, `member_id`, `service_date`),
    KEY `idx_service_team_id` (`service_team_id`),
    KEY `idx_member_id`       (`member_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='봉사 참여 출결 기록';
