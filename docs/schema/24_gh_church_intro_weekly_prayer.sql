-- gh_church_intro.weekly_prayer 컬럼 추가 (이번 주 공동 기도제목 — 교회별 한 줄 텍스트)
-- 2026-11-20, 교회로 앱 기도제목 화면(P13) 맨 위 「이번 주 공동 기도제목」 카드와 홈 기도제목 영역 표시용 (두레이 앱 QA 필수 CP-6)
-- 적용 상태: 스테이징(NHN gyohyero_dev, @@hostname tochurch-db-01.novalocal) 2026-11-20 적용 완료(26,388행, NULL 컬럼 추가 확인) · 운영(NHN gyohyero, @@hostname tochurch-db-01.novalocal) 2026-11-20 적용 완료(26,388행, NULL 컬럼 추가 확인) · 로컬 미적용.
-- 배포 순서(반드시 지킬 것):
--   1) 이 SQL 을 대상 DB 에 먼저 실행 (스테이징 → 확인 → 운영, @@hostname 확인 후)
--   2) 03_gh_www_api 배포 — ChurchHomeRepository::getProfile 이 intro.weekly_prayer 를 SELECT 하므로 컬럼이 없으면 교회 프로필 조회 전체가 실패한다
--   3) 04_gh_registry_api / 04_gh_registry_front 배포 (오피스 교회 페이지 > 교회소개 > 「이번 주 공동 기도제목」 입력 칸)
--   4) 03_gh_www_front_renewal 배포 (값이 없으면 카드가 보이지 않으므로 순서 무관)
-- gh_church_intro 는 교회로 공유 테이블(02 관리자·03 앱·04 교적 공용)이라 기존 행에는 영향 없음(NULL 허용, 기본값 NULL).

ALTER TABLE `gh_church_intro`
  ADD COLUMN `weekly_prayer` VARCHAR(100) NULL DEFAULT NULL COMMENT '이번 주 공동 기도제목(교회별 한 줄, 앱 기도제목 화면 상단 카드)' AFTER `directions`;

-- 확인
SHOW COLUMNS FROM gh_church_intro LIKE 'weekly_prayer';
