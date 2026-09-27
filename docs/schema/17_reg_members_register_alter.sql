-- ============================================================
-- 17_reg_members_register_alter.sql
-- 교인 등록 화면 개편 (/member/list/register) 에 따른 스키마 변경
--
--  1. reg_members
--     - email / password : NOT NULL → NULL 허용
--         · 교인 로그인이 없는 구조(관리자 전용 2티어) — 이메일은 "연락용"(선택), 비밀번호는 입력하지 않음
--         · UNIQUE(email, church_id) 는 유지 (NULL 끼리는 충돌하지 않음)
--     - status : ENUM 에 등록상태 코드 추가 (기존 값 active/inactive/unknown 은 그대로 유지 → 데이터 변환 불필요)
--         · active=재적, new_family=새가족, inactive=장기결석, transferred=이전,
--           removed=제적, deceased=소천, unknown=미분류
--     - unlinked_at : 앱 연결 해제 시각 (앱 연결 상태 "연동해제" 판정용)
--  2. reg_member_profiles
--     - registration_type : 등록 구분 (신규등록/전입/기존교인)
--     - occupation        : 직업 (기존 workplace = 직장/학교명 과 별개)
--  3. reg_member_invites (신규) : 교회로 앱 초대 기록 (불투명 토큰 → member_id)
--
-- 실행: HeidiSQL (Laravel 마이그레이션 사용 금지)
-- 실행 이력: 로컬 dev DB — 2026-09-27 적용 완료(사용자 승인) / dev·staging — 미실행 / 운영 — 미실행
-- ============================================================

USE gyohyero;

-- ------------------------------------------------------------
-- 1. reg_members
-- ------------------------------------------------------------
ALTER TABLE `reg_members`
  MODIFY COLUMN `email`    VARCHAR(100) NULL DEFAULT NULL COMMENT '연락용 이메일 (선택, 로그인 계정 아님)',
  MODIFY COLUMN `password` VARCHAR(255) NULL DEFAULT NULL COMMENT '(미사용) 교인 비밀번호 — 교인 로그인 없음, 성도 본인이 교회로 앱에서 설정. 신규 등록 시 NULL',
  MODIFY COLUMN `status`   ENUM('active','new_family','inactive','transferred','removed','deceased','unknown')
                           NOT NULL DEFAULT 'active'
                           COMMENT '등록상태 코드: active=재적, new_family=새가족, inactive=장기결석, transferred=이전, removed=제적, deceased=소천, unknown=미분류',
  ADD COLUMN `unlinked_at` TIMESTAMP NULL DEFAULT NULL COMMENT '교회로 앱 연결 해제 시각 (NULL=해제 이력 없음, 앱연결상태 "연동해제" 판정용)' AFTER `churchero_user_id`;

-- ------------------------------------------------------------
-- 2. reg_member_profiles
-- ------------------------------------------------------------
ALTER TABLE `reg_member_profiles`
  ADD COLUMN `registration_type` ENUM('new','transfer','existing') NULL DEFAULT NULL
             COMMENT '등록구분: new=신규등록, transfer=전입, existing=기존교인(기존 교적 이전)' AFTER `registered_at`,
  ADD COLUMN `occupation` VARCHAR(100) NULL DEFAULT NULL
             COMMENT '직업 (workplace=직장/학교명 과 별개)' AFTER `workplace`;

-- ------------------------------------------------------------
-- 3. reg_member_invites (신규)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reg_member_invites` (
  `id`                BIGINT(20)   NOT NULL AUTO_INCREMENT COMMENT 'PK',
  `church_id`         BIGINT(20)   NOT NULL COMMENT '교회 ID (gh_church.church_no)',
  `member_id`         BIGINT(20)   NOT NULL COMMENT '초대 대상 교인 ID (reg_members.id)',
  `phone`             VARCHAR(20)  NOT NULL COMMENT '초대 시점 휴대전화 스냅샷',
  `invite_token`      VARCHAR(64)  NOT NULL COMMENT '초대 링크 불투명 토큰 (링크에 member_id 를 직접 노출하지 않음)',
  `status`            ENUM('pending','sent','joined','expired','cancelled') NOT NULL DEFAULT 'pending'
                      COMMENT '상태: pending=기록 저장(문자 게이트웨이 미연동으로 실발송 전), sent=실발송, joined=링크로 가입·연결 완료, expired=만료, cancelled=취소',
  `expires_at`        TIMESTAMP    NULL DEFAULT NULL COMMENT '토큰 만료 시각',
  `joined_account_no` BIGINT(20)   NULL DEFAULT NULL COMMENT '링크로 가입한 교회로 계정 (gh_account.account_no) — 웹앱 연동 시 채움',
  `joined_at`         TIMESTAMP    NULL DEFAULT NULL COMMENT '가입·연결 완료 시각 — 웹앱 연동 시 채움',
  `sent_by`           BIGINT(20)   NOT NULL COMMENT '초대 실행 관리자 (gh_church_admin.admin_no)',
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '초대 기록 생성 시각',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invite_token` (`invite_token`),
  KEY `idx_church_member` (`church_id`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='교회로 앱 초대 기록 (교인 등록 완료 화면의 "초대 보내기")';

-- ------------------------------------------------------------
-- 확인 쿼리
-- ------------------------------------------------------------
-- SHOW CREATE TABLE reg_members;
-- SHOW CREATE TABLE reg_member_profiles;
-- SHOW CREATE TABLE reg_member_invites;
-- SELECT status, COUNT(*) FROM reg_members GROUP BY status;   -- 기존 값 유지 확인
