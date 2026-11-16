# 교적 관리 API — 로컬 테스트 및 AWS 배포 가이드

> 최종 수정: 2026-09-27 (서버 업로드를 소스 정리본 저장소 기준으로 변경)  
> 스택: Laravel 10 / PHP 8.1 / MariaDB 10.11 / Apache (VirtualHost)  
> 데모 배포 경로: `/var/www/gh_registry_api/v_1` , `/var/www/gh_registry_api/v_2`  
> 서비스 배포 경로: `/var/www/gh_registry/api/v_1` , `/var/www/gh_registry/api/v_2`

> **서버에는 정리본 저장소(`04_gh_registry_api_release`)의 파일을 올린다.** 작업 저장소 커밋을 주석 제거 정리본에 반영하는 절차는 [`00_교적_api_서버sync.md`](00_교적_api_서버sync.md) 참고 (배포 전 커밋 → sync → 정리본 커밋 → 정리본 폴더에서 업로드).  
> 로컬 개발·테스트(2~4번)는 지금처럼 작업 저장소에서 한다. DB 변경은 마이그레이션 없이 `docs/schema/*.sql`을 HeidiSQL에서 직접 실행하므로, 새 SQL 파일이 있으면 API 업로드 전에 대상 DB에 먼저 적용한다.

---

## 목차

1. [개발 환경 요구사항](#1-개발-환경-요구사항)
2. [로컬 환경 설정](#2-로컬-환경-설정)
3. [환경변수 설정 (.env)](#3-환경변수-설정-env)
4. [로컬 개발 서버 실행](#4-로컬-개발-서버-실행)
5. [AWS Linux 서버 — 디렉토리 생성](#5-aws-linux-서버--디렉토리-생성)
6. [SFTP 업로드 목록](#6-sftp-업로드-목록)
7. [업로드 후 서버 작업](#7-업로드-후-서버-작업)
8. [블루-그린 무중단 배포 (v_1 / v_2)](#8-블루-그린-무중단-배포-v_1--v_2)
9. [Apache VirtualHost 설정](#9-apache-virtualhost-설정)
10. [배포 후 필수 명령어 (업데이트 시)](#10-배포-후-필수-명령어-업데이트-시)
11. [폴더 권한 문제 해결](#11-폴더-권한-문제-해결)
12. [트러블슈팅](#12-트러블슈팅)

---

## 1. 개발 환경 요구사항

| 항목 | 버전 | 비고 |
|---|---|---|
| PHP | 8.1 이상 | 8.2 권장 |
| Composer | 2.x | PHP 패키지 매니저 |
| MariaDB | 10.11 | MySQL 호환 |
| Apache / Nginx | 최신 | 로컬은 `php artisan serve` 사용 가능 |
| Git | 최신 | 소스 클론용 |

### PHP 확장 모듈 필요 목록

```
php-mbstring
php-xml
php-curl
php-pdo
php-mysql (또는 php-mysqlnd)
php-tokenizer
php-bcmath
php-json
```

---

## 2. 로컬 환경 설정

### 2-1. 소스 클론

```bash
git clone <repo_url> 04_gh_registry_api
cd 04_gh_registry_api
```

### 2-2. Composer 의존성 설치

```bash
composer install
```

> 서버 배포 시에는 개발 패키지 제외:
> ```bash
> composer install --no-dev --optimize-autoloader
> ```

### 2-3. .env 파일 생성

```bash
cp .env.example .env
php artisan key:generate
```

---

## 3. 환경변수 설정 (.env)

`.env.example` 기반으로 아래 항목을 환경에 맞게 수정합니다.

```env
APP_NAME="교적관리API"
APP_ENV=production          # 로컬: local
APP_KEY=                    # key:generate 후 자동 채워짐
APP_DEBUG=false             # 로컬: true
APP_URL=https://api-registry.tochurch.org

LOG_CHANNEL=stack
LOG_LEVEL=warning           # 로컬: debug

# JWT 설정 (firebase/php-jwt v7 — SECRET 최소 32자 필수)
JWT_KEY=registry-api
JWT_SECRET=your-jwt-secret-must-be-at-least-32-chars!
JWT_TTL=3600

# DB 설정
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gyohyero_db
DB_USERNAME=<db_user>
DB_PASSWORD=<db_password>

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

> **주의:** `.env` 파일은 절대 Git에 커밋하지 않습니다. `.env.example` 에만 샘플 작성.

---

## 4. 로컬 개발 서버 실행

```bash
php artisan serve --port=8098
```

- 접속: `http://127.0.0.1:8098`
- 프론트 `.env.development` 의 `VITE_API_BASE_URL=http://127.0.0.1:8098/api` 와 매칭

---

## 5. AWS Linux 서버 — 디렉토리 생성

### 5-1. 데모용 디렉토리 (`gh_registry_api`)

```bash
# 버전 디렉토리까지 한 번에 생성
sudo mkdir -p /var/www/gh_registry_api/v_1

# 소유자 및 권한 설정
sudo chown -R ec2-user:ec2-user /var/www/gh_registry_api
sudo chmod -R 755 /var/www/gh_registry_api
```

### 5-2. 서비스용 디렉토리 (`gh_registry`) — 안 A 구조

프론트와 API 를 하나의 폴더로 통합 관리합니다.

```bash
# API v_1, v_2 디렉토리 생성
sudo mkdir -p /var/www/gh_registry/api/v_1
sudo mkdir -p /var/www/gh_registry/api/v_2

# 프론트 v_1, v_2 디렉토리 생성
sudo mkdir -p /var/www/gh_registry/front/v_1
sudo mkdir -p /var/www/gh_registry/front/v_2

# 소유자 및 권한 설정
sudo chown -R ec2-user:ec2-user /var/www/gh_registry
sudo chmod -R 755 /var/www/gh_registry
```

### 5-3. 생성 후 구조 확인

```bash
ls -al /var/www/ | grep gh_registry
```

```
drwxr-xr-x.  ec2-user ec2-user  gh_registry          ← 서비스용
drwxrwxr-x.  ec2-user ec2-user  gh_registry_api      ← 데모 API
drwxrwxr-x.  ec2-user ec2-user  gh_registry_front    ← 데모 프론트
```

```bash
ls -al /var/www/gh_registry/
```

```
drwxr-xr-x.  api/
│   ├── v_1/
│   └── v_2/
drwxr-xr-x.  front/
    ├── v_1/
    └── v_2/
```

---

## 6. SFTP 업로드 목록

SFTP로 직접 업로드할 파일/폴더 목록입니다. **정리본 폴더**(`D:\1101_free_pj\04_tochurch\00_pj_src\03_git\04_gh_registry_api_release`)에서 올리며, 작업 저장소에서 올리지 않습니다.

업로드 전에 정리본을 최신 커밋에 맞춥니다.

```powershell
# 1. 작업 저장소 커밋 후 sync (결과: PASS 확인)
cd D:\1101_free_pj\04_tochurch\00_pj_src\_export_tools
node bin/sync.js sync -p 04_gh_registry_api

# 2. 정리본 커밋 (변경이 있을 때만 — "추가 0 / 수정 0 / 삭제 0"이면 생략)
cd D:\1101_free_pj\04_tochurch\00_pj_src\03_git\04_gh_registry_api_release
git diff --stat
git add -A
git commit -m "<작업 저장소 커밋과 같은 요지의 메시지>"
```

> sync 검증 중에 정리본 폴더에 `vendor/`가 생기지만 업로드하지 않습니다(아래 제외 목록). `.env.example`은 정리본의 템플릿 파일입니다.

### ✅ 업로드할 것

```
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
artisan
composer.json
composer.lock
.env.example
```

### ❌ 제외할 것

| 제외 항목 | 이유 |
|---|---|
| `vendor/` | 서버에서 `composer install` 로 생성 |
| `node_modules/` | API 서버에서 불필요 |
| `.env` | 서버 전용 값이 다름 — 서버에서 직접 작성 |
| `.git/` | 용량 큼, 서버에서 Git 사용 안 함 |
| `.claude/` | 개발 도구 설정, 불필요 |
| `.idea/` | IDE 설정, 불필요 |
| `package.json`, `package-lock.json`, `vite.config.js` | API 서버에서 불필요 |
| `storage/logs/*.log` | 로그 파일 제외 (폴더 구조만 업로드) |

> `storage/` 는 폴더 구조(빈 폴더)만 있으면 됩니다.  
> 서버에서 `mkdir -p` 로 직접 생성해도 동일합니다. → [5번 참고](#5-aws-linux-서버--디렉토리-생성)

---

## 7. 업로드 후 서버 작업

### 7-1. 의존성 설치

**데모용:**
```bash
cd /var/www/gh_registry_api/v_1
composer install --no-dev --optimize-autoloader
```

**서비스용:**
```bash
cd /var/www/gh_registry/api/v_1
composer install --no-dev --optimize-autoloader
```

### 7-2. .env 파일 생성 (최초 1회)

```bash
cp .env.example .env
vi .env    # 내용 직접 입력
php artisan key:generate
```

### 7-3. Laravel 전용 폴더 권한 설정 (핵심)

Laravel은 `storage/` 와 `bootstrap/cache/` 에 **쓰기 권한**이 없으면 동작하지 않습니다.  
이 부분이 배포 후 동작 안 하는 가장 흔한 원인입니다.

**데모용:**
```bash
cd /var/www/gh_registry_api/v_1

# storage 하위 디렉토리 생성 (없을 경우)
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
mkdir -p storage/logs
mkdir -p bootstrap/cache

# 소유자 및 권한 설정
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

**서비스용:**
```bash
cd /var/www/gh_registry/api/v_1

# storage 하위 디렉토리 생성 (없을 경우)
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
mkdir -p storage/logs
mkdir -p bootstrap/cache

# 소유자 및 권한 설정
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

> **왜 `apache` 그룹인가?**  
> Apache 프로세스는 `apache` 유저로 실행됩니다. `ec2-user:apache` 로 그룹 소유 설정 후 그룹에 775 권한을 주면 ec2-user(배포)와 apache(실행) 양쪽이 모두 쓸 수 있습니다.

### 7-4. 권한 확인

**데모용:**
```bash
ls -al /var/www/gh_registry_api/v_1/storage/
ls -al /var/www/gh_registry_api/v_1/bootstrap/cache/
```

**서비스용:**
```bash
ls -al /var/www/gh_registry/api/v_1/storage/
ls -al /var/www/gh_registry/api/v_1/bootstrap/cache/
```

정상 상태 예시:

```
drwxrwxr-x. ec2-user apache storage/
drwxrwxr-x. ec2-user apache bootstrap/cache/
```

### 7-5. 캐시 최적화

```bash
php artisan optimize        # config·route 캐시 생성 (Laravel 9/10 기준, 뷰 캐시는 포함되지 않음)
php artisan view:cache      # 뷰 캐시는 별도 실행
```

---

## 8. 블루-그린 무중단 배포 (v_1 / v_2)

소스 업데이트를 서비스 중단 없이 적용하는 방식입니다.  
`v_1`, `v_2` 를 번갈아 사용하며 Apache 경로 전환만으로 배포합니다.

### 8-1. 개념

```
현재 운영: v_1  ←  Apache DocumentRoot
업데이트:  v_2 에 새 소스 업로드 + 준비 (서비스 영향 없음)
전환:      Apache DocumentRoot → v_2 로 변경 후 reload (무중단)
다음 업데이트: v_1 에 작업 후 다시 전환
```

### 8-2. v_2 초기 생성 (v_1 완료 후 1회만)

`-a` 옵션으로 권한, 소유자, 타임스탬프까지 그대로 복사합니다.

**데모용:**
```bash
sudo cp -a /var/www/gh_registry_api/v_1 /var/www/gh_registry_api/v_2
```

**서비스용:**
```bash
sudo cp -a /var/www/gh_registry/api/v_1 /var/www/gh_registry/api/v_2
```

> `.env`, `vendor/`, `storage/`, `bootstrap/cache/` 전부 그대로 복사되므로  
> 별도 설정 없이 바로 사용 가능합니다.

### 8-3. 복사 후 권한 확인

**데모용:**
```bash
ls -al /var/www/gh_registry_api/
ls -al /var/www/gh_registry_api/v_2/storage/
ls -al /var/www/gh_registry_api/v_2/bootstrap/cache/
```

혹시 권한이 틀어졌다면 일괄 복구:

```bash
sudo chown -R ec2-user:apache /var/www/gh_registry_api/v_2/storage
sudo chown -R ec2-user:apache /var/www/gh_registry_api/v_2/bootstrap/cache
sudo chmod -R 775 /var/www/gh_registry_api/v_2/storage
sudo chmod -R 775 /var/www/gh_registry_api/v_2/bootstrap/cache
```

**서비스용:**
```bash
ls -al /var/www/gh_registry/api/
ls -al /var/www/gh_registry/api/v_2/storage/
ls -al /var/www/gh_registry/api/v_2/bootstrap/cache/
```

혹시 권한이 틀어졌다면 일괄 복구:

```bash
sudo chown -R ec2-user:apache /var/www/gh_registry/api/v_2/storage
sudo chown -R ec2-user:apache /var/www/gh_registry/api/v_2/bootstrap/cache
sudo chmod -R 775 /var/www/gh_registry/api/v_2/storage
sudo chmod -R 775 /var/www/gh_registry/api/v_2/bootstrap/cache
```

### 8-4. 업데이트 배포 순서

> 1번 업로드 전에 작업 저장소 커밋 → sync → 정리본 커밋을 먼저 한다([6번](#6-sftp-업로드-목록)).
>
> ⚠️ **대기 버전에는 변경 파일만이 아니라 [6번 업로드 목록] 전체를 올리고, 전환 직전에 두 폴더를 비교한다.** 변경분만 올리면 반대 폴더가 건너뛴 이전 배포가 전환 때 사라진다 → [8-6 블루/그린 배포 규칙](#8-6-블루그린-배포-규칙--대기-폴더에는-항상-전체를-올린다-누락-방지)
>
> **`config:cache` 사용 가능:** `app/` 코드는 환경 값을 `config()`로만 읽는다(2026-09-27 `GeocodeHelper`·`S3FileHelper` 수정). 새 코드에서 `env()`를 직접 부르면 `config:cache` 후 null이 되므로 `config/*.php`에 키를 추가하고 `config()`로 읽는다.

**데모용:**
```bash
# 1. 정리본 폴더(04_gh_registry_api_release)에서 현재 대기 버전에 소스 업로드 (SFTP)
#    현재 v_1 운영 중이면 → v_2 에 업로드

# 2. 대기 버전에서 composer, 캐시 작업
cd /var/www/gh_registry_api/v_2
composer install --no-dev --optimize-autoloader
php artisan optimize        # config·route 캐시 생성 (Laravel 9/10 기준, 뷰 캐시는 포함되지 않음)
php artisan view:cache      # 뷰 캐시는 별도 실행

# 3. Apache 경로 전환 (v_1 → v_2)
sudo sed -i 's|gh_registry_api/v_1/public|gh_registry_api/v_2/public|g' \
    /etc/httpd/conf.d/gh_registry_api.conf

# 4. 설정 확인 후 무중단 reload
sudo apachectl configtest
sudo systemctl reload httpd   # restart 아닌 reload — 무중단

# 다음 업데이트 시: v_1 에 작업 후 경로를 다시 v_1 로 전환
```

**서비스용:**
```bash
# 1. 정리본 폴더(04_gh_registry_api_release)에서 현재 대기 버전에 소스 업로드 (SFTP)
#    현재 v_1 운영 중이면 → v_2 에 업로드

# 2. 대기 버전에서 composer, 캐시 작업
cd /var/www/gh_registry/api/v_2
composer install --no-dev --optimize-autoloader
php artisan optimize        # config·route 캐시 생성 (Laravel 9/10 기준, 뷰 캐시는 포함되지 않음)
php artisan view:cache      # 뷰 캐시는 별도 실행

# 3. Apache 경로 전환 (v_1 → v_2)
sudo sed -i 's|gh_registry/api/v_1/public|gh_registry/api/v_2/public|g' \
    /etc/httpd/conf.d/gh_registry_api.conf

# 4. 설정 확인 후 무중단 reload
sudo apachectl configtest
sudo systemctl reload httpd   # restart 아닌 reload — 무중단

# 다음 업데이트 시: v_1 에 작업 후 경로를 다시 v_1 로 전환
```

> **주의:** `systemctl restart` 는 프로세스 재시작으로 순간 중단 발생.  
> 반드시 `systemctl reload` 를 사용합니다.

### 8-5. 업로드 후 캐시 정리와 데몬 재시작 (이전에 서비스하던 폴더를 다시 쓰는 경우)

8-4 처럼 직전까지 서비스하던 폴더(`v_1` ↔ `v_2`)를 대기 폴더로 다시 쓰면 `bootstrap/cache/`에 **예전 캐시 파일이 남아 있다**. 업로드 때 `bootstrap/cache/*.php`는 제외하므로 소스를 덮어써도 지워지지 않고, 라우트 캐시가 남아 있으면 새로 추가한 라우트가 404가 된다. (2026-10-24 `03_gh_www_api` 신고 API 배포 때 정리한 절차를 이 프로젝트에도 동일 적용)

**순서** (대기 폴더에서, 7-3 권한 설정 후)

```bash
# 재사용 폴더는 storage·bootstrap/cache 가 apache 소유라 ec2-user 가 쓰지 못할 수 있다 — 먼저 맞춘다
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
mkdir -p storage/logs storage/framework/{cache,sessions,views}
php artisan optimize:clear      # 예전 캐시(config·route·view·events·compiled) 모두 삭제
composer install --no-dev --optimize-autoloader   # composer.json/lock 을 바꾼 배포만 (아니면 생략)
php artisan optimize            # config·route 캐시 생성 (Laravel 9/10 은 뷰 캐시 미포함)
php artisan view:cache          # 뷰 캐시는 별도 실행 (7-5 의 개별 명령 3개와 같은 결과)
php artisan route:list | grep <새 라우트>   # 새 라우트가 보이는지 확인
```

캐시 생성 후 **소유권·권한을 한 번 더 맞춘다.** `ec2-user` 로 `php artisan` 을 실행하면 새로 생기는 파일(`laravel.log`, 컴파일된 뷰)이 644 라서 apache 그룹(php-fpm)이 쓰지 못하고, 나중에 `Permission denied` 가 난다.

```bash
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;   # 선택: 새 파일이 apache 그룹으로 생성되게
```

SELinux 를 쓰는 서버라면 `sudo chcon -R -t httpd_sys_rw_content_t storage bootstrap/cache` 도 필요할 수 있다.

Apache 경로 전환(8-4 의 3번) 후:

```bash
sudo apachectl configtest      # "Syntax OK" 확인
sudo systemctl reload httpd    # 새 conf 반영 (무중단)
sudo systemctl restart php-fpm # OPcache·realpath 캐시 비우기 — 전환 후 마지막에 한다 (php-fpm 구성인 서버만)
```

- `optimize` 는 캐시를 새로 만들 뿐 clear 나 데몬 재시작을 하지 않는다. 재사용 폴더라면 `optimize:clear` 를 먼저 하고, 재시작은 별도로 한다.
- 재시작은 캐시를 만든 뒤에 한다. 먼저 했다면 캐시 생성 후 한 번 더.
- `optimize`(=`config:cache`) 전에 `.env` 가 운영 값이고 `storage/framework/views` 폴더가 있어야 한다.
- php-fpm 구성인지 확인: `systemctl is-active php-fpm` (다른 두 API 서버는 php-fpm 구조). php-fpm 이 아니면 httpd reload 만으로 충분하다.
- 처음 만든 폴더(8-2 `v_2` 초기 생성 직후)는 이전 캐시가 없어 `optimize:clear` 는 생략할 수 있다.

### 8-6. 블루/그린 배포 규칙 — 대기 폴더에는 "항상 전체"를 올린다 (누락 방지)

`v_1`/`v_2` 는 번갈아 서비스되고, 각 폴더는 **자기 직전 배포 상태**에 머문다. 이번 변경분만 대기 폴더에 올리면 **그 폴더가 건너뛴 이전 배포가 빠진다.**

> **예시** — 파일 1·2·3 을 바꾼 배포를 `v_2` 에 올려 전환했다. 다음 배포에서 파일 4·5·6 만 바꿔 대기 폴더인 `v_1` 에 올리고 전환하면, `v_1` 에는 1·2·3 이 없으므로 **전환하는 순간 이전 배포가 사라진다.** 오류 없이 조용히 발생해서 알아채기 어렵다. (같은 사고가 `03_gh_www_api` 에서 `resources/` 변경 누락으로 실제 발생)

| # | 규칙 | 이유 |
|---|---|---|
| 1 | **대기 폴더를 먼저 확인**한다 (서비스 중이 아닌 쪽) — `grep -R "DocumentRoot" /etc/httpd/conf.d/` | 서비스 중인 폴더에 덮어쓰는 사고 방지 |
| 2 | 대기 폴더에는 **변경 파일만이 아니라 정리본(`04_gh_registry_api_release`)의 [6번 업로드 목록] 전체**를 덮어쓴다. "변경분 패치"만 올리는 방식은 쓰지 않는다 | 정리본은 누적된 최신 전체 소스라서, 전체를 올리면 이전 배포 내용도 함께 들어간다 |
| 3 | 올린 뒤 **정리본과 해시 비교**(아래)로 `SAME` 을 확인한다 | 누락·옛 파일은 눈으로 보이지 않는다 |
| 4 | **전환 직전에 두 폴더를 서로 비교**한다(아래). 차이가 이번에 의도한 변경뿐인지 확인한다 | 한쪽에만 들어간 변경을 전환 전에 발견 |
| 5 | **서비스 중인 폴더를 직접 수정하지 않는다.** 긴급 수정도 대기 폴더에서 하고 전환한다. 불가피하게 직접 고쳤다면 같은 파일을 반대 폴더에도 즉시 반영하고 배포 기록에 적는다 | 직접 수정이 다음 전환 때 사라진다 |
| 6 | 전환 후 이상 없음을 확인하면 **이전 서비스 폴더도 같은 내용으로 맞춰 둔다.** 단, 롤백이 필요 없다고 판단한 뒤에 한다 | 이전 폴더는 롤백 지점이므로 그 전에는 건드리지 않는다 |
| 7 | `.env` 는 폴더마다 다르므로 **두 폴더의 키 이름을 비교**한다(값은 출력 안 됨) | 새 키가 한쪽에만 들어가는 누락 방지 |
| 8 | **DB 는 두 폴더가 공유**한다. 롤백이 가능하도록 DB 변경은 옛 코드와도 호환되게 한다 — 테이블·컬럼 **추가**는 가능, **삭제·이름 변경**은 코드 전환이 안정화된 뒤 별도 단계로 | 롤백해서 옛 폴더로 돌아가도 DB 는 이미 바뀐 상태 |
| 9 | **같은 서버에 동시 배포하지 않는다.** 다른 세션·담당자가 같은 conf 나 대기 폴더를 만지고 있지 않은지 확인하고, 다른 쪽 배포가 끝난 뒤 서비스 폴더를 다시 확인해서 진행한다 | 서로의 업로드·conf 전환을 덮어쓴다 |
| 10 | 배포할 때마다 아래 **배포 기록**에 한 줄 남긴다 | "어느 폴더가 어디까지 반영됐는지" 확인용 |

**정리본 ↔ 서버 폴더 해시 비교** (`.env` · `vendor` · `storage` · `bootstrap/cache` 는 서버마다 달라 제외, `tr -d '\r'` 은 줄바꿈 차이 방지)

```bash
# 로컬 정리본 폴더 (Git Bash)
cd /d/1101_free_pj/04_tochurch/00_pj_src/03_git/04_gh_registry_api_release
find app config routes resources database -type f | sort | while read f; do echo "$(tr -d '\r' < "$f" | sha256sum | cut -c1-64)  $f"; done > manifest_release.txt

# 서버 폴더 — 블루·그린 각각
cd /var/www/gh_registry/api/v_N
find app config routes resources database -type f | sort | while read f; do echo "$(tr -d '\r' < "$f" | sha256sum | cut -c1-64)  $f"; done > /tmp/manifest_v_N.txt

# 두 파일을 한곳에 모아 비교
diff manifest_release.txt manifest_v_N.txt && echo SAME
```

- `SAME` 이면 그 폴더는 정리본과 일치한다. 차이가 있으면 `<` 는 서버에 **없거나 내용이 다른 파일(누락)**, `>` 는 서버에만 있는 파일(삭제된 옛 파일)이다.

**전환 직전 두 폴더 비교** (서비스 중 ↔ 대기)

```bash
cd /var/www/gh_registry/api
diff -rq --exclude=.env --exclude=vendor --exclude=storage --exclude=bootstrap v_1 v_2
```

- 아무것도 나오지 않으면 두 폴더가 같은 상태이다. 이번 배포 직후 대기 폴더에서 이 명령을 실행하면 **이번 배포의 변경분만 차이로 나와야 한다.** 의도하지 않은 파일이 나오면 한쪽에서 누락·직접 수정이 있었다는 뜻이므로 전환하지 말고 원인을 확인한다.
- `.env` 키 비교: `diff <(cut -d= -f1 v_1/.env | sort) <(cut -d= -f1 v_2/.env | sort) && echo SAME`

**배포 기록** (배포할 때마다 한 줄 추가)

| 날짜 | 서버 | 폴더(블루/그린) | 반영 정리본 커밋 | 해시 비교 | 전환 여부 |
|---|---|---|---|---|---|
| | | | | | |

---

## 9. Apache VirtualHost 설정

> 서버에서 이미 설정된 내용 기준으로 정리합니다.

### 데모용 — HTTP (포트 7003)

```apache
<VirtualHost *:7003>
    ServerName api-registry.tochurch.org
    DocumentRoot /var/www/gh_registry_api/v_1/public

    <Directory /var/www/gh_registry_api/v_1/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_METHOD} OPTIONS
        RewriteRule ^(.*)$ $1 [R=200,L]
    </Directory>

    ErrorLog logs/registry-api_7003_error.log
    CustomLog logs/registry-api_7003_access.log combined
</VirtualHost>
```

### 데모용 — HTTPS (포트 7103) — CORS 설정 포함

```apache
<VirtualHost *:7103>
    ServerName api-registry.tochurch.org
    DocumentRoot /var/www/gh_registry_api/v_1/public

    Include conf.d/gh_ssl_common.inc

    # CORS — tochurch.org 서브도메인만 허용
    SetEnvIf Origin "^https://(admin|admin-dev|registry|registry-dev|www|church|dev)\.tochurch\.org$" CORS_ORIGIN=$0
    Header always set Access-Control-Allow-Origin "%{CORS_ORIGIN}e" env=CORS_ORIGIN
    Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS, PUT, DELETE"
    Header always set Access-Control-Allow-Headers "Authorization, Content-Type, X-Requested-With"
    Header always set Access-Control-Allow-Credentials "true"
    Header always set Vary "Origin"

    <Directory /var/www/gh_registry_api/v_1/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_METHOD} OPTIONS
        RewriteRule ^(.*)$ $1 [R=200,L]
    </Directory>

    ErrorLog logs/gh_registry-api_7103_https_error.log
    CustomLog logs/gh_registry-api_7103_https_access.log combined
</VirtualHost>
```

### 서비스용 — HTTP (포트 7003)

```apache
<VirtualHost *:7003>
    ServerName registry.tochurch.org
    DocumentRoot /var/www/gh_registry/api/v_1/public

    <Directory /var/www/gh_registry/api/v_1/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_METHOD} OPTIONS
        RewriteRule ^(.*)$ $1 [R=200,L]
    </Directory>

    ErrorLog logs/gh_registry-api_7003_error.log
    CustomLog logs/gh_registry-api_7003_access.log combined
</VirtualHost>
```

### 서비스용 — HTTPS (포트 7103) — CORS 설정 포함

```apache
<VirtualHost *:7103>
    ServerName registry.tochurch.org
    DocumentRoot /var/www/gh_registry/api/v_1/public

    Include conf.d/gh_ssl_common.inc

    # CORS — tochurch.org 서브도메인만 허용
    SetEnvIf Origin "^https://(admin|admin-dev|registry|registry-dev|www|church|dev)\.tochurch\.org$" CORS_ORIGIN=$0
    Header always set Access-Control-Allow-Origin "%{CORS_ORIGIN}e" env=CORS_ORIGIN
    Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS, PUT, DELETE"
    Header always set Access-Control-Allow-Headers "Authorization, Content-Type, X-Requested-With"
    Header always set Access-Control-Allow-Credentials "true"
    Header always set Vary "Origin"

    <Directory /var/www/gh_registry/api/v_1/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_METHOD} OPTIONS
        RewriteRule ^(.*)$ $1 [R=200,L]
    </Directory>

    ErrorLog logs/gh_registry-api_7103_https_error.log
    CustomLog logs/gh_registry-api_7103_https_access.log combined
</VirtualHost>
```

### VirtualHost 파일 위치 및 적용

```bash
# VirtualHost 설정 파일 편집
sudo vi /etc/httpd/conf.d/gh_registry_api.conf

# 설정 문법 검사
sudo apachectl configtest

# Apache 재시작 (설정 반영)
sudo systemctl reload httpd
```

---

## 10. 배포 후 필수 명령어 (업데이트 시)

업데이트 배포 시 매번 실행해야 하는 명령어 목록:

**데모용:**
```bash
cd /var/www/gh_registry_api/v_1

# 패키지 업데이트 (composer.json 변경 시)
composer install --no-dev --optimize-autoloader

# 캐시 클리어 및 재생성 (코드/설정 변경 시 필수)
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# 권한 확인 (문제 발생 시)
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Apache 재시작
sudo systemctl reload httpd
```

**서비스용:**
```bash
cd /var/www/gh_registry/api/v_1

# 패키지 업데이트 (composer.json 변경 시)
composer install --no-dev --optimize-autoloader

# 캐시 클리어 및 재생성 (코드/설정 변경 시 필수)
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# 권한 확인 (문제 발생 시)
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Apache 재시작
sudo systemctl reload httpd
```

---

## 11. 폴더 권한 문제 해결

배포 후 **500 에러** 또는 **로그 기록 안 됨** 현상은 대부분 권한 문제입니다.

### 증상별 원인 및 해결

| 증상 | 원인 | 해결 |
|---|---|---|
| 500 Internal Server Error | `storage/` 쓰기 권한 없음 | `chmod -R 775 storage` |
| 뷰 컴파일 오류 | `storage/framework/views/` 없거나 권한 없음 | 디렉토리 생성 후 권한 설정 |
| 캐시 오류 | `bootstrap/cache/` 쓰기 권한 없음 | `chmod -R 775 bootstrap/cache` |
| 로그가 안 쌓임 | `storage/logs/` 권한 없음 | `chown ec2-user:apache storage/logs` |
| config:cache 실패 | `bootstrap/cache/` 없음 | `mkdir -p bootstrap/cache` |

### 권한 일괄 복구 명령어

문제 발생 시 아래 명령어로 일괄 복구:

**데모용:**
```bash
cd /var/www/gh_registry_api/v_1

# 디렉토리 구조 확인 및 생성
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# 소유자 설정
sudo chown -R ec2-user:apache storage
sudo chown -R ec2-user:apache bootstrap/cache

# 권한 설정
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache

# public/.htaccess 권한도 확인
sudo chmod 644 public/.htaccess
```

**서비스용:**
```bash
cd /var/www/gh_registry/api/v_1

# 디렉토리 구조 확인 및 생성
mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# 소유자 설정
sudo chown -R ec2-user:apache storage
sudo chown -R ec2-user:apache bootstrap/cache

# 권한 설정
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache

# public/.htaccess 권한도 확인
sudo chmod 644 public/.htaccess
```

### Apache 프로세스 유저 확인

```bash
# Apache 실행 유저 확인
ps aux | grep httpd | head -3

# apache 그룹에 ec2-user 포함 여부 확인
groups ec2-user
id apache
```

---

## 12. 트러블슈팅

### .htaccess 동작 안 함 (404)

`AllowOverride All` 설정 확인 후 Apache 재시작:

```bash
sudo apachectl configtest
sudo systemctl restart httpd
```

`mod_rewrite` 모듈 활성화 확인:

```bash
httpd -M | grep rewrite
# rewrite_module (shared) 가 보여야 정상
```

### JWT "Provided key is too short" 오류

`.env` 의 `JWT_SECRET` 이 **32자 미만**일 때 발생합니다.

```bash
# 안전한 랜덤 키 생성
openssl rand -base64 48
```

생성된 값을 `.env` 의 `JWT_SECRET` 에 붙여넣기.

### DB 연결 실패

```bash
# MariaDB 서비스 상태 확인
sudo systemctl status mariadb

# 연결 테스트
php artisan db:show

# .env DB 설정 캐시 반영
php artisan config:clear
php artisan config:cache
```

### 로그 확인

**데모용:**
```bash
# Laravel 애플리케이션 로그
tail -f /var/www/gh_registry_api/v_1/storage/logs/laravel.log

# Apache 에러 로그
tail -f /etc/httpd/logs/gh_registry-api_7103_https_error.log
tail -f /etc/httpd/logs/registry-api_7003_access.log
```

**서비스용:**
```bash
# Laravel 애플리케이션 로그
tail -f /var/www/gh_registry/api/v_1/storage/logs/laravel.log

# Apache 에러 로그
tail -f /etc/httpd/logs/gh_registry-api_7103_https_error.log
tail -f /etc/httpd/logs/gh_registry-api_7003_access.log
```

---

## 빠른 배포 명령어 요약

### 업로드 전 (로컬)

```bash
# 작업 저장소 커밋 후
cd D:/1101_free_pj/04_tochurch/00_pj_src/_export_tools
node bin/sync.js sync -p 04_gh_registry_api   # 결과: PASS 확인
cd ../03_git/04_gh_registry_api_release
git diff --stat && git add -A && git commit -m "<메시지>"   # 변경이 있을 때만
# → 이 정리본 폴더의 파일을 SFTP로 업로드 (6번 목록)
```

### 데모용

```bash
# ── 최초 배포 ──────────────────────────────────────
sudo mkdir -p /var/www/gh_registry_api/v_1
sudo chown -R ec2-user:ec2-user /var/www/gh_registry_api

cd /var/www/gh_registry_api/v_1
composer install --no-dev --optimize-autoloader

cp .env.example .env && vi .env          # .env 내용 입력
php artisan key:generate

mkdir -p storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
sudo systemctl reload httpd

# ── 업데이트 배포 ───────────────────────────────────
cd /var/www/gh_registry_api/v_1
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo systemctl reload httpd
```

### 서비스용

```bash
# ── 최초 배포 ──────────────────────────────────────
sudo mkdir -p /var/www/gh_registry/api/v_1
sudo chown -R ec2-user:ec2-user /var/www/gh_registry

cd /var/www/gh_registry/api/v_1
composer install --no-dev --optimize-autoloader

cp .env.example .env && vi .env          # .env 내용 입력
php artisan key:generate

mkdir -p storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
sudo systemctl reload httpd

# ── 업데이트 배포 ───────────────────────────────────
cd /var/www/gh_registry/api/v_1
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R ec2-user:apache storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo systemctl reload httpd
```
