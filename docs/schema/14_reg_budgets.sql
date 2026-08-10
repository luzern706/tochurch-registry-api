-- reg_budgets 신규 테이블
-- 2026-08-09, 재정 > 예산 관리(/finance/budget) 프론트 연동
-- 로컬 dev DB(church_id=33632)에 이 세션에서 직접 적용 완료(사용자 승인) — 브라우저 실연동 검증까지 완료.
-- 원격/스테이징 DB에는 별도로 HeidiSQL에서 실행 필요.
--
-- 예산은 원본 mock과 동일하게 7개 대분류(인건비/사역비/운영비/시설비/선교비/행사비/예비비) 단위로
-- 연도별 1건씩 저장한다. "집행액"(현재 지출 실적)은 이 테이블에 저장하지 않고, 매 조회 시
-- reg_expense_records를 App\Constants\ExpenseCategoryGroup 매핑으로 롤업해 실시간 계산한다
-- (헌금/지출과 마찬가지로 실데이터 기준 계산 — 스냅샷 캐시 없음).

CREATE TABLE `reg_budgets` (
  `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `church_id`   BIGINT(20) UNSIGNED NOT NULL COMMENT '교회 ID',
  `year`        SMALLINT(5) UNSIGNED NOT NULL COMMENT '예산 연도',
  `category`    VARCHAR(20) NOT NULL COMMENT '예산 대분류 (인건비/사역비/운영비/시설비/선교비/행사비/예비비)',
  `amount`      BIGINT(20) UNSIGNED NOT NULL COMMENT '연간 예산액',
  `formula`     VARCHAR(200) NULL DEFAULT NULL COMMENT '계산식 메모 (예: 8,700,000 × 12)',
  `updated_by`  BIGINT(20) UNSIGNED NOT NULL COMMENT '최종 등록/수정한 관리자 admin_no (gh_church_admin.admin_no)',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_church_year_category` (`church_id`, `year`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='교적 - 연간 예산 (재정 > 예산 관리, 대분류 7종 단위)';
