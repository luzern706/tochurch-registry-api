# CLAUDE.md - 교적 관리 API

## 프로젝트 개요
- 서비스명: 교적 관리 시스템 (교회로 연동)
- 스택: Laravel 10, PHP 8.1, MariaDB 10.11
- 프론트: React (별도 저장소: 04_gh_registry_front)
- 참고: 기존 관리자 API (02_gh_admin_api) V4 패턴 동일하게 유지

---

## 디렉토리 구조

```
app/
├── Helpers/
│   ├── ApiResponse.php     ← 응답 헬퍼 (이미 존재)
│   ├── JwtHelper.php       ← JWT 헬퍼 (이미 존재)
│   └── LogHelper.php       ← 로그 헬퍼 (이미 존재)
├── Http/
│   ├── Controllers/Api/    ← API 컨트롤러
│   ├── Middleware/         ← 미들웨어
│   └── Requests/           ← Form Request (유효성 검사)
├── Models/                 ← Eloquent 모델
├── Services/               ← 비즈니스 로직
└── Repositories/           ← DB 쿼리

docs/
├── schema/                 ← 테이블 DDL (HeidiSQL export)
│   ├── reg_members.sql
│   ├── reg_member_profiles.sql
│   └── ...
└── api-spec.md             ← API 명세 (기능 완료 후 자동 생성)
```

---

## 아키텍처 패턴

### 3레이어 구조 (반드시 준수)
```
Controller → Service → Repository
```
- **Controller**: 요청 수신, FormRequest로 유효성 검사, Service 호출, 응답 반환만 담당
- **Service**: 비즈니스 로직 전담. ApiResponse 반환
- **Repository**: DB 쿼리 전담. 순수 데이터만 반환 (JsonResponse 반환 금지)

---

## 인증 방식

- **JWT 사용** (Sanctum 사용 금지)
- 패키지: `firebase/php-jwt` (이미 설치됨)
- 설정: `config/jwt.php` (JWT_KEY, JWT_SECRET, JWT_TTL)
- 토큰 추출: `JwtHelper::extractTokenFromRequest($request)`
- 사용자 ID 추출: `JwtHelper::getAdminNoFromRequest($request)`
- 미들웨어: `jwt.auth`
- **로그인 엔드포인트는 jwt.auth 미들웨어 제외**

### JWT 인증 체크 패턴 (Controller에서)
```php
$memberNo = JwtHelper::getAdminNoFromRequest($request);
if ($memberNo === null) {
    return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
}
```

---

## 응답 형식

**ApiResponse 헬퍼 반드시 사용**

```php
// 성공
return ApiResponse::success($data);

// 실패
return ApiResponse::fail('ERROR_CODE', '한국어 메시지', $httpStatus);
```

### 응답 구조
```json
{
  "status":  "success" | "fail",
  "code":    "" | "ERROR_CODE",
  "message": "" | "사용자용 메시지",
  "data":    실제데이터 | null
}
```

### 공통 에러 코드
- `TOKEN_INVALID` : 인증 실패 (401)
- `VALIDATION_FAILED` : 유효성 검사 실패 (400)
- `NOT_FOUND` : 데이터 없음 (404)
- `INSUFFICIENT_PERMISSION` : 권한 없음 (403)
- `DUPLICATE_DATA` : 중복 데이터 (400)
- `INTERNAL_ERROR` : 서버 오류 (500)

---

## 라우트 구조

- **모든 라우트: POST 방식**
- **prefix: v4/{리소스}**
- 미들웨어 그룹으로 묶어서 작성

```php
// 로그인 (jwt.auth 제외)
Route::prefix('v4/member')->group(function () {
    Route::post('/signIn', [MemberController::class, 'signIn']);
});

// 인증 필요 라우트
Route::prefix('v4/member')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',   [MemberController::class, 'getList']);
    Route::post('/getDetail', [MemberController::class, 'getDetail']);
    Route::post('/register',  [MemberController::class, 'register']);
    Route::post('/update',    [MemberController::class, 'update']);
    Route::post('/delete',    [MemberController::class, 'delete']);
});
```

---

## DB 설정

- **DB명**: gyohyero_db (단일 DB, 커넥션 하나)
- **교적 신규 테이블 prefix**: `reg_`
- **테이블/프로시저 생성**: HeidiSQL에서 직접 작업
- **Laravel 마이그레이션 파일 생성 금지**
- **기존 교회로 테이블 스키마 변경 금지**
- 교회로 회원 연동: `reg_members.churchero_user_id` → 기존 `users.id`
- **스키마 파일 위치**: `docs/schema/*.sql` 참조

### 교적 테이블 목록
| 테이블명 | 역할 |
|---|---|
| reg_members | 교인 계정 |
| reg_member_profiles | 교인 교적 상세 정보 |
| reg_organizations | 조직 (남전도회, 청년부 등) |
| reg_member_organizations | 교인-조직 매핑 |
| reg_families | 가족 관계 |
| reg_services | 예배/모임 종류 |
| reg_attendance_records | 출석 기록 |
| reg_visit_records | 심방 기록 |
| reg_prayer_records | 기도 목록 |
| reg_service_teams | 봉사 팀 |
| reg_service_members | 봉사 참여자 |
| reg_education_courses | 교육 과정 |
| reg_education_records | 교육 수강 기록 |
| reg_offering_records | 헌금 기록 |
| reg_messages | 메시지 발송 기록 |
| reg_audit_logs | 감사 로그 (write 액션 추적) |

---

## 코드 작성 규칙

### Controller 작성 패턴
```php
namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemberRequest;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    protected MemberService $memberService;

    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
    }

    public function getList(MemberRequest $request): JsonResponse
    {
        $memberNo = JwtHelper::getAdminNoFromRequest($request);
        if ($memberNo === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->memberService->getMemberList($request->validated(), $memberNo);
    }
}
```

### Service 작성 패턴
```php
namespace App\Services;

use App\Helpers\ApiResponse;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use Illuminate\Http\JsonResponse;

class MemberService
{
    protected MemberRepository $memberRepository;

    public function __construct(MemberRepository $memberRepository)
    {
        $this->memberRepository = $memberRepository;
    }

    public function getMemberList(array $filters, int $memberNo): JsonResponse
    {
        try {
            $data = $this->memberRepository->getMemberList($filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] getMemberList error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '목록 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
```

### Repository 작성 패턴
```php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class MemberRepository
{
    public function getMemberList(array $filters): array
    {
        $query = DB::table('reg_members')
            ->where('church_id', $filters['church_id'])
            ->where('is_deleted', 0);

        $total = $query->count();
        $list  = $query->orderBy('created_at', 'desc')
                       ->paginate($filters['size'] ?? 20);

        return [
            'total' => $total,
            'list'  => $list,
        ];
    }
}
```

---

## 네이밍 규칙

| 구분 | 규칙 | 예시 |
|---|---|---|
| 컨트롤러 | {기능}Controller | MemberController |
| 서비스 | {기능}Service | MemberService |
| 리포지토리 | {기능}Repository | MemberRepository |
| FormRequest | {기능}Request | MemberRequest |
| 메서드(목록) | getList | getMemberList |
| 메서드(상세) | getDetail | getMemberDetail |
| 메서드(등록) | register | registerMember |
| 메서드(수정) | update | updateMember |
| 메서드(삭제) | delete | deleteMember |

---

## 절대 금지 사항

- Laravel 마이그레이션 파일 생성 금지 (DB 작업은 HeidiSQL에서)
- 기존 교회로 테이블 스키마 변경 금지 (reg_ prefix 없는 테이블)
- .env 파일 직접 수정 금지 (.env.example에만 샘플 작성)
- Sanctum 사용 금지 (JWT로 통일)
- 프론트 저장소(04_gh_registry_front) 파일 수정 금지
- Repository에서 JsonResponse 반환 금지
- response()->json() 직접 사용 금지 (ApiResponse 헬퍼 사용)

---

## 감사 로그 (reg_audit_logs)

**모든 write 액션은 Service 의 성공 분기 끝에서 `AuditLogHelper` 호출 필수.**

```php
use App\Constants\AuditMenuCode;
use App\Constants\AuditActionType;
use App\Helpers\AuditLogHelper;

// 생성
AuditLogHelper::logCreate(
    memberId:    $authMemberId,
    churchId:    $churchId,
    menuCode:    AuditMenuCode::MEMBER,
    summary:     "교인 등록: {$name} ({$memberNo})",
    targetId:    (string) $newId,
    targetLabel: $name,
    created:     ['member_no' => $memberNo, 'email' => $email],
);

// 수정 (before/after diff 자동)
AuditLogHelper::logUpdate(
    memberId:      $authMemberId,
    churchId:      $churchId,
    menuCode:      AuditMenuCode::MEMBER,
    summary:       "교인 수정: {$name}",
    targetId:      (string) $memberId,
    targetLabel:   $name,
    before:        (array) $member,
    after:         array_merge((array) $member, $data),
    compareFields: array_keys($data),
);

// 삭제 / 그 외 액션(LOGIN/LOGOUT/ASSIGN/UNASSIGN/ENROLL/WITHDRAW/BULK_UPDATE/SEND) 도 동일 패턴.
```

**규칙:**
- 메뉴 코드는 [AuditMenuCode](app/Constants/AuditMenuCode.php) 상수만 사용 (문자열 직접 입력 금지)
- 액션 타입도 [AuditActionType](app/Constants/AuditActionType.php) 상수만 사용
- audit 호출은 try 블록 내, **비즈니스 성공 직후, ApiResponse::success() 반환 직전**
- Helper 내부에서 try/catch 처리됨 — audit 실패가 메인 흐름을 막지 않음
- 조회(GET) 액션에는 audit 안 함 (데이터량 폭증 방지)
- 새 액션 타입 필요하면 먼저 `AuditActionType` 에 상수 추가 후 사용

---

## API 명세 문서화 규칙 (방법 B)

- **api-spec.md는 손으로 작성하지 않음**
- 각 기능 개발 완료 시점에 아래 명령으로 자동 생성/갱신 요청:

```
"지금까지 완료된 API를 docs/api-spec.md에 문서화해줘.
실제 Controller, Route 코드를 기반으로 작성하고
Request/Response 예시도 포함해줘."
```

- 문서화 포함 항목: 엔드포인트, 메서드, 인증여부, Request 파라미터, Response 예시
- **프론트 개발 시 이 파일을 핵심 참조 문서로 사용**

---

## 세션 시작 시 참고사항

새 세션 시작 시 아래 형식으로 현황을 알려줄 것:

```
완료된 기능: 인증, Member CRUD
오늘 목표: 출석 관리 API (AttendanceController, AttendanceService, AttendanceRepository)
참고 테이블: reg_attendance_records, reg_services
참고 스키마: docs/schema/reg_attendance_records.sql
```

---

## 개발 진행 현황

- [x] JWT 미들웨어 등록 (JwtAuthMiddleware)
- [x] 인증 (로그인/로그아웃)
- [x] 교인(Member) CRUD
- [x] 조직 관리
- [x] 가족 관계
- [x] 출석 관리 (예배 정의 + 출석 기록)
- [x] 심방 관리
- [x] 봉사 관리
- [x] 교육 관리
- [x] 헌금 관리
- [x] 메시지 발송 (발송 기록 저장 — 실제 게이트웨이 연동은 TODO)
- [x] 보고서/통계
- [x] 감사 로그 시스템 (write 액션 자동 기록, 관리자 V4 패턴)
- [x] 관리자 로그인 (`POST /v4/auth/adminSignIn`, gh_church_admin 테이블, 별도 엔드포인트)
  - JWT payload에 `churchId`, `actorType` 클레임 추가 (member/admin 구분)
  - 전체 Service 에서 churchId 를 JWT 에서 직접 추출 (`JwtHelper::getChurchIdFromRequest()`)
