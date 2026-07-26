# 교적 관리 API — 로컬 테스트 및 AWS 배포 가이드

> 최종 수정: 2026-07-23 (서비스용 디렉토리 구조 추가)  
> 스택: Laravel 10 / PHP 8.1 / MariaDB 10.11 / Apache (VirtualHost)  
> 데모 배포 경로: `/var/www/gh_registry_api/v_1` , `/var/www/gh_registry_api/v_2`  
> 서비스 배포 경로: `/var/www/gh_registry/api/v_1` , `/var/www/gh_registry/api/v_2`

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

SFTP로 직접 업로드할 파일/폴더 목록입니다.

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
php artisan config:cache
php artisan route:cache
php artisan view:cache
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

**데모용:**
```bash
# 1. 현재 대기 버전에 소스 업로드 (SFTP)
#    현재 v_1 운영 중이면 → v_2 에 업로드

# 2. 대기 버전에서 composer, 캐시 작업
cd /var/www/gh_registry_api/v_2
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

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
# 1. 현재 대기 버전에 소스 업로드 (SFTP)
#    현재 v_1 운영 중이면 → v_2 에 업로드

# 2. 대기 버전에서 composer, 캐시 작업
cd /var/www/gh_registry/api/v_2
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

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
