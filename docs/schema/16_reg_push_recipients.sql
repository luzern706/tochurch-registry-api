-- ================================================================
-- 16. 푸시 알림 발송 대상자 스냅샷 (reg_push_recipients)
--     + reg_messages ALTER (deep_link, resend_of)
--
--  실제 FCM/APNs 발송 인프라가 이 프로젝트엔 없어(문자발송과 동일하게
--  "기록만 저장") 이 테이블은 전송 성공/실패 결과가 아니라,
--  발송 시점에 계산한 "도달 가능성"(계정연동 + 기기토큰존재 + 수신동의)
--  스냅샷만 정직하게 기록한다.
--
--  도달가능성 계산에 쓰는 gh_push_token_v4 / gh_account_settings 는
--  03_gh_www_api 가 관리하는 기존 공유 테이블 — 읽기 전용으로만 조회하고
--  스키마는 건드리지 않는다.
-- ================================================================

CREATE TABLE IF NOT EXISTS `reg_push_recipients` (
    `id`            BIGINT       NOT NULL AUTO_INCREMENT COMMENT 'PK',
    `message_id`    BIGINT       NOT NULL COMMENT 'reg_messages.id (send_type=push 인 행만 대상)',
    `member_id`     BIGINT       NOT NULL COMMENT '대상 교인 reg_members.id',
    `member_name`   VARCHAR(100) NOT NULL COMMENT '발송 시점 이름 스냅샷',
    `org_name`      VARCHAR(200) NULL     COMMENT '발송 시점 대표 소속 조직명 스냅샷',
    `phone`         VARCHAR(30)  NULL     COMMENT '발송 시점 연락처 스냅샷',
    `reachable`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '발송 시점 도달가능 여부(계정연동+기기토큰존재+수신동의 모두 충족)',
    `skip_reason`   VARCHAR(20)  NULL     COMMENT 'reachable=0일 때 사유: no_account(교회로 계정 미연동), no_token(등록된 기기 토큰 없음), optout(푸시 수신거부)',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '스냅샷 생성 시각',
    PRIMARY KEY (`id`),
    KEY `idx_message_id` (`message_id`),
    KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='푸시 알림 발송 대상자 스냅샷 — 실제 전송 성공/실패 결과가 아니라 발송 시점 도달가능성 기록';

-- reg_messages 는 기존에 이미 적용된 테이블(01_registry_tables.sql) — 신규 컬럼만 추가.
-- deep_link: 푸시 전용 선택 입력값(문자발송에는 의미 없어 항상 NULL)
-- resend_of: "실패자만 재발송" 시 원본 메시지를 가리키는 셀프 참조(FK 제약 없음, reg_audit_logs 관례와 동일)
ALTER TABLE `reg_messages`
    ADD COLUMN `deep_link` VARCHAR(255) NULL COMMENT '딥링크 (푸시 전용, 선택입력)' AFTER `content`,
    ADD COLUMN `resend_of` BIGINT       NULL COMMENT '재발송 원본 메시지 id (reg_messages.id 셀프참조, 최초 발송이면 NULL)' AFTER `deep_link`;
