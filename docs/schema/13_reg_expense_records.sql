-- reg_expense_records 신규 테이블
-- 2026-08-09, 재정 > 지출 관리(/finance/expense)·지출 입력(/finance/expense/input) 프론트 연동
-- 로컬 dev DB(church_id=33632)에 이 세션에서 직접 적용 완료(사용자 승인) — 브라우저 실연동 검증까지 완료.
-- 원격/스테이징 DB에는 별도로 HeidiSQL에서 실행 필요.
--
-- reg_offering_records(헌금)와 대칭 구조. 지출 항목(category)은 프론트 ExpenseInput.jsx의
-- 26종 고정 목록(담임목사/목회비/부교역자/... 등)을 자유 입력 문자열로 저장 — 교회마다
-- 항목 구성이 다를 수 있어 ENUM 대신 offering.category와 동일하게 VARCHAR로 설계.
--
-- 원본 mock(Expense.jsx AllTab)의 "상태"(정상/확인) 컬럼은 실제로는 항상 증빙 유무와
-- 1:1로 일치하는 데이터였음 — 별도 컬럼을 두지 않고 receipt_files 유무로 프론트에서 derive.
-- "예산 대비" 사용률(CategoryTab)은 예산 테이블이 아직 없어(다음 세션: 예산 관리) 이번엔
-- 대응 컬럼/쿼리 없이 "예산 미설정"으로 표시.

CREATE TABLE `reg_expense_records` (
  `id`            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `church_id`     BIGINT(20) UNSIGNED NOT NULL COMMENT '교회 ID',
  `expense_date`  DATE NOT NULL COMMENT '지출 날짜',
  `category`      VARCHAR(50) NOT NULL COMMENT '지출 항목 (자유 입력 — 프론트 26종 고정 목록 참고)',
  `amount`        INT(11) UNSIGNED NOT NULL COMMENT '지출 금액 (원)',
  `purpose`       VARCHAR(200) NOT NULL COMMENT '사용 목적',
  `vendor`        VARCHAR(100) NULL DEFAULT NULL COMMENT '거래처',
  `method`        ENUM('cash','transfer','card','etc') NULL DEFAULT NULL COMMENT '결제 수단 (현금/계좌이체/법인카드/기타)',
  `ledger`        VARCHAR(100) NULL DEFAULT NULL COMMENT '출금 원장 (예: 현금, 국민은행(선교비지출))',
  `receipt_files` JSON NULL DEFAULT NULL COMMENT '증빙 첨부파일 목록 [{"url":"...","name":"..."}], 최대 5개',
  `note`          TEXT NULL DEFAULT NULL COMMENT '메모',
  `recorded_by`   BIGINT(20) UNSIGNED NOT NULL COMMENT '등록한 관리자 admin_no (gh_church_admin.admin_no)',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_church_date` (`church_id`, `expense_date`),
  KEY `idx_church_category` (`church_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='교적 - 지출 기록 (재정 > 지출 관리)';
