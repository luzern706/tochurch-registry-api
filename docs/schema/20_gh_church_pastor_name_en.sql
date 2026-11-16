-- gh_church_pastor.name_en 컬럼 추가 (담임목사 영문 이름)
-- 2026-11-16, 교회로 앱 교회소개·홈 담임목사 카드의 「이름 + 직함 / Rev. 영문 이름」 표시용 (두레이 QA 재수정요청 P-04-5)
-- 적용 상태: 스테이징(NHN gyohyero_dev, @@hostname tochurch-db-01.novalocal) 2026-11-16 적용 완료(26,367행, 컬럼 NULL 추가 확인) · 운영(NHN gyohyero, @@hostname tochurch-db-01.novalocal) 2026-11-16 적용 완료(26,367행, NULL 컬럼 추가 확인) · 로컬 미적용.
-- 배포 순서(반드시 지킬 것):
--   1) 이 SQL 을 대상 DB 에 먼저 실행 (스테이징 → 확인 → 운영, @@hostname 확인 후)
--   2) 03_gh_www_api 배포 — ChurchHomeRepository::getProfile 이 p.name_en 을 SELECT 하므로 컬럼이 없으면 교회 프로필 조회 전체가 실패한다
--   3) 04_gh_registry_api / 04_gh_registry_front 배포 (오피스 교회 페이지 > 교회소개 > 목회자 소개의 「영문 이름」 입력 칸)
-- gh_church_pastor 는 교회로 공유 테이블(02 관리자·03 앱·04 교적 공용)이라 기존 행에는 영향 없음(NULL 허용, 기본값 NULL).

ALTER TABLE `gh_church_pastor`
  ADD COLUMN `name_en` VARCHAR(100) NULL DEFAULT NULL COMMENT '목회자 영문 이름(예: Rev. Deokbong Lee) — 교회소개 담임목사 카드 표시용' AFTER `name`;

-- 확인
SHOW COLUMNS FROM gh_church_pastor LIKE 'name_en';
