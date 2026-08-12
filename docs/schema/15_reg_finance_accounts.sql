-- reg_finance_accounts 신규 테이블
-- 2026-08-10, 재정 > 설정 > 계좌관리(`/finance/settings/categories`, 문서상 "재정 카테고리") 프론트 연동
-- 로컬 dev DB(church_id=33632)에 이 세션에서 직접 적용 완료(사용자 승인) — 브라우저 실연동 검증까지 완료.
-- 원격/스테이징 DB에는 별도로 HeidiSQL에서 실행 필요.
--
-- 원본 mock 제목은 "계좌관리" — 헌금/지출 입력 화면의 "출금 원장"/"입력 원장" 드롭다운이
-- 지금까지 DonationInput.jsx/ExpenseInput.jsx에 하드코딩된 3개 문자열(현금/국민은행.../농협...)
-- 이었던 것의 실제 관리 소스가 이 화면. 계좌를 등록하면 헌금·지출 입력 화면의 원장
-- 드롭다운이 이 테이블에서 실시간으로 채워지도록 프론트도 함께 연동.
--
-- reg_offering_records.ledger / reg_expense_records.ledger 는 여전히 자유 입력 문자열
-- 컬럼(FK 아님) — 계좌를 삭제해도 과거 기록의 ledger 문자열은 그대로 남는다(의도된 설계,
-- 회계 기록은 계좌 설정 변경과 무관하게 보존되어야 하므로).

CREATE TABLE `reg_finance_accounts` (
  `id`               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `church_id`        BIGINT(20) UNSIGNED NOT NULL COMMENT '교회 ID',
  `type`             VARCHAR(20) NOT NULL COMMENT '계좌 유형 (현금/보통예금/적금/예치금)',
  `name`             VARCHAR(100) NOT NULL COMMENT '계좌명 (예: 국민은행(선교비전용)) — 헌금·지출 입력의 원장 드롭다운에 표시되는 값',
  `bank`             VARCHAR(50) NULL DEFAULT NULL COMMENT '은행명',
  `account_number`   VARCHAR(50) NULL DEFAULT NULL COMMENT '계좌번호',
  `description`      VARCHAR(200) NULL DEFAULT NULL COMMENT '설명/메모',
  `use_for_offering` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '헌금 입력 화면 원장 드롭다운 노출 여부',
  `use_for_expense`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '지출 입력 화면 원장 드롭다운 노출 여부',
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1 COMMENT '활성 여부 (꺼지면 헌금·지출 입력 모두에서 숨김)',
  `sort_order`       INT(11) NOT NULL DEFAULT 0 COMMENT '목록/드롭다운 표시 순서',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_church` (`church_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='교적 - 재정 계좌/원장 관리 (헌금·지출 입력 화면 원장 드롭다운 소스)';
