-- ================================================================
-- 09_reg_role_permissions_v2.sql
-- 권한 관리 화면(설정 > 권한설정) 재설계 — 08_reg_role_permissions.sql 대체.
--
-- 변경 사항:
--   1. can_view/can_manage 2개 컬럼 → can_access 1개로 단순화 (조회/관리 구분 폐지)
--   2. menu_code(중메뉴, 17개) → page_code(소메뉴/실제 화면 단위, 약 30개)로 저장 단위를
--      세분화 — 교인 하위의 "교인목록"/"가입승인"처럼 화면 단위로 독립적인 토글 필요
--      (App\Constants\PermissionMenuTree::allPages() 참조)
--
-- 하위 화면이 없는 메뉴(게시판, 재정/관리 미구현 항목 등)는 메뉴 코드 자신을 유일한
-- page_code로 사용 — 기존 17개 코드 중 다수가 그대로 page_code 값으로 재사용됨.
--
-- 백엔드 API 미들웨어(CheckPermissionMiddleware)는 여전히 "중메뉴" 단위로만 라우트를
-- 보호함 — 소속 page_code 중 하나라도 접근 가능하면 해당 메뉴의 API 전체 허용
-- (App\Services\PermissionService::hasMenuAccess). 화면 단위 정밀 차단은 프론트
-- 라우트 가드(PermissionGuard)에서 담당.
--
-- 2025-XX-XX 기준 로컬/dev 모두 이 테이블에 저장된 행이 0건이었음을 확인하고
-- DROP 후 재생성 — 데이터 마이그레이션 불필요.
-- ================================================================

USE gyohyero;

DROP TABLE IF EXISTS `reg_role_permissions`;

CREATE TABLE `reg_role_permissions` (
    `id`         BIGINT      NOT NULL AUTO_INCREMENT,
    `church_id`  BIGINT      NOT NULL COMMENT '교회 ID',
    `role`       VARCHAR(20) NOT NULL COMMENT 'admin_type 값 (pastor/minister/volunteer — admin은 저장 안 함)',
    `page_code`  VARCHAR(30) NOT NULL COMMENT 'PermissionMenuTree::allPages() 값 (MEMBER_LIST, ATTENDANCE_INPUT 등)',
    `can_access` TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '접근 권한 (조회/관리 구분 없음)',
    `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_church_role_page` (`church_id`, `role`, `page_code`),
    KEY `idx_church_role` (`church_id`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='역할별 화면(page) 접근 권한 — 교회별 커스터마이즈, 미설정 시 앱 기본 정책 사용';

-- 확인 쿼리
-- SELECT * FROM reg_role_permissions WHERE church_id = 33632 ORDER BY role, page_code;
