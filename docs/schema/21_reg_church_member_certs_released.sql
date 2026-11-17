-- ================================================================
-- 성도 인증 「교회 변경으로 해제」 기록 (reg_church_member_certs 변경)
--
-- 실행 상태: 로컬 DB 2026-11-16, NHN 스테이징(gyohyero_dev)·NHN 운영(gyohyero) 2026-11-17 적용 완료.
-- 선행: 19_reg_church_member_certs.sql
--
-- 배경(기획 추가 요청):
--   성도가 출석교회를 바꾸면 이전 교회 인증은 해제하고 새 교회에서 「인증 대기」부터 다시 시작한다.
--   이전 교회 오피스의 성도 인증 목록에는 「교회 변경으로 해제」 사유가 남아야 한다.
--   원래 교회로 돌아와도 인증은 새로 받는다. 이전 교회의 교적(reg_members) 데이터는 건드리지 않는다.
--
-- 변경:
--   1) status 에 'released'(해제) 추가 — 승인(approved)이던 인증이 교회 변경으로 풀린 기록.
--      reason 에 해제 사유(예: '교회 변경으로 해제'), updated_at 이 해제 시각.
--   2) 계정당 1행(UNIQUE account_no) → 계정·교회당 1행(UNIQUE account_no, church_id).
--      새 교회에서 승인/거절해도 이전 교회의 해제 기록이 덮어써지지 않게 한다.
--
-- 판정 규칙(변경 후):
--   현재 교회(gh_account.church_no)에 대한 행이 approved/rejected 이면 그 상태,
--   행이 없거나 released 이면 인증 대기. → 원래 교회로 돌아오면 released 행이 있어도 대기로 시작한다.
--   「해제」 목록 = church_id 가 내 교회인 released 행 중, 현재 성도가 내 교회에 있지 않은 것.
--
-- 기존 데이터: 행은 계정당 1행이므로 새 유니크 키(account_no, church_id)를 그대로 만족한다.
-- ================================================================

ALTER TABLE `reg_church_member_certs`
    MODIFY COLUMN `status` ENUM('approved','rejected','released') NOT NULL
        COMMENT 'approved=승인 rejected=거절 released=교회 변경으로 해제',
    ADD UNIQUE KEY `uq_account_church` (`account_no`, `church_id`),
    DROP INDEX `uq_account`,
    COMMENT = '성도 인증 승인/거절/해제 결과 (계정·교회당 1행)';
