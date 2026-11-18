-- ================================================================
-- 예전 방식(21번)의 status='released' 행을 이력(reg_church_member_cert_events)으로 옮기고 정리
--
-- 실행 상태: 스테이징(gyohyero_dev)은 해당 행 0건이라 적용할 것 없음, NHN 운영(gyohyero) 2026-11-18 적용 완료(1건 이전).
-- 선행: 22_reg_church_member_cert_events.sql
--
-- 배경: 21번에서는 교회 변경 시 이전 교회 승인 행을 status='released' 로 바꿔 남겼다.
--   22번부터는 해제 이력을 reg_church_member_cert_events 에 쌓고, reg_church_member_certs 는
--   「현재 교회 기준 승인/거절」만 가진다. 이미 만들어진 released 행(운영 확인 시점 1건)을 이력으로 옮기지 않으면
--   오피스 「해제」 탭에서 사라지므로 한 번 옮기고 정리한다.
-- 처리: released 행마다 이력(released) 1건 — moved_to_church_id 는 성도의 현재 출석교회(그 교회가 이 행의
--   교회와 같으면 NULL), created_at 은 행의 updated_at. 옮긴 뒤 released 행 삭제(읽기에서는 대기로만 취급되던 값).
-- 두 문장을 한 트랜잭션으로 실행한다(적용 스크립트가 처리).
-- ================================================================

INSERT INTO `reg_church_member_cert_events` (`church_id`, `account_no`, `event_type`, `reason`, `moved_to_church_id`, `created_at`)
SELECT c.`church_id`, c.`account_no`, 'released',
       COALESCE(NULLIF(c.`reason`, ''), '교회 변경으로 해제'),
       CASE WHEN a.`church_no` IS NULL OR a.`church_no` = c.`church_id` THEN NULL ELSE a.`church_no` END,
       c.`updated_at`
FROM `reg_church_member_certs` c
LEFT JOIN `gh_account` a ON a.`account_no` = c.`account_no`
WHERE c.`status` = 'released';

DELETE FROM `reg_church_member_certs` WHERE `status` = 'released';
