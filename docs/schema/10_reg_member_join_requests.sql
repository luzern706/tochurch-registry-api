-- ================================================================
-- 교회로 회원 → 교적 연동 처리 이력 (reg_member_join_requests)
--
-- 실행 상태: 2026-08-09 로컬 dev DB(gyohyero, 사용자 승인 하에 이 세션에서 직접 실행)에는
-- 적용 완료. 원격/스테이징 DB에는 아직 미실행 — 배포 전 HeidiSQL에서 동일하게 실행 필요.
--
-- 배경:
--   gh_account(교회로 계정)에는 "가입 승인 대기" 상태가 없음 — 일반회원(성도)이
--   교회를 선택하면 gh_account.church_no 가 즉시 저장되고 끝(승인 절차 자체가
--   교회로 쪽에 존재하지 않음). 그래서 이 화면의 "가입 승인"은 "교회 가입 승인"이
--   아니라 "이미 교회로에 가입해 이 교회를 선택했지만 아직 교적(reg_members)에
--   연동되지 않은 사람을 찾아 매칭/신규등록하는" 기능이다.
--
--   대상 목록 자체(gh_account.church_no = 현재 교회 AND account_no 가 어떤
--   reg_members.churchero_user_id 로도 연결되지 않은 계정)는 기존 데이터의
--   안티조인만으로 실시간 계산 가능해 별도 테이블이 필요 없다. 이 테이블은
--   그 목록에서 "보류"·"반려" 처리한 계정을 기억해 다음 조회 때 다시 뜨지
--   않도록 하는 용도로만 쓰인다(교적 연동/신규생성은 기존 reg_members
--   update/register API를 그대로 재사용 — 이 테이블에 기록하지 않음).
--
-- 연결 구조:
--   gh_account.account_no ← 교회로 계정 (공유 테이블, 스키마 변경 없음)
--   reg_members.churchero_user_id ← 교적 연동 완료 시 이 값이 채워짐
-- ================================================================

DROP TABLE IF EXISTS `reg_member_join_requests`;
CREATE TABLE `reg_member_join_requests` (
    `id`          BIGINT       NOT NULL AUTO_INCREMENT,
    `church_id`   BIGINT       NOT NULL COMMENT 'gh_account.church_no / reg_members.church_id',
    `account_no`  BIGINT       NOT NULL COMMENT 'gh_account.account_no',
    `status`      ENUM('held','rejected') NOT NULL COMMENT 'held=보류 rejected=반려',
    `reason`      VARCHAR(300) NULL     COMMENT '보류/반려 사유',
    `created_by`  BIGINT       NOT NULL COMMENT '처리한 관리자 admin_no (gh_church_admin)',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_church_account` (`church_id`, `account_no`),
    KEY `idx_church_status` (`church_id`, `status`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='교회로 회원(gh_account) → 교적 연동 대기 목록의 보류/반려 처리 이력';
