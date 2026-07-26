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
- [x] 메시지 발송 (발송 기록 저장 — 실제 게이트웨이 연동은 TODO)
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
