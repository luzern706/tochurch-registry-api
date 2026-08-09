-- ================================================================
-- 문자 템플릿 (reg_message_templates)
--
-- 실행 상태: 아직 미실행 (2026-08-09 세션에서 코드까지만 구현, DB 적용은 사용자가
-- HeidiSQL에서 직접 실행하기로 결정 — 이 세션에서 실행/검증하지 않음).
-- 로컬 dev DB 포함 실행된 환경 없음. 실행 전까지 관련 API(v4/message/template/*)는
-- 500 에러(테이블 없음)가 발생한다.
--
-- 배경: 메시지 발송 화면(Compose.jsx)의 템플릿 선택은 지금까지 컴포넌트 내부
-- JS 상수(TPL, 4종 고정)였다. 이 테이블은 관리자가 자유롭게 등록/수정/비활성화할
-- 수 있는 템플릿을 위한 것 — 가변 개수 CRUD가 필요해 reg_church_settings(범용
-- 키-값)로는 부적합하다고 판단해 별도 테이블로 신설.
--
-- scope: public(공용, 교회 전체 공유) / private(개인, created_by 본인만 노출)
--        공용 템플릿은 원본을 직접 수정하지 않고 "복제" 후 개인 템플릿으로
--        편집하는 게 원칙(프론트 mock 문구 그대로 유지) — 백엔드는 강제하지
--        않고 프론트에서 공용 템플릿의 "수정" 버튼을 막고 "복제"만 노출.
-- ================================================================

DROP TABLE IF EXISTS `reg_message_templates`;
CREATE TABLE `reg_message_templates` (
    `id`             BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`      BIGINT       NOT NULL,
    `name`           VARCHAR(100) NOT NULL COMMENT '템플릿명',
    `category`       VARCHAR(30)  NOT NULL COMMENT '분류: worship/newcomer/attendance/visitation/education/service/urgent/other',
    `msg_type`       ENUM('SMS','LMS') NOT NULL DEFAULT 'SMS' COMMENT '메시지 유형(글자수 기준 참고용 — 실제 발송 시 send_type=sms 고정)',
    `scope`          ENUM('public','private') NOT NULL DEFAULT 'private' COMMENT '공용/개인',
    `content`        TEXT         NOT NULL COMMENT '본문 (변수 {이름} 등 포함, 실제 치환은 발송 화면에서)',
    `unsub_enabled`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '수신거부 안내문 자동 추가 여부',
    `unsub_text`     VARCHAR(200) NULL     COMMENT '수신거부 안내문',
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '사용여부(0이면 발송 화면에 노출 안 함)',
    `created_by`     BIGINT       NOT NULL COMMENT '작성자 admin_no (gh_church_admin)',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_church_scope_active` (`church_id`, `scope`, `is_active`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='문자 템플릿 (공용/개인, 발송 화면·템플릿 관리 화면 공용 재사용)';
