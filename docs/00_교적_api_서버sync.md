# 교적 API 소스 정리본 sync 가이드 — 2026-09-27 정리

작업 저장소(`04_gh_registry_api`)의 **커밋**을 읽어, 주석과 작업 흔적을 제거한 정리본 저장소(`04_gh_registry_api_release`)로 미러링하는 절차다. 도구 사용법·규칙 전체는 `_export_tools/README.md` 참고.

서버에 올릴 때는 정리본 폴더에서 올린다. 이 문서는 정리본 저장소를 최신 커밋에 맞추는 절차만 다룬다.

## 기본 정보

| 항목 | 값 |
|---|---|
| 도구 위치 | `D:\1101_free_pj\04_tochurch\00_pj_src\_export_tools` |
| 프로젝트 이름 | `04_gh_registry_api` (`projects/04_gh_registry_api.json`) |
| 정리본 저장소 | `D:\1101_free_pj\04_tochurch\00_pj_src\03_git\04_gh_registry_api_release` (`main` 브랜치, 원격 저장소 없음. 첫 커밋 `6048629` — 작업 저장소 `a2c3d99` 기준) |
| 로그 | `_export_out/04_gh_registry_api.log` (sync 1회당 1줄), 빌드 전체 출력은 `_export_out/04_gh_registry_api.build.log` |
| 필요 환경 | Node.js, PHP 8.1 이상, Composer — 셋 다 PATH에 있어야 함 (`node -v`, `php -v`, `composer -V`로 확인). PHP가 다른 경로면 환경 변수 `PHP_BIN` |

**원칙**

- **sync는 커밋된 내용만 읽는다.** 커밋하지 않은 수정·추적되지 않은 파일은 정리본에 들어가지 않는다.
- **정리본은 직접 수정하지 않는다.** 고칠 것이 있으면 작업 저장소에서 고치고 커밋한 뒤 다시 sync 한다. 정리본을 손으로 고치면 동일성 검사가 실패한다.
- 정리본 커밋은 결과를 확인한 뒤 사람이 한다. 정리본 커밋 메시지에는 작업 도구 흔적(`Co-Authored-By` 등)을 넣지 않는다.
- 서버에는 정리본 저장소를 올린다. 작업 저장소에서 바로 올리지 않는다.

## 정리본에 들어가는 것 / 빠지는 것

| 구분 | 대상 |
|---|---|
| 주석 제거 후 포함 | `*.php`(`.blade.php` 포함), `*.js`, `*.css` |
| 그대로 복사 | `composer.json` / `composer.lock`, `package.json` / `package-lock.json`, `artisan`, `phpunit.xml`, `public/.htaccess`, `public/favicon.ico`, `public/robots.txt`, `.editorconfig`, `.gitattributes`, 각 폴더의 `.gitignore` 등 |
| 제외 | `docs/` (이 문서, 스키마·시드 SQL, API 명세, Insomnia 포함), `_docs/`, `CLAUDE.md`, `.claude/`, `.env`·`.env.*`, `public/uploads/`의 업로드 파일, `public/web_log/`, `vendor/`, `node_modules/`, `.idea/` 등 |
| 템플릿으로 대체 | `README.md`, `.gitignore`, `.env.example`, `public/uploads/.gitignore` — `_export_tools/templates/04_gh_registry_api/` |
| 로컬 복사 (커밋 안 됨) | `.env` (작업 폴더에 있으면 `.env.staging`, `.env.live`, `.env.production`도) — 정리본 폴더에 복사만 되고 `.gitignore` 대상. 값은 출력되지 않음 |

- PHP 주석은 PHP 내장 토크나이저(`token_get_all`)로 찾은 위치만 지운다. `artisan`처럼 확장자가 없는 파일과 `phpunit.xml`, `.htaccess`는 처리하지 않고 그대로 복사한다(Laravel 기본 파일).
- docblock(`/** */`)도 지운다. 이 프로젝트의 docblock은 설명과 `@param`/`@return` 같은 IDE용 표기뿐이고, 동작에 쓰이는 어노테이션이나 지시 주석은 없다. 그래서 남기는 주석이 없다.
- SQL 문자열 안의 `-- …` 주석은 PHP 문자열 내용이라 지우지 않는다(현재 없음).
- `.blade.php`의 PHP 코드 밖 주석(`<!-- -->`, `{{-- --}}`)은 지우지 않고 경고만 한다. 현재 `resources/views/welcome.blade.php`(Laravel 기본 페이지)에서 매번 경고가 나오며, 결과에는 영향이 없다.
- `public/uploads/`는 업로드 파일을 S3로 올리기 전에 임시로 두는 곳이라 폴더만 남기고(`.gitignore`) 파일은 넣지 않는다.
- `tests/`(Laravel 기본 예제 테스트), `package.json`·`vite.config.js`·`resources/js`·`resources/css`(Laravel 기본 프런트 빌드 설정)는 관리자 API·웹 API와 같게 정리본에 포함한다. API 서버 동작에는 쓰이지 않으며, sync 출력의 "개발 전용 후보"에 매번 표시된다.

---

## 1. 전체 순서 (확인 → 커밋 → sync → 정리본 커밋)

| 단계 | 위치 | 내용 |
|---|---|---|
| 1 | 작업 저장소 | 수정 후 `php -l <파일>`, `php artisan route:list`로 로드 확인 |
| 2 | 작업 저장소 | 변경사항 커밋 |
| 3 | `_export_tools` | `node bin/sync.js sync -p 04_gh_registry_api` |
| 4 | 출력 확인 | 마지막 줄 `결과: PASS`, 종료 코드 0 (3번 표) |
| 5 | 정리본 저장소 | `git diff --stat`으로 변경 확인 후 커밋 |
| 6 | 서버 | 배포가 필요하면 정리본 폴더에서 진행 |

## 2. 명령어 (PowerShell)

Windows PowerShell 5.1은 `&&`를 지원하지 않으므로 한 줄씩 실행한다. 출력 한글이 깨지면 먼저 `chcp 65001`. (Git Bash에서도 같은 명령으로 동작한다.)

```powershell
# 1~2. 작업 저장소: 확인 후 커밋
cd D:\1101_free_pj\04_tochurch\00_pj_src\03_git\04_gh_registry_api
php artisan route:list
git add <변경 파일>
git commit -m "<커밋 메시지>"

# 3~4. sync (반영 + 동일성·빌드·흔적 검증까지 자동 실행)
cd D:\1101_free_pj\04_tochurch\00_pj_src\_export_tools
node bin/sync.js sync -p 04_gh_registry_api
$LASTEXITCODE          # 0 이어야 함

# 5. 정리본 저장소: 확인 후 커밋
cd D:\1101_free_pj\04_tochurch\00_pj_src\03_git\04_gh_registry_api_release
git status
git diff --stat
git add -A
git commit -m "<작업 저장소 커밋과 같은 요지의 메시지>"
```

정리본 저장소는 처음 sync 때 `git init`으로 만들어졌다(`main` 브랜치, 원격 저장소 없음).

sync가 자동으로 하는 일:

1. 작업 저장소 커밋(기본 `HEAD`)을 읽어 제외·주석 제거·템플릿 적용 후 정리본 폴더에 반영 (원본에서 지워진 파일은 정리본에서도 삭제)
2. **동일성 검사**: 주석을 뺀 토큰열이 원본과 같은지, 정리본 PHP에 구문 오류가 없는지, 제거되지 않은 주석이 없는지
3. **빌드 검사**: 정리본 폴더에서 다음을 차례로 실행 (1~2분)
   1. `composer install`
   2. `php -l`: 정리본의 모든 `.php` 파일 문법 검사 (`bin/php-check.js lint`, 현재 195개)
   3. `php artisan route:list`: 라우트 수 확인 (`bin/php-check.js routes`, 현재 221개: api 216, web 5)
   4. `php artisan route:cache` / `view:cache`: 라우트·Blade 캐시 생성 확인 후 바로 `route:clear` / `view:clear`
   - `DB_HOST`/`REDIS_HOST`를 닿지 않는 주소로 덮어쓴 상태로 실행하므로 **DB에 접속하지 않는다**(`build.env`).
   - `config:cache`는 검증 단계에서 실행하지 않는다(서버 배포 시 배포 가이드대로 실행). `app/`의 `env()` 직접 호출은 2026-09-27에 `config()`로 옮겨서 서버에서 `config:cache`를 써도 된다(4번 참고).
   - `vendor/`, `bootstrap/cache/*.php`, `storage/logs/`가 생기지만 `.gitignore` 대상이고 서버에도 올리지 않는다.
4. **흔적 검사**: TODO/레거시/다른 저장소 이름/로컬 경로/이메일/사설 IP/AI 도구 이름/토큰(FCM·JWT·API 키)/`.env` 값 등이 정리본 파일에 남았는지
5. `_export_out/04_gh_registry_api.log`에 결과 한 줄 기록 (커밋은 하지 않음)

옵션:

| 옵션 | 용도 |
|---|---|
| `--ref <커밋\|태그>` | HEAD 대신 특정 커밋 기준으로 맞춤 |
| `--skip-build` | 빌드 검사 생략 (composer install 시간 절약) |
| `--replace-uncommitted` | 정리본에 커밋 안 된 이전 sync 결과가 남아 있을 때 덮어쓰기 (직접 수정이 있으면 거부) |

반영 없이 검증만: `node bin/sync.js verify -p 04_gh_registry_api`
흔적 검사만: `node bin/sync.js scan -p 04_gh_registry_api`

## 3. 결과 판정

| 출력 / 종료 코드 | 의미 | 조치 |
|---|---|---|
| `결과: PASS`, exit 0 | 반영·검증 모두 통과 | 정리본 커밋 |
| exit 1, `검증 1. 동일성` FAIL | 주석 제거 결과가 원본과 다르거나 주석이 남음 | 출력된 파일·줄 확인. 정리본을 손으로 고치지 말 것 |
| exit 1, `검증 2. 빌드` FAIL (`composer install`) | 정리본에서 의존성 설치 실패 | `.build.log` 확인. 제외 목록 때문에 빠진 파일이 원인인지 출력의 "원인 후보" 확인 |
| exit 1, `검증 2. 빌드` FAIL (`php-check.js lint` / `routes`, `artisan ...`) | 문법 오류, 라우트 등록 실패, Blade 컴파일 실패 | `.build.log`에서 해당 파일 확인 |
| exit 1, `흔적 검사` FAIL | 정리본 파일에 작업 흔적이나 env 값이 남음 | 작업 저장소에서 해당 코드 수정 → 커밋 → 재sync (4번 기준). 프레임워크 기본값 같은 오탐이면 `projects/04_gh_registry_api.json`의 `trace.allow`에 추가 |
| exit 2, "커밋되지 않은 변경이 있어 중단" | 정리본에 커밋 안 된 변경이 있음 | 이전 sync 결과를 커밋하거나, 직접 수정이 없으면 `--replace-uncommitted` |
| exit 2, "처리 오류" | PHP 토크나이저 실행 실패 등 | 정리본은 바뀌지 않음. `php -v`가 되는지, 출력된 파일 문법 확인 |

## 4. 흔적 검사 대응 기준과 사례

- 주석 안의 흔적은 주석 제거로 자동으로 없어진다. **코드(문자열·SQL·설정 기본값 등) 안에 남은 것만** 문제가 된다.
- `.env` 값이 코드에 그대로 들어가 있으면 `env-value-leak`으로 잡힌다. 값은 출력되지 않고 파일·줄·변수 이름만 나온다.
- 현재 허용(`trace.allow`)해 둔 것:
  - Laravel 기본 설정값: `config/session.php` 등의 session 관련 이름, `config/*.php`의 `127.0.0.1`/`localhost`, `config/mail.php`의 `hello@example.com`
  - 교육 회차(session) 기능의 이름(`getSessionList`, `registerSession`, `session_id`, `reg_education_sessions`, `sp_v4_reg_edu_session_*` 등) — `Education*` 파일, `ReportRepository.php`, `routes/api.php`에만
  - `composer.lock`의 외부 패키지 메타데이터(작성자 이메일, OpenAI·legacy 문자열), `package-lock.json`의 패키지 이름
  - env 값 일치: `APP_NAME`(Laravel 기본값), config의 `APP_URL`·`DB_HOST`·`REDIS_HOST`·`MEMCACHED_HOST` 기본값, `config/mail.php`의 `MAIL_FROM_ADDRESS` 기본값, `FileUploadConstants.php`의 S3 버킷 이름
- 마지막 항목(S3 버킷)은 코드에 박혀 있어 허용해 둔 것이다. `config()`로 옮기면 허용 항목에서 빼도 된다.

**첫 sync (2026-09-27, 작업 저장소 `757c64e`)** — 코드에 남은 실제 흔적은 없었고, 위 오탐만 허용 목록에 추가했다. 주석 644개를 지웠다. 다른 저장소 이름(`02_gh_admin_api`, `03_gh_www_api`), `CLAUDE.md`, `TODO` 등은 모두 주석 안에 있어서 정리본에서는 빠졌다.

같은 날 `app/Services/MemberService.php`의 전화번호 마스킹 설명 주석에 들어 있던 휴대폰 번호 예시를 가상 번호(`010-1234-5678`)로 바꿨다. 주석이라 정리본에는 원래 들어가지 않았지만, 작업 저장소 이력에는 남아 있다.

> 새 코드에서도 DB 이름·버킷·도메인·토큰 같은 환경 값과 실제 전화번호·이메일은 SQL이나 코드(주석 포함)에 직접 쓰지 말고 `config()`로 읽거나 가상 값을 쓴다. `env()`를 `app/` 코드에서 직접 부르면 서버에서 `config:cache` 후 null이 된다. 2026-09-27에 `GeocodeHelper`(카카오 키 → `config('services.kakao.rest_api_key')`)와 `S3FileHelper`(AWS 키·리전 → `config('filesystems.disks.s3.*')`)를 옮겼고, `config:cache` 상태에서 값 조회·좌표 변환이 되는 것을 확인했다.

---

## 빠른 명령어 요약

```bash
# 작업 저장소 커밋 후
cd D:/1101_free_pj/04_tochurch/00_pj_src/_export_tools
node bin/sync.js sync -p 04_gh_registry_api        # 결과: PASS 확인

cd ../03_git/04_gh_registry_api_release
git diff --stat
git add -A
git commit -m "<메시지>"
```
