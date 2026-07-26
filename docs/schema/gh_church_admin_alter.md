# gh_church_admin 테이블 변경 이력

> HeidiSQL에서 직접 실행. Laravel 마이그레이션 사용 금지.

---

## [2026-07-17] email / phone 컬럼 추가 (내정보 기능)

"내정보" 프로필 설정 화면(이메일/휴대폰 입력란)을 위해 추가. 둘 다 선택 입력.

```sql
ALTER TABLE `gh_church_admin`
  ADD COLUMN `email` VARCHAR(100) NULL DEFAULT NULL COMMENT '이메일'
  AFTER `name`;

ALTER TABLE `gh_church_admin`
  ADD COLUMN `phone` VARCHAR(20) NULL DEFAULT NULL COMMENT '휴대폰 번호'
  AFTER `email`;
```

---

## [2026-06-26] admin_type 컬럼 추가

교회별 관리자 구분을 위해 추가.

```sql
ALTER TABLE `gh_church_admin`
  ADD COLUMN `admin_type` VARCHAR(10) NOT NULL DEFAULT 'admin'
  COMMENT 'admin=관리자, pastor=담임목회자, minister=사역자, volunteer=봉사자'
  AFTER `status`;
```

---

## [2026-06-26] status 컬럼 확장 (소프트 삭제 도입)

실삭제 → 소프트 삭제 전환.

```sql
ALTER TABLE `gh_church_admin`
  MODIFY COLUMN `status` VARCHAR(10) NOT NULL DEFAULT 'ALIVE'
  COMMENT 'ALIVE=활성, INACTIVE=비활성, DELETE=삭제';
```

---

## [2026-06-27] admin_type / status 값 재정의

관리자 구분 4종, 상태 3종으로 확정.

### 기존 데이터 마이그레이션

```sql
-- admin_type: super → admin
UPDATE `gh_church_admin` SET `admin_type` = 'admin' WHERE `admin_type` = 'super';

-- 기존 WAIT/SUSPEND → INACTIVE
UPDATE `gh_church_admin` SET `status` = 'INACTIVE' WHERE `status` IN ('WAIT', 'SUSPEND');

-- 관리자 계정 admin_type 수동 지정 (admin_no 실제 값으로 변경)
-- UPDATE `gh_church_admin` SET `admin_type` = 'pastor'   WHERE `admin_no` = {담임목회자_admin_no};
-- UPDATE `gh_church_admin` SET `admin_type` = 'minister' WHERE `admin_no` = {사역자_admin_no};
-- UPDATE `gh_church_admin` SET `admin_type` = 'volunteer' WHERE `admin_no` = {봉사자_admin_no};
```

---

## [2026-06-27] name / last_login_at 컬럼 추가 및 코멘트 수정

```sql
-- 이름 컬럼 추가
ALTER TABLE `gh_church_admin`
  ADD COLUMN `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '관리자 이름'
  AFTER `id`;

-- 마지막 로그인 일시 추가
ALTER TABLE `gh_church_admin`
  ADD COLUMN `last_login_at` DATETIME NULL DEFAULT NULL COMMENT '마지막 로그인 일시'
  AFTER `updated`;

-- 코멘트 수정
ALTER TABLE `gh_church_admin`
  MODIFY COLUMN `status` VARCHAR(10) NOT NULL DEFAULT 'ALIVE'
  COMMENT 'ALIVE=활성, INACTIVE=비활성, DELETE=삭제'
  COLLATE 'utf8mb4_unicode_ci';

ALTER TABLE `gh_church_admin`
  MODIFY COLUMN `admin_type` VARCHAR(10) NOT NULL DEFAULT 'admin'
  COMMENT 'admin=관리자, pastor=담임목회자, minister=사역자, volunteer=봉사자'
  COLLATE 'utf8mb4_unicode_ci';
```

---

## 최종 테이블 구조

| 컬럼 | 타입 | 비고 |
|---|---|---|
| admin_no | INT(11) PK AUTO_INCREMENT | |
| church_no | INT(11) FK | gh_church.church_no |
| id | TEXT | 로그인 ID |
| name | VARCHAR(50) | 관리자 이름 |
| email | VARCHAR(100) | NULL 허용, 이메일 |
| phone | VARCHAR(20) | NULL 허용, 휴대폰 번호 |
| password | TEXT | bcrypt 해시 |
| status | VARCHAR(10) | ALIVE=활성 / INACTIVE=비활성 / DELETE=삭제 |
| admin_type | VARCHAR(10) | admin=관리자 / pastor=담임목회자 / minister=사역자 / volunteer=봉사자 |
| registered | TIMESTAMP | DEFAULT current_timestamp() |
| updated | DATETIME | DEFAULT current_timestamp() |
| last_login_at | DATETIME | NULL 허용, 마지막 로그인 일시 |
| passwordTemp | VARCHAR(45) | NULL 허용 |
