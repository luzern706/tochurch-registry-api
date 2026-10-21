-- reg_service_applications 신규 테이블
-- 2026-10-21, 교적·재정 서비스 신청 접수 (서비스 권한이 없는 관리자에게 열리는 안내 페이지의 '교적·재정 신청하기')
-- 적용 상태: 로컬 dev DB 2026-10-21(API·브라우저 검증) · 스테이징 DB 2026-10-21(@@hostname ip-172-31-44-66 확인 후 실행, 0건) 적용 완료 / 운영 DB는 미적용 — HeidiSQL에서 직접 실행 필요.
-- 배포 순서: 이 SQL 을 대상 DB 에 먼저 실행한 뒤 API 를 배포한다(테이블이 없으면 v4/onboarding/getStatus 가 500 → 프론트는 fail-open 으로 기존 화면 유지).
--
-- 서비스 권한의 소스는 기존 gh_church_admin_role.perm_registry / perm_finance (02_gh_admin_api 가 관리).
-- 이 테이블은 "신청이 접수되었다"는 기록만 남긴다 — 권한 부여(perm_* = 1)는 02 관리자 화면의 담당자 권한에서
-- 처리하며, 처리 후 status 를 completed 로 바꾸는 것은 02 관리자 쪽 후속 작업이다(현재는 수동/미연동).
-- 교회당 접수(received) 상태 신청은 1건만 허용 — 애플리케이션(OnboardingService::applyService)에서 강제.

CREATE TABLE `reg_service_applications` (
  `application_id`  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `church_id`       BIGINT(20) UNSIGNED NOT NULL COMMENT '신청 교회 — gh_church.church_no',
  `admin_no`        INT(11) NOT NULL COMMENT '신청한 교적 관리자 — gh_church_admin.admin_no',
  `applicant_name`  VARCHAR(100) NULL DEFAULT NULL COMMENT '신청 당시 신청자 이름 스냅샷',
  `applicant_email` VARCHAR(200) NULL DEFAULT NULL COMMENT '신청 당시 안내 메일 수신 주소 스냅샷 — 담당자 안내 메일 발송 대상',
  `status`          ENUM('received','completed','canceled') NOT NULL DEFAULT 'received' COMMENT '접수 / 처리완료(권한 부여됨) / 취소',
  `requested_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '신청 접수 일시',
  `processed_at`    DATETIME NULL DEFAULT NULL COMMENT '처리(완료·취소) 일시',
  `processed_by`    INT(10) UNSIGNED NULL DEFAULT NULL COMMENT '처리한 플랫폼 관리자 — gh_admin.admin_no (02 관리자)',
  `memo`            VARCHAR(500) NULL DEFAULT NULL COMMENT '처리 메모(자동이체 동의 확인 등)',
  PRIMARY KEY (`application_id`),
  KEY `idx_church_status` (`church_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='교적 - 교적·재정 서비스 신청 접수 기록 (권한 부여 자체는 gh_church_admin_role.perm_registry/perm_finance)';
