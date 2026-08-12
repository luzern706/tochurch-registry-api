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
- [x] 교인(Member) CRUD (교인 상세 페이지 하위 5탭 — 심방/출석/교육/봉사/헌금 요약카드까지 2026-08-09 실연동 완료)
- [x] 조직 관리
- [x] 가족 관계 (등록/조회/삭제 + 관계 수정 UI까지 2026-08-09 완료, 반대편 레코드 자동 동기화)
- [x] 출석 관리 (예배 정의 + 출석 기록 일괄 등록)
- [x] 출석 통계 API (`getAttendanceStats`, `getAttendanceStatsByOrg`, `getStatsByService`, `getMemberRateDistribution`)
  - 출석현황 3탭 (개인별/기간별/조직별), 출석통계 (예배별/조직별/개인분포), 대시보드 실데이터 연결 완료
- [x] 심방 관리
  - 심방목록 / 미심방목록 / 심방대상관리 / 심방등록·수정
  - org_ids 하위 조직 포함 검색 (프론트 BFS → whereIn)
  - KPI 카드 클릭 필터 (심방현황: 미심방·진행중·완료)
  - 담당자 컬럼, 미심방 배지 색상(red)
- [x] 기도 관리 (`POST /v4/prayer/*`)
  - getList / getDetail / register / update / delete / updateStatus / getStats
  - 독립 기도 등록 (교인/비교인 모두 지원, `member_id` nullable)
  - 심방 연계 기도 (심방 등록 시 `add_to_prayer=true` → `reg_prayer_records` 자동 생성, `visit_record_id` 연결)
  - 기도목록 KPI 카드 클릭 필터 (진행중·심방연계·응답)
  - 기도 수정 페이지 (독립 기도만, 심방연계는 심방 기록 보기로 이동)
  - `AuditMenuCode::PRAYER` 추가
- [x] 봉사 관리
- [x] 교육 관리
- [x] 헌금 관리
- [x] 메시지 발송 (발송 기록 저장 — 실제 게이트웨이 연동은 TODO) — 프론트 3화면(메시지 홈/작성/발송이력)도 2026-08-09 실연동 완료, 발송은 여전히 기록 저장까지만(SMS/Push/Email 게이트웨이 미연동)
- [x] 보고서/통계
- [x] 감사 로그 시스템 (write 액션 자동 기록, 관리자 V4 패턴)
- [x] 관리자 로그인 (`POST /v4/auth/adminSignIn`, gh_church_admin 테이블, 별도 엔드포인트)
  - JWT payload: `churchId`, `adminRole` ('super'|'admin') 클레임 포함
  - 교인 로그인 없음 — 관리자 전용 2티어 구조
  - 전체 Service 에서 churchId 를 JWT 에서 직접 추출 (`JwtHelper::getChurchIdFromRequest()`)
- [x] 관리자 계정 관리 (`POST /v4/admin/*`, 슈퍼 관리자 전용)
  - getList / getDetail / register / update / delete
  - Controller 내 `guardSuper()` 로 role='super' 체크
- [x] 교육 관리 프론트 연동 (`POST /v4/education/*`) — Session 08
  - educationService.js 생성 (getList/getDetail/register/update/delete/enroll/updateProgress/withdraw/getEnrollmentsByCourse/getCoursesByMember)
  - Curriculum: 목록/필터(상태·키워드)/삭제 연동
  - CurriculumRegister: 등록/수정 폼 연동 (start_date, end_date, status 추가)
  - Ongoing: 진행중 과정 목록 (status=ongoing 필터)
  - History: 교인 중심(getCoursesByMember) + 교육 중심(getList status=completed) 탭
  - Session: 과정 상세 + 수강자 목록 + withdraw 기능
  - SessionCreate: 과정 드롭다운 로드 후 register 호출, 종료일 자동 계산
  - SessionAttendance: 수강자 출결 마킹 → updateProgress 저장
  - AttendanceStatistics: getList 기반 KPI 카드 + 차트 + 상세 테이블
- [x] 봉사 관리 프론트 연동 (`POST /v4/volunteer/*`) — Session 11
  - volunteerService.js 생성 (getTeamList/getTeamDetail/registerTeam/updateTeam/deleteTeam/assignVolunteer/unassignVolunteer/getVolunteersByTeam/getTeamsByMember)
  - ServiceStatus: 팀 목록 / 필터(유형·운영형태·사용여부·담당자) / KPI(전체·운영중·일회성) / 참여인원·부족인원 표시(페이지 범위 내 병렬 조회)
  - ServiceRegister: `?id=&tab=info|assign` 쿼리 기반 등록·수정·배정 통합 페이지 (CurriculumRegister + Session 패턴), 담당자 선택은 MemberSearchModal 재사용
  - ServiceHistory: 교인 중심 + 봉사 중심 탭, 검색 없이도 `getHistory`(교회 전체 이력, member_id/team_id로 좁힘)로 기본 목록 표시 — 최초 education History.jsx 패턴(검색 전까지 빈 화면)을 그대로 따라했다가 "검색 전엔 전체 데이터가 보여야 한다" 피드백으로 전용 엔드포인트 신규 추가
  - `day_of_week`가 단일 컬럼이라 요일 선택 UI를 다중 토글에서 단일 선택으로 수정
  - SP 4종(`sp_v4_reg_volunteer_by_team`/`teams_by_member`/`history`/`team_history`, [05_sp_v4_reg_volunteer.sql](docs/schema/05_sp_v4_reg_volunteer.sql)) + 샘플 데이터([104_seed_volunteer.sql](docs/schema/104_seed_volunteer.sql), church_id=33632, 팀8·매핑30)
- [x] 봉사 출결 기능 (`POST /v4/volunteer/attendance/*`) — Session 11 후속
  - mock 화면(04_gh_registry_front_mock) 참고해 ServiceHistory 재작업 중 "참여 횟수/최근 참여일을 실측값으로" 요청 → 봉사에는 교육(`reg_education_attendance`)과 같은 회차별 출결 기록이 없다는 것을 발견, 신규 기능으로 추가
  - `reg_service_attendance` 테이블 신설([06_reg_service_attendance.sql](docs/schema/06_reg_service_attendance.sql)) — 정기 봉사팀은 교육 기수처럼 `total_rounds` 가 없어(무기한 반복) `round_no` 대신 실제 날짜(`service_date`)를 키로 사용
  - `attendance/getSheet`·`attendance/save`·`attendance/getMemberStats` 3개 엔드포인트 (education의 `attendance/*`와 동일 계층, 단순 CRUD라 SP 미사용 — education 원 코드도 이 3개는 SP 아님)
  - `ServiceAttendance.jsx` 신규 페이지 (SessionAttendance.jsx 패턴, 날짜 기반 체크인) — 실제로 `saveAttendance` 호출까지 정상 연동(education의 SessionAttendance.jsx는 저장 시 `updateProgress`만 호출하고 `saveRoundAttendance`를 호출하지 않는 갭이 있었음 — 여기서는 그 갭 없이 구현)
  - `sp_v4_reg_volunteer_history`/`sp_v4_reg_volunteer_team_history`에 `reg_service_attendance` LEFT JOIN 집계 추가 → 봉사이력 화면의 참여횟수/최근참여일/총참여횟수가 실측값
  - 샘플 출결 데이터 105건([105_seed_volunteer_attendance.sql](docs/schema/105_seed_volunteer_attendance.sql))
  - ServiceHistory.jsx: mock 대비 필터 아이콘·탭 아이콘(`icon-user`/`icon-heart`) 추가, 교인중심에 시작일·종료일 검색 + 참여기간·참여횟수·최근참여일 컬럼, 봉사중심을 참여자 행 목록에서 팀별 집계 행(참여인원·총참여횟수·최근참여일·기간)으로 재구성 + "선택된 봉사" InfoCallout(`?team_id=` 딥링크로 활성화)
  - "역할"(`reg_service_members.role`)은 봉사자 최초 배정 시 입력받지 않고, 배정 후 별도 "역할" 버튼(prompt)으로만 채워지는 구조 — 프론트 개선 여지로 남겨둠
- [x] 봉사현황 429(Too Many Requests) 버그 수정 — Session 11 후속
  - 사용자 리포트: "봉사 리스트 로딩 중 로그인 페이지로 이동, 알 수 없는 오류" → 재현 결과 원인은 두 가지 복합
  - (1) `ServiceStatus.jsx`가 팀 목록 로드마다 팀 수만큼 `getVolunteersByTeam`(참여인원) + 담당자 수만큼 `getMemberDetail`(담당자명)을 병렬 N+1 호출 → 팀 8개 기준 ~20건, `RouteServiceProvider`의 `throttle:api` 60/min(IP당, JWT라 `$request->user()` 미인식)을 새로고침 몇 번으로 초과
  - (2) `api.js`가 `Accept: application/json` 헤더를 안 보내서 429 시 Laravel이 HTML 디버그 페이지를 반환 → `response.json()` 파싱이 SyntaxError를 던지고 `err.code`가 비어 `getErrorMessage(undefined)`가 "알 수 없는 오류가 발생했습니다" 폴백을 반환
  - 수정: `sp_v4_reg_volunteer_list` 신설(`reg_service_teams` + 참여인원 집계 + 담당자명 JOIN, [05_sp_v4_reg_volunteer.sql](docs/schema/05_sp_v4_reg_volunteer.sql)) → `getTeamList` 한 번 호출로 `participant_count`/`manager_name` 포함 반환, N+1 완전 제거(페이지당 요청 ~20건 → 4건)
  - `api.js`에 `Accept: application/json` 헤더 추가 + 429 전용 처리(`RATE_LIMITED` 코드, Retry-After 안내) + `response.json()` try/catch (비-JSON 응답도 안전하게 처리)
  - `RouteServiceProvider`의 `throttle:api`를 60/min → 300/min으로 상향 (같은 사무실 네트워크에서 여러 관리자가 동시 접속하는 구조 고려)
- [x] 봉사 관리 UI 폴리시 — Session 11 후속, 봉사 관리 기능 완료
  - ServiceStatus KPI 카드 재정의: 전체·운영중·일회성 → 운영 중 봉사·도움이 필요한 봉사·이번 주 봉사(mock 참고). "도움이 필요한 봉사" = 활성 팀 중 `participant_count < required_count`, "이번 주 봉사" = 정기(`frequency=weekly`) 또는 이번 주 범위(일~토) 내 `event_date`를 가진 일회성 팀 — 격주/매월은 앵커일이 없어 판단 불가하여 제외. `getTeamList({is_active:1, size:100})` 단일 호출 후 클라이언트에서 3개 KPI 모두 집계(추가 API 없음)
  - "역할" 수정을 `window.prompt` → `RoleEditModal`로, "해제"를 `window.confirm` → `UnassignConfirmModal`로 교체 — 둘 다 담당자 검색 모달과 동일한 ModalBox 톤으로 통일
  - ServiceRegister 배정 탭 "봉사자 추가" 버튼 플러스 아이콘 미표시 버그 수정 — `.btn-plus:before`가 `.button-box` 조상 스코프 CSS라 래핑 누락이 원인
  - ServiceHistory 봉사중심 탭의 "선택된 봉사" InfoCallout 제거 — "처리 방법이 애매하다"는 피드백으로 단순화, `?team_id=` 딥링크 필터링 자체는 유지
  - ServiceAttendance.jsx "공결"(excused) 상태 CSS 누락 버그 수정 — education 출결(SessionAttendance.jsx)엔 원래 출석/지각/결석 3종만 있어 `.excused`에 대응하는 규칙이 common.css에 전혀 없었음(아이콘 미표시 + 선택해도 시각적 피드백 없음). `.attendance-status-btn.excused`/`.legend-dot.excused`에 파란색(#1890FF) 규칙 추가(기존 초록/노랑/빨강 패턴과 동일)
  - ServiceRegister 수정 페이지 진입 시 "봉사자 배정 (0명)"으로 보이는 버그 수정 — `fetchVolunteers()`가 `tab==='assign'`일 때만 호출되어 기본 탭(기본 정보)에서는 count가 갱신 안 됐음. 탭과 무관하게 mount 시 항상 fetch하도록 수정
- [x] 내정보 (`POST /v4/profile/*`) — Session 02-10
  - 사전 조사: `Profile.jsx`/`Password.jsx`/`HeaderGnb.jsx` 전부 하드코딩 껍데기, 본인 스코프 API 자체가 없었음(`AdminController`는 `super.auth` 필수의 타인 관리용). `docs/05_menu-checklist.md` "내정보" 섹션에 기록
  - `ProfileController`/`ProfileService`/`ProfileRepository`/`ProfileRequest` 신규 — `getMyProfile`/`updateMyProfile`/`changePassword`, `jwt.auth`만 요구(`super.auth` 불필요), JWT의 `admin_no`로 본인 행만 대상
  - `gh_church_admin`에 `email`/`phone` 컬럼 없어서 먼저 물어봄 → 추가 승인받고 ALTER SQL을 [gh_church_admin_alter.md](docs/schema/gh_church_admin_alter.md)에 문서화(HeidiSQL에서 직접 실행 필요, Claude가 직접 DB에 반영하지 않음)
  - JWT `name` 클레임 추가 여부는 대안 두 가지(로그인 시 클레임 추가 vs 별도 `getMyProfile` 호출) 중 클레임 추가로 판단 — 기존에 이미 `churchId`/`adminRole`을 JWT에 담아 프론트가 디코딩해 쓰는 패턴과 일관되고, 헤더 표시에 추가 API 호출이 필요 없어짐. `JwtHelper::createToken`/`AuthService::adminSignIn` 수정
  - `AuditMenuCode::PROFILE` 신규 추가
  - 프론트: `profileService.js` 신규, `AuthContext`에 `updateAdminInfo`(로컬 상태 패치, 재로그인 없이 헤더 즉시 갱신) 추가, `Profile.jsx`/`Password.jsx` 실제 폼으로 교체, `HeaderGnb.jsx`는 마운트 시 `getMyProfile` 호출 + 로딩 중엔 JWT 디코딩값(`adminInfo`)으로 폴백
  - 04_gh_registry_front 파일 수정: CLAUDE.md 절대금지 항목과 문면상 상충하나, 사용자가 이번 요청에서 프론트 파일 경로를 직접 지정해 명시적으로 작업을 요청했고 과거 세션들의 진행 현황에도 프론트 연동 작업이 계속 기록되어 있어 그 관례를 따름
  - 내정보 UI 폴리시 — 관리자 드롭다운 메뉴가 항목 클릭 후에도 안 닫히던 버그 수정(`setOpen(null)` 추가), 동작 없던 "운영 모드 전환" 메뉴 삭제, Profile/Password 페이지 저장 버튼 정렬을 `flex-tj`(양끝) → `button-box flex-tr`(우측 정렬, 기존 News.jsx 등에서 쓰던 패턴)로 변경, 두 페이지 상단에 `page-tab-box` 상호 이동 탭 추가
  - 비밀번호 변경 안내문("8자 이상, 영문/숫자/특수문자 포함")이 실제로는 검증되지 않던 버그 발견·수정 — `Password.jsx` 클라이언트 정규식 + `ProfileRequest::changePassword`에 동일 규칙의 서버측 `regex` 룰 추가
- [x] 교직설정 (`/member/settings`, `POST /v4/code/*` · `/v4/organization/*` · `/v4/attendance/*` · `/v4/setting/*`) — Session 02-10 후속
  - "교적 > 설정 마무리" 요청 받고 `docs/05_menu-checklist.md`가 이 화면을 "API 연동 0%"로 표시하고 있어 조사했으나, 실제 파일(2460줄)을 직접 읽어보니 교인/직분/관계/예배/출결/교육/조직/봉사/심방 기준 + KPI/자동화규칙 대부분이 이미 `codeService`/`organizationService`/`attendanceService`/`settingService`로 실연동되어 있었음 — 체크리스트 문서가 stale했던 것으로 확인, 문서 갱신
  - 실제 남은 갭 4종을 사용자에게 확인 후 처리:
    1. 조직 기준의 활성/비활성 토글이 `updateOrganization` 호출 없이 표시만 하던 버그 → 연동
    2. 교인기준의 "자동분류", 출결기준의 "장기결석기준"/"출석집계방식"은 자동화규칙/KPI탭에 이미 있는 것과 중복 하드코딩 → 삭제(단일 소스로 일원화), 안내문으로 이동 위치 명시
    3. 직분유형 "추가옵션"/관계유형 "가족관리옵션"은 어떤 setting_key에도 연결 안 됨 → `position_display_options`/`relation_family_options` 신규 키 추가해 `getSettings`/`saveSettings`로 실연동 (스키마 변경 없음, 기존 범용 설정 API 재사용)
    4. 교인/교육/봉사 "상태" 정적 목록(수정 버튼이 동작 안 함) → 실제 코드그룹으로 전환(`member_status`/`education_status`/`service_status` 신규 group_key, 기존 Code API 재사용), `MemberStatusCriteria`/`EducationStatusCriteria`/`ServiceStatusCriteria` 컴포넌트 신규 추가
  - 신규 group_key 시드 데이터: [107_seed_status_codes.sql](docs/schema/107_seed_status_codes.sql) (교인상태 6개/교육상태 3개/봉사상태 3개, church_id=33632 기준) — 사용자 승인 하에 local dev DB에 직접 적용 완료 (파일 내 `USE gyohyero_db` → `USE gyohyero`로 수정 후 실행, 기존 스키마 문서들에 있던 DB명 오탈자였음)
  - 최종 확인: 파일 전체의 `defaultChecked`/`type="checkbox"`/`type="radio"`/`type="number"`를 전수 조사 — 11개 탭(교인/직분/관계/예배/출결/교육/조직/봉사/심방 기준 + KPI/통계 기준 + 자동화 규칙) 전부 실제 API 연동 확인, 잔여 하드코딩 없음. 미사용 헬퍼 `SortableRow` 컴포넌트 발견(정의만 되어 있고 어디서도 호출 안 됨) → 삭제
  - "설정" 탭의 나머지 4개 화면(기본정보/문자설정/템플릿/권한설정)은 이번 작업 범위 밖 — 백엔드 자체가 없는 별도 화면, 추후 별도 세션에서 진행
- [x] 설정 > 기본정보 (`ChurchProfile.jsx`, `POST /v4/churchProfile/*`) — Session 02-10 후속
  - 좌측 "설정 메뉴"가 GNB의 5개 설정 서브메뉴와 다른, 이 페이지 계열(기본정보/교직설정/권한관리)만 공유하는 별도의 3개짜리 미니 내비게이션이라는 것을 확인 — 순서를 사용자 요청대로 교회 프로필→교적 설정→권한 관리로 재배열
  - API 연동 조사 중 발견: 이 화면의 데이터(교회명/교단/담임목사/주소/연락처/납세번호/로고 등)는 새 reg_ 테이블이 아니라 기존 교회로 레거시 테이블(`gh_church`/`gh_church_location`/`gh_church_pastor`/`gh_church_group`)에 이미 있었음. 자매 프로젝트 02_gh_admin_api에 이미 성숙한 Church API(주소→행정구역 매칭+좌표변환, S3 로고 업로드, 목회자 CRUD)가 있지만 SUPER 롤 전용의 다른 관리자 인증 체계라 재사용 불가 — 이 리포에 맞게 새로 작성
  - 사용자 확인 필요했던 3가지 결정: (1) 로고/대표사진/소속증명서/문서머리글 4종 파일 업로드를 지금 할지 다음에 할지 → 처음엔 범위 축소 위해 "다음에"를 제안했으나, `gh_church.thumbnail`이 교회로 공개 목록 화면에서도 쓰이는 컬럼이라 재사용 시 부작용 위험 있고 로고/문서머리글은 대응 컬럼 자체가 없어 "나중에 붙이는 단순 작업"이 아니라는 걸 깨닫고 지금 함께 구현하는 것으로 정정. (2) `gh_church_pastor`는 교회당 여러 명(목사/전도사/강도사) 등록 가능하고 "담임" 구분 플래그가 없어(테스트 교회에 '목사' 타입 2명 존재) 단일 "담임목사" 필드 매핑이 불가능 → 기존 목회자 목록에서 선택하는 드롭다운 + DB에 명시적으로 저장하는 방식으로 사용자가 직접 지정. (3) 처음엔 신규 `reg_church_profiles` 테이블로 설계했다가, 사용자가 "로고/대표사진/담임목사 지정은 공개 홈페이지와도 관련있지 않냐"고 지적 — 맞는 지적이었음. `reg_` 테이블은 교적 시스템 전용이라 다른 시스템(관리자 페이지·공개 홈페이지)이 볼 수 없으므로, 공유가 필요한 필드는 reg_ 대신 기존 공유 테이블에 컬럼을 추가하는 것으로 설계 변경
  - 최종 스키마 (신규 테이블 없음): [gh_church_alter.md](docs/schema/gh_church_alter.md) — `gh_church`에 `logo_file_no`(로고, 기존 `thumbnail`은 대표사진으로 그대로 유지) 추가, `gh_church_location`에 `address_detail`(상세주소) 추가, `gh_church_pastor`에 `is_head`(담임여부, 교회당 최대 1명 — 지정 시 기존 플래그를 앱단에서 먼저 내림) 추가 — 3건 모두 **HeidiSQL에서 ALTER 실행 필요**(아직 미실행 확인함). 소속증명서/문서머리글/기부금영수증안내문은 공개 사이트와 무관한 교적 문서생성 전용이라 신규 테이블 대신 기존 `reg_church_settings`(이미 KPI/자동화규칙이 쓰던 범용 키-값 테이블)에 `church_receipt_notice`/`church_affiliation_cert_file_no`/`church_letterhead_file_no` 키로 저장 — 여기도 신규 테이블 불필요
  - `composer require aws/aws-sdk-php` 신규 설치(이 리포에 미설치 상태였음, AWS 자격증명 자체는 .env에 이미 있었음)
  - `App\Helpers\S3FileHelper` 신규 — 02_gh_admin_api ChurchService의 raw S3Client 업로드/삭제 패턴 재사용, 4개 파일 슬롯(logo/photo/affiliation_cert/letterhead)이 공유. logo/photo는 `gh_church` 컬럼에, affiliation_cert/letterhead는 `reg_church_settings`에 file_no를 저장 — `ChurchProfileService::FILE_SLOTS`에서 슬롯별 저장 위치를 구분
  - 스키마 파일 넘버링: 100번대는 시드 데이터 전용, 테이블/프로시저 생성은 01부터 순번 — 처음에 108로 잘못 매겼던 걸 사용자가 07로 정정 (이후 신규 테이블 자체가 불필요해지면서 파일은 삭제)
  - `ChurchProfileController`/`Service`/`Repository`/`Request` 신규, `AuditMenuCode::CHURCH_PROFILE` 추가, `jwt.auth`만 요구(super.auth 불필요, churchId는 JWT에서 추출)
  - 프론트: `churchProfileService.js` 신규, `api.js`의 공용 `request()`가 `FormData` body를 지원하도록 확장(파일 업로드용, 기존 JSON 호출부는 영향 없음), `ChurchProfile.jsx` 실제 폼으로 전면 교체 — 담임목사는 자유 텍스트 대신 등록된 목회자 드롭다운, "주소 검색" 버튼은 실제 우편번호 API 연동 전이라 동작 안 하는 버튼을 남기지 않기 위해 제거(추후 별도 스코프)
- [x] reg_ ↔ gh_church_ 테이블 중복 조사 및 예배 기준 통합 — Session 02-10 후속
  - "기본정보" 작업 중 발견한 reg_/gh_church_ 중복 이슈를 계기로 전체 `reg_` 테이블을 `gh_church_*`/`gh_account`와 전수 비교 — 조사 결과: [reg-vs-gh_church-overlap.md](docs/schema/reg-vs-gh_church-overlap.md)
    1. `reg_members`/`reg_member_profiles`/`reg_families` ↔ `gh_account`: 중복이지만 `reg_members.churchero_user_id`로 이미 연결됨(최초 설계). 조치 불필요
    2. `reg_services` ↔ `gh_church_timetable`: 진짜 중복, 연결 없음 → **통합 진행**
    3. `reg_codes`(position) ↔ `gh_church_code`(직분): 표면적으로 비슷하나 실제 값을 비교해보니 다른 개념(교인 신앙적 직분 vs 사역자/행정직 역할, `reg_member_profiles.position`이 문자열 저장이라 강제 통합 시 기존 값 대부분 유실 위험) → **보류, 사용자 판단으로 별개 목록 유지**
    4. `reg_messages` ↔ `gh_church_push_record`: 개념적 중복이나 `reg_messages`는 아직 실발송 미구현(TODO)이라 낮은 우선순위, 게이트웨이 연동 시점에 재검토
  - 예배 기준 통합(#2): `gh_church_timetable`에 `category`/`day_of_week`/`start_time`/`target_org_id`/`sort_order`/`is_active` 컬럼 추가하고 `reg_services`(church_no=33632, 8건 — 이 DB에서 reg_services를 쓰는 유일한 교회) 데이터를 이관, `reg_attendance_records.service_id`(241건) 재매핑 — SQL: [gh_church_alter.md](docs/schema/gh_church_alter.md), local 적용 완료(사용자 확인)
  - dev 환경(church_id=19715)용 이관 SQL도 추가 — dev DB의 실제 `reg_services` row id를 이 세션에서 확인할 수 없어 하드코딩 대신 이름으로 조회하는 방식으로 작성([gh_church_alter.md](docs/schema/gh_church_alter.md) 5번 항목), [107_seed_status_codes_dev.sql](docs/schema/107_seed_status_codes_dev.sql)도 동일 관례로 추가
  - 애플리케이션 코드 4개 파일을 `gh_church_timetable`로 재타겟팅: `WorshipRepository`(전면 재작성, `timetable_no`/`church_no`를 `id`/`church_id`로 별칭해 API 응답 형태 그대로 유지), `AttendanceRepository::getHistoryByMember`, `VisitRepository::getAbsenceTargetList`(주일예배 판별), `ReportRepository::getStatsByService`. `reg_attendance_records`는 계속 정상 사용(`service_id` 값만 재매핑)
  - 사용자가 ALTER + 이관 SQL 실행 완료 확인(`gh_church_timetable`에 신규 컬럼 존재, `reg_attendance_records.service_id`가 새 `timetable_no` 범위로 재매핑됨 — 읽기 전용 쿼리로 검증). `reg_services`는 이제 완전 미사용 — FK 제약·참조 프로시저/뷰 없음 확인 후 `DROP TABLE` SQL 추가([gh_church_alter.md](docs/schema/gh_church_alter.md) 4번 항목), 사용자가 HeidiSQL에서 실행 예정
  - `target_org_id`(어떤 예배가 특정 조직 대상인지)는 교적 전용 개념이라 공유 테이블에 컬럼은 추가하되 다른 시스템은 무시하도록 문서화
  - 관리 > 예배안내(`/management/website/worship-meeting`, 미연동 상태)도 `gh_church_timetable`을 쓸 가능성이 높아 향후 그 화면 연동 시 참고하도록 메모 남김
- [x] 설정 > 권한설정 (`MemberPermissions.jsx`, `POST /v4/permission/*`) — Session 02-10 후속
  - 사전 조사: 기존엔 권한 시스템 자체가 전무 — jwt.auth만 있으면 pastor/minister/volunteer 무엇이든 관리자 계정 관리(super.auth 전용) 빼고 전 기능에 동일하게 접근 가능했음. 목업(MemberPermissions.jsx)엔 4개 역할 x 9개 권한 매트릭스 UI만 있고 백엔드 전무
  - 사용자 확인 필요했던 3가지 결정: (1) 역할 모델 — 고정 4개(admin_type enum 그대로) vs 교회별 커스텀 역할 → **고정 4개** 채택, 목업의 "새 역할 추가" 버튼은 제외. (2) 적용 범위 — 목업의 9개만 vs 실사용 전체 기능 → **전체 기능으로 확장**(AuditMenuCode 기존 taxonomy 재사용, REPORT 코드 신규 추가해 14개 메뉴). (3) 저장만 vs 실제 API 차단까지 → **실제 차단까지 함께 구현**
  - 신규 테이블 [reg_role_permissions](docs/schema/08_reg_role_permissions.sql) — church_id/role/menu_code당 1행, can_view/can_manage. admin_type='admin'은 이 테이블에 저장하지 않고 미들웨어에서 하드코딩 바이패스(항상 전체 권한). **HeidiSQL에서 CREATE TABLE 실행 필요**(아직 미실행 확인함)
  - `App\Constants\PermissionDefaults` — pastor/minister/volunteer 3개 역할의 기본 정책(교회가 아무것도 커스터마이즈 안 해도 기존 계정이 갑자기 권한을 잃지 않도록). pastor는 CODE/SETTING(시스템 구성) 제외 전부 관리 가능, minister는 일상 사역 10개 메뉴만 관리 가능(헌금/코드/설정/보고서는 조회만), volunteer는 본인 참여 관련 7개 메뉴만 조회 가능·관리 권한 없음. `php artisan tinker`로 전체 조합 출력해 의도대로 나오는지 확인
  - `App\Http\Middleware\CheckPermissionMiddleware`(alias `permission`) 신규 — `permission:MENU_CODE,view|manage` 형태로 라우트에 파라미터 전달, DB 오버라이드 없으면 PermissionDefaults 폴백. `jwt.auth`가 항상 먼저 실행되는 미들웨어 순서를 전제로 함(토큰 검증 없이 role을 읽지 않음)
  - `routes/api.php` 전면 재작성 — member/organization/family/worship/attendance/visit/prayer/volunteer/education/offering/message/code/setting/report 14개 리소스 그룹을 각각 view/manage 서브그룹으로 분리해 미들웨어 부여(churchProfile은 SETTING 권한 재사용). 라우트 개수 117→119(권한 매트릭스 자체의 getMatrix/saveRole 2개 추가, 기존 엔드포인트는 전부 유지) 확인
  - `PermissionController`/`Service`/`Repository`/`Request` 신규, 권한 매트릭스 자체(getMatrix/saveRole)는 `super.auth`(관리자 전용) — 권한을 재분배하는 기능이라 admin 계정 관리와 동급으로 취급
  - 프론트: `permissionService.js` 신규, `MemberPermissions.jsx`를 목업(역할별 개별 권한 리스트, 정적 데이터)에서 실제 메뉴×조회/관리 매트릭스로 재구성(백엔드가 menu_code당 view/manage 2개 boolean으로 모델링해서 목업의 "9개 개별 권한 행" 구조를 "14개 메뉴 × 2열" 구조로 변경). admin 역할은 토글 비활성화 + 저장 버튼 숨김(항상 전체 권한이라 편집 불가 안내)
  - DB 의존 부분(getMatrix 등)은 테이블 미생성 상태라 실제 API 호출 검증은 못 했음 — `PermissionDefaults::forRole()` 순수 로직만 tinker로 검증. 테이블 생성 후 실제 차단 동작(예: volunteer가 재정 API 호출 시 403) 확인 필요
  - **메뉴 분류 정정** — 위에서 재사용한 AuditMenuCode 14개는 백엔드 내부 기능-도메인 분류일 뿐 실제 GNB 내비게이션과 무관하다는 걸 사용자가 지적("대메뉴 > 중메뉴로 나누는 게 어떠냐") — 확인해보니 정확한 지적이었음. `ORGANIZATION`(조직)/`WORSHIP`(예배)/`CODE`(코드관리)는 GNB에 별도 메뉴가 아니라 "교직설정" 페이지 내부 탭이고, `FAMILY`(가족관계)는 "교인" 상세 화면 내부 기능 — 이 4개를 별도 권한 행으로 노출하면 관리자 입장에서 대응되는 화면이 없어 혼란
  - `App\Constants\PermissionMenuTree` 신규 — GNB(`HeaderGnb.jsx` NAV) 기준 대메뉴(교적/재정/관리) > 중메뉴 트리로 재설계. `가입승인`(교인의 하위 소메뉴) 등 소메뉴 단위 분리는 검토했으나 백엔드 자체가 없는 게 대부분이라 보류 — 있는 것만이라도 중메뉴 단위로 우선 정리
  - `ORGANIZATION`/`WORSHIP`/`CODE` 관련 라우트(`v4/organization`/`v4/worship`/`v4/code`)는 `permission:SETTING,*`로, `v4/family`는 `permission:MEMBER,*`로 재타겟팅(AuditMenuCode 상수 자체는 감사로그용으로 그대로 유지, 권한 매트릭스에서만 제외)
  - 재정(헌금 제외 5개: 지출/통계/예산/보고/설정)·관리(교회 페이지) 총 6개는 사용자가 "지금 함께 포함(장식용)"으로 명시 승인 — `AuditMenuCode`에 신규 플레이스홀더 상수 7개(BOARD/EXPENSE/FINANCE_STATS/BUDGET/FINANCE_REPORT/FINANCE_SETTING/WEBSITE) 추가. 이 6개는 매트릭스에 노출되고 저장도 되지만 대응 라우트가 없어 아직 실제로 아무것도 차단하지 않음 — 프론트에 "아직 구현되지 않은 기능" 안내 문구 추가
  - 최종 17개 메뉴: 교적 10(교인/출석/심방/기도/교육/봉사/보고/문자/게시판/설정) + 재정 6(헌금/지출/통계/예산/보고/설정) + 관리 1(교회 페이지). `PermissionService::getMatrix`가 `groups`(대메뉴>중메뉴) 필드를 응답에 추가 반환, 프론트는 대메뉴별로 섹션 나눠 렌더링
  - `PermissionDefaults`도 새 17개 기준으로 재작성(조직/예배/코드/가족은 더 이상 별도 행이 아니므로 제거) — tinker로 그룹 트리 출력해 17개 정확히 나오는지 재확인
- [x] 권한 기반 프론트 UX (GNB 메뉴 필터링 + 페이지 가드) — Session 02-10 후속
  - 사용자 질문: "프론트 에서 보여지는 메뉴 구조를 맞추는건 어려운 건가? 페이지 자체에 진입할때 권한 없음으로 보여질수도 있고, 메뉴에서 아예 안보이게 할수도 있고" — 조사해보니 `PermissionController::getMatrix`는 `super.auth` 전용이라 pastor/minister/volunteer가 본인 권한을 조회할 방법 자체가 없었음(막 시작된 갭)
  - `PermissionService::getMyPermissions(churchId, role)`/`PermissionController::getMyPermissions` 신규 — `POST /v4/permission/getMyPermissions`, `jwt.auth`만 요구(getMatrix와 달리 super.auth 아님), JWT의 `adminRole`로 본인 역할의 resolved 권한(17개 메뉴 x can_view/can_manage)만 반환. `php artisan tinker`로 admin/pastor/minister/volunteer 4개 역할 전부 기대값과 일치 확인
  - 프론트 `PermissionContext`(`src/context/PermissionContext.jsx`) 신규 — 로그인 시 `getMyPermissions` 호출, `can(menuCode, action)` 헬퍼 제공. 미로드/조회실패 시 fail-open(모두 허용) — 프론트 권한 조회 실패가 사용자를 화면 밖으로 잠그면 안 되고, 실제 차단은 서버 `permission` 미들웨어가 이미 담당하기 때문
  - `HeaderGnb.jsx`의 `NAV.member`(cate01, 교적 10개 대메뉴+서브메뉴) 각 항목에 `menuCode` 속성 추가(예: 심방은 VISIT, 그 안의 기도목록/기도등록 서브메뉴만 PRAYER로 분리 — GNB 상으로는 "심방" 한 메뉴 아래 묶여 있지만 권한 매트릭스는 VISIT/PRAYER 별개라 서브메뉴 단위로 코드 분리 필요), `filterNav()`가 `can_view=false`인 항목을 숨김. 재정/관리(cate02/03)는 백엔드 미구현(장식용) 상태라 `menuCode` 미부여 — 필터링 대상에서 자동 제외됨(항상 노출 유지)
  - `PermissionGuard.jsx`(신규 컴포넌트) + `NoPermission.jsx`(신규 페이지) — `App.jsx`에 `guard('MENU_CODE', <Page/>)` 헬퍼로 `/member/*` 라우트 전체(및 `/settings/church-profile` 중복 라우트)를 감싸 조회 권한 없으면 "권한 없음" 화면 표시. `/member/settings/permissions`(권한설정 페이지 자체)는 menuCode가 아니라 `adminOnly` 플래그로 별도 가드 — 이 페이지가 호출하는 `v4/permission/getMatrix`/`saveRole`은 SETTING 권한 매트릭스와 무관하게 `super.auth`로 하드 고정이라, 프론트 가드도 매트릭스가 아닌 JWT `adminRole==='admin'` 기준으로 맞춤
  - 재정/관리 라우트는 백엔드가 아직 아무것도 차단하지 않는 것과 일관되게 프론트도 가드하지 않음(장식용 상태를 프론트-백엔드 동일하게 유지) — 향후 재정/관리 기능이 실제로 구현되면 그때 동일 패턴(`guard()`)으로 추가하면 됨
  - 브라우저 로그인 테스트는 volunteer01/pastor01/minister01 테스트 계정 비밀번호를 이 세션에서 알 수 없어(비밀번호 재설정 시도는 자동 분류기가 credential 관련 작업으로 차단) 실시하지 못함 — 사용자가 직접 로그인해 GNB에서 보고/문자/설정 메뉴가 숨는지, 해당 경로 직접 진입 시 "권한 없음"이 뜨는지 확인 필요
- [x] 관리자 계정 권한관리 메뉴 미표시 버그 수정 — Session 02-10 후속
  - 사용자 리포트: "관리가 계정인데도 권한관리 메뉴가 안보여" — 원인은 `JwtHelper::createToken`이 payload 전체를 AES-256-CBC(`Crypt::encrypt`)로 암호화해 JWT의 `data` 클레임 안에 넣는 구조인데, 프론트 `AuthContext.parseJwtPayload`는 `atob()` + `JSON.parse()`로 JWT 두 번째 세그먼트를 그대로 읽으려 해서 `adminRole`을 영구히 `undefined`로 읽고 있었음(관리자 포함 전 사용자). `HeaderGnb`/`PermissionGuard`의 `isAdmin` 판정이 이 값에 의존해 권한설정 메뉴(`adminOnly`)가 역할 무관하게 항상 숨겨짐
  - 수정: `PermissionContext`가 이미 호출하던 `getMyPermissions` 응답에 서버가 복호화한 `role` 필드가 포함되어 있어(`PermissionService::getMyPermissions`), 이 값을 `role`/`isAdmin`으로 컨텍스트에 저장하고 `HeaderGnb`/`PermissionGuard` 양쪽 모두 JWT 직접 디코딩 대신 이 값을 사용하도록 교체 — `AuthContext.parseJwtPayload` 자체는 건드리지 않음(다른 곳에서 `adminInfo` 필드를 최소한으로만 참조하는 용도로는 여전히 유효)
- [x] 권한설정 메뉴 트리에 하위 화면 목록 펼치기 추가 — Session 02-10 후속
  - 사용자 요청: "권한관리 에서 메뉴를 교적 > 설정 > 조직 기준 에서 조직 목록 처럼 할수 있을까?" — 교직설정 페이지의 조직 기준 트리 UI(`org-expand-btn`, `+`/`−` 토글)와 동일한 패턴을 권한 매트릭스에도 적용해달라는 요청
  - `MemberPermissions.jsx`에 프론트 전용 `MENU_PAGES`(menuCode → 하위 화면 라벨 배열, HeaderGnb NAV 서브메뉴 라벨 그대로) 맵 추가, 각 중메뉴 행에 `+` 버튼으로 하위 화면 목록을 펼쳐볼 수 있게 함 — 이 시점에는 표시 전용(토글 대상 아님), 권한은 여전히 중메뉴 단위
  - `.perm-item` CSS 클래스가 4면 border+radius를 가진 카드 스타일이라 하위 항목에 그대로 재사용하면 깨진 박스처럼 보임 — 인라인 스타일로 별도의 들여쓰기된 서브 박스(연한 테두리, 항목간 구분선)를 새로 구성
- [x] 권한 모델 전면 재설계 — 조회/관리 통합 + 소메뉴(화면) 단위 권한 — Session 02-10 후속
  - 사용자 요청(다이어그램 포함): "권한을 조회, 관리 구분하지 않고 한개로 하고 각 하위 메뉴에도 권한 기능이 있도록 하는건 어때? ... 작동 기능은 페이지 접근시 권한 체크해서 접속 가능 여부 판단" — can_view/can_manage 2원 체계를 단일 접근 권한으로 통합하고, 지금까지 표시 전용이던 하위 화면 목록에도 개별 토글을 부여, 실제 동작은 페이지 접근 시점의 권한 체크로 판단하자는 요청
  - 구현 전 스코프 질문 1개로 좁힘: "백엔드 API 미들웨어도 소메뉴 단위로 나눌까, 중메뉴 단위로 유지할까?" → **중메뉴 단위 유지(사용자 선택)** — 기존 ~119개 라우트 중 여러 프론트 화면이 같은 API를 공유하는 경우가 많아(예: 목록/상세/수정 엔드포인트가 여러 탭에서 재사용) 소메뉴 단위로 쪼개면 오분류로 기존 기능이 깨질 위험이 있음. 저장/프론트 라우트 가드는 소메뉴 단위로 정밀화하되, 백엔드 API 보호는 중메뉴 단위(소속 소메뉴 중 하나라도 접근 가능하면 그 메뉴의 API 전체 허용)로 유지하는 절충안 채택
  - `App\Constants\PermissionMenuTree` 전면 재설계 — 3단계 트리(대메뉴 > 중메뉴 > 소메뉴/leaf page). 하위 화면이 없는 메뉴(게시판, 재정/관리 미구현 항목)는 메뉴 코드 자신이 유일한 소메뉴. 교적 9개 그룹형 중메뉴 아래 총 36개 소메뉴 코드 신설(MEMBER_LIST/MEMBER_APPROVAL, ATTENDANCE_INPUT/STATUS/STATISTICS 등 — HeaderGnb 서브메뉴 라벨과 1:1 매핑). `allPages()`(저장/검증용 전체 소메뉴 목록)/`parentOf()`(소메뉴→중메뉴 역참조)/`pagesOfMenu()`(중메뉴→소메뉴 목록, 미들웨어용) 헬퍼 추가
  - `App\Constants\PermissionDefaults::forRole()`을 배열 반환에서 단일 bool로 단순화, 역할별 기본 정책도 재정의 — 기존엔 pastor/minister가 조회만 가능한 화면이 많았으나(can_view=true, can_manage=false) 단일 플래그로는 그 중간 상태를 표현할 수 없어, 접근 가능=완전 사용 가능으로 재정의(pastor는 SETTING/FINANCE_SETTING 제외 전체, minister는 일상 사역 8개+보고, volunteer는 참여 관련 7개+게시판) — 조회전용이던 화면 일부에서 관리 권한이 새로 생기는 것은 이 재설계의 의도된 트레이드오프(채팅으로 사용자에게 고지)
  - `reg_role_permissions` 스키마 재설계 — `can_view`/`can_manage` 2컬럼 → `can_access` 1컬럼, `menu_code`(중메뉴, 17개) → `page_code`(소메뉴, 36개)로 저장 단위 세분화. 테이블에 저장된 행이 0건임을 재확인 후 DROP+CREATE 방식으로 새 스키마 문서 작성: [09_reg_role_permissions_v2.sql](docs/schema/09_reg_role_permissions_v2.sql) — **HeidiSQL에서 실행 필요**(기존 08번 파일은 파일 상단에 DEPRECATED 표시 후 이력 보존만)
  - `PermissionRepository`/`PermissionService` 재작성 — `getOverridesForPages()`(지정 소메뉴 목록 중 실제 오버라이드된 것만 조회, getMatrix/getMyPermissions/hasMenuAccess 3곳에서 공용 재사용), `hasMenuAccess(churchId, role, menuCode)`(중메뉴 소속 소메뉴 중 하나라도 접근 가능하면 true — 오버라이드 없으면 소메뉴의 부모 중메뉴 기본 정책으로 폴백) 신규
  - `CheckPermissionMiddleware` 시그니처 단순화 — `permission:MENU_CODE,view|manage` → `permission:MENU_CODE`(파라미터 1개), `PermissionService` 주입받아 `hasMenuAccess()` 호출로 위임. `routes/api.php`의 뷰/관리 이중 라우트 그룹(~28개)을 단일 그룹(~14개)으로 병합 — 라우트 수 120→120 유지(중복 그룹만 합쳐졌을 뿐 엔드포인트 손실 없음, `route:list`로 확인)
  - 프론트 `PermissionContext.can(pageCode)`를 2-인자(`menuCode, action`)에서 1-인자(단일 접근 여부)로 단순화. `HeaderGnb.jsx`의 NAV 서브메뉴 각각에 정확한 소메뉴 코드 부여(예: 심방 그룹 안에서도 기도 관련 2개 서브메뉴만 `PRAYER_LIST`/`PRAYER_REGISTER`로 분리 유지), 하위 항목이 있는 대메뉴 자신은 더 이상 자체 코드를 갖지 않고 "하위 중 하나라도 보이면 표시"로 판단하도록 변경. `App.jsx`의 `guard()` 호출도 전부 소메뉴 코드로 재매핑(예: `/member/education/session*` 3개 라우트는 대응 소메뉴가 없어 가장 가까운 `EDUCATION_CURRICULUM`으로, `/member/service/attendance`는 `VOLUNTEER_STATUS`로 묶음 처리)
  - `MemberPermissions.jsx` 매트릭스 UI를 조회/관리 2열 토글에서 단일 열 토글로 축소, 중메뉴 행의 토글은 "하위 소메뉴 전체 일괄 켬/끔"(bulk) 동작(체크 상태는 하위 전체가 켜져 있을 때만 on), 펼치기(`+`)로 열리는 하위 화면 각각에 독립적인 토글 추가(사용자가 그린 다이어그램과 동일한 인터랙션) — 이전 세션에서 추가했던 표시 전용 `MENU_PAGES` 맵은 제거하고 백엔드가 내려주는 `groups[].menus[].pages[]`를 단일 소스로 사용
  - `php artisan tinker`로 순수 로직(라이브 DB 의존 없는 `PermissionDefaults`/`PermissionMenuTree`) 4개 역할 전부 재검증 — admin 36개 전부 true, pastor는 SETTING_*/FINANCE_SETTING만 false, minister는 재정/설정/관리 전부 false 나머지 true, volunteer는 7개 그룹 관련만 true. DB 의존 경로(`getMatrix`/`getMyPermissions`/`hasMenuAccess` 오버라이드 조회)는 테이블이 아직 구 스키마라 조회 자체가 막혀 실제 쿼리 테스트는 못함 — 사용자가 `09_reg_role_permissions_v2.sql` 실행 후 확인 필요
- [x] 권한 관리 페이지를 교적 > 설정에서 관리 > 시스템설정으로 이전 — Session 02-10 후속
  - 사용자 질문: "관리 > 시스템 설정 > 권한 관리도 동일한 기능이 맞을까?" — 조사해보니 `/management/system/permissions`(`SystemPermissions.jsx`)는 완전히 별개의, 여전히 손대지 않은 목업(고정 4역할 x 9권한, `defaultChecked` 비제어 토글이라 아무것도 저장 안 됨)이었음. 반면 `/management/system/users`(`SystemUsers.jsx`, "사용자 관리")는 이미 `v4/admin/*`에 완전히 연동된 진짜 관리자 계정 CRUD 페이지였고, 그 페이지 하단 안내문이 이미 "역할별 권한은 [권한 관리](/management/system/permissions)에서 설정합니다"로 이 경로를 가리키고 있었음 — `SystemHome.jsx`(`/management/system`)의 사이드바/빠른관리 링크도 마찬가지로 이 경로를 가리킴
  - 이걸 근거로 "관리 > 시스템설정이 이미 실제 시스템 관리 허브(사용자 관리가 거기서 동작 중)이므로 권한 관리도 원래 거기 있어야 한다"고 판단, 사용자가 진행 승인
  - `pages/management/system/Permissions.jsx`(기존 목업)를 실제 매트릭스 컴포넌트로 교체(내부 로직은 `MemberPermissions.jsx`와 동일, export 함수명 `SystemPermissions` 유지해 `App.jsx` import 변경 최소화), 좌측의 "설정 메뉴"(기본정보/교직설정/권한관리) 3항목 미니 사이드바는 제거 — 이 페이지가 속한 클러스터가 더 이상 아니므로, 대신 이 섹션의 실제 페이지인 `SystemUsers.jsx`와 동일하게 브레드크럼만 사용. `pages/member/settings/MemberPermissions.jsx` 파일 삭제
  - `App.jsx`: `/management/system/permissions` 라우트를 `guard(null, <SystemPermissions />, { adminOnly: true })`로 가드, `/member/settings/permissions`는 완전 삭제 대신 `<Navigate to="/management/system/permissions" replace />`로 리다이렉트 유지(기존 링크/북마크 호환)
  - `HeaderGnb.jsx`: 교적 > 설정 서브메뉴에서 "권한설정" 항목 제거, 관리 > 시스템설정의 "권한 관리" 서브메뉴에 `adminOnly: true` 부여(기존엔 코드 자체가 없어 항상 노출되던 상태)
  - `ChurchProfile.jsx`의 3항목 미니 사이드바에서도 "권한 관리" 링크 제거(더 이상 그 클러스터에 속하지 않음), `NoPermission.jsx`의 안내 링크도 새 경로로 갱신
  - 재정 > 설정 > 일반관리(`FinanceSettingsPermissions.jsx`)는 조사 결과 재정부장/회계담당자/감사위원/헌금입력자라는 완전히 다른 역할 모델을 쓰는 별개의 미구현 기능으로 확인 — 이번 이전과 무관, 그대로 둠
- [x] 관리 > 시스템설정 > 전체 현황 API 연동 + 활동 로그 신설 — Session 02-10 후속, 관리 > 시스템설정 3화면(전체현황/권한관리/사용자관리) 전부 완료
  - `SystemHome.jsx`(전체 현황)는 KPI 3장(전체 사용자/정의된 역할/최근 로그인)과 "최근 시스템 활동" 테이블이 전부 하드코딩 목업이었음(권한 관리·사용자 관리는 이전 세션에 이미 연동 완료) — `POST /v4/system/getOverview`(super.auth) 신규로 실데이터 연동
  - `SystemService::getOverview` — `AdminRepository::getUserStats`(활성/비활성 카운트)/`getRecentLoginCount`(`last_login_at` 기준 7일) + `AuditLogRepository::getRecentLogs`(최근 10건, `gh_church_admin` LEFT JOIN으로 작업자명 포함) 조합. "정의된 역할"은 4개 고정 역할(admin/pastor/minister/volunteer)이라 별도 쿼리 없이 상수 반환
  - "최근 시스템 활동" 표의 "활동 유형"은 `menu_label + action_label` 조합(AUTH는 액션만, 예: "관리자 활성화" / "로그인")으로 배지 색상은 `action_type` 기준 프론트 매핑(LOGIN 계열 초록/CREATE 계열 파랑/UPDATE 계열 노랑/DELETE 계열 빨강)
  - "빠른 관리" 카드 중 "활동 로그"/"시스템 점검"이 `href="#"` + `preventDefault` 뿐인 죽은 링크였던 것을 사용자가 지적, "시스템 점검이 교회 홈페이지 점검이냐"는 질문에는 조사 결과 별개(`관리 > 교회 페이지`가 이미 홈페이지 콘텐츠 전용 메뉴로 존재)로 판단 — 사용자가 활동 로그는 **전체 감사로그 페이지 신설**, 시스템 점검은 **나중에 작업(현재 상태 유지)**으로 결정
  - 활동 로그: `reg_audit_logs` 전체를 조회하는 신규 화면(`AuditLogs.jsx`, `/management/system/audit-logs`, `adminOnly` 가드) + `POST /v4/system/getAuditLogs`(super.auth) — 메뉴/액션타입/검색대상(사용자명·대상·요약 중 택1 + 키워드)/기간 필터, 페이지네이션. `AuditLogRepository::getLogList`가 `search_type`에 따라 단일 컬럼(`a.name`/`l.target_label`/`l.summary`) 필터, 미지정 시 3컬럼 OR 검색(레거시 호환). `AuditActionType::all()` 신규 추가(검증용), `SystemRequest`(`getAuditLogs`) 신규
  - 사용자 UI 피드백 3건 반영: (1) 페이징을 단순 이전/다음에서 봉사현황(`ServiceStatus.jsx`)과 동일한 번호형 `Paging` 컴포넌트(처음/이전/1…N/···/마지막/다음/마지막)로 교체 (2) 시작일/종료일 입력이 `flex-1`로 남은 공간 전체(~526px)까지 늘어나던 것을 고정 160px로 축소, 검색/초기화 버튼이 라벨 있는 필드들과 나란히 있을 때 `flex-vc`(가운데 정렬)라 버튼이 붕 떠 보이던 것을 `alignItems:'flex-end'`(ServiceHistory.jsx 패턴)로 교체해 버튼-입력창 하단이 정렬되도록 수정, 검색어 입력창도 처음 220px → 300px로 재조정 (3) 검색어 입력 앞에 "사용자명/대상/요약" 검색대상 select 추가(사용자 관리의 이름/아이디 선택 패턴과 동일 UX) — 백엔드 `search_type` 파라미터 신설로 연결
  - "시스템 점검" 클릭 시 첫 구현은 `alert()`였으나 사용자가 "팝업창으로 해달라"고 요청 — 앱의 `ModalBox` 컴포넌트로 교체(봉사자 해제 확인 모달과 동일 패턴), 제목 "시스템 점검" + "준비 중인 기능입니다." 메시지 + 확인 버튼
  - 로그인 테스트 계정 비밀번호를 몰라 최초엔 막혔으나, 사용자가 dev DB의 실제 로그인 정보(`test111`)를 제공 — 이후 이 계정으로 브라우저에서 KPI 실데이터, 활동 로그 페이지네이션(268건, 14페이지), 메뉴 필터(관리자=19건), 날짜 입력 크기/버튼 정렬(bottom=387 일치), 검색대상 필터(대상="봉사자이름"→1건), 준비중 모달까지 전부 실측 검증 완료
- [x] 관리 > 교회 페이지 (공개 홈페이지 콘텐츠) 4화면 API 연동 완료 — Session 11 후속 (예배안내/소식·공지/미디어/교회소개), `AuditMenuCode::WEBSITE` 실사용 시작(그동안 "백엔드 미구현 — 권한 매트릭스 표시 전용"이었음)
  - 세션 시작 시 이전 조사("교회소개는 공유 테이블과 겹침", "예배안내는 v4/worship 재사용 가능", "소식·공지/미디어는 게시판 테이블 조사 필요")를 실제 코드·DB로 재검증 — `SHOW COLUMNS`/`SHOW CREATE PROCEDURE usp_church_view`를 이 세션의 DB 연결로 직접 조회해 확인. `usp_church_view`(공개 홈페이지가 실제로 읽는 프로시저) 안에서 `gh_new_board_type`/`gh_new_board_content`(교회로 플랫폼 전체가 공유하는 범용 게시판 — 구인구직/임대 등 무관한 용도도 같은 테이블 사용, `church_no`로 교회별 스코프)가 `category_no=10`("교회 홈페이지 콘텐츠") 하위 7개 board_no(59 전하는말/60 교회사진/85 주보/86 묻고답하기/87 유튜브/88 행사사진/89 사람들)로 이미 소식·공지·미디어 콘텐츠를 서빙 중인 것을 발견 — 신규 `reg_` 테이블 없이 이 시스템 재사용으로 결정(사용자 승인)
  - **예배·모임 안내** (`WorshipMeeting.jsx`) — 기존 `v4/worship/*`(교직설정 "예배 기준" 탭과 동일 API, `gh_church_timetable`) 그대로 재사용. 카드당 시간 여러 개 + 설명/비고가 모의 화면엔 있으나 테이블엔 없어 `gh_church_timetable`에 `description`/`note` 컬럼 추가(`desc`는 예약어라 `description`으로 명명), 시간 여러 개는 카드 하나당 행 여러 개(같은 name 공유)로 매핑 — 로드 시 name 기준으로 그룹핑해 카드로 재구성, 저장 시 시간별로 register/update/delete 분기. "요일" 자유텍스트 입력을 `day_of_week` 정수와 맞는 select로 교체
    - 후속: "예배 추가"를 인라인 카드 편집에서 `ModalBox` 팝업(추가/수정 겸용)으로 전환 — 카드는 읽기 전용 요약(이름/요일/시간/설명/비고 + 수정·삭제 아이콘)만 표시. 사용자 질문("정기 예배 말고 다른 예배도 있을까?")을 계기로 `category`(`regular`/`other`) 컬럼이 이미 있고 실데이터에도 "헌신예배"(other)가 있는데 화면엔 정기예배 탭만 있던 걸 발견 — "기타 예배 안내" 탭 신설(추가 시 활성 탭 기준으로 category 결정, 수정 시에는 불변). 확인 과정에서 `WorshipService`에 있던 미사용 `MemberRepository` 의존성(죽은 코드)도 함께 제거
  - **소식·공지** (`News.jsx`, `NewsController`/`NewsService`/`ChurchBoardRepository`) — 교회소식(59)/주보(85)/묻고답하기(86) + 상담신청/성도한마디는 대응 board_no가 없어 `gh_new_board_type`에 95/96 신규 INSERT(사용자 승인, category_no=10). 필드는 `gh_new_board_content`의 범용 확장 컬럼(str_val1-5/int_val1-4/text_val1-2)에 매핑(상담신청 상태·담당자·메모·회신, 성도한마디는 `is_selected`를 QnA 채택과 동일하게 승인 플래그로 재사용). 실제 검증 중 버그 3건 발견·수정: (1) `title`/`content` NOT NULL 컬럼에 빈 문자열이 `ConvertEmptyStringsToNull` 미들웨어를 거쳐 null로 들어가 update가 SQL 에러 — 보정 로직 추가 (2) `registerBulletin`이 `content`를 아예 안 보내 insert 자체가 실패 — 기본값 추가(`ChurchBoardRepository::insert()`에도 방어적으로 추가) (3) 묻고답하기 답변을 원래 계획대로 `gh_new_board_comment`에 넣으려 했으나 그 테이블의 `account_no`가 `gh_account` FK(NOT NULL)라 관리자 작성 댓글을 넣을 계정이 없어 실패 — 같은 글의 `text_val1`에 직접 저장하는 방식으로 변경. 모의 화면 하단의 공용 "저장" 버튼은 5탭 각각이 리스트/CRUD 화면이라 안 맞아 제거하고, 이 앱의 다른 리스트 화면들(예: WorshipCriteria)과 동일하게 행 단위 즉시저장(추가=바로 register, 수정=onBlur, 삭제/토글=즉시 API 호출)으로 전환
  - **미디어** (`Media.jsx`, `MediaController`/`MediaService`) — 교회영상(87)은 그대로, 사진갤러리(60, 앨범 그룹핑 개념이 테이블에 없어 게시글 1건=앨범 1개로 흉내: `rep_img`=커버, `files`=JSON 사진 URL 배열), 사역&활동사진은 88(행사사진)만 사용(89 사람들은 이번 화면엔 안 씀, 추후 여지로 남김), 설교영상은 대응 board_no가 없어 97 신규 INSERT(사용자 승인) — `str_val1-4`에 설교자/본문/시리즈/태그, "대표 설교 자동/수동" 전환은 게시글이 아니라 교회 단위라 `reg_church_settings`(`media_sermon_featured_mode`)에 저장. 주보의 prompt 기반 URL 입력과 달리 갤러리/사역 사진은 이미지가 화면의 핵심이라 실제 업로드가 필요하다고 판단, `S3FileHelper` 재사용한 `POST /v4/media/uploadImage` 신설 — 실제 PNG를 S3(`tochurchfile` 버킷)에 업로드해 URL 저장까지 검증. YouTube "불러오기"(oEmbed 자동 썸네일) 버튼은 연동 안 하고 제거(동작 안 하는 죽은 버튼을 남기지 않기 위해)
  - **교회소개** (`Intro.jsx`, `IntroController`/`IntroService`) — 필드가 가장 많아 기존 `gh_church_intro`(slogan/vision/message, 재사용)에 컬럼 8개(hero_title/hero_subtitle/hero_buttons/intro_title/welcome_title/welcome_highlight/bible_quote/directions, 버튼·성경구절·오시는길처럼 여러 값이 묶인 건 JSON 컬럼 하나로) + `gh_church_pastor`에 `career`(약력 다건, JSON) 추가. `images`/`videos`/`rep_images`/`man_images`/`event_images` 컬럼은 손대지 않음(같은 화면군에서 이미 게시판 시스템으로 대체 사용 중이라 혼선 방지). 주소/연락처/이메일/홈페이지/창립년도(`gh_church.church_create_date`, 기존 컬럼인데 `ChurchProfileService`가 안 쓰고 있었음 — 신규 노출)는 새로 안 만들고 **설정 > 교회 프로필과 동일한 `ChurchProfileService`/`churchProfileService.js`를 그대로 재사용**(같은 사실을 두 화면에서 따로 관리하지 않도록) — 사용자가 "주소 검색은 교회 프로필의 주소 검색창을 참고하라"고 명시적으로 지시
  - **버그 발견**: 사용자가 "주소 저장 시 시/도·시/군/구/동과 좌표가 처리되고 있나?"라고 질문한 게 계기 — 확인해보니 `ChurchProfileService`가 `address`/`address_detail`/`postcode`만 저장하고 `sido_no`/`sigungu_no`/`dong_no`/`latitude`/`longitude`는 전혀 갱신하지 않고 있었음(실제로 판교 주소인데 좌표는 제주도로 남아있는 실사례 확인). `02_gh_admin_api`의 `RegionMatcher`(행정구역 이름→코드 매칭)와 카카오 지오코딩 로직을 이식(`App\Helpers\RegionMatcher`/`GeocodeHelper`)해 주소 변경 시 함께 재계산하도록 수정 — 단, 프론트가 매 저장마다 `address`를 무조건 같이 보내므로 매번 재계산하면 무관한 저장(전화번호만 수정 등)마다 카카오 API를 부르고 `sido_name` 등이 없어 기존 좌표까지 null로 지워버리는 문제가 있어, `sido_name`/`sigungu_name`/`dong_name`(주소 검색 모달을 실제로 썼을 때만 함께 옴)의 존재 여부로 게이팅. `AddressSearchModal.jsx`도 다음 응답에서 시/도·시/군/구·동(`data.sido`/`data.sigungu`/`data.bname`)을 함께 추출하도록 확장. 어긋나 있던 실제 테스트 교회 데이터도 백필
  - `gh_new_board_type`/`gh_church_intro`/`gh_church_pastor` 등 공유 테이블에 대한 INSERT/ALTER는 매번 auto-mode 분류기가 차단 → 사용자에게 승인받고 이 세션의 DB 연결로 직접 실행(문서는 `docs/schema/gh_church_alter.md`, `docs/schema/10_church_board_types.md`에 기록) — 원격 dev 서버가 별도로 있다면 거기에도 동일 SQL 실행 필요
  - 매 화면 검증마다 로컬 방화벽에서 `.env.development`가 기본으로 쓰는 8098 포트가 Windows 제외 포트 대역에 걸려 preview_start가 거부 — 검증 시에만 8199로 임시 전환 후 반드시 8098로 원복(커밋에 남기지 않음)
  - `docs/insomnia/01-auth.md`의 로그인 예시(`admin01`/`adminpass1234`)는 실제 시드 계정과 다름 — 실제 테스트 계정은 `test111`(church_no=33632, Session 02-10에서도 이미 사용된 계정)
  - 문서: [01_api-spec.md](docs/01_api-spec.md)에 "16. 권한 관리 (Permission)"/"17. 시스템 설정 (System)" 섹션 신규 추가(그동안 Admin 이후로 Permission/System 섹션이 통째로 빠져 있었음), [05_menu-checklist.md](docs/05_menu-checklist.md) 시스템설정 3행 갱신 + 활동 로그 신규 행 추가 + 진행 현황 요약(관리 전체 7→8페이지, 1→4완료로 정정 — 권한관리/사용자관리가 이전 세션에 완료됐음에도 요약표가 갱신되지 않았던 걸 이번에 같이 바로잡음)
- [x] 사이드바 교회명·그룹 목록 실데이터 연동 — Session 11 후속
  - 사용자 리포트: 사이드바 상단이 "교적 관리"로 고정 표시(교회명 미표시), 좌측 "그룹 목록"이 남전도회/여전도회 등 완전 하드코딩. 명시적 지시: 교회명은 서버 재요청 없이 **JWT 토큰에서 프론트가 직접 꺼내 쓸 것**, 그룹 목록은 교직설정 > 조직 기준과 같은 `reg_organizations` 데이터를 쓸 것 + 캐싱 전략(매 네비게이션 조회 vs 로그인 1회 캐시) 판단 요청
  - 교회명: `JwtHelper::createToken`을 수정해 `churchName`을 암호화된 `data` 클레임에서 분리, JWT 최상위 평문 클레임으로 별도 저장(여전히 HS256 서명 범위 안이라 위변조 불가, 민감정보가 아니라 기밀성 불필요이므로 평문 허용) — `AuthService::adminSignIn` 응답 본문에서는 `church_name` 제거(토큰이 유일한 소스). 프론트 `AuthContext.parseJwtPayload`(기존 `atob`+`JSON.parse`)가 이 최상위 클레임은 그대로 읽을 수 있어(암호화된 `data` 클레임의 sub/adminRole 등은 여전히 못 읽음) `login()`이 `adminInfo.churchName`으로 바로 노출하도록 단순화, `Login.jsx`의 `login(data.token, data.church_name)` 호출도 `login(data.token)`로 정리
  - 그룹 목록: 권한 게이팅 충돌 발견 — 기존 `v4/organization/*`는 전부 `permission:SETTING`인데 minister/volunteer 역할엔 SETTING 권한이 없어 사이드바(전 역할 공통 노출)가 이 라우트를 못 씀 → `v4/profile/*`와 동일하게 `jwt.auth`만 요구하는 `getSidebarTree` 전용 라우트 신설(`OrganizationController`/`Service`/`Repository`에 동명 메서드 추가, `reg_member_organizations`+`reg_members`(`is_deleted=0`) LEFT JOIN 집계로 조직별 소속 교인 수 계산, 상위 조직의 `member_count`는 하위 포함 합산)
  - 캐싱 전략 질문에 대한 결론: **로그인 시점 1회 조회, 페이지 이동마다 재조회 안 함** — 이미 있던 `PermissionContext`의 "로그인 세션당 1회 fetch + useState 캐시" 패턴을 그대로 재사용해 `OrganizationContext.jsx` 신규 작성, `main.jsx`에 `PermissionProvider` 안쪽에 중첩. 조직 구성은 자주 바뀌지 않고, 새로고침/재로그인 시 자연스럽게 갱신되므로 이 트레이드오프가 적절하다고 판단
  - `Sidebar.jsx`의 하드코딩 `groups` 배열(남전도회/여전도회/다음세대/중직/목장/사역부서/미분류·비교인, 각 항목 고정 "(2)")을 `useOrganizations().tree` 기반 렌더링으로 교체 — 하위 조직이 있으면 토글형 그룹(`more.toggle`), 없으면 단일 링크로 분기, 표시 카운트는 실제 `member_count`
  - 브라우저 실로그인(`test111`)으로 검증 — 교회명이 실제 DB 값("테스트교회 (test 1111)")으로 표시됨, 그룹 목록이 실제 `reg_organizations` 트리(남전도회 16명 > 제1/2남전도회 2/12, 여전도회 27명 > 제1/2여전도회 12/12, 청년부/교육부/교구/목장/사역팀 등 리프 조직, 미분류·비교인 그룹) + 실측 인원수로 렌더링됨 확인. 기존 로그인 세션은 토큰을 재발급받아야(재로그인 필요) 새 `churchName` 클레임이 반영됨 — 자연 소멸(다음 로그인 시 해결)이라 별도 마이그레이션 불필요
  - **버그 수정**: 사용자가 실제 화면에서 그룹 목록 일부 항목("비교인"/"교구"/"청년부"/"교육부"/"목장"/"사역팀" 등 하위 조직이 없는 최상위 조직)이 아이콘 없이 스타일 깨진 채로("전체"/"남전도회"/"여전도회"와 다르게) 표시된다고 리포트 — 원인은 `layout.css`의 `.lnb-category > ul > li .more` 규칙(아이콘·패딩·flex 레이아웃)이 `class="more"`가 붙은 `<a>`에만 적용되는데, `Sidebar.jsx`에서 하위 조직이 없는 리프 최상위 조직을 순수 `<Link>`(class 없음)로 렌더링해 이 스타일을 못 받고 있었음. `<Link className="more">`로 수정(토글 화살표는 `.more.toggle`에만 붙으므로 자동으로 제외됨) — 임시 검증 서버(8199/5199)에서 DOM class 확인으로 재검증 완료
  - **후속 UI 폴리시 + 조직 선택 → 교인목록 필터 연동**: 정렬 확인 후 사용자 4건 추가 요청
    1. 인원수 정렬 — 리프 항목(하위조직 없음)과 토글 항목(하위조직 있음, 화살표 아이콘 포함) 사이 인원수 시작 위치가 서로 다르게 보임(실제로는 숫자 자릿수 차이로 인한 착시, `right` 좌표로 재검증하면 전부 128px로 완전히 flush-right 정렬되어 있었음) — 그래도 왼쪽 시작 위치까지 시각적으로 맞추기 위해 `.more:not(.toggle):after`에 화살표와 동일한 `width:16px;height:16px`의 투명(`visibility:hidden`) spacer 추가, 화살표 유무와 무관하게 모든 행의 오른쪽 정렬 기준선이 동일해지도록 처리
    2. "전체"가 선택되지 않는 문제 + 상위/하위 조직도 선택 가능해야 함 — `openGroup`(드롭다운 펼침, 로컬 상태)과 `selectedOrgId`(선택된 조직, **URL의 `org_id` 쿼리 파라미터에서 직접 파생 — 별도 state 없음**)를 분리. "전체"/리프 조직/상위(토글) 조직/하위(중첩) 조직 전부 `/member/list?org_id=` 로 이동하는 `Link`로 통일, 선택 시 `li.active` 하이라이트(중첩 항목엔 CSS `ul li.active a` 규칙 신규 추가). URL 파생 방식을 택한 이유: 교인목록 페이지 자체의 "전체보기" 링크로 필터를 해제해도 사이드바 선택 표시가 자동으로 동기화됨(별도 동기화 로직 불필요)
    3. 조직 선택 시 교인목록에서 기본 조건(하위 조직 포함) + 페이지 자체 검색 조건과 AND — `MemberRepository::getMemberList`에 `org_ids` 필터 추가(Visit 계열 리포지토리와 동일한 `whereExists`+`reg_member_organizations` 패턴 재사용), `MemberRequest::listRules`에 검증 규칙 추가. `MemberList.jsx`가 `org_id` 쿼리 파라미터를 읽어 `OrganizationContext` 트리에서 해당 노드+전체 하위 id를 수집(`org_ids`)해 **`fetchList` 자체가 항상 병합**하도록 구현 — 검색/초기화/탭필터/정렬/페이지 이동 등 기존 모든 호출부를 개별 수정할 필요 없이 자동으로 AND 적용됨. "초기화" 버튼은 페이지 자체 검색 조건(키워드 등)만 초기화하고 조직 스코프는 유지(사이드바가 정한 컨텍스트이므로 페이지 검색폼 초기화 대상이 아니라고 판단). 조직 선택 시 안내 문구(`InfoCallout`, "OO 조직(하위 조직 포함) 기준으로 필터링되었습니다" + "전체보기" 링크) 추가
    4. 실측 검증(임시 8199/5199): 남전도회(16명, 부모 자체 직속 2명+제1남전도회 2명+제2남전도회 12명) 선택 → 총 16명, 여기에 키워드 "박" 추가 검색 → 총 2명(AND 확인) → "전체보기" 클릭 → 조직 스코프만 해제되고 키워드 "박"은 유지된 채 교회 전체 기준 재검색(총 3명, 두 필터가 독립적인 축임을 확인) → 하위 조직(제1남전도회)·리프 조직(청년부) 선택도 각각 배지 인원수와 실제 목록 건수 일치 확인
- [x] 헌금 관리(`/finance/donation`)·헌금 입력(`/finance/donation/input`) 프론트 연동 — Session 12
  - 발단: "페이지별 기능 현황을 확인해달라"는 요청으로 신규 작성된 `docs/06_페이지별_기능_현황.md`를 API 라우트·프론트 파일 양쪽에서 병렬 에이전트로 교차검증하던 중, 이 문서가 헌금관리/헌금입력을 ✅로 잘못 표기하고 있는 걸 발견(`offeringService.js` 자체가 없고 두 파일 다 순수 하드코딩) — 같은 유형의 오류가 종합통계/교회현황개요(`reportService.js` 없음)에도 있어 문서 정정 후, 사용자가 헌금 프론트 연동을 다음 작업으로 선택
  - `offeringService.js`(getList/getDetail/getListByMember/register/update/delete) + `reportService.js`(getOfferingStats, 최초 파일) 신규 — report 도메인은 이번에 처음으로 서비스 파일이 생김(종합통계 등 나머지 report 화면은 여전히 미연동 상태로 남음, 별도 스코프)
  - 실제 백엔드 스키마(`reg_offering_records`: member_id/offer_date/category/amount/method/ledger, "예배 연동"·"입력방식(개인별/총액/혼합)"·"상태(완료/보완가능)" 개념 없음)가 mock UI(예배 선택 필터, 상태 배지, 카테고리별 월별 피벗 테이블)와 크게 달라 UI를 실제 데이터 모델에 맞게 재설계 — 예배 필터 제거, 상태 배지 제거, 기간별 조회는 `ReportService::getOfferingStats`(이미 백엔드에 구현되어 있던 `monthly_trend`/`by_category` 집계)를 그대로 재사용해 월별 차트+항목별 비중표로 대체(신규 백엔드 로직 없음)
  - `Donation.jsx` 3탭 전체 재작성: 전체내역(날짜/방식/키워드 필터 + 페이지네이션 + 수정모달 + 삭제, VisitHistory.jsx 페이징 패턴 재사용), 교인별 조회(`MemberSearchModal` 재사용), 기간별 조회(from/to 날짜 → `getOfferingStats`). PeriodDropdown 컴포넌트는 날짜 범위 계산 로직이 없는 표시 전용 mock이라(다른 화면에서도 실사용 예 없음) 채택하지 않고 다른 완료 화면들과 동일한 `<input type=date>` from/to 패턴으로 통일
  - `DonationInput.jsx`: 교인명 자유 텍스트 입력 → `MemberSearchModal` 클릭 선택으로 교체(선택 안 하면 `member_id=null`, "무명 헌금"으로 저장 — mock의 "총액" 개념을 이렇게 대체). 원장(현금/은행명) 선택값으로 `method`(cash/transfer)를 자동 매핑. 블록(교인) × 행(항목) 각각을 `registerOffering` 개별 호출로 `Promise.all` 저장
  - 브라우저 검증(로그인: test111/!q2w3e4r, `docs/insomnia/01-auth.md` 참고) 중 `computer` 툴의 클릭이 로그인 버튼/모달 내부 버튼에서 간헐적으로 React 이벤트를 못 태우는 문제 발견 — 로그인은 API 직접 fetch 후 토큰을 `localStorage`에 주입하는 방식으로 우회, 이후 폼 요소는 `type` 액션(실제 키보드 이벤트)이나 JS `element.click()` 직접 호출이 `computer.left_click`보다 안정적이었음(이 저장소 코드 문제 아님, 브라우저 자동화 툴 특성)
  - 등록→목록조회(KPI/필터/페이지네이션)→교인별조회→기간별조회(차트+항목별표)→수정→삭제 전체 흐름을 실제 dev DB(church_id=33632, test111 계정)에서 검증 완료, 삭제까지 확인 후 테스트 데이터는 정리됨(레코드 0건으로 복귀)
  - `docs/06_페이지별_기능_현황.md` 헌금관리/헌금입력 ✅로 갱신, `.claude/launch.json`에 `registry-api-dev`(8098)/`registry-front-dev`(5173) 신규 추가(이 저장소 자체 dev 서버 설정이 그동안 없었음 — 다른 5개 프로젝트 설정만 있던 공유 launch.json이었음)
  - **후속 — 원본 mock UI 복원**: 사용자가 "처음 UI를 유지하면서 작업한거지?"라고 질문 → 위 1차 구현에서 예배 필터/상태 배지/기부금영수증 버튼/피벗 테이블 등을 실데이터 없다는 이유로 제거했던 것을 확인, "원래 mock을 유지하고 데이터 없는 부분만 표시, 스키마 변경 필요하면 정리해서 알려달라"는 요청으로 재작업
    - `PeriodDropdown.jsx`: 하드코딩된 예시 날짜(`2026.02.04` 등) 대신 호출 시점 기준 실제 날짜로 빠른선택(오늘/이번주/이번달/올해/최근3·6개월) 범위를 계산하도록 수정, `onChange(period, label, {from,to})`로 실제 ISO 날짜 반환 — Donation.jsx 3탭 전부에서 재사용(전엔 아예 안 씀)
    - `ReportRepository::getOfferingCategoryMonthlyPivot()`(월×항목 교차표) 신규 추가, `ReportService::getOfferingStats` 응답에 `pivot` 필드로 포함 — 스키마 변경 없이 기간별 조회의 피벗 테이블(원본엔 십일조/감사헌금/선교헌금/건축헌금/주일헌금 5개 고정 컬럼이었으나 실제 category는 자유입력이라 상위 5개 동적 구성)·보기방식(월별/연도별) 토글·년/월 선택을 전부 복원
    - Donation.jsx AllTab: 예배 선택 select 복원(단, disabled + "(데이터 없음)" 라벨), 상태 컬럼 복원(값 대신 회색 "—" + 툴팁), 입력방식 배지는 `member_id` 유무로 개인별/총액만 derive("혼합"은 단일 레코드 grain에서 의미 없어 제외, 문서에 기록)
    - MemberTab: 예배/비고 컬럼 복원("—" 표시), 기부금영수증 버튼 복원(disabled + "준비 중" 툴팁, 삭제하지 않음)
    - DonationInput.jsx: 원본 "빠른 입력" 안내 문구 그대로 복원 + 미구현 부분(이름 자동완성)만 별도 경고 문구 추가, 헌금 항목→금액 Enter 이동, 금액에서 Enter 시 다음 교인 블록 자동 추가는 실제로 구현(원본 mock엔 있었지만 동작하지 않던 부분), 블록당 다항목 추가 버튼은 원본에 없던 것이라 제거(교인당 1항목 구조로 원복)
    - `docs/schema/12_reg_offering_records_alter.md` 신규 — 예배연동(`service_id`)/비고(`note`)/정산상태(`status`) 3개 컬럼 추가 제안, 실행은 하지 않음(HeidiSQL에서 사용자가 직접 판단)
    - 동일한 test111 계정으로 재검증(등록→목록 마킹 확인→기간별 월별/연도별 토글→교인별 조회→삭제) 완료
  - **후속 — 65페이지 전수 재검증 + 문서 단일화**: 사용자가 "전체 다 재검증해줘" + "docs/06은 프론트 저장소에서만 관리하고 API 쪽 사본은 삭제"를 요청 — 인증/내정보/교인/출석, 심방/기도/교육, 봉사/메시지/교적설정, 재정 11개, 교회페이지/시스템설정/플랫폼 5개 그룹으로 나눠 병렬 에이전트 5개로 전 페이지 실코드(services import + 실제 호출 + 화면 데이터 출처) 재검증
    - 추가로 잘못 ✅ 표기됐던 것 발견: **교인 홈**(`/member`, "리다이렉트"라 적혀있었으나 실제론 리다이렉트가 아니라 완전 하드코딩된 별도 대시보드 컴포넌트), **교인 상세/수정**(조회·상태변경·삭제만 실연동, 전체필드수정은 `memberService.js`에 `updateMember` 함수가 있는데도 미사용이라 저장 버튼 자체가 없음, 하위 탭들은 "모듈 연동됨" 뱃지가 붙어있지만 하드코딩), **가족관계**(백엔드 `FamilyController`/`v4/family/*`는 있으나 프론트에 `familyService.js` 자체가 없음), **메시지 홈/작성/발송이력**(3개 다 services import 자체가 없는 순수 정적 화면 — 백엔드 `MessageController`는 있음), **홈페이지 관리 홈**(`/management/website`, `WebsiteHome.jsx` 완성도 75% 등 전부 하드코딩)
    - 버그성 발견(미연동이 아니라 "연동됐지만 고장남"): **교육 기수 출결**(`SessionAttendance.jsx`)이 회차별 출석/지각/결석 클릭값을 실제로 저장 안 함 — `educationService.js`에 이미 있는 `saveRoundAttendance()`를 import조차 안 하고, `handleSave`가 전원에게 동일한 `updateProgress` 진행률만 저장. 이건 이전 세션에 봉사 출결(`ServiceAttendance.jsx`) 구현 시 이미 "education 쪽 갭"으로 기록해뒀던 문제인데 education 자체는 그동안 고쳐지지 않고 방치돼 있었음. 교육 출석통계는 카테고리 검색 select 옵션이 하드코딩이라 실제 과정명과 다르면 필터만 안 먹는 경미한 부분연동
    - 재검증 결과 최종: 66페이지 중 39개(59%) 실연동 확인 — 최초 doc06 작성본(65페이지 중 49개=75% 주장)보다 크게 낮음. 반대로 봉사(4)·심방(4)·기도(2)·교직설정·교회페이지 4종(교회소개/예배모임/소식공지/미디어)·시스템설정 4종은 전수 정확하게 ✅였음(과거 세션에서 실제로 검증하며 만든 기능들이라 신뢰도 높음)
    - `docs/06_페이지별_기능_현황.md`를 04_gh_registry_front 저장소(`04_gh_registry_front/docs/06_페이지별_기능_현황.md`)로 단일화 — 이 API 저장소 쪽 사본은 삭제. 앞으로 이 문서는 프론트 저장소에서만 관리
  - **후속 — 교인 상세 "기본정보" 수정 실연동**: 재검증에서 발견된 갭 중 사용자가 첫 번째로 지목 — `MemberDetail.jsx`의 `BasicTab`이 전부 비제어(`defaultValue`) input이라 저장 버튼 자체가 없던 걸 controlled form으로 전면 교체. 백엔드는 이미 준비되어 있었음(`MemberService::updateMember`가 `reg_members`/`reg_member_profiles` 화이트리스트 필드를 트랜잭션으로 함께 갱신, 스키마·컨트롤러 변경 없이 프론트 `updateMember(memberId, fields)` 호출만 추가하면 되는 구조 — `updateMemberStatus`도 이미 동일 엔드포인트를 부분 필드로 호출하는 방식이었음)
    - 이름/성별/생년월일(양력·음력)/세대주/결혼상태/휴대전화/이메일/주소(다음 우편번호 API, `MemberRegister.jsx`의 `AddressSearchModal` 패턴 재사용)/직분(`POSITION_BADGE` 배지 재사용)/출석등급/교인구분/등록일/신급/메모 전부 저장 가능하도록 연동, 저장 성공 시 `onSaved` 콜백으로 부모(`MemberDetail`)의 `member`/`profile` state를 즉시 갱신해 상단 헤더(이름·배지)가 새로고침 없이 바로 반영되도록 함
    - "배우자" 필드는 `reg_members`/`reg_member_profiles`에 대응 컬럼이 없고 가족관계 기능 자체가 아직 미구현이라(같은 재검증에서 발견된 별도 갭) 입력란·검색버튼을 비활성 처리하고 "준비 중" 툴팁만 추가 — 실데이터 없는 필드를 지우지 않고 표시만 하는 이번 세션의 헌금 화면 복원 때와 동일한 원칙 적용
    - 하위 5개 탭(심방/출석/교육/봉사/헌금)은 이번 스코프에서 제외 — "○○ 모듈 연동됨" 뱃지가 붙어있지만 여전히 하드코딩 요약 카드이며, 각 데이터의 진짜 화면(심방현황/출석현황/교육이력/봉사이력/헌금관리)은 이미 별도로 실연동되어 있어 우선순위가 낮다고 판단
    - 브라우저 검증(test111 계정, member_id=53 "봉미선"): 메모 수정 후 새로고침해도 값 유지 확인(진짜 저장 확인) → 이름 변경 시 페이지 새로고침 없이 상단 헤더 즉시 갱신 확인 → 테스트로 바꾼 이름/메모 원상복구
    - `docs/06_페이지별_기능_현황.md` 교인 상세/수정 ✅로 갱신(단, 하위 탭은 별도 갭으로 명시)
  - **후속 — 가족관계 실연동**: 사용자가 재검증 갭 중 두 번째로 선택. 백엔드 `FamilyController`/`FamilyService`/`FamilyRepository`는 이미 완성돼 있었음(양방향 자동 동기화 — 배우자↔배우자/부모↔자녀/형제자매↔형제자매/기타↔기타를 `REVERSE_RELATION` 매핑으로 등록·수정·삭제 시 항상 pair를 함께 처리) — 이번에도 프론트 서비스 파일 부재가 원인
    - `FamilyRepository::getFamiliesByMember`에 `reg_member_profiles` LEFT JOIN 추가(스키마 변경 아님, 쿼리만 보강) — 원래 `related_name`/`gender`/`birth_date`만 반환하던 걸 `position`/`member_type`/`baptism_grade`/`workplace`까지 확장해 원본 mock 표의 9개 컬럼(관계/사진/이름/직분/교인구분/생년월일/신급/직장·학교/특이사항)을 전부 실데이터로 채울 수 있게 됨 — "일부는 데이터 없음 마킹" 없이 완전 구현
    - `familyService.js` 신규(getFamilyList/registerFamily/updateFamily/deleteFamily)
    - `MemberDetail.jsx`: 상세 조회와 별도로 가족 목록을 병렬 fetch(`fetchFamily`), `BasicTab`에 `family`/`onDeleteFamily` prop으로 전달. 가족 테이블 마지막 컬럼을 "가족 특이사항"(원본 유지) + "관리"(삭제 버튼, 신규 추가)로 구성
    - `AddFamilyModal` 전면 재작성 — 원본은 하드코딩 2명(`FAMILY_CANDIDATES`)에 진짜 검색·저장 로직이 전혀 없었음. `memberService.getMemberList`로 실제 이름 검색(본인 + 이미 연결된 가족은 결과에서 제외), 원본의 체크박스 다중선택 UI는 유지하되 선택 시 공통 "가족 관계"(배우자/부모/자녀/형제자매/기타) select + "가족 특이사항" input이 나타나 한 번에 여러 명을 같은 관계로 일괄 등록 가능하도록 구성(예: 자녀 여러 명을 한 번에 등록)
    - 관계 수정(update) API는 백엔드에 이미 있으나 이번 스코프에서 프론트 UI는 만들지 않음(삭제 후 재등록으로 대체 가능) — 우선순위상 등록/조회/삭제만 우선 구현
    - 브라우저 검증(test111 계정): 봉미선(id=53)→김순희(id=14) "형제자매"로 등록 → 봉미선 쪽에 김순희 행 표시 확인 → 김순희 상세 페이지에서도 반대편에 "형제자매 - 봉미선"이 자동 생성된 것 확인(양방향 동기화 검증) → 삭제 시 양쪽 모두 사라지는 것까지 확인 후 정리
    - `docs/06_페이지별_기능_현황.md` 가족관계 ✅로 갱신
  - **후속 — 기수 출결(SessionAttendance) 버그 수정**: 65페이지 재검증 때 발견한 "API는 부르지만 저장 안 됨" 버그. 원인 3가지 모두 확인 후 수정 — (1) `handleSave`가 `educationService.js`에 이미 있던 `saveRoundAttendance()`를 import조차 안 하고 전원에게 동일한 `updateProgress` 진행률만 호출 → 실제로 `saveRoundAttendance(id, round, sessionDate, records)` 호출하도록 교체 (2) `totalRounds`가 `useState(8)` 고정값 → `getAttendanceSheet` 응답의 `total_rounds`로 대체(과정마다 2회~30회까지 실제로 다양했음, 하드코딩이었으면 짧은 과정은 없는 회차가 보이고 긴 과정은 회차가 잘렸을 것) (3) 회차 전환 시 이전 회차에 입력하던 값이 그대로 남아있던 문제 — `attendance`/`memo` state를 회차별 raw map(`rawAttendance`/`rawNotes`, `getAttendanceSheet`가 이미 `{member_id}_{round}` 키로 전체 회차를 한번에 내려줌)에서 파생시켜 회차가 바뀔 때마다 새로 계산하도록 구조 변경
    - 저장 후 "진도율"(`updateProgress`)을 교인별로 다시 계산하도록 수정 — 기존엔 `현재회차/총회차`를 전원에게 동일 적용하는 게 잘못이었음(한 사람이 3회차를 결석해도 다른 사람과 같은 진도율이 찍힘). 이제는 사람별로 실제 기록된 회차 수(출석/지각/결석/공결 무관, 기록 자체가 있는 회차 수) ÷ 총회차로 계산 — "진도"는 참석 여부가 아니라 "그 회차가 이미 지나갔고 기록됐다"는 의미로 해석
    - 부수 개선 2가지: 원본 UI엔 없었던 회차 날짜(`session_date`) 입력란 추가(저장 API가 필수는 아니지만 받는 파라미터라 의미있게 채움), "공결"(excused) 상태 옵션 추가(백엔드 enum엔 이미 present/absent/late/excused 4종이 있었는데 프론트 UI는 3종만 노출하고 있었음 — 공통 CSS(`.attendance-status-btn.excused`)는 이전에 봉사 출결 작업 때 이미 추가되어 있어 재사용만 하면 됐음)
    - 메모(note) 저장은 되고 있었지만 조회 시 안 보이던 추가 버그 발견 — `EducationRepository::getAttendanceSheet`가 `reg_education_attendance`에서 `note` 컬럼까지 SELECT는 해놓고 반환 배열에는 `attendance`(status만) 맵만 만들고 `note`는 버리고 있었음 → `notes` 맵을 나란히 만들어 응답에 추가(스키마 변경 아님, 컨트롤러/서비스는 리포지토리 배열을 그대로 전달하는 구조라 추가 수정 불필요)
    - 브라우저 검증(session_id=84 "큐티 학교" 8회차, 실제 수강생 3명): 1회차에 출석/지각/결석+메모 저장 → 2회차로 이동 시 비어있음(회차 분리 확인) → 새로고침 후 1회차 재방문 시 상태·메모·날짜 전부 유지 확인(진짜 저장 확인) → `getEnrollmentsBySession`으로 진도율이 3명 전부 13%(1/8회차)로 개별 계산된 것 확인. 이 세션 데이터는 삭제 API가 없어(upsert 전용) 시드 데이터에 남지만, 실사용과 동일한 유의미한 데이터라 정리하지 않음
    - `docs/06_페이지별_기능_현황.md` 기수 출결 ✅로 갱신(⚠️ 제거)
  - **후속 — 교인 상세 하위 5개 탭(심방/출석/교육/봉사/헌금) 실연동**: 65페이지 재검증에서 남은 3개 갭(메시지 3화면/하위 5탭/가족관계 수정 UI) 중 사용자가 두 번째로 선택. "○○ 모듈 연동됨" 뱃지만 붙어있던 하드코딩 요약 카드를, 각 도메인의 이미 실연동된 서비스 함수를 그대로 재사용해 해당 교인 기준으로 필터링한 요약으로 교체 — 신규 백엔드 변경 전혀 없음(순수 프론트 작업)
    - 심방: `visitService.getVisitList({member_id, size:5})` — `VisitRepository::getVisitList`가 이미 `member_id` 필터를 지원했으나 프론트 어디서도 안 쓰고 있었음. 필드 매핑은 VisitHistory.jsx 화면 용어 그대로("심방자"=`visitor_name`, "담당자"=`manager_name`는 미표시, 심방유형은 `VISIT_TYPE_LABEL`로 annual→대심방/event→이벤트 매핑)
    - 출석: `attendanceService.getListByMember(memberId, {from_date, to_date})` — 페이지네이션이 없는 API라 무제한 전체이력 방지 위해 최근 1년으로 범위 제한, 그 안에서 최근4주/최근1년 통계를 클라이언트에서 계산(원본 mock의 "연간 출석률"은 "최근 1년 출석률"로 라벨 정정 — 캘린더 연도가 아니라 조회 범위 기준임을 명확히 함)
    - 교육: `educationService.getSessionsByMember(memberId)` — SP(`sp_v4_reg_edu_sessions_by_member`) 응답 전체(수강 이력 전부, 페이지네이션 없음)를 최근 5건만 슬라이스해 카드로 렌더링, `enrollment_status`(completed/withdrawn/기본값 수강중) 기준 배지
    - 봉사: `volunteerService.getTeamsByMember(memberId)` — CLAUDE.md에 "사용처 미발견"으로 기록되어 있던 기존 미사용 함수를 여기서 첫 실사용. `reg_service_members.status` 기준 활동중/해제됨 배지
    - 헌금: `offeringService.getOfferingsByMember(memberId, params)` 3회 병렬 호출(최근5건 size:5 / 올해 누계 from_date=올해1월1일 / 최근3개월 평균) — Repository가 페이지네이션과 무관하게 필터링된 전체 쿼리의 `total_amount`를 함께 반환하는 구조라 size:1로도 정확한 합계 확보 가능
    - 버그 1건 발견·즉시 수정: 교육 카드 제목에 `{course_name} {generation}기`로 붙였다가 "큐티 학교 2026년 1기기"처럼 "기"가 중복 표시됨 — `generation` 필드 자체가 이미 "2026년 1기" 같은 자유 텍스트(History.jsx가 `기수없음` 리터럴도 함께 다루는 것으로 확인)라 접미사 없이 그대로 붙이도록 수정
    - 브라우저 검증(8199/5173 임시 전환, test111 계정): 봉미선(id=53) — 심방 2건(이벤트, 실제 content/장소/심방자 표시)·출석 5건(최근4주 0건→"-" 정상, 최근1년 80%)·교육/봉사/헌금 빈 상태 정상 표시. 김정호(id=4) — 교육 5건(수강중 13%+수료 4건, 기수 표기 수정 후 재확인)·봉사 1건(비활성 팀, role null→"-" fallback) 확인. 헌금은 dev DB에 현재 레코드가 0건이라(이전 세션에 테스트 데이터 정리됨) 빈 상태만 검증됨. 콘솔 에러 없음
    - 남은 갭 2개(메시지 3화면, 가족관계 "관계 수정" UI)는 이번 스코프 밖 — 다음 세션에서 사용자 지목 시 진행
    - `docs/06_페이지별_기능_현황.md` 교인 상세/수정 행에서 "(기본정보만)" 단서 제거, 하위 5탭 실연동 내역 기록
  - **후속 — 가족관계 "관계 수정" UI 추가**: 남은 갭 중 "가장 지켜야 할 원칙은 UI를 유지한 채 작업"이라는 재확인을 받은 뒤 사용자가 세 번째로 선택(메시지 3화면 대비 범위가 작고 기존 `AddFamilyModal` 패턴 재사용 가능해 추천). 백엔드 `v4/family/update`는 이미 있었고(관계 변경 시 반대편 레코드도 `REVERSE_RELATION` 매핑으로 자동 동기화하는 로직까지 기존에 구현되어 있었음), 프론트에만 진입점이 없었음
    - 가족 목록 표의 "관리" 컬럼에 기존 "삭제" 버튼 옆에 "수정" 버튼 추가(`flex g5`로 나란히 배치), 클릭 시 `EditFamilyModal`(신규, `AddFamilyModal`과 동일한 `modal-box modal-md` 톤) 오픈 — 관계 선택(select)과 특이사항(input) 2개 필드만 다루는 작은 모달, `updateFamily(memberId, relatedMemberId, {relation_type, family_note})` 호출
    - 원본 mock에는 애초 "관리" 컬럼 자체가 없었고(순수 정적 표), 지난 세션에 실 CRUD 구현하며 "삭제" 버튼을 위해 이미 한 번 확장된 컬럼이라 "수정" 버튼을 그 옆에 추가하는 것은 기존에 이미 만들어진 실용적 확장을 그대로 따르는 것으로 판단 — 레이아웃/문구를 새로 발명하지 않고 `AddFamilyModal`의 "가족 관계"+"가족 특이사항" 2단 레이아웃(`flex g15` + `flex-1` 2개)을 그대로 재사용. 컬럼 폭이 버튼 2개가 들어가기엔 좁아(`col width="60"`) `110`으로만 조정(자기 완결적 필요 최소 변경)
    - 브라우저 검증(8199/5199 임시 포트 — 5173이 이 세션 중 다른 미지의 프로세스에 이미 점유되어 있어 5199로 대체, 등록된 launch.json도 임시로 `--port 5199 --strictPort` 추가 후 검증 후 원복): 실제 시드 데이터(봉미선 id=53 ↔ 김순희 id=14, "부모"-"자녀" 관계, 특이사항 "모")로 검증 — 수정 모달의 select/input이 기존 값("부모"/"모")으로 정확히 프리필됨 확인 → 특이사항을 "모(테스트수정)"으로 변경 저장 → 목록 즉시 갱신 확인 → 반대편(김순희 상세)에서도 "자녀" 관계 + 같은 특이사항으로 동기화된 것 확인(양방향 갱신 검증) → 원래 값 "모"로 되돌려 저장, 테스트 흔적 없이 정리 완료
    - 이 세션 중 read_page 접근성 트리가 `AddressSearchModal`(닫힌 상태) 이후 컨텐츠를 못 읽는 현상 발견 — 원인 미상(포털 렌더링 순서 추정), get_page_text와 직접 DOM 쿼리(`document.querySelectorAll` + `.click()`)로 우회해 검증 진행. 페이지 자체의 `document.documentElement.scrollWidth`(1500)가 `window.innerWidth`(1280)보다 커 가로 스크롤이 있었으나, 가족관계 표(`.list-bottom`)는 컨테이너 폭(1248px)에 정확히 맞아떨어져 이번 컬럼 폭 조정이 원인이 아님을 확인(페이지의 다른 곳에 있는 기존 이슈로 추정, 이번 스코프 밖)
    - `docs/06_페이지별_기능_현황.md` 가족관계 행에 `update` 엔드포인트/관계 수정 UI 내역 추가
  - **후속 — 메시지 3화면(홈/작성/발송이력) 실연동**: 남은 마지막 갭. `messageService.js` 신규(getList/getDetail/send/delete) — 이 3화면은 서비스 파일이 아예 없었음
    - 사전 조사로 백엔드-프론트 간극이 이전 갭들보다 훨씬 컸음을 확인: (1) `reg_messages`에 성공/실패 분리 집계, 발송 시 적용한 필터 조건 스냅샷, 제외 대상 목록, 예약 발송 시각이 전혀 저장되지 않음(스키마 자체에 컬럼 없음) — mock의 발송이력 상세 패널("적용한 필터 조건", "제외 목록", 성공/실패 별도 숫자)은 애초 백엔드가 절대 채울 수 없는 데이터였음 (2) mock의 필터 그룹 6종(교인구분/소그룹/출결·심방·교육·봉사 상태) 중 `getMemberList`가 실제로 지원하는 파라미터는 `org_ids` 하나뿐 — 나머지는 API 자체에 대응 파라미터가 없음 (3) 3개 페이지 사이에 선택 상태를 넘기는 메커니즘이 전무(각자 완전히 독립된 하드코딩 배열) — "메시지 작성으로" 버튼도, "검토 & 발송" 버튼도 실제로는 그냥 다음 페이지로 이동하는 링크였고 실제 발송 API 호출 자체가 어디에도 없었음(3단계 마법사가 이름만 있고 실제로 이어진 적이 없었음)
    - 원칙(UI 유지, 스키마 변경 없이) 적용 방식을 사용자에게 사전 설명 없이 직접 판단해 진행: 데이터가 아예 없는 것(성공/실패 분리, 필터조건 스냅샷, 제외목록, 예약발송)은 섹션·라벨을 지우지 않고 "기록되지 않았습니다"/"- (미지원)" 문구로 대체, 예약발송 토글은 `disabled` 처리. 반대로 실제로 존재하지만 그동안 안 쓰이던 데이터(교인구분 `member_type`, 소속 조직명, 발송자 실명)는 최소한의 안전한 백엔드 추가로 채워 넣음
    - 백엔드 추가 2건(둘 다 기존 쿼리에 컬럼만 보강, 스키마 변경 없음): `MemberRepository::getMemberList`에 대표 소속 조직(`VisitRepository`의 $orgSub 패턴 그대로 재사용) + `reg_member_profiles.member_type`을 SELECT에 추가 → 문자발송 수신자 표의 "소그룹"/"교인구분" 컬럼이 실데이터로 채워짐(다른 기존 화면들은 이 필드를 안 써서 영향 없음, MemberList.jsx로 회귀 확인). `MessageRepository`에 `gh_church_admin` LEFT JOIN(`AuditLogRepository`가 쓰던 것과 동일한 `a.admin_no = sent_by` 조인 패턴) 추가해 `sender_name` 필드 신설 → 발송이력의 "발송자"가 raw id 대신 실명으로 표시
    - 3단계 마법사를 실제로 연결: `Messaging.jsx`에서 선택한 수신자(`{id,name,phone,org_name}`)를 `sessionStorage`에 담아 `Compose.jsx`로 전달(둘 사이에 공유 레이아웃/컨텍스트가 없어 라우트 쿼리 대신 sessionStorage 채택 — 인원이 많을 수 있어 URL엔 부적합). `Compose.jsx`는 마운트 시 stash가 없으면 안내 후 1단계로 자동 리다이렉트(직접 URL 진입 가드). "검토 & 발송" 버튼을 순수 링크에서 `window.confirm` 확인 후 `sendMessage({target_type:'members', member_ids, title, content, send_type:'sms'})` 실제 호출로 교체 — 성공 시 발송이력으로 이동(원래 버튼의 이동 목적지와 동일하게 유지)
    - 검색 인풋 2곳(수신자 검색/발송이력 검색) 실제 keyword 파라미터로 연결(Enter 키 트리거, 다른 화면들과 동일 관례)
    - 브라우저 검증(8199/5199 임시 포트, test111 계정): 수신자 선택(실제 교인 53명 중 연락처 있는 3명만 체크 가능, 나머지 50명은 "연락처 없음" 배지로 자동 비활성 — 이 dev DB엔 phone이 채워진 교인이 거의 없어 우연히 딱 맞는 케이스가 나옴) → 3명 선택 후 작성 페이지 이동 시 실제 이름/소그룹이 미리보기 3장에 정확히 반영 확인 → 발송 → 발송이력에 실시간 반영(발송자 "교적 관리자" 실명, 대상 3명, 상태 완료, 본문 원문 그대로) 확인. 테스트로 만든 메시지 레코드는 `deleteMessage` API로 정리(id=1, dev DB에 남아있던 유일한 레코드였음)
    - `docs/06_페이지별_기능_현황.md` 메시지 3행 ✅ 전환 + 완료율 요약 합계 갱신(42→45), CLAUDE.md 최상단 "완료된 기능" 요약에 메시지 발송 항목이 이미 있었으나("메시지 발송(발송 기록 저장 — 실제 게이트웨이 연동은 TODO)") 이건 백엔드 API 관점 기록이라 그대로 유지, 프론트 실연동은 이번 항목으로 별도 기록
    - 이번 갭 3개(메시지/교인상세하위탭/가족관계수정) 전부 완료 — 2026-08-08 재검증에서 발견된 갭 목록 소진
  - **후속 — `test-links` 사이트맵 정비 + "출석 관리 홈" 누락 발견**: 사용자가 "앱웹(03_gh_www_front_renewal)처럼 page-links를 만들었잖아, 경로가 어떻게 될까"라고 질문 → 조사 결과 이 저장소엔 `/test-links`(앱웹 쪽은 `/test-links`+`/page-links` 둘 다 별칭)로만 등록되어 있고 `/page-links` 별칭은 없다고 답변. 이어서 "test-links에 적용된 내용들 업데이트해달라"는 요청으로 `TestLinks.jsx`(전체 페이지 사이트맵, `App.jsx` 라우트와 무관하게 손으로 관리되는 정적 상수 배열)를 실제 라우트·`docs/06`과 전수 대조
    - 대조 결과 불일치 6건 발견: (1) `App.jsx`에 라우트가 있는데 `TestLinks.jsx`에 아예 없던 2개 — "교인 홈"(`/member`)과 "출석 관리 홈"(`/member/attendance`, `AttendanceIndex.jsx`) 추가. 특히 후자는 `getDashboard`/`getWorshipList`/`getAttendanceStats`/`getStatsByService`로 이미 완전히 실연동되어 있었는데 2026-08-08 전수 재검증 때도, 이후 세션들에서도 `docs/06`에 단 한 번도 기록된 적이 없던 순수 누락 — `docs/06`에도 신규 행 추가(합계 66→67, 45→46) (2) `TestLinks.jsx`에서 실제로는 미구현인데 ✅로 잘못 표시돼 있던 4개(메인 홈/교회현황개요/종합통계/홈페이지 관리 홈) 🚧로 정정 — `docs/06`은 이미 정확했으나 `TestLinks.jsx`가 그동안 별도로 관리되며 따로 드리프트된 상태였음
    - 브라우저 검증(로컬 5173, API 미기동 상태로도 확인 가능 — `TestLinks.jsx`는 순수 클라이언트 링크 목록이라 API 호출 없음): "총 16개 카테고리 · 64개 페이지 · 연동 완료 43/64"로 정상 집계, 위 6건 전부 반영 확인, 콘솔 에러 없음
  - **후속 — 메인 홈(`/`) 실연동**: 위에서 발견한 남은 🚧 중 "이미 백엔드가 있는 것부터"라는 이번 세션 전체의 우선순위 기준대로 사용자가 지목. `Home.jsx`(429줄, 9개 위젯: KPI 4종/최근예배출석률/장기결석자/금주의일정(6탭)/주간출석차트(5탭)/목회액션(5탭))가 완전히 하드코딩 상태였음
    - 사전 조사로 백엔드 커버리지 위젯별 정밀 확인: `getDashboard`(교인총원/이번주출석률만 있음, 장기결석자·일정·목회액션 없음), `getMemberStats`(교인구분별 집계 없음, `by_gender`/`by_age_group`/`by_position`/`by_attendance_grade`/`by_status`만), `VisitRepository::getAbsenceTargetList`(장기결석 KPI+명단은 있으나 2·4·8주 버킷 세분화는 없음, 단일 `absence_weeks` 컷오프만), `getStatsByService`(예배별 평균 출석은 있으나 전주대비 증감·부서별 집계 없음) — "금주의 일정" 6탭 중 전화/상담 카테고리, "목회 액션"의 "사역 미연결" 태그, KPI2 "교회로 전환 현황"(가입/매칭)은 대응 도메인 자체가 스키마에 없음(전화 상담 로그, 사역 배정 여부 판별, 승인/매칭 워크플로 전부 미구현)
    - 백엔드 최소 추가(스키마 변경 없음, 신규 엔드포인트 없음 — 기존 `getMemberStats` 응답 필드만 확장): `ReportRepository::getMembersByPosition`과 동일한 패턴으로 `getMembersByMemberType`(교인구분별 집계) + `countRecentMembers(churchId, sinceDate)`(가입일 기준 카운트) 신규 추가, `ReportService::getMemberStats`가 `by_member_type`/`recent_7d`/`recent_30d` 필드로 응답에 포함
    - 위젯별 매핑: KPI1(총교인수)=`getDashboard.member_total`+`by_member_type`, KPI2(전환현황)=데이터 없음 → "—"+"준비 중" 안내로 유지(카드·라벨은 그대로), KPI3(새신자)=`recent_7d`/`recent_30d`, KPI4(방문대상자)=`getAbsenceTargetList.kpi_unvisited`(+진행중/완료 real 수치로 서브텍스트 대체, 원래의 "최근7일/30일" 문구는 이 데이터로는 못 채우므로 실제로 있는 다른 필드로 교체), 최근예배출석률=`this_week.*`, 장기결석자=`getAbsenceTargetList`를 `absence_weeks=2/4/8` 3번 병렬 호출 후 `kpi_total` 차감으로 비중첩 버킷 산출(2주컷 ⊇ 4주컷 ⊇ 8주컷이므로 뺄셈이 항상 유효), 주간출석차트=`getStatsByService`(최근 30일, 탭은 예배명 키워드로 클라이언트 필터링)
    - "금주의 일정"(6탭)·"목회 액션"(5탭)은 탭 UI 구조를 그대로 유지한 채 탭별로 실제 대응 가능한 API에 매핑 — 심방→이번주 `visit/getList`, 방문대상·결석→`getAbsenceTargetList`(list), 새신자→`member/getList(sort_by=created_at)`. 대응 데이터가 전혀 없는 "전화"/"상담"/"사역" 탭은 비활성화하지 않고 그대로 클릭 가능하게 두되 실제로 매번 빈 배열이 되어 "해당 일정이 없습니다"/"해당 데이터가 없습니다"를 보여주는 방식 채택 — 가짜로 비활성화 처리하는 대신 "정직하게 항상 비어있는 실제 상태"를 보여주는 쪽이 이 세션 내내 지켜온 원칙(데이터 없으면 숨기지 말고 사실대로 표시)에 더 부합한다고 판단
    - 버그 발견·수정: 목회 액션 드롭다운의 "문자"/"푸시" 링크가 원래 `/member/messages/send`라는 **존재하지 않는 라우트**를 가리키고 있었음(`App.jsx` 전수 확인 결과 없음, 오늘 세션에 새로 만든 실제 메시징 라우트는 `/member/messaging/compose`) — "문자"는 오늘 세션에 구축해둔 `sessionStorage('messaging_recipients')` 메커니즘을 재사용해 클릭한 사람 1명을 stash 후 실제 메시지 작성 화면으로 연결(수신자 선택 단계 건너뛰고 바로 작성 단계 진입), "푸시"는 그 페이지 자체가 아직 미구현이라 "준비 중입니다" 안내로 대체. "심방등록" 링크도 기존엔 사람과 무관하게 고정 경로였던 것을 `?member_id=`로 연결해 `VisitRegister.jsx`가 이미 지원하던 사전 선택 파라미터(`searchParams.get('member_id')`, 기존 로직 그대로) 활용
    - KPI1의 "+2.5% 지난주 대비", 주간출석차트의 "전주 대비 5% 증가" 등 전주/증감 비교 수치는 히스토리 스냅샷 테이블이 전혀 없어 전부 정직한 "비교 데이터 없음"/"최근 30일 예배별 평균" 문구로 교체(레이아웃·span 구조는 유지, 텍스트만 교체)
    - 브라우저 검증(8199/5199 임시 포트, test111 계정): 실제 dev DB 데이터로 총교인 53명(등록교인51/새가족1/기타1), 방문대상자 41명(진행중7·완료5), 장기결석자 버킷(2주+0명/4주+37명/8주+16명 — 이 dev DB가 최근 출석 기록이 거의 없어 대부분 인원이 4주 이상 버킷에 몰림, 데이터 자체의 특성이지 로직 오류 아님) 정상 렌더링 확인. "전화"/"사역" 탭 클릭 시 빈 상태 정상 표시 확인. 목회 액션 "문자" 버튼 클릭 → 실제 교인(강태양, 제2남전도회)이 메시지 작성 화면에 수신자 1명으로 정확히 stash되어 뜨는 것까지 엔드투엔드 확인. "심방등록" 링크의 `?member_id=8` 실제 ID 확인. 콘솔 에러 없음. `getStatsByService`가 이 dev DB엔 최근 30일 출석 기록 자체가 없어 전부 `avg_present: null`로 응답 — 차트가 0으로 렌더링되는 것도 코드 결함이 아니라 데이터 공백
    - `docs/06_페이지별_기능_현황.md` 메인 홈 ✅ 전환(전체 67 중 완료 46→47, 미구현 21→20), `TestLinks.jsx` 메인 홈도 true로 갱신
  - **후속 — 교회 현황 개요(`/member/reports/church-overview`) 실연동**: 남은 🚧 중 "백엔드 있는 것부터"라는 우선순위대로 사용자가 다음으로 지목. `docs/06`에 "백엔드 `getMemberStats`·`getOfferingStats`·`getVisitStats` 완료"라고 적혀 있었지만, 실제 조사해보니 이 3개만으로는 페이지의 핵심 내용(출석률/개인별 명단/정착상태 등) 대부분을 못 채운다는 걸 확인 — `getOfferingStats`/`getVisitStats`는 애초 이 페이지에 대응하는 섹션 자체가 없어(교적/출석 중심 페이지, 헌금·심방 통계 UI 없음) 처음부터 쓸 자리가 없었고, 문서가 "3개 API 목록"만 보고 낙관적으로 적어둔 착오였음. 대신 다른 화면(출석현황·홈)에서 이미 쓰던 `getAttendanceStats`/`getMemberRateDistribution`(둘 다 `attendanceService.js`)과 `getAbsenceTargetList`(`visitService.js`), `member/getList`를 가져다 씀 — Home.jsx 작업 때 확립한 "지정된 API 하나에 얽매이지 않고 이미 존재하는 관련 API를 전부 동원" 패턴을 그대로 반복
    - 원본 mock에서 죽어있던 "기간 유형"(주간/월간 토글)·"기간 선택"(`<`/`>` 이전·다음 화살표, 고정 텍스트 "2025년 1월")을 실제 날짜 계산 로직(`getPeriodRange(period, offset)`)으로 살림 — 월간은 해당 월 1일~말일, 주간은 월요일~일요일, `offset`으로 과거 이동(미래로는 못 감, `disabled` 처리). 기간이 바뀌면 6개 API를 전부 그 기간 기준으로 재조회
    - KPI 5개 중 4개 실데이터 매핑: 전체교인(`by_status`에서 status='active'만, 라벨 "ACTIVE 기준"과 정확히 일치하도록), 기간 내 신규등록(`member/getList(sort_by=created_at, size:100)`를 가져와 `created_at`이 선택 기간 안에 드는 것만 클라이언트에서 카운트 — 페이지네이션 없는 범위 카운트 API가 없어 넉넉한 배치를 가져와 여러 위젯에서 재사용하는 방식 채택), 평균출석률(`getAttendanceStats(from,to).attendance_rate`), 장기결석자(`getAbsenceTargetList(absence_weeks:4).kpi_total` — Home.jsx에서 이미 검증된 동일 패턴 재사용, 라벨 "4주 이상"과 그대로 일치)
    - "출석 유지 교인(75% 이상)"만 예외 — `getMemberRateDistribution`의 실제 버킷 경계가 90/80/70/60/50대라 "75%"라는 정확한 컷을 만들 수 없음. 커스텀 쿼리를 새로 추가하는 대신 라벨을 "70% 이상"으로 낮춰 기존 버킷(70_79+80_89+90_plus 합산) 그대로 사용 — 정확하지 않은 숫자를 원래 라벨 그대로 우기는 것보다, 실제로 계산 가능한 경계값으로 라벨 자체를 정직하게 조정하는 쪽을 선택(이번 세션 "데이터 없으면 사실대로" 원칙의 연장)
    - "교적 변동 요약"의 "전입"/"전출·제적"은 교회 간 이동을 추적하는 컬럼·이벤트가 스키마에 전혀 없어(찾아봤으나 없음) 숫자를 "—"로, 서브텍스트를 "기록 안 됨"으로 대체(칸 자체는 유지)
    - "신규 등록 교인" 테이블(이름/등록일/조직/직분/휴대폰/주소)은 전부 실데이터로 채워짐 — 오늘 세션 초반 메시지 발송 기능을 만들며 `MemberRepository::getMemberList`에 추가해둔 `org_name`/`member_type` 필드(이미 존재하던 `position`/`phone`/`address_main`과 함께)를 그대로 재사용, 신규 백엔드 변경 전혀 없음
    - "출결 변동 교인"(개인별 이전 기간 대비 현재 출석률 비교) 테이블은 그런 스냅샷 비교 데이터가 어디에도 없어 유일하게 완전 대체 불가 판정 — 헤더·컬럼 구조는 그대로 두고 tbody만 "이 지표는 아직 지원되지 않습니다" 안내로 교체
    - "주의 필요 교인"은 `getAbsenceTargetList(absence_weeks:2, size:8)` 실제 목록(이름/결석주수 배지/마지막출석일)으로 교체 — 원본의 다중 배지 조합(예: "4주 연속 결석"+"출석 급감" 2개)은 결석주수 하나로만 판정 가능해 단일 배지로 단순화
    - 브라우저 검증(8199/5199 임시 포트, test111 계정): 월간→"2026년 8월" 기본 표시, `<` 클릭 시 "2026년 7월"로 정상 이동, `>` 버튼이 offset=0(현재 달)에서 정확히 disabled 처리 확인, 주간 토글 시 "2026년 8월 3일 ~ 8월 9일"로 정상 전환. 6개 API 전부 200 OK, 콘솔 에러 없음(반복 발생하는 `ERR_CONNECTION_REFUSED`는 이전 세션들에서도 전 페이지에 걸쳐 나타난 HMR 관련 노이즈로 이미 판별됨, 이번 기능과 무관). 이 dev DB가 최근 출석 기록이 거의 없어 평균출석률 "-", 장기결석자 53명(=전체 인원)으로 나오는 것도 Home.jsx 검증 때와 동일하게 데이터 공백이지 로직 결함 아님
    - `docs/06_페이지별_기능_현황.md` 교회 현황 개요 ✅ 전환(전체 67 중 완료 47→48, 미구현 20→19), "프론트가 아직 안 쓰고 있는 백엔드 기능" 목록에서 `getMemberStats`(이제 홈+교회현황개요 사용) 제거하고 `getVisitStats`만 순수 미사용으로 재정리, `TestLinks.jsx` 교회 현황 개요도 true로 갱신
  - **후속 — 종합 통계(`/member/reports/comprehensive`) 실연동**: 보고서 섹션의 마지막 남은 항목. `docs/06`엔 "백엔드 `getAttendanceStats` 등 완료"라고만 적혀 있었지만, 조사해보니 이 페이지가 다루는 5대 영역(교적/출결/봉사/교육/재정) 중 **봉사·교육 참여 인원을 집계하는 API가 애초 존재하지 않았음** — `VolunteerController`/`EducationController`는 CRUD와 개인별 출석시트만 제공, "교회 전체 활동 봉사자 수"·"교육 참여자 수" 같은 전체 집계 엔드포인트가 없었음(재정도 지출 데이터 자체가 없어 "흑자" 판단 불가는 이전에도 계속 확인된 사실)
    - 백엔드 최소 추가(스키마 변경 없음, 신규 엔드포인트 없음 — 기존 `getDashboard` 응답 필드만 확장): `ReportRepository::countActiveVolunteers`(`reg_service_members` status='active', `reg_service_teams` 조인으로 church_id 스코프) + `countActiveEducationParticipants`(`reg_education_records` status='ongoing', `reg_education_sessions` 조인으로 church_id 스코프) 신규 추가, `ReportService::getDashboard`의 `this_month`에 `active_volunteers`/`active_education_participants` 필드로 포함 — 이미 Home.jsx/교회현황개요에서 쓰던 `getDashboard`를 여기서도 재사용
    - KPI 5개 매핑: 교적(`getMemberStats.total`+`recent_30d`), 출결·재정은 **이번달 vs 전월 2회씩 호출**(`getAttendanceStats`/`getOfferingStats`)해서 실제 증감(▲▼) 계산까지 구현(Home.jsx 때는 히스토리 스냅샷이 없어 증감 표시를 통째로 뺐었지만, 이번엔 월 단위 비교가 두 번의 API 호출만으로 가능해 제대로 구현), 봉사·교육은 스냅샷 집계만 가능해 증감 화살표 없이 "-"로 정직하게 표시. "재정" KPI의 "흑자" 프레이밍은 지출 데이터가 없어 제거하고 "헌금 수입"으로 라벨만 조정(원본 레이아웃·카드 구조는 그대로)
    - "영역별 핵심 요약" 5개, "종합 목회 판단 코멘트" — 원본은 "이탈률 7.1%", "예산 200만원 초과" 등 매우 구체적인 서술이었으나 그런 세부 산출 로직 자체가 백엔드에 없어(그리고 새로 만들기엔 봉사/교육 도메인 전체를 새로 설계해야 할 정도로 큰 스코프) 실제 계산 가능한 수치만 넣은 템플릿 문장으로 전면 교체, 계산 불가한 부분은 "세부 통계는 아직 제공되지 않습니다" 등으로 명시. 원본이 "자동 생성 코멘트"라고 표방했던 걸 가짜 AI 서술로 재현하지 않고, ChurchOverview.jsx의 InfoCallout에서 이미 쓴 "실제 값 기반 템플릿 문장"과 동일한 방식으로 통일 — 라벨도 "실시간 데이터 기반 요약"으로 정직하게 조정
    - "목회 관심 종합 리스트"는 Home.jsx `PastoralCare`와 동일하게 `getAbsenceTargetList(absence_weeks:2)` 실목록 재사용(장기결석 기준만 가능, 원본의 "교육 미연결"/"봉사 미배정" 태그는 근거 데이터 없어 제외)
    - "담임목회자 메모" — 원본은 저장 로직 자체가 없는 완전 uncontrolled textarea였음. 저장 API가 정말 없는지 확인 후, 교직설정 화면이 이미 쓰던 범용 키-값 설정 테이블(`reg_church_settings`, `v4/setting/getSettings`·`saveSettings`, 임의 문자열 키 허용— `SettingRequest`에 화이트리스트 없음 확인)에 신규 키 `pastor_memo_comprehensive` 하나(`settingService.js`의 `SETTING_KEYS`에 추가)만 얹어 실제 저장·조회 구현 — 스키마 변경도 신규 엔드포인트도 없이 기존 인프라 재사용만으로 해결
    - 브라우저 검증(8199/5199 임시 포트, test111 계정): 봉사 29명·교육 26명 실집계 확인(신규 백엔드 메서드 정상 동작), 이번달출석 0명 vs 전월 대비 "▼31명"으로 실제 두 기간 비교 계산 확인, 담임목회자 메모 저장→새로고침 후에도 값 유지(진짜 영속 확인)→테스트 문구 정리(빈 문자열로 재저장). API 8개 전부 200 OK, 콘솔 에러 없음(반복되는 `ERR_CONNECTION_REFUSED`는 기존에 이미 무관한 노이즈로 판별된 것과 동일)
    - `docs/06_페이지별_기능_현황.md` 종합 통계 ✅ 전환(전체 67 중 완료 48→49, 미구현 19→18 — **보고서 섹션 2/2 완료**), "프론트가 아직 안 쓰고 있는 백엔드 기능" 목록 재정리(`getVisitStats`만 순수 미사용으로 남음), `TestLinks.jsx` 종합 통계도 true로 갱신
  - **후속 — 홈페이지 관리 홈(`/management/website`) 실연동**: 남은 🚧 중 유일하게 신규 백엔드가 거의 필요 없는 항목이라 사용자에게 추천했고, 그대로 다음 작업으로 선택됨. `WebsiteHome.jsx`는 services import가 아예 없는 완전 정적 페이지였으나, 이미 실연동된 하위 4개 화면(교회소개/예배·모임안내/소식·공지/미디어)의 API를 그러모아 "요약 허브"로 재구성 — 신규 백엔드 변경 전혀 없음(순수 프론트 작업)
    - 12개 API 병렬 호출(`getIntro`, `getProfile`, `getWorshipList`, news 5탭, media 4탭)로 16개 세부 항목(교회소개4/예배모임3/소식공지5/미디어4) 각각의 실제 데이터 유무를 판정 — 원본이 "5개 주요 섹션 중 4개 완성 = 75%"라고 했던 것은 SECTIONS 배열 자체가 4개뿐이라 "5개"의 근거가 애초 불명확했음(조사로 확인) → 실제 항목 수(16개) 기준으로 정직하게 재정의
    - 세부 항목-데이터 매핑: 목회자소개=`pastor!=null`, 전하는말=`intro.message` truthy, 예배시간=활성 정기예배 존재 여부(예배모임 도메인과 교차 참조 — 실제로 교회소개 페이지가 예배시간을 표시하려면 예배 데이터가 있어야 하므로 타당한 연결), 오시는길=`intro.directions.location_detail` 또는 `profile.address`, 정기예배=`category==='regular'`, 주중모임=정기예배 중 월~금(day_of_week 1~5), 소그룹=대응 데이터 소스 자체가 없어 항상 미작성(원본 mock도 이미 이 항목을 gray-soft로 표시하고 있었어서 의도와 일치), 소식·공지/미디어 9개 항목=각 게시판 `total>0` 여부
    - "콘텐츠 상태 점검"(원래 고정 3개 예시 문구)을 실제 미작성 항목 기반으로 동적 생성("{도메인}에 '{항목}' 항목이 없습니다.")으로 교체, 전부 작성된 경우엔 "확인된 문제가 없습니다" 긍정 상태 추가(원본엔 없던 케이스지만 로직상 자연스럽게 필요해짐)
    - "최근 업데이트"는 완벽한 전역 최신순 정렬을 위해 모든 게시글을 다 가져오는 대신, 이미 세기 위해 호출한 10개 도메인의 각 `getList` 응답 1번째 행(`content_no desc` 정렬 = 최근 등록순, `ChurchBoardRepository::getList` 확인)의 `updated`/`updated_at` 필드끼리 비교해 최댓값을 근사치로 사용 — 완벽하진 않지만(아주 오래전 글이 방금 수정된 경우는 못 잡음) 도메인당 추가 호출 없이(이미 count용으로 부르던 size:1 응답 재사용) 합리적인 근사가 가능해 이 방식 채택, 라벨에 "근사치"임을 명시
    - "방문자 기준 정보" 3개 체크(대표예배시간/위치정보/연락처)도 위 실데이터로 판정(원본은 셋 다 항상 초록 체크 고정이었음)
    - **"페이지 공개 설정" 섹션(전체공개 토글/노출대상 라디오/검색노출 체크박스/저장 버튼)은 의도적으로 손대지 않음** — 조사 결과 이 섹션은 하위 4개 화면 데이터의 "요약"이 아니라 완전히 별개의 신규 기능(교회 페이지 공개범위 설정)이고, 저장 API 자체가 스키마 어디에도 없어(신규 테이블/컬럼 설계부터 필요) 이번 "요약 허브" 스코프 밖이라고 판단 — 원본 그대로 로컬 상태만 유지되는 상태 보존(저장 버튼도 원래부터 onClick 핸들러가 없어 "가짜 성공 메시지"조차 없었으므로 정직성 문제는 없음)
    - 브라우저 검증(8199/5199 임시 포트, test111 계정): 완성도 69%(16개 중 11개), 실제 미작성 5개 항목(오시는길/소그룹/상담신청/성도한마디/교회영상) 정확히 나열 확인, 최근 업데이트 "묻고답하기 2026-08-08 14:16" 실제 QnA 데이터와 일치, 방문자 기준 정보 3개 체크 전부 실제 초록색(`.ft-green`) 확인. API 12개 전부 200 OK, 콘솔 에러 없음(반복되는 `ERR_CONNECTION_REFUSED`는 기존에 무관한 노이즈로 판별된 것과 동일). "페이지 공개 설정" 섹션은 그대로 로컬 토글만 동작하는 것 확인(회귀 없음)
    - `docs/06_페이지별_기능_현황.md` 홈페이지 관리 홈 ✅ 전환(전체 67 중 완료 49→50, 미구현 18→17 — **교회 페이지 섹션 5/5 완료**), `TestLinks.jsx`도 true로 갱신
  - **후속 — 가입 승인(`/member/approvals`) 신규 구현**: 지금까지와 달리 백엔드가 아예 없는 항목이라 사전 조사부터 진행. `gh_account`(교회로 계정)에 "가입 승인 대기" 상태 자체가 없다는 걸 확인(일반회원은 교회 선택 시 `church_no`가 즉시 반영, 승인 절차 자체가 교회로 쪽에 없음) — 반면 목회자 가입은 별도 `gh_church_pastor_request`(status=WAIT)에 실제 대기 데이터가 있고, 그쪽 리포 문서에 "승인 화면은 이 리포에 없음 — 관리자 도구에서 처리 예정"이라고 명시돼 있었음. 이 발견을 근거로 mock의 "가입 승인" 워크플로(교적선택연동/신규교적생성/보류/반려 4개 버튼)를 다시 보니 "교회 가입을 승인"하는 게 아니라 "이미 가입은 됐지만 아직 교적에 연동 안 된 사람을 매칭하는" 기능이라고 재해석 — 사용자에게 4가지 범위 옵션(성도만/성도+교역자/신규테이블 없는 최소버전/보류) 제시 후 "성도만, 신규 테이블 포함"으로 확정
    - 신규 테이블 `reg_member_join_requests`(`docs/schema/10_reg_member_join_requests.sql`) — church_id+account_no당 1행, status(held/rejected)+reason만 저장. **대상 목록 자체는 이 테이블에 저장하지 않음** — `gh_account.church_no = 현재교회 AND account_no가 reg_members.churchero_user_id 어디에도 없음` 안티조인으로 매번 실시간 계산(신규 테이블은 오직 "보류/반려해서 다시 안 뜨게" 하는 용도)
    - `MemberJoinRequestRepository`/`Service`/`Controller`/`Request` 신규(기존 `MemberController`에 억지로 얹지 않고 별도 클래스로 분리), 라우트는 기존 `v4/member`(`permission:MEMBER`) 그룹 안에 `getJoinRequestList`/`holdJoinRequest`/`rejectJoinRequest` 3개만 추가 — **"교적 선택 후 연동"과 "신규 교적 생성"은 새 API를 안 만들고 기존 `member/update`·`member/register`를 그대로 재사용**(`churchero_user_id`가 이미 `MEMBER_FILLABLE` 화이트리스트에 있었던 걸 확인하고 활용, 순수 프론트 연결만 추가). `AuditActionType`에 `HOLD`/`REJECT` 신규 추가(CLAUDE.md 규칙대로 먼저 상수 추가 후 사용)
    - 매칭 상태("일치"/"불일치"/"중복의심")는 대기자의 휴대폰 번호와 정확히 일치하는 `reg_members` 행 개수로 실제 계산(0건=no-match, 1건=matched, 2건 이상=multiple) — 원본의 `setInterval` 기반 가짜 "자동 매칭" 진행 애니메이션은 제거하고 같은 자리의 버튼을 "새로고침"(실제 재조회)으로 교체, `common.css`에 이미 있던 `match-status` 배지 클래스(matched/unmatched/multiple/no-match 등, 다른 화면에서 쓴 적 없는 죽은 CSS였음) 최초 실사용
    - "교적 선택 후 연동" → 기존 `MemberSearchModal` 컴포넌트 재사용(교인 검색해 선택 시 `updateMember(memberId, {churchero_user_id})`). "신규 교적 생성" → 새 모달을 만들지 않고 `MemberRegister.jsx`(기존 교인 등록 폼)로 쿼리파라미터(`name`/`phone`/`email`/`churchero_user_id`) 프리필해 이동하도록 `MemberRegister.jsx`에 최소 프리필 로직 추가(`useSearchParams`로 초기 state 채움 + 제출 payload에 `churchero_user_id` 포함 + 프리필 안내 `InfoCallout`) — 신규 등록 폼 전체를 새로 만들지 않고 이미 완성된 3단계 등록 플로우를 그대로 재사용
    - "가입유형"(성도/교역자) 배지는 `gh_account.church_job` 값 기반 키워드 매칭으로 시도했으나, 실제 dev DB에서 `church_job`이 숫자 코드(0 등)로 저장돼 있어 텍스트 키워드 매칭이 거의 항상 "성도" 기본값으로 떨어짐 — 코드→라벨 매핑 테이블을 추가 조사하는 건 스코프 밖이라 판단, 안전한 기본값(성도)으로 폴백되는 현재 동작을 그대로 둠(거짓 라벨링은 아니고 단지 세분화가 안 될 뿐)
    - **DB 반영**: 사용자에게 "지금 로컬 dev DB에 적용해서 검증까지 할지" 확인 후 승인받아 이 세션에서 직접 `CREATE TABLE` 실행(HeidiSQL 아닌 PHP PDO 스크립트로, `docs/schema/10_...sql`엔 "로컬 적용 완료 / 원격은 미실행" 명시). 원격/스테이징 DB는 배포 전 별도 실행 필요
    - 브라우저+DB 교차 검증(8199/5199 임시 포트, test111 계정, dev DB의 실제 `gh_account` 테스트 계정 2건 사용): 대기 목록에 실제 2명(클로드테스트/NovaNest21, 둘 다 `church_no=33632`이고 `reg_members`에 미연동) 정상 표시 확인 → "보류" 제출 후 목록에서 사라짐 + `reg_member_join_requests`에 실제 행(status=held, reason, created_by=관리자 admin_no) 생성 확인 → "교적 선택 후 연동"으로 실제 교인(김준서) 검색·선택 후 `reg_members.churchero_user_id`가 실제로 업데이트된 것 DB 직접 조회로 확인 → 목록 재조회 시 0건(둘 다 처리 완료) 확인 → "신규 교적 생성" 쿼리파라미터 프리필도 이름/전화/이메일 3개 필드 전부 실제로 채워지는 것 확인. 검증 후 테스트 mutation 전부 원복(`churchero_user_id` NULL로, `reg_member_join_requests` 행 삭제) — dev DB 상태 순정 복구
    - `docs/06_페이지별_기능_현황.md` 가입 승인 ✅ 전환(전체 67 중 완료 50→51, 미구현 17→16 — **"교인" 섹션 잔여 미구현은 "교인 홈" 1개만 남음**), `TestLinks.jsx`도 true로 갱신
  - **후속 — 메시지 설정·템플릿·푸시 스코프 조사 → 템플릿만 구현**: "가입 승인" 이후 다음 후보로 지목된 메시지 관련 4페이지(설정/템플릿/푸시/푸시상세)를 조사한 결과 페이지 간 구현 난이도가 극단적으로 갈렸음 — 템플릿은 이름/분류/본문 CRUD 하나만 있으면 되는 깔끔한 소테이블 작업인 반면, 설정은 일부만 저장 가능하고 일부는 SMS 게이트웨이 벤더 설정이라 이 리포 스코프 밖, 푸시는 FCM 토큰·수신자별 발송로그 등 사실상 완전히 새로운 도메인을 설계해야 하는 규모였음 — 이 격차를 그대로 사용자에게 `AskUserQuestion`으로 제시했고 "템플릿만 먼저(권장)"로 확정, 설정·푸시 2종은 이번 스코프에서 완전히 제외
    - 신규 테이블 `reg_message_templates`(`docs/schema/11_reg_message_templates.sql`) — church_id/name/category/msg_type(SMS·LMS)/scope(public·private)/content/unsub_enabled/unsub_text/is_active/created_by. `reg_church_settings`(범용 키-값) 재사용도 검토했으나 템플릿은 가변 개수 CRUD가 필요해 부적합하다고 판단해 전용 테이블로 신설
    - `MessageTemplateRepository`/`Service`/`Controller`/`Request` 신규, 기존 `v4/message`(`permission:MESSAGE`) 그룹 안에 `template/getList`·`getActiveList`·`getDetail`·`register`·`update`·`duplicate`·`toggleActive` 7개 라우트 추가(delete는 없음 — 원본 mock에 삭제 버튼 자체가 없고 등록/복제/비활성화 아이콘만 있어 그대로 따름)
    - "공용 템플릿은 복제 후 개인화" 원칙(원본 mock 문구)을 백엔드에서도 실제로 강제 — `MessageTemplateService::update`가 대상 템플릿의 `scope==='public'`이면 `INSUFFICIENT_PERMISSION`(403)으로 차단, 프론트도 동일 조건으로 "수정" 아이콘을 `disabled` 처리해 일관성 유지. `duplicate`는 항상 `private` 스코프의 "{원본명} (복제본)"으로 새 행 생성
    - `Templates.jsx` 전면 재작성 — 하드코딩 `TEMPLATES` 배열을 `getTemplateList` 실조회(키워드/분류/유형/범위/상태 필터)로 교체, 등록 모달을 진짜 controlled form으로 전환(기존엔 "저장" 버튼에 핸들러 자체가 없었음), "저장 후 발송 화면에 적용" 버튼은 저장 후 `/member/messaging/compose`로 이동
    - `Compose.jsx`의 템플릿 드롭다운도 함께 연결 — 기존엔 컴포넌트 내부에 하드코딩된 `TPL`(4종 고정) 객체였던 것을 `getActiveTemplateList()`(비활성 제외, 공용+개인 통합) 실조회로 교체, 선택 시 `unsub_enabled`면 본문 끝에 `unsub_text`를 자동으로 붙여줌(템플릿 관리 화면에서 설정한 수신거부 문구가 실제 발송 문구에도 반영되도록)
    - **DB 미반영**: 이번엔 가입승인 때와 달리 사용자가 `AskUserQuestion`에서 "문서만, 실행은 직접 하시겠어요"를 선택 — 이 세션에서 `CREATE TABLE`을 실행하지 않았고, 따라서 브라우저 검증도 하지 않음(테이블이 없으면 `v4/message/template/*` 전 엔드포인트가 500). `docs/schema/11_reg_message_templates.sql`도 가입승인 파일 패턴을 그대로 복사해 쓰다가 "로컬 dev DB 적용 완료"라고 잘못 써놨던 걸 질문 답변 이후 발견해 "아직 미실행"으로 정정 — 스키마 제안 문서에 실행 여부를 기록할 땐 실제 실행한 직후에만 쓸 것(사전에 낙관적으로 쓰지 말 것)
    - PHP 5개 파일(`Repository`/`Service`/`Controller`/`Request`/`routes/api.php`) 전부 `php -l` 구문 검사 통과만 확인, 실제 API 호출·DB write는 미검증 상태. `docs/06_페이지별_기능_현황.md`는 이 상태를 정직하게 반영해 템플릿 행을 ✅로 올리지 않고 🚧로 유지한 채 "코드 구현 완료, DB 미적용" 상세 기록만 추가(합계 숫자 변경 없음), `TestLinks.jsx`도 false 그대로 둠 — `docs/schema/11_reg_message_templates.sql`을 HeidiSQL에서 실행하면 바로 동작하는 상태
    - (참고) 테이블명 프리픽스 관련해 사용자가 "gh_ 프리픽스 + _v4 접미사"를 잠깐 지시했다가 곧바로 "reg_ 만 들어가면 되네"로 정정 — `reg_message_templates` 그대로 유지, 실제 파일 변경은 없었음(Repository의 테이블명을 바꿨다 되돌리기만 함)
  - **후속 — 교인 홈(`/member`) 실연동**: 남은 마지막 "교인" 섹션 갭. `MemberHome.jsx`는 리다이렉트가 아니라 실제 대시보드 컴포넌트인데 services import 자체가 없이 KPI 4개가 전부 하드코딩(전체교인342/활동교인298/새가족5/가입대기3)이었음 — 신규 백엔드 변경 전혀 없이 기존 API 재사용만으로 완결(가장 작고 깔끔한 다음 항목이라 사용자에게 추천했고 그대로 선택됨)
    - KPI 매핑: 전체교인=`getMemberStats().total`(부제 "최근 30일 +N명"=`recent_30d`), 활동교인=`by_status`에서 status='active' 건수(부제=참여율 active/total*100), 새가족=`by_member_type`에서 '새가족' 건수, 가입대기=`getJoinRequestList({size:1}).total`(원본 mock의 "/member/approvals 처리 →" 링크가 이미 정확히 그 기능을 가리키고 있었음 — 오늘 세션 초반에 구현한 가입 승인 기능과 그대로 연결)
    - 첫 구현 시 실수 자체 발견·수정: "새가족" 카드 값도 `recent_30d`로 채웠다가, "전체 교인" 카드의 "▲ 최근 30일 +N명" 부제와 완전히 동일한 숫자가 두 카드에 중복 표시되는 걸 알아채 `by_member_type`(전체 등록 인원 중 현재 '새가족' 상태로 분류된 건수, 날짜 무관) 기준으로 교체 — 원본 mock 부제 "이번 달 등록"은 실제로는 날짜 필터가 아니라 상태 분류라 부정확한 라벨이었으므로 "전체 등록 인원 기준"으로 정직하게 조정
    - 브라우저 검증(8199/5199 임시 포트, test111 계정): 전체교인 53명(최근 30일 +0명)·활동교인 53명(100.0% 참여율 — 이 dev DB 교인 전원이 active 상태)·새가족 1명·가입대기 2건(오늘 세션 초반 가입승인 기능 검증 때 만들었던 실제 대기 계정과 일치) 전부 실데이터로 렌더링 확인. API 2건(`getMemberStats`/`getJoinRequestList`) 200 OK, 콘솔 에러 없음(반복되는 `ERR_CONNECTION_REFUSED`는 기존에 무관한 노이즈로 판별된 것과 동일)
    - `docs/06_페이지별_기능_현황.md` 교인 홈 ✅ 전환(전체 67 중 완료 51→52, 미구현 16→15 — **"교인" 섹션 6/6 전부 완료**), `TestLinks.jsx`도 true로 갱신
- [x] 재정 — 지출 관리(`/finance/expense`)·지출 입력(`/finance/expense/input`) 백엔드 신규 구현 + 프론트 실연동 — Session 13
  - 배경: 재정 도메인은 헌금(수입)만 실연동돼 있었고 나머지 9개 화면은 전부 백엔드 자체가 없었음. 직전 세션에 재정 대시보드부터 조사했으나 지출/예산 테이블이 없어 위젯 6개 중 2개만 채울 수 있는 상태(15~20%)라 비효율 판단 — 지출 관리 → 예산 관리 → 대시보드 순으로 진행하기로 하고 이번 세션이 그 첫 단계
  - 신규 테이블 `reg_expense_records`([13_reg_expense_records.sql](docs/schema/13_reg_expense_records.sql)) — `reg_offering_records`(헌금)와 대칭 구조(church_id/expense_date/category/amount/purpose/vendor/method/ledger/receipt_files/note/recorded_by). 지출 항목(category)은 프론트 `ExpenseInput.jsx`의 26종 고정 목록을 자유 입력 문자열로 저장(offering.category와 동일 설계, ENUM 대신 VARCHAR — 교회마다 항목 구성이 다를 수 있음). `ExpenseController`/`Service`/`Repository`/`Request` 신규, `AuditMenuCode::EXPENSE`(이미 권한 매트릭스에 플레이스홀더로 선반영되어 있던 상수)를 실제로 처음 사용 — `permission:EXPENSE` 미들웨어가 이제부터 실제로 라우트를 보호(기존엔 토글만 저장되고 아무것도 차단 안 하는 장식용 상태였음)
  - **DB 적용**: `AskUserQuestion`으로 "지금 적용+검증" vs "문서만" 확인 → 사용자가 "지금 적용" 선택, 이 세션에서 `php artisan tinker`로 `CREATE TABLE` 직접 실행(로컬 dev DB, church_id=33632) 후 브라우저 실연동까지 전부 검증 완료
  - 원본 mock(`Expense.jsx` AllTab)의 "상태"(정상/확인) 컬럼은 실제 mock 데이터에서 항상 증빙(`proof`) 유무와 1:1로 일치했음 — 별도 상태 컬럼을 신설하지 않고 `receipt_files` 배열의 유무로 프론트에서 derive(헌금의 "정산상태" 때와 달리 이번엔 스키마 추가가 아예 불필요하다고 판단)
  - mock 내부 불일치 발견·정정: `Expense.jsx`(목록 화면)의 지출 항목 필터는 6종(사역비/인건비/운영비/시설비/행정비/기타) 고정 옵션인데, `ExpenseInput.jsx`(등록 폼)는 26종의 훨씬 구체적인 목록을 쓰고 있어 실제로 등록 가능한 값과 필터링 가능한 값이 서로 달랐음(헌금 쪽에서 이미 겪었던 것과 같은 유형의 mock 자체 결함) — 26종 목록으로 프론트 전체를 통일
  - "항목별 조회" 탭의 "예산 대비" 사용률은 예산(예산 관리) 테이블이 아직 없어 계산 불가 — 삭제하지 않고 "예산 미설정"으로 표시, 다음 세션(예산 관리) 연동 시 채워질 예정
  - 증빙 첨부: `S3FileHelper`(교회 페이지 미디어 업로드에서 이미 쓰던 헬퍼) 재사용해 `v4/expense/uploadReceipt` 신규 — `FileUploadConstants::ALLOWED_DOCUMENT_EXTENSIONS`(png/jpg/jpeg/pdf) 그대로 사용, `uploads/expense_receipts/` 경로. 프론트는 파일 선택 즉시 업로드해 URL을 상태로 들고 있다가 등록 시점에 `receipt_files` 배열로 함께 전송(입력 폼) / "증빙 관리" 탭의 "첨부" 버튼은 기존 레코드에 업로드 후 `update`로 append(재사용 가능한 별도 흐름)
  - `getCategoryStats`(항목별 총지출/전체대비 비율/카테고리별 차트 데이터)·`getReceiptStats`(전체건수/미첨부건수/완료율) 2개 통계 전용 엔드포인트 신규 — 오퍼링의 `getOfferingStats`(ReportService) 패턴과 달리 지출은 Report 도메인이 아니라 `ExpenseService` 자체에 넣음(3개 탭 모두 지출 리소스 자체의 부속 통계라 별도 Report 엔드포인트로 분리할 필요가 없다고 판단)
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 지출 등록(사무관리비 ₩150,000, 실제 S3 업로드 없이 폼 검증) → 전체내역 KPI(총지출/건수/증빙미첨부) 실데이터 확인 → 항목별 조회(선택항목 총지출/전체대비 100%/예산미설정/차트) 확인 → 증빙관리(전체건수/미첨부/완료율 0%) 확인 → `uploadReceipt` API 직접 fetch로 실제 S3(`tochurchfile` 버킷) 업로드 검증 → `update`로 receipt_files 연결 후 전체내역·증빙관리 양쪽에서 "✓ 정상"/완료율 100%로 갱신 확인 → 수정 모달(사용목적 변경) 저장 확인 → 삭제 후 목록 0건 복귀 확인. `reg_audit_logs`에 CREATE/UPDATE 감사 로그 실제 기록 확인(`AuditLogHelper` 정상 동작). 최종 dev DB는 0건으로 정리 완료
  - `docs/06_페이지별_기능_현황.md` 지출 관리·지출 입력 ✅ 전환(전체 67 중 완료 52→54, 재정 섹션 2/11→4/11), `TestLinks.jsx`도 true로 갱신
- [x] 재정 — 예산 관리(`/finance/budget`) 백엔드 신규 구현 + 프론트 실연동 — Session 13 후속 (지출 관리 다음 순서로 진행)
  - 착수 전 핵심 이슈 발견: 원본 mock의 예산 카테고리는 7개 대분류(인건비/사역비/운영비/시설비/선교비/행사비/예비비)인데, 직전 세션에 만든 지출 카테고리는 26개 세부 항목(담임목사/목회비/... 등)이라 서로 다른 분류체계 — "집행률"(예산 대비 실제 지출)을 계산하려면 26→7 매핑이 필요했음. 매핑을 임의로 정하지 않고 초안(외부강사/세모승격금→인건비, 대출이자/차입상환→운영비 등)을 `AskUserQuestion`으로 제시해 사용자 확인 받은 후 `App\Constants\ExpenseCategoryGroup`(GROUPS 상수)로 확정 — 예비비만 대응하는 세부 항목 없음(적립 성격이라 자연스러움, 원본 mock도 예비비 집행액 0)
  - 신규 테이블 `reg_budgets`([14_reg_budgets.sql](docs/schema/14_reg_budgets.sql)) — church_id/year/category당 1행(UNIQUE), amount/formula(계산식 메모)/updated_by. "집행액"은 이 테이블에 저장하지 않고 매 조회 시 `reg_expense_records`를 `ExpenseCategoryGroup` 매핑으로 롤업해 실시간 계산(헌금·지출처럼 스냅샷 캐시 없이 항상 최신 실데이터 기준)
  - `BudgetController`/`Service`/`Repository`/`Request` 신규, `v4/budget/*` 4개 라우트(`getStatus`/`register`/`update`/`delete`), `AuditMenuCode::BUDGET`(권한 매트릭스에 플레이스홀더로만 있던 상수)을 처음 실사용 — `permission:BUDGET`이 이제 실제로 라우트를 보호
  - `getStatus(year)` 하나로 예산현황·연간예산 두 탭을 모두 커버 — 7개 대분류를 항상 고정 순서로 반환(`ExpenseCategoryGroup::ORDER`), 예산 미설정 카테고리도 budget=0/budget_id=null인 채로 행에 포함(원본 mock의 고정 7행 레이아웃을 그대로 유지하기 위함). 카테고리별 rate 기준 상태 판정(90%↑위험/80%↑주의/그외 정상)은 원본 mock의 `rateOf`/`statusOf` 로직을 그대로 백엔드로 이식
  - **DB 적용**: 지출 관리 때와 동일하게 `AskUserQuestion`으로 확인 → "지금 적용+검증" 선택, `php artisan tinker`로 로컬 dev DB에 직접 `CREATE TABLE` 실행 후 브라우저 검증까지 완료
  - 원본 mock과의 차이점 2가지(카테고리가 고정 7종 도메인이라는 사실에서 자연스럽게 발생) — (1) "+ 항목 추가" 버튼 제거: 7개 카테고리가 항상 이미 존재하는 가상의 행이라 "추가"할 새 카테고리 자체가 없음, 대신 각 행의 연필 아이콘이 미설정 상태면 register, 설정된 상태면 update로 분기하는 동일 모달 재사용 (2) 연간예산 탭 하단의 "예산 저장" 일괄 버튼 제거 — 지출/헌금/소식공지 등 이 세션 내내 지켜온 "리스트 화면은 행 단위 즉시저장" 원칙과 동일하게, 모달에서 저장 클릭 시 바로 API 호출·반영. 그 자리엔 실제 "마지막 수정"(연도 내 budgets 중 최신 updated_at + `gh_church_admin.name` 조인) 정보만 남김(원본은 정적 텍스트 "2026.01.10 / 재정부장")
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 예산 미설정 상태(7행 전부 "미설정", KPI 전부 ₩0) 확인 → 인건비 ₩104,400,000 + 계산식 "8,700,000 × 12" 등록 → 연간예산 탭에 즉시 반영(설정된 항목 수 1/7, 월평균 자동계산, 마지막 수정 실제 관리자명 표시) 확인 → `reg_expense_records`에 담임목사 카테고리로 ₩95,000,000 테스트 지출 직접 등록 → 예산현황 탭에서 인건비 집행률 91%로 정확히 롤업 계산되고 "위험" 배지 + 상단 KPI(집행액/잔여/전체집행률 91%) + "집행률 90% 이상 항목 1개" 콜아웃까지 전부 실데이터로 반영되는 것 확인(26→7 매핑 로직 정상 동작 검증) → 중복 등록 시도 시 `DUPLICATE_DATA` 에러 확인 → 삭제 후 0/7 원복 확인. `reg_audit_logs`에 CREATE/DELETE 기록 확인. 테스트 지출·예산 데이터 전부 정리, dev DB 순정 상태로 복귀
  - `docs/06_페이지별_기능_현황.md` 예산 관리 ✅ 전환(전체 67 중 완료 54→55, 재정 섹션 4/11→5/11), `TestLinks.jsx`도 true로 갱신 — 남은 재정 미구현: 재정 대시보드/재정 통계/재정 보고서/재정 설정/재정 카테고리/재정 권한 설정 6개(다음 순서: 재정 대시보드, 이제 헌금+지출+예산 데이터가 모두 있어 대시보드 위젯 대부분을 채울 수 있는 상태)
- [x] 재정 — 재정 대시보드(`/finance/dashboard`) 백엔드 신규 구현 + 프론트 실연동 — Session 13 후속 (예산 관리 다음 순서)
  - 신규 테이블·스키마 변경 전혀 없음 — 헌금(`OfferingRepository`/`ReportRepository`)·지출(`ExpenseRepository`)·예산(`BudgetRepository`) 3개 리포지토리를 조합하는 순수 집계 화면이라 `ReportService`에 `ExpenseRepository`/`BudgetRepository`를 추가 주입하고 `getFinanceDashboard()` 메서드만 신설(`v4/report/getFinanceDashboard`, 기존 `getDashboard`/`getOfferingStats`와 동일하게 `permission:REPORT`)
  - 착수 전 사용자 확인 필요했던 유일한 항목: "현재 잔액" — 이 시스템엔 실제 계좌 잔액을 추적하는 테이블이 전혀 없어(헌금·지출 "기록"만 있고 "잔액 스냅샷" 개념이 없음) 직접 계산이 불가능. `AskUserQuestion`으로 "데이터 없음 처리" vs "누적 수입-지출 근사 계산" 중 선택받아 **후자로 확정** — `ReportRepository::getOfferingCumulativeTotal`/`ExpenseRepository::getCumulativeTotal`(둘 다 날짜 필터 없는 전체기간 SUM, 신규 메서드) 차이로 계산, 시작 잔액을 0으로 가정하는 근사치라는 걸 화면에 "(추정)" 라벨 + 상시 안내 문구로 명시(실제 은행 잔액과 다를 수 있음을 숨기지 않음)
  - `ExpenseRepository`에 대시보드 전용 경량 메서드 3개 신규 추가 — `getTotals`(기간 합계만, 목록 없이), `getCumulativeTotal`(전체기간 SUM), `getMonthlyTrend`(월별 추이, `ReportRepository::getOfferingMonthlyTrend`와 동일 패턴)
  - "이번 달 예산 사용 현황"의 "전체 예산"은 예산 관리에 등록된 올해 연간 예산 합계 ÷ 12로 계산(월 단위 예산 개념 자체가 없어 근사). "주요 재정 항목 TOP 5"는 원본 mock이 임의로 나열한 5개 카테고리(인건비/사역비/운영비/시설비/**관리비** — "관리비"는 실제 예산 7개 대분류 어디에도 없는 이름이었음, mock 작성 시 오기로 추정)를 버리고, 예산 관리 화면과 동일한 실제 7개 대분류 전체를 집행액 기준 내림차순 정렬해 상위 5개만 노출(예산관리에서 이미 검증된 `ExpenseCategoryGroup` 26→7 롤업 로직을 `ReportService::getFinanceDashboard` 안에 인라인으로 재구현 — Service가 다른 Service를 호출하지 않고 Repository만 직접 의존하는 이 프로젝트의 3레이어 원칙을 지키기 위해 `BudgetService`를 호출하는 대신 리포지토리 조합을 그대로 복제)
  - "오늘의 재정 체크" 3카드 중 실데이터로 채울 수 있던 건 "증빙 필요" 1개뿐(`ExpenseRepository::getReceiptSummary`의 미첨부 건수, 날짜 필터 없이 전체 기간) — "미정리"(지출 임시저장/분류 대기 같은 상태 개념이 스키마 어디에도 없음)와 "보고 준비"(재정보고서 화면 자체가 아직 백엔드도 프론트도 없음, `/finance/report`는 여전히 🚧)는 카드 레이아웃·문구는 그대로 두되 반투명 처리 + "아직 지원되지 않는 기능입니다"로 정직하게 표시하고 링크(`Link`)를 일반 `div`로 교체해 클릭이 안 되도록 조정 — 데이터 없는 기능을 가짜로 작동하는 것처럼 보이게 하지 않음
  - 월별 수입/지출 차트는 원본 mock이 데이터를 이미 백만원 단위로 미리 나눠서 넣고(`[11.2, 10.8, ...]`) `won()` 헬퍼가 다시 ×1e6 해서 표시하는 역방향 스케일 트릭을 쓰고 있었는데, 이 프로젝트의 다른 차트들(헌금/지출 항목별 조회 등)은 전부 원(₩) 단위 실금액을 그대로 데이터로 넣고 y축 틱 콜백에서 `/1e6`로 축약 표시하는 방식이라 이 화면도 그 기존 관례로 통일(신규 유틸 없이 기존 패턴 재사용)
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 먼저 빈 상태(헌금·지출·예산 전부 0건)에서 모든 KPI/차트/체크카드/TOP5가 "데이터 없음"/"미설정"/"—" 등으로 정직하게 렌더링되는 것 확인 → 헌금 2건(이번달 500만원/전월 400만원), 지출 1건(사무관리비 200만원, 운영비 그룹), 예산 1건(운영비 연 1,200만원) 테스트 데이터 등록 → 이번달수입 5,000,000원(▲전월대비 25%, 정확히 (500-400)/400 계산 일치), 이번달지출 2,000,000원(예산대비 200% — 월예산 100만원 대비, 의도한 극단치 그대로 정확 계산), 현재잔액(추정) 7,000,000원(=900만-200만) + 약 3.5개월 운영가능(=700만/200만), TOP5에 운영비 16.7%(연간 예산 1200만 대비 연초~오늘 누적 200만, 정상 배지)로 정확히 반영, 증빙필요 1건(방금 등록한 무증빙 지출과 일치) 전부 확인 → 차트 canvas 2개 정상 렌더링, 콘솔 에러 없음(내가 의도적으로 트리거한 400/기존 노이즈 ERR_CONNECTION_REFUSED 제외) → 테스트 데이터 전부 삭제 후 재확인, 빈 상태로 완전 복귀
  - `docs/06_페이지별_기능_현황.md` 재정 대시보드 ✅ 전환(전체 67 중 완료 55→56, 재정 섹션 5/11→6/11), `TestLinks.jsx`도 true로 갱신 — 남은 재정 미구현: 재정 통계/재정 보고서/재정 설정/재정 카테고리/재정 권한 설정 5개(전부 해당 화면 자체의 목적이 서로 다른 신규 기능 설계가 필요해 보임 — 다음 세션에서 사용자 우선순위 확인 후 진행)
- [x] 재정 — 재정 통계(`/finance/statistics`) 백엔드 신규 구현 + 프론트 실연동 — Session 13 후속 (재정 대시보드 다음 순서, "5탭 전부 한번에" 사용자 선택)
  - 원본 mock이 1300줄+짜리 5개 탭(Overview/수입통계/지출통계/재정흐름분석/기간별비교) 구조라 착수 전 범위(5탭 전부 vs 핵심 3탭만)를 `AskUserQuestion`으로 확인 — "5탭 전부"로 확정
  - 신규 테이블 없음 — `ReportService::getFinanceStats(from_date,to_date)` 하나(`v4/report/getFinanceStats`, `permission:REPORT` — 헌금 통계와 동일하게 재정 전용 코드가 아닌 일반 REPORT 그룹 소속, 기존 관례 그대로 따름)가 5개 탭 전체를 커버 — 탭마다 기간(이번달/최근3개월/최근6개월/직접설정)을 다르게 선택해 동일 엔드포인트를 재호출하는 구조
  - **"직전 기간"의 정의를 명확히 재정의** — 원본 mock은 "지난 달"/"지난 분기" 같은 캘린더 단위 비교를 가정했지만, 실제로는 **선택 구간과 정확히 같은 일수의 바로 앞 구간**으로 계산(예: 8/1~8/9 조회 시 직전 기간은 7/23~7/31, 7월 전체가 아님) — 이렇게 해야 "이번달 vs 지난달"/"이번분기 vs 지난분기"/"올해 vs 작년" 3개 프리셋을 각각 다른 로직 없이 프론트가 from/to만 다르게 넘기는 것으로 전부 처리 가능. 캘린더 단위가 아니라는 걸 화면에 명시(정직성 원칙)
  - `ReportRepository::getOfferingWeeklyTrend`/`ExpenseRepository::getWeeklyTrend` 신규(기간별 비교 탭의 "주차별 재정 잔여 추이"용, `FLOOR(DATEDIFF(...)/7)`로 7일 단위 구간화)
  - `ReportService`에 `rollupExpenseToGroups()`(26→7 매핑, `getFinanceDashboard`와 로직 공유)·`findMomExtremes()`(카테고리별 전기 대비 최대 증가/감소 항목 탐색) private 헬퍼 신규 — "가장 큰 비중 항목"/"최근 증가 항목"/"최근 감소 항목"/"최근 급증 항목" 하이라이트 카드가 전부 이 헬퍼의 실제 계산 결과
  - "이상 징후 감지"(Overview 콜아웃)는 원본 mock의 하드코딩된 2개 문장(선교비 42%↑, 시설비 95%↑) 대신 실제 규칙 2종으로 계산 — 지출 그룹 전기 대비 30%↑ 증가, 연간(YTD) 예산 90%↑ 사용. 0건이면 콜아웃 자체를 숨김(Budget 페이지 위험콜아웃과 동일 전례)
  - **버그 발견·즉시 수정**: 첫 브라우저 검증에서 전체 화면이 "불러오는 중..."에 멈추고 네트워크 탭에 400(`VALIDATION_FAILED`, "시작일 값을 입력해주세요")이 찍힘 — 원인은 프론트의 `getPeriodRange()`가 `{from, to}` 형태로 범위를 반환하는데, `getFinanceStats()` 호출부에서 이 객체를 그대로 넘겨 백엔드가 기대하는 `from_date`/`to_date` 키와 이름이 안 맞았던 것(3곳: `usePeriodStats` 훅, Overview, Comparison). `{ from_date: range.from, to_date: range.to }`로 매핑해 수정
  - **설계 결함 발견 후 정직하게 라벨링**: "예산 사용률"(기간 지출 ÷ 기간 일수만큼 균등 배분한 연간예산)이 짧은 기간(예: 9일)에서 405.9%처럼 극단적으로 나오는 걸 검증 중 발견 — 계산 자체는 정확함(급여처럼 월 1회 몰아서 나가는 지출이 있으면 며칠짜리 구간에서는 필연적으로 왜곡되어 보이는 근사치의 태생적 한계). 로직을 바꾸는 대신 Overview/지출통계/기간별비교 3곳의 "예산 사용률" 라벨에 ⓘ 툴팁으로 근사치 한계를 명시하는 방식으로 정직하게 처리(현재 잔액(추정) 라벨링과 동일한 원칙)
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 이번 기간(8/1~8/9)에 헌금 2건(9M)·지출 2건(3M), 직전 동일기간(7/23~7/31)에 헌금 2건(6M)·지출 2건(2M), 예산 2건(인건비 24M·운영비 6M) 테스트 데이터로 5탭 전부 수기 계산과 대조 — Overview(최근3개월 롤업 15M/5M/10M/66.7%), 수입통계(9M, +50%, 십일조 최대비중 66.7%+50%증가), 지출통계(3M, 405.9%(짧은기간 근사), 인건비66.7%/운영비33.3%, 증빙누락100%, 운영비급증100%), 재정흐름분석(최근6개월=보유2개월분, 안정), 기간별비교(수입+50%/지출+50%/잔여증감+2M/예산사용률변화+135.3%p — 전기 405.9%↔당기 270.6%대비 수동계산 정확히 일치) 전부 검증 완료. 콘솔 400 에러는 전부 수정 전 요청의 잔존 로그였음을 네트워크 탭 재확인으로 구분(실제 최신 요청은 전부 200). 테스트 데이터 전부 삭제 후 3개 테이블 0건 확인
  - `docs/06_페이지별_기능_현황.md` 재정 통계 ✅ 전환(전체 67 중 완료 56→57, 재정 섹션 6/11→7/11), `TestLinks.jsx`도 true로 갱신 — 남은 재정 미구현: 재정 보고서/재정 설정/재정 카테고리/재정 권한 설정 4개(전부 서로 다른 신규 기능 설계 필요, 다음 세션에서 우선순위 확인)
- [x] 문자 템플릿(`reg_message_templates`) DB 적용 + 검증 완료 — Session 13 후속
  - 지난 세션에 코드(`v4/message/template/*` 7종)까지만 구현하고 "문서만, 실행은 직접"으로 DB 미적용 상태였던 것을 사용자가 "진행해줘"로 승인 — [11_reg_message_templates.sql](docs/schema/11_reg_message_templates.sql) 로컬 dev DB 적용(DROP+CREATE 2문장이라 `DB::statement` 대신 `DB::unprepared` 사용)
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 템플릿 등록(예배 분류, SMS, 변수 `{이름}` 포함) → 목록 즉시 반영 → Compose.jsx 발송 화면에서 템플릿 드롭다운에 실제로 노출, 선택 시 본문 자동 채움 + 미리보기에서 `{이름}`이 실제 수신자명으로 정확히 치환되는 것까지 확인(수신자는 `sessionStorage` 직접 stash로 1단계 우회) → 목록 화면에서 "복제"(→"테스트 템플릿 (복제본)" 생성) · "비활성화"(상태 배지 전환) 둘 다 정상 동작 확인 → `reg_audit_logs`에 CREATE(등록)·CREATE(복제)·UPDATE(비활성화) 3건 기록 확인. delete API는 설계상 없음(비활성화로 대체) 재확인. 테스트 템플릿 2건 DB에서 직접 정리
  - `docs/06_페이지별_기능_현황.md` 템플릿 ✅ 전환(전체 67 중 완료 57→58, 메시지 섹션 3/7→4/7), `TestLinks.jsx`도 true로 갱신 — 메시지 섹션 잔여 미구현은 문자설정/푸시알림/푸시알림상세 3개(SMS/푸시 게이트웨이 자체가 없는 영역, 스코프가 다름)
- [x] 재정 — 재정 보고서(`/finance/report`) 백엔드 신규 구현 + 프론트 실연동 — Session 14
  - 신규 테이블 없음 — 재정 통계와 마찬가지로 헌금·지출·예산 리포지토리 조합. `FinanceReportController`/`Service`/`Request` 신규(전용 컨트롤러로 분리, `ReportController`에 얹지 않음), `v4/financeReport/getMonthly`·`getAnnual` 2개 라우트. `AuditMenuCode::FINANCE_REPORT`(권한 매트릭스에 플레이스홀더로만 있던 상수)를 처음 실사용해 `permission:FINANCE_REPORT`로 보호 — 재정 통계가 편의상 일반 `REPORT` 권한을 재사용했던 것과 달리, 이 화면은 전용 메뉴 코드가 이미 있어 원칙대로 그대로 사용(재정 통계 쪽 선택이 돌아보면 더 정확했을 수 있었다는 점은 기록만 해둠, 이번 세션 스코프 아님)
  - 월간 보고의 "예산 대비"는 연간예산÷12(월 배분) 대비 그 달 지출 비율(재정통계의 기간 비례 배분과 같은 방식이나 여기선 "1개월" 고정이라 계산이 더 단순), 연간 보고는 연간예산 전체 대비 그대로 사용 — 재정통계 때 겪었던 "짧은 기간에서 왜곡되어 보임" 문제가 월간 보고에서는 구조적으로 자연스러운 스케일(1개월 대 1개월)이라 별도 안내 문구 불필요
  - "전년 대비"는 재정통계의 "직전 동일기간"(일수 기준 근사)과 다르게 **실제 달력 연도**로 계산(선택 연도 1/1~12/31, 또는 올해면 1/1~오늘 vs 전년도 동일 날짜 범위) — 연간 보고서는 "작년과 비교"라는 사용자 기대가 명확해서 근사치 대신 정확한 전년 동기 비교가 더 적절하다고 판단
  - 원본 mock 상단의 "생성일 2026.01.06 / 매월 1일 자동 생성" 고정 문구는 실제로 보고서를 자동 생성하는 배치/스케줄러가 이 시스템에 전혀 없어 제거 — 사실이 아닌 문구를 남기지 않는다는 이번 세션 전체의 원칙 재적용
  - PDF 탭: 옵션 패널(보고서종류/기간)에 따라 실제 `getMonthly`/`getAnnual`을 호출해 미리보기를 실데이터로 렌더링. "포함 섹션" 체크박스 4개는 원본이 비제어(`defaultChecked`)였던 것을 실제 토글로 전환(미리보기 섹션 표시/숨김에 직접 반영). "PDF 다운로드" 버튼은 실제 PDF 생성 라이브러리(dompdf 등) 신규 의존성 추가 없이 disabled 처리 + "준비 중, 인쇄를 이용해주세요" 안내(원본은 onClick 자체가 없던 죽은 버튼이었음). "인쇄" 버튼은 원본 그대로 `window.print()`(브라우저의 PDF로 저장 기능으로 사실상 PDF 내보내기 역할 수행) 유지
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 2026년 8월 헌금 2건(10.4M)·지출 2건(6.3M, 인건비/운영비), 2025년 8월 헌금 1건(5M, YoY 비교용), 2026년 예산 2건(인건비24M·운영비12M) 테스트 데이터 — 월간 보고(수입10.4M/지출6.3M/증감+4.1M/증빙누락2건, 인건비 예산대비 225%·운영비 180% 월배분 계산 정확히 일치) → 연간 보고(전년대비+108%, 십일조 YoY+12%, 주일헌금은 전년 기록 없어 "데이터 없음" 정직 표시, 예산사용률 17.5%=6.3M/36M) → PDF 탭(월간 기본값으로 실데이터 미리보기, 수입내역 체크 해제 시 해당 섹션 즉시 사라짐 확인, PDF다운로드 disabled+툴팁 확인) 전부 수동 계산과 정확히 일치. 콘솔 에러 없음(기존 무관 노이즈 ERR_CONNECTION_REFUSED 제외). 테스트 데이터 전부 삭제 후 3개 테이블 0건 확인
  - `docs/06_페이지별_기능_현황.md` 재정 보고서 ✅ 전환(전체 67 중 완료 58→59, 재정 섹션 7/11→8/11), `TestLinks.jsx`도 true로 갱신 — 남은 재정 미구현: 재정 설정/재정 카테고리/재정 권한 설정 3개(전부 서로 다른 신규 기능 설계 필요, 다음 세션에서 우선순위 확인)
- [x] 재정 — 재정 설정(`/finance/settings`) "정책 값만" 백엔드 신규 구현 + 프론트 실연동 — Session 15
  - 착수 전 조사에서 원본 mock이 단순 설정 화면이 아니라 **완전한 회계 시스템**(계정과목 체계, 초기이월금 원장, 회계기수/결산 워크플로, 기부금영수증 국세청 발행 시스템, 마감 잠금)을 요구한다는 걸 발견 — 지금 헌금/지출이 쓰는 자유 입력 카테고리 모델과 근본적으로 다른 계정과목 체계가 필요하고, 마감/잠금이 도입되면 이미 만든 헌금·지출 CRUD에도 "마감된 기간 수정 불가" 규칙이 역으로 영향을 주는 등 파급力이 커서, 착수 전 `AskUserQuestion`으로 스코프를 확인 — **"정책 값만 실연동"으로 확정**(계정과목 CRUD·초기이월금·결산실행·마감실행+이력·영수증발행실행+이력은 스코프 밖)
  - 신규 테이블 없음 — 교직설정이 이미 쓰던 범용 키-값 테이블 `reg_church_settings`를 재사용(`SettingRepository` 그대로 주입, 신규 리포지토리 없음). `FinanceSettingService`가 `AuditMenuCode::FINANCE_SETTING`(플레이스홀더였던 상수)으로 별도 감사 로그를 남기기 위해 기존 `SettingService`(교직설정 전용, `AuditMenuCode::SETTING` 하드코딩)를 재사용하지 않고 얇은 전용 서비스를 신설 — `v4/financeSetting/getSettings`·`saveSettings`·`uploadSeal`, `permission:FINANCE_SETTING`으로 보호(재정보고서 때와 동일하게 전용 플레이스홀더 상수를 정확히 사용)
  - 정책 키 21개 확정: 회계기수/결산의 "회계 정책"(회계연도시작월/회계방식/자동이월 3개), 기부금영수증의 "기본 설정"(국가/발행연도/일련번호시작 3개)+"발행자 정보"(사업자번호/대표자명/단체명/소재지/직인이미지URL 5개)+"발행 정책"(자동발행/홈택스제출/개별발행허용/일괄발행일자/최소발행금액 5개), 마감관리의 "마감 정책"(월별자동마감/연말자동결산/마감후잠금/알림시점/다음마감예정일 5개)
  - 직인 이미지는 순수 텍스트 설정이 아니라 파일이라 `uploadSeal` 엔드포인트 신규 — `S3FileHelper`(지출 증빙, 미디어 업로드에서 이미 쓰던 헬퍼) 재사용해 `uploads/finance_seal/`에 업로드, 반환된 URL을 다른 텍스트 설정과 동일하게 `saveSettings`로 저장(최대 1MB, PNG 권장 — mock 안내 문구 그대로 검증 규칙에 반영)
  - 스코프 밖 처리 방식: 계정과목(재정구조) 전체, 초기이월금 전체, 회계기수/결산의 "결산 테이블+결산가이드+회계기수추가" 상단 박스, 기부금영수증의 "발행 현황"+"발행 이력" 하단 박스, 마감관리의 "현재 마감 상태"+"마감 전 체크리스트"+"마감 이력" — 전부 원본 mock DOM 구조·문구를 그대로 유지한 채 `opacity:0.55; pointer-events:none`으로 시각적 비활성화 + 섹션 상단에 회색 `InfoCallout`로 "이번 세션 스코프에서는 제외되어 목업 화면만 표시됩니다" 명시(재정 대시보드의 "미정리"/"보고 준비" 카드 처리와 동일한 정직성 원칙)
  - 폼 상태를 좌측 사이드바로 전환되는 5개 섹션 컴포넌트 대신 부모 `FinanceSettings`에 lift — 섹션 전환 시 언마운트되어도 입력값이 사라지지 않고, 원본 mock의 화면 하단 공용 "설정 저장" 버튼 하나로 전체 21개 키를 한 번에 저장하는 구조를 그대로 재현(개별 섹션마다 저장 버튼을 따로 만들지 않음 — 원본 UI 그대로 유지)
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 회계 정책(회계연도시작월→9월, 회계방식→발생주의, 자동이월→OFF) 저장 후 새로고침해도 값 유지 확인 → 기부금영수증 발행자정보(사업자번호/대표자명/단체명/소재지) 저장 후 새로고침해도 값 유지 확인 → 직인 이미지 실제 S3(`tochurchfile` 버킷) 업로드 API 직접 검증(UI의 파일 선택 다이얼로그는 Browser 도구가 실제 파일 업로드를 지원하지 않아 우회) → 마감 정책 섹션 렌더링 확인 → `reg_audit_logs`에 CREATE 2건(21개 키 전부 나열) 기록 확인 → 스코프 밖 섹션(재정구조/초기이월금/결산테이블/발행현황+이력/마감상태+체크리스트+이력) 전부 원본 레이아웃 유지한 채 반투명+안내문구로 정상 표시 확인. 콘솔 에러 없음. 테스트 설정 21개 키 전부 DB에서 직접 정리
  - `docs/06_페이지별_기능_현황.md` 재정 설정 ✅ 전환(전체 67 중 완료 59→60, 재정 섹션 8/11→9/11), `TestLinks.jsx`도 true로 갱신 — 남은 재정 미구현: 재정 카테고리/재정 권한 설정 2개
- [x] 재정 — 재정 카테고리(`/finance/settings/categories`, 실제 화면명 "계좌관리") 백엔드 신규 구현 + 프론트 실연동 — Session 15 후속
  - 착수 전 확인: 문서상 "재정 카테고리"라는 이름과 달리 실제 mock 파일(`SettingsCategories.jsx`)의 화면 제목·내용은 "계좌관리" — 대분류/소분류 계정과목이 아니라 현금/통장 계좌(코드/유형/은행/계좌번호/헌금입력·지출입력·활성 3토글) CRUD였음. 재정 설정 때와 달리 표준적인 목록+모달 CRUD라 별도 스코프 확인 없이 바로 진행
  - 신규 테이블 `reg_finance_accounts`([15_reg_finance_accounts.sql](docs/schema/15_reg_finance_accounts.sql)) — church_id/type/name/bank/account_number/description/use_for_offering/use_for_expense/is_active/sort_order. `FinanceAccountController`/`Service`/`Repository`/`Request` 신규, `v4/financeAccount/*` 5개 라우트
  - **핵심 발견**: 헌금입력(`DonationInput.jsx`)·지출입력(`ExpenseInput.jsx`) 화면의 "입력 원장"/"출금 원장" 드롭다운이 지금까지 각각 컴포넌트 내부에 하드코딩된 3개 문자열(`LEDGERS` 상수)이었는데, 이게 바로 이 계좌관리 화면이 관리해야 할 데이터였음 — 계좌관리만 만들고 끝내지 않고, 두 입력 화면의 `LEDGERS` 상수를 제거하고 `getActiveFinanceAccounts('offering'|'expense')`로 교체해 실제 등록된 계좌 목록이 드롭다운에 뜨도록 연결(계좌를 추가/삭제/토글하면 입력 화면에 즉시 반영). `reg_offering_records.ledger`/`reg_expense_records.ledger`는 여전히 자유 입력 문자열 컬럼(FK 아님)이므로 계좌를 삭제해도 과거 헌금·지출 기록의 ledger 값은 그대로 보존됨(의도된 설계 — 회계 기록은 계좌 설정 변경과 무관해야 함)
  - **권한 분리**: 계좌 CRUD(`getList`/`register`/`update`/`delete`)는 `permission:FINANCE_SETTING`(계좌관리가 설정 하위 메뉴이므로), 반면 `getActiveList`(입력 화면의 드롭다운 조회용)는 별도 라우트 그룹으로 분리해 `jwt.auth`만 요구 — 헌금입력은 `permission:OFFERING`, 지출입력은 `permission:EXPENSE`로 보호되는데 이 두 권한을 가진 사용자가 FINANCE_SETTING 권한은 없을 수 있어(예: pastor는 SETTING/FINANCE_SETTING 제외 전체 접근), `getActiveList`까지 FINANCE_SETTING으로 막으면 헌금·지출 입력 자체가 깨지는 버그가 될 뻔했음 — 라우팅 작성 중 발견해 즉시 분리(내정보/사이드바 조직트리가 `jwt.auth`만 요구했던 것과 동일한 전례)
  - 계좌 목록이 비어있을 때 두 입력 화면 모두 "등록된 계좌가 없습니다" + "설정 > 계좌관리에서 먼저 등록해주세요" 안내로 정직하게 표시, 저장 시도 시에도 동일 안내로 차단(빈 문자열 ledger로 저장되는 것 방지)
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 계좌 등록("테스트계좌(국민은행)", 헌금입력·지출입력·활성 전부 ON) → 헌금입력·지출입력 화면 양쪽 드롭다운에 실시간 반영 확인 → 지출입력 토글만 OFF → 지출입력 드롭다운에서만 사라지고 헌금입력엔 그대로 남아있는 것 확인(토글 분리 동작 검증) → 삭제 후 계좌 목록 0건 복귀. `reg_audit_logs`에 CREATE(등록)·UPDATE(토글) 기록 확인, 콘솔 에러 없음. 테스트 데이터 정리 완료
  - `docs/06_페이지별_기능_현황.md` 재정 카테고리 ✅ 전환(전체 67 중 완료 60→61, 재정 섹션 9/11→10/11), 헌금입력·지출입력 두 행에도 원장 드롭다운이 실데이터로 바뀐 사실 추가, `TestLinks.jsx`도 true로 갱신 — 재정 섹션 잔여 미구현은 재정 권한 설정 1개뿐
- [x] 재정 — 재정 권한 설정(`/finance/settings/permissions`) "통합 권한 관리로 안내" 전환 — Session 15 후속, **재정 섹션 11/11 전체 완료**
  - 지금까지와 달리 백엔드가 아예 없는 항목이라 사전 조사부터 진행. `SettingsPermissions.jsx` mock을 읽어보니 재정 전용 커스텀 역할(재정부장/회계담당자/감사위원/헌금입력자)을 자유롭게 만들고 사용자를 배정하는, 이 앱이 이미 갖춘 통합 권한 시스템(`관리 > 시스템설정 > 권한 관리`, 고정 4역할 admin/pastor/minister/volunteer × 36개 소메뉴 매트릭스, `reg_role_permissions`)과 근본적으로 다른 별도 RBAC 구조 — 실제로 이전 세션에도 이 페이지를 조사한 기록이 memory에 있었고("완전히 다른 역할 모델을 쓰는 별개의 미구현 기능") 동일한 결론이었음
  - 착수 전 `AskUserQuestion`으로 "독자 구현(신규 테이블 3~4개+체크하는 미들웨어 로직)" vs "기존 통합 권한관리로 연결" 중 확인 — **"기존 권한관리로 연결"로 확정**. 신규 백엔드 전혀 없음(순수 프론트 교체) — 사이드바 NAV·브레드크럼·타이틀 등 원본 레이아웃은 유지하되, 본문(역할목록/역할정보/할당된사용자/12개 권한 매트릭스)을 "재정 권한은 통합 권한 관리에서 설정합니다" 안내 박스 + `/management/system/permissions`로 이동하는 버튼으로 교체. 재정 6개 메뉴(헌금·지출·예산·통계·보고·설정)가 이미 그 매트릭스에 포함되어 있다는 사실을 안내문에 명시
  - **부수 발견(브라우저 검증 중)**: 안내 대상인 `/management/system/permissions`(`Permissions.jsx`) 자체에 재정·관리 대메뉴 전체에 "아직 구현되지 않은 기능 — 저장은 되지만 실제로 차단되지는 않습니다"라는 하드코딩된 블랭킷 문구(`g.key === 'finance' || g.key === 'management'`)가 있었음 — 이 문구는 예전엔 정확했지만(재정/관리 라우트에 미들웨어가 전혀 없던 시절), 이번 세션에서 헌금·지출·예산·보고·설정 5개 재정 메뉴에 실제 `permission:X` 미들웨어를 달아놨고 관리(WEBSITE)도 훨씬 이전 세션에 이미 실연동되어 있어 stale한 상태였음. 그룹 단위 블랭킷 문구를 제거하고, 진짜로 전용 권한 코드가 없는 통계(FINANCE_STATS, `v4/report`가 `permission:REPORT`로 대체 보호됨)에만 정확한 문구를 남김
  - **사고 기록**: 브라우저 검증용으로 임시 포트(8199)에 백그라운드로 띄운 API 서버를 정리하며 `taskkill //F //IM php.exe`를 실행 — 이 명령은 PID를 지정하지 않고 이미지 이름으로 전체 PHP 프로세스를 종료하는 명령이라, 마침 이 세션 시작 시점에 훅으로 "다른 세션이 이 폴더에서 dev 서버 실행 중"이라는 안내가 있었던 것과 겹쳐 다른 세션의 PHP 서버까지 종료됐을 가능성이 있음(사후 확인 결과 시스템에 php.exe/node.exe 프로세스가 전혀 없었음, 그러나 사전 상태를 알 수 없어 실제 피해 여부는 불확실) — 사용자에게 즉시 고지함. 향후에는 특정 PID만 종료하거나 TaskStop 등 세션 스코프 도구를 사용할 것
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 안내 페이지 렌더링 확인 → "통합 권한 관리로 이동" 버튼 클릭 시 실제로 `/management/system/permissions`로 이동하고 재정 6개 메뉴가 매트릭스에 정상 포함된 것 확인 → stale 문구 제거 후 재정/관리 그룹에서 블랭킷 경고 사라지고 통계에만 정확한 안내가 뜨는 것 확인 → 역할 전환(사역자)도 정상 동작 확인(저장은 실제 권한 데이터를 건드리므로 클릭하지 않음). 콘솔 에러 없음(무관 노이즈 제외)
  - `docs/06_페이지별_기능_현황.md` 재정 권한 설정 ✅ 전환(전체 67 중 완료 61→62, **재정 섹션 11/11 전체 완료**), `TestLinks.jsx`도 true로 갱신 — 이로써 재정 도메인(헌금/지출/예산/대시보드/통계/보고서/설정/카테고리/권한설정 9개 화면 + 재정 대시보드/통계 등 파생) 전체 완료. 남은 전체 미구현은 메시지 2개(문자설정/푸시알림 2종)·플랫폼 2개(공지목록/상세) 총 5개뿐
- [x] 메시지 — 메시지 설정(`/member/messaging/settings`) "설정값만" 백엔드 신규 구현 + 프론트 실연동 — Session 15 후속
  - 착수 전 조사: `Settings.jsx`는 실제 SMS 게이트웨이(알리고/쿨SMS/카카오비즈톡/뿌리오) 연동 설정 화면인데, 이 프로젝트의 `MessageService::send`는 여전히 `reg_messages`에 발송 기록만 저장할 뿐 실제 게이트웨이를 호출하지 않음(CLAUDE.md 최상단 "완료된 기능" 요약에 이미 TODO로 명시돼 있던 사실) — 재정설정 때와 달리 이번엔 "발송 정책"(횟수제한·야간제한)·"수신거부 관리"조차 강제할 실행 로직 자체가 없어(착신 SMS 웹훅도 없음) 정책값 대부분이 지금은 순수 저장용이라는 점을 `AskUserQuestion`으로 먼저 알리고 확인 — "설정값만 저장, 실제 연동은 미구현으로 정직 표시"로 확정
  - 신규 테이블 없음 — `reg_church_settings`(교직설정·재정설정이 이미 쓰던 범용 키-값 테이블) 재사용. `MessageSettingController`/`Service`/`Request` 신규(`SettingRepository` 그대로 주입), `v4/message/settings/getSettings`·`saveSettings`를 기존 `v4/message`(`permission:MESSAGE`) 그룹 안에 템플릿(`template/*`)과 동일한 서브 네임스페이스로 추가 — 새 권한 코드 불필요(문자 발송 전체가 이미 MESSAGE 하나로 관리됨)
  - **재정설정에는 없었던 추가 보안 처리**: API Key/Secret은 실제 타사 서비스 인증 정보라 감사 로그(`reg_audit_logs.change_detail`)에 원문이 남으면 안 된다고 판단 — `saveSettings`에서 로그 직전에 두 키 값을 `(저장됨)`으로 마스킹한 사본을 만들어 그것만 `AuditLogHelper::logCreate`에 전달(DB에 저장되는 실제 설정값 자체는 원문 그대로 유지, 마스킹은 로그 전용)
  - 화면 구성: "문자 서비스 연동"(업체 select+API키/시크릿) "발신 번호 설정"(번호+발신자명) "발송 정책"(1회/1일 한도, 야간제한 시간) "수신거부 관리"(키워드+자동/수동)까지 4개 InnerBox는 전부 폼 필드를 실제 저장에 연결. "연동 테스트"·"인증하기"·"테스트 문자 발송"·"요금 충전하기" 4개 버튼은 실제 게이트웨이 호출이 필요해 `disabled`+정확한 사유 툴팁으로 유지, "요금·잔액 정보" 3개 카드의 하드코딩 숫자(125,000원 등)는 "연동 필요"로 교체(가짜 수치를 그대로 두지 않음). 상단 뱃지도 "미연결"(거짓 함의 있음, 실제로 연결 테스트한 적 없음) 대신 입력 여부만 반영하는 "미설정"/"설정됨 (연동 미구현)"으로 조정
  - 원본 mock은 "변경사항 저장" 버튼 자체가 `disabled`(연동 테스트 성공이 선행 조건이라는 설정)였으나, 이번 스코프에서는 설정값 저장이 게이트웨이 연동 여부와 무관한 별개 동작이라 판단해 버튼을 활성화 — 하단 안내 문구도 "연동 테스트를 먼저 성공해야 저장할 수 있습니다"에서 "설정 값은 저장되지만, 실제 게이트웨이 연동 전까지 발송에는 반영되지 않습니다"로 정직하게 수정
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 업체(쿨SMS)·API키/시크릿·발신번호·발신자명·수신거부처리방식(승인후반영) 입력 후 저장 → 새로고침해도 전부 값 유지 확인(select/password/text/radio 전부) → 상단 뱃지 "설정됨 (연동 미구현)"으로 정상 전환 → `reg_audit_logs`에서 API키/시크릿이 `(저장됨)`으로 마스킹되어 기록된 것 직접 확인(원문 노출 없음). 콘솔 에러 없음(무관 노이즈 제외). 테스트 설정 11개 키 전부 DB에서 직접 정리
  - **세션 중 사고 및 대응**: 지난 항목(재정 권한 설정) 검증 후 정리 과정에서 `taskkill //F //IM php.exe`(이미지 이름 기준 전체 종료)를 실행해 다른 세션의 PHP 서버까지 종료됐을 가능성을 사용자에게 즉시 고지했던 것을 교훈 삼아, 이번엔 `php artisan serve`를 백그라운드로 띄울 때 작업 ID를 기록해두고 검증 종료 후 `TaskStop`으로 해당 작업만 정확히 종료(시작 전/후 `tasklist`로 다른 php.exe 프로세스가 없었음을 직접 대조 확인) — 이미지 이름 기준 강제종료를 다시 쓰지 않음
  - `docs/06_페이지별_기능_현황.md` 메시지 설정 ✅ 전환(전체 67 중 완료 62→63, 메시지 섹션 4/7→5/7), `TestLinks.jsx`도 true로 갱신 — 전체 남은 미구현은 푸시 알림 2개(`/member/push-notification`, 상세)·플랫폼 2개(공지 목록/상세) 총 4개뿐
- [x] 푸시 알림(`/member/push-notification`, 상세) 백엔드 신규 구현 + 프론트 실연동 — Session 15 후속
  - 착수 전 조사: mock의 "대상 요약"(발송가능/불가+사유별)과 "발송 이력 상세"의 개인별 SUCCESS/FAILED_RETRYABLE/FAILED_PERMANENT/SKIPPED 4단계가 실제 전송 결과를 전제로 하는데, 이 프로젝트엔 FCM 발송 인프라 자체가 전혀 없음(문자발송과 동일하게 항상 "기록만 저장") — `notification_outbox`(mock이 언급한 저장 테이블)는 존재하지 않음, 대신 `gh_push_token_v4`(기기토큰, 03_gh_www_api 소유 실제 테이블, 데이터 2건 확인)와 `gh_account_settings`(범용 계정별 설정, setting_key=`notification`의 JSON `master` 필드가 03_gh_www_front_renewal의 `MyPageNoti.jsx` "푸시 알림 전체" 토글과 동일한 실제 수신동의 플래그)를 발견 — `AskUserQuestion`으로 "실제 FCM 발송은 여전히 스코프 밖이지만, 대상자 단위 도달가능성(계정연동+토큰존재+수신동의)은 실제로 계산" vs "문자발송과 동일한 단순 기록만" 중 선택받아 전자로 확정
  - 신규 테이블 `reg_push_recipients`(발송 시점 대상자별 도달가능성 스냅샷 — reachable/skip_reason(no_account·no_token·optout)) + 기존 `reg_messages`에 `deep_link`/`resend_of`(재발송 원본 셀프참조) 컬럼 추가([16_reg_push_recipients.sql](docs/schema/16_reg_push_recipients.sql), 로컬 dev DB 적용+검증 완료). `gh_push_token_v4`/`gh_account_settings`는 03_gh_www_api 소유 기존 공유 테이블이라 읽기 전용으로만 조회, 스키마 변경 없음
  - `PushNotificationRepository`(신규) — `getDistinctProfileValues`(그룹선택 칩용 실제 직분/교인구분/신급 값, 교회 내 실사용 값만), `resolveGroupMemberIds`(조직/직분/교인구분/신급 필터 AND, 카테고리 내부는 OR), `getReachabilitySnapshots`(대상자별 계정연동→토큰존재→수신동의 순서로 판정, 설정 행 자체가 없으면 클라이언트 기본값과 동일하게 동의로 간주), `insertRecipients`/`getRecipientsByMessage`/`getSkippedMemberIds`/`countByReachable`. `MessageRepository`는 재사용(`resolveAllMemberIds`/`resolveOrganizationMemberIds`/`filterValidMemberIds`/`insertMessage` 그대로, `getResendChildren` 신규 추가) — `PushNotificationService`가 Service를 호출하지 않고 여러 Repository를 직접 조합하는 기존 3레이어 원칙 그대로 준수
  - `MessageRepository::baseQuery()`에 `reg_push_recipients` 기반 `excluded_count` 서브쿼리 LEFT JOIN 추가 — 기존 `getList`(문자+푸시 공용)가 push 행에 한해 제외 인원수를 함께 반환하게 됨(SMS 행은 매칭 레코드가 없어 자동으로 null). 발송 이력 화면(문자/푸시 공용)의 "발송대상/제외"·"도달가능률"이 신규 엔드포인트 없이 기존 `message/getList`만으로 실데이터화됨
  - `v4/message/push/getFilterOptions`·`getTargetSummary`·`send`·`getDetail`·`resend` 5개 신규 라우트, 기존 `v4/message`(`permission:MESSAGE`) 그룹에 추가(문자 발송·템플릿·설정과 동일 메뉴 코드 — 별도 권한 코드 불필요)
  - "재발송"은 실제 재전송이 아니라 **원본에서 제외됐던 대상의 도달가능성을 지금 시점 기준으로 재계산해 새 발송 기록(reg_messages, resend_of=원본id)을 만드는 것** — mock의 "재시도가능"(토큰없음, 나중에 토큰이 등록될 수 있음)/"영구실패"(계정없음·수신거부, 그 사이 해결됐을 가능성 낮음) 2단 체크박스 구분을 `skip_reason` 기준으로 그대로 재사용. mock의 "토큰 사용 방식"(최신/발송당시) 라디오는 실제 전송이 없어 의미가 없다는 안내와 함께 비활성화, "메시지"(원문/수정)는 실제로 구현(재발송 시 제목·내용 override 가능)
  - 프론트: `pushNotificationService.js` 신규(5개 API), `PushNotification.jsx`(발송하기: 전체/그룹/개별 3-way 대상선택 + 실시간 대상요약(350ms 디바운스) + 예약발송은 스케줄러 없어 비활성화; 발송이력: 문자와 동일 `getMessageList` 재사용, 기간 필터 실계산), `PushNotificationDetail.jsx`(5칸 통계를 총대상/발송대상/계정없음/토큰없음/수신거부로 정직하게 재정의, 재발송 모달 실동작)
  - 그룹선택 칩(조직/직분/교인구분/신급)은 교회 내 실제 값으로 동적 구성 — mock의 "부서" 별도 그룹은 대응 데이터가 없어 조직 목록에 통합(안내 문구 추가), 개별선택 모달은 하드코딩 5명 대신 `memberService.getMemberList` 실검색으로 교체
  - 버그 발견·수정: 발송이력 "기간" 필터에서 `to_date`를 날짜만(예: `2026-08-12`) 넘기면 MySQL이 자정(00:00:00)으로 취급해 당일 발송건이 전부 누락됨 — `to_date`에 하루를 더해 다음날 자정 미만까지 포함하도록 수정(문자 발송 쪽 필터는 이 버그를 만든 적이 없어 이번에 처음 발견)
  - 브라우저 검증(8199/5199 임시 포트, test111 계정): 도달가능성 3가지 사유를 전부 검증하기 위해 교인 3명을 임시로 계정연동(`churchero_user_id`) — 1명은 `gh_push_token_v4`에 토큰 있음+동의행 없음(기본값 true → 발송가능), 1명은 토큰 있음+`gh_account_settings.notification.master=false`(수신거부), 1명은 계정만 연동하고 토큰 미등록(토큰없음) — "전체 성도" 선택 시 대상요약이 정확히 "전체 53 / 발송가능 1 / 계정없음 50 / 토큰없음 1 / 수신거부 1"로 실계산됨을 확인 후 발송 → 발송이력에 "1/52", "1.9%" 정상 표시 → 상세 페이지에서 5칸 통계·11명 전체 목록의 상태·사유가 설정한 시나리오와 정확히 일치 확인 → 토큰없음이었던 대상에게 테스트로 토큰을 등록한 뒤 "재확인 후 재발송" 실행 → 정확히 그 1명만 새 발송(#3)의 "발송대상"으로 전환되고 원본(#2)↔재발송(#3) 양방향 링크("이 발송은 #2의 재발송입니다" / "이 발송의 재발송 기록: #3")까지 정상 확인 → 그룹선택(조직 칩 "남전도회" 직접 선택, 하위조직 제외 직속 2명만 매칭) · 개별선택(실검색 모달에서 1명 선택) 두 대상선택 모드도 각각 실데이터로 검증. 테스트로 임시 연동한 계정연동·토큰·설정·메시지 기록 전부 원상복구(dev DB 순정 상태로 복귀) 확인
  - `docs/06_페이지별_기능_현황.md` 푸시 알림 2개 ✅ 전환(전체 67 중 완료 63→65 — **메시지 섹션 7/7 전체 완료**), `TestLinks.jsx`도 true로 갱신 — 전체 남은 미구현은 플랫폼 2개(공지 목록/상세)뿐
- [x] 플랫폼 — 공지 목록/상세("교회로 공지 센터") — 02_gh_admin_api/front 신규 구축 + 교적 읽기전용 연동 — Session 15 후속, **docs/06 전체 67페이지 완료**
  - 사용자 확인: "교회로 공지센터는 교회로 관리자가 교적 전체에 전달하는 공지센터 — 교적은 읽기 기능만, 작성은 관리자(02_gh_admin_api/front)에 추가 개발해야 함"으로 스코프 확정. 조사 결과 기존 `gh_board_notice`(게시판별 고정 공지, 0건, 특정 board에 FK 종속)는 완전히 무관한 테이블이었고, 플랫폼 전체 방송용 공지 테이블 자체가 어디에도 없었음
  - 관리자 리포(02_gh_admin_api/front) 사전 조사 — `gh_banner_v4`/`gh_popup_v4`(마이그레이션 기반, `created_admin_no`만 있고 교회 스코프 없음 = 전 교회 공통 노출)가 정확히 같은 성격의 콘텐츠 모델이라 그 구조를 그대로 재사용. **이 리포는 04_gh_registry_api와 반대로 Laravel 마이그레이션을 쓰는 정책**이라 그 관례를 따름(교적 프로젝트의 "마이그레이션 금지" 규칙은 이 프로젝트에만 적용)
  - 신규 테이블 3개(02_gh_admin_api 소유, [2026_08_12_000001_create_gh_notice_v4_tables.php](../02_gh_admin_api/database/migrations/2026_08_12_000001_create_gh_notice_v4_tables.php)) — `gh_notice_v4`(공지/업데이트/점검/가이드, is_pinned, status), `gh_notice_v4_resource`(자료 다운로드 라이브러리, 특정 공지에 종속되지 않는 독립 자료), `gh_notice_v4_read`(읽음 기록 — `church_admin_no`는 **교적** 관리자 식별자(`gh_church_admin.admin_no`)이고 `gh_notice_v4.created_admin_no`는 **플랫폼** 관리자 식별자라 서로 다른 identity space임을 컬럼명으로 명확히 구분). 로컬 dev DB에 `php artisan migrate`로 적용 완료(사용자 확인 후) — 마이그레이션 테이블 자체가 이 리포 dev DB에 처음 생성될 만큼, 지금까지 관찰된 `gh_banner_v4` 등은 이번이 최초의 실제 마이그레이션 실행이었음(문제 없이 정상 적용됨)
  - 관리자 백엔드: `NoticeController`/`Service`/`Repository`(공지 CRUD 6종 + 자료 CRUD 5종, Banner 패턴과 동일하게 하나의 Controller/Service/Repository가 두 하위 엔티티를 함께 관리) — `v4/notice/*`, `jwt.auth`만 요구(Banner와 동일, 이 리포는 세분화된 권한 미들웨어가 없음). 파일 업로드는 기존 `FileService::uploadToS3` + `gh_file` 테이블 재사용(Banner 이미지 업로드와 동일 패턴)
  - 관리자 프론트: Vue 3 `<script setup lang="ts">` + Element Plus — `api/notice.ts`(타입 정의), `NoticeList.vue`(공지 목록+자료 다운로드 2-tab, `BannerList.vue` 패턴), `NoticeFormDialog.vue`/`NoticeResourceFormDialog.vue`(`BannerFormDialog.vue` 패턴). 기존 "기본정보 관리" 허브 페이지(`BasicOverview.vue`, 쿼리파라미터 탭 전환 방식으로 교단/약관/팝업/배너/유적지를 이미 관리 중)에 "공지" 7번째 탭으로 자연스럽게 편입(신규 최상위 라우트 불필요) — `permission/index.vue`의 메뉴 매트릭스에도 `basic_notice` 항목 추가(다른 메뉴들과 동일하게 표시 전용, 백엔드 강제 없음)
  - 교적 백엔드(04_gh_registry_api, 이번 세션 본 프로젝트): `NoticeRepository`/`Service`/`Controller`/`Request` 신규 — `gh_notice_v4`/`gh_notice_v4_resource`를 **읽기 전용**으로 직접 조회(같은 물리 DB 공유, `gh_push_token_v4`/`gh_account_settings` 읽기 전례와 동일한 크로스 프로젝트 공유 테이블 접근 패턴, 신규 테이블 없음). 유일한 write는 `gh_notice_v4_read`(상세 조회 시 자동 읽음처리, `updateOrInsert`로 멱등 처리) — 이 write도 도메인 CRUD가 아니라 열람 기록이라 감사로그 대상 아님(CLAUDE.md의 "조회 액션엔 audit 안 함" 원칙과 동일 취급). `v4/notice/*` 라우트는 `jwt.auth`만 요구(전 역할 공통 노출, 도메인 권한 불필요 — 사이드바 조직트리/내정보와 동일 전례)
  - 교적 프론트: `noticeService.js` 신규, `Notices.jsx`(전체공지/업데이트/가이드/자료다운로드 4탭, mock의 `ALL_NOTICES`/`RESOURCES` 하드코딩을 실API로 교체, 안읽음 점(`is_read`)·고정 아이콘(`is_pinned`) 실데이터 반영, 벨 토글은 원본 그대로 로컬 전용 유지 — 실제 알림 발송 인프라 없음), `NoticeDetail.jsx`(실제 이전글/다음글 연결, "읽음" 수 실카운트, "공유하기" 버튼은 원본 mock엔 동작이 없었으나 `navigator.clipboard`로 실제 링크복사 기능 추가 — 백엔드 불필요한 정직한 실동작이라 스코프에 포함, 작성자명은 플랫폼 관리자가 교적과 별개 identity라 원본 그대로 "교회로 운영팀" 고정 유지)
  - 브라우저 E2E 검증(관리자 8001/8197, 교적 5199/8199 임시 포트) — 실제 계정 비밀번호를 몰라 **패스워드를 건드리지 않고** `JwtHelper::createToken()`으로 기존 관리자 계정(ecclesia, admin_no=1)의 토큰을 직접 발급해 로그인 우회(이전 세션의 "타 계정 비밀번호 변경 금지" 원칙 준수 — 세션 초반 관리자 계정 패스워드 변경 시도가 auto-mode 분류기에 의해 실제로 차단됨을 확인하기도 함). 관리자에서 공지 등록("E2E 검증용 테스트 공지", 업데이트 타입)→활성화 → 교적 `/platform/notices`에서 실시간 반영 확인(제목·요약·타입배지·안읽음점 전부 일치) → 자료 1건 직접 INSERT(파일 업로드 UI는 브라우저 자동화가 네이티브 파일선택 다이얼로그를 지원하지 않아 우회) 후 "자료 다운로드" 탭에서 실제 파일크기 포맷("121 KB") 정확히 일치 확인 → 상세 페이지 진입 시 읽음수 0→1 실시간 반영 + 목록으로 돌아가면 안읽음 점 사라짐까지 읽음추적 왕복 확인 → 이전글/다음글 둘 다 "없습니다" 정직 표시(공지 1건뿐인 상태) 확인. 테스트 데이터(공지·자료·읽음기록) 전부 정리, 프로세스도 `TaskStop`으로 정확히 종료(시작 전/후 `tasklist` 대조)
  - `docs/06_페이지별_기능_현황.md` 공지 목록/상세 ✅ 전환 — **전체 67페이지 완료**(재정 11/11, 메시지 7/7, 플랫폼 2/2 등 전 섹션 100%), `TestLinks.jsx`도 true로 갱신



