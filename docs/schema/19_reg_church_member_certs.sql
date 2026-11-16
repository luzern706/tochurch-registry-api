-- ================================================================
-- 성도 인증 처리 결과 (reg_church_member_certs)
--
-- 실행 상태: 로컬 DB·NHN 스테이징(gyohyero_dev) 2026-11-15, NHN 운영(gyohyero) 2026-11-16 적용 완료.
--
-- 배경:
--   앱(교회로)에서 성도가 출석교회를 고르면 gh_account.church_no 가 즉시 바뀌고 끝이다.
--   "교회가 성도로 인증한다"는 절차가 어디에도 없어서, 오피스(교적)에 교회 관리자가
--   소속 성도를 승인/거절하는 "성도 인증" 기능을 무료 메뉴로 새로 만든다.
--
-- 상태 모델 (테이블에는 승인/거절 결과만 저장):
--   승인(approved) : 이 테이블에 church_id = 현재 gh_account.church_no 인 approved 행이 있음
--   거절(rejected) : 같은 조건의 rejected 행이 있음
--   대기(pending)  : gh_account.church_no = 내 교회 인데 위 두 경우가 아님 (행 없음 또는 다른 교회 행)
--
-- 교회 변경 시 초기화:
--   계정당 1행(UNIQUE account_no). 성도가 교회를 바꾸면 행의 church_id 가 새 교회와 달라져
--   자동으로 "인증 없음"으로 판정된다. 앱 API(교회 변경 성공 시)가 이전 행도 삭제해서
--   이전 교회로 되돌아왔을 때 예전 승인이 되살아나지 않게 한다.
--
-- 연결 구조:
--   gh_account.account_no  ← 교회로 계정 (공유 테이블, 스키마 변경 없음)
--   church_id              ← gh_account.church_no (처리 시점의 교회)
--   교적(reg_members) 데이터는 만들지 않는다 — 인증은 교적과 별개(무료 기능).
-- ================================================================

CREATE TABLE IF NOT EXISTS `reg_church_member_certs` (
    `id`          BIGINT       NOT NULL AUTO_INCREMENT,
    `account_no`  BIGINT       NOT NULL COMMENT 'gh_account.account_no',
    `church_id`   BIGINT       NOT NULL COMMENT '처리 시점의 gh_account.church_no',
    `status`      ENUM('approved','rejected') NOT NULL COMMENT 'approved=승인 rejected=거절',
    `reason`      VARCHAR(300) NULL     COMMENT '거절 사유',
    `created_by`  BIGINT       NOT NULL COMMENT '처리한 관리자 admin_no (gh_church_admin)',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_account` (`account_no`),
    KEY `idx_church_status` (`church_id`, `status`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='성도 인증 승인/거절 결과 (계정당 1행, 교회가 바뀌면 무효)';
