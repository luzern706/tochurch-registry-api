-- ================================================================
-- 성도 인증 해제·신청 취소 이력 (reg_church_member_cert_events 신설)
--
-- 실행 상태: 로컬 DB·NHN 스테이징(gyohyero_dev)·NHN 운영(gyohyero) 2026-11-18 적용 완료.
-- 선행: 19_reg_church_member_certs.sql, 21_reg_church_member_certs_released.sql
--
-- 배경(두레이 성도 인증 보완 요청 4번, 2026-11-18):
--   21번에서는 교회 변경 시 이전 교회 승인 행을 status='released' 로 바꿔 「해제」 탭의 근거로 썼다.
--   그런데 그 행이 (계정·교회)당 1행이라 같은 교회로 돌아와 승인·거절하면 덮어써져 해제 기록이 사라지고,
--   「인증 대기」 상태에서 교회를 옮긴 성도는 기록 자체가 남지 않았다.
--   → 해제·신청 취소를 인증 현재 상태(reg_church_member_certs)와 분리해 이력으로 쌓는다.
--
-- 이벤트 종류:
--   released : 이전 교회에서 승인(approved)이던 인증이 교회 변경으로 해제됨  (reason '교회 변경으로 해제')
--   canceled : 이전 교회에서 인증 대기(처리 전)이던 성도가 교회를 옮겨 신청이 취소됨 (reason '교회 변경으로 신청 취소')
--
-- 기록 주체: 앱 API(03_gh_www_api)가 출석교회 변경 직후 INSERT 한다. 오피스(04)는 읽기만 한다.
-- 교회·성도가 다시 바뀌어도 이력은 지우지 않는다(되돌아와도 「해제」 탭에 계속 남음).
-- reg_church_member_certs 는 이제 「현재 교회 기준 승인/거절 1행」만 가진다
-- (교회 변경 시 이전 교회 승인·거절 행은 이력으로 옮긴 뒤 삭제). 기존 'released' 값은 읽기에서 대기로만 취급한다.
-- ================================================================

CREATE TABLE IF NOT EXISTS `reg_church_member_cert_events` (
    `id`                 BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`          BIGINT       NOT NULL COMMENT '인증이 있던(또는 신청하던) 이전 교회',
    `account_no`         BIGINT       NOT NULL COMMENT 'gh_account.account_no',
    `event_type`         ENUM('released','canceled') NOT NULL COMMENT 'released=인증 해제 canceled=신청 취소',
    `reason`             VARCHAR(300) NULL     COMMENT '사유 문구',
    `moved_to_church_id` BIGINT       NULL     COMMENT '옮겨 간 교회(gh_account.church_no)',
    `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '해제·취소된 시각',
    PRIMARY KEY (`id`),
    KEY `idx_church_created` (`church_id`, `created_at`),
    KEY `idx_account` (`account_no`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='성도 인증 해제·신청 취소 이력 (교회 변경 시 앱 API 가 기록)';
