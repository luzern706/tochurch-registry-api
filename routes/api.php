<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\CodeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\ChurchProfileController;
use App\Http\Controllers\Api\EducationController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\FinanceAccountController;
use App\Http\Controllers\Api\FinanceReportController;
use App\Http\Controllers\Api\FinanceSettingController;
use App\Http\Controllers\Api\IntroController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MemberJoinRequestController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MessageSettingController;
use App\Http\Controllers\Api\PushNotificationController;
use App\Http\Controllers\Api\MessageTemplateController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\OfferingController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PrayerController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\VolunteerController;
use App\Http\Controllers\Api\WorshipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 권한 매트릭스(설정 > 권한설정) 적용 대상 라우트는 'permission:MENU_CODE' 미들웨어를
| 붙인다(조회/관리 구분 없음 — 소속 소메뉴 중 하나라도 접근 가능하면 통과).
| admin_type='admin'은 항상 통과(CheckPermissionMiddleware 하드코딩 바이패스).
| 본인 스코프(profile) · 관리자 계정 관리(admin, super.auth 고정) · 인증(auth)은
| 권한 매트릭스 대상이 아니라 jwt.auth/super.auth만 적용.
|
*/

// 인증 (로그인은 jwt.auth 제외)
Route::prefix('v4/auth')->group(function () {
    Route::post('/adminSignIn', [AuthController::class, 'adminSignIn']);
});

Route::prefix('v4/auth')->middleware('jwt.auth')->group(function () {
    Route::post('/signOut', [AuthController::class, 'signOut']);
});

// 내정보 (로그인한 관리자 본인 — jwt.auth만, super.auth 불필요)
Route::prefix('v4/profile')->middleware('jwt.auth')->group(function () {
    Route::post('/getMyProfile',    [ProfileController::class, 'getMyProfile']);
    Route::post('/updateMyProfile', [ProfileController::class, 'updateMyProfile']);
    Route::post('/changePassword',  [ProfileController::class, 'changePassword']);
});

// 교회로 공지 센터 (읽기 전용) — 02_gh_admin_api가 작성한 gh_notice_v4*를 조회, 전 역할 공통 노출이라 jwt.auth만 요구
Route::prefix('v4/notice')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',         [NoticeController::class, 'getList']);
    Route::post('/getDetail',       [NoticeController::class, 'getDetail']);
    Route::post('/getResourceList', [NoticeController::class, 'getResourceList']);
});

// 교회 기본정보 (설정 > 기본정보 — SETTING 권한 사용)
Route::prefix('v4/churchProfile')->middleware(['jwt.auth', 'permission:SETTING'])->group(function () {
    Route::post('/getProfile',             [ChurchProfileController::class, 'getProfile']);
    Route::post('/getPastorOptions',       [ChurchProfileController::class, 'getPastorOptions']);
    Route::post('/getDenominationOptions', [ChurchProfileController::class, 'getDenominationOptions']);
    Route::post('/updateProfile', [ChurchProfileController::class, 'updateProfile']);
    Route::post('/uploadFile',    [ChurchProfileController::class, 'uploadFile']);
    Route::post('/deleteFile',    [ChurchProfileController::class, 'deleteFile']);
});

// 관리자 계정 관리 (슈퍼 관리자 전용)
Route::prefix('v4/admin')->middleware(['jwt.auth', 'super.auth'])->group(function () {
    Route::post('/getList',   [AdminController::class, 'getList']);
    Route::post('/getDetail', [AdminController::class, 'getDetail']);
    Route::post('/register',  [AdminController::class, 'register']);
    Route::post('/update',    [AdminController::class, 'update']);
    Route::post('/suspend',   [AdminController::class, 'suspend']);
    Route::post('/activate',  [AdminController::class, 'activate']);
    Route::post('/delete',    [AdminController::class, 'delete']);
    Route::post('/purge',     [AdminController::class, 'purge']);
});

// 권한 관리 (설정 > 권한설정 — 슈퍼 관리자 전용)
Route::prefix('v4/permission')->middleware(['jwt.auth', 'super.auth'])->group(function () {
    Route::post('/getMatrix', [PermissionController::class, 'getMatrix']);
    Route::post('/saveRole',  [PermissionController::class, 'saveRole']);
});

// 시스템 설정 > 전체 현황 / 활동 로그 (슈퍼 관리자 전용)
Route::prefix('v4/system')->middleware(['jwt.auth', 'super.auth'])->group(function () {
    Route::post('/getOverview',   [SystemController::class, 'getOverview']);
    Route::post('/getAuditLogs',  [SystemController::class, 'getAuditLogs']);
});

// 본인 권한 조회 (모든 로그인 사용자 — 프론트 메뉴 필터링/페이지 가드용)
Route::prefix('v4/permission')->middleware(['jwt.auth'])->group(function () {
    Route::post('/getMyPermissions', [PermissionController::class, 'getMyPermissions']);
});

// 교인 CRUD
Route::prefix('v4/member')->middleware(['jwt.auth', 'permission:MEMBER'])->group(function () {
    Route::post('/checkEmail', [MemberController::class, 'checkEmail']);
    Route::post('/getList',    [MemberController::class, 'getList']);
    Route::post('/getDetail',  [MemberController::class, 'getDetail']);
    Route::post('/register', [MemberController::class, 'register']);
    Route::post('/update',   [MemberController::class, 'update']);
    Route::post('/delete',   [MemberController::class, 'delete']);

    // 가입 연동 대기(교회로 회원 → 교적 매칭/신규등록/보류/반려) — 연동/신규등록은 기존 update/register 재사용
    Route::post('/getJoinRequestList', [MemberJoinRequestController::class, 'getJoinRequestList']);
    Route::post('/holdJoinRequest',    [MemberJoinRequestController::class, 'holdJoinRequest']);
    Route::post('/rejectJoinRequest',  [MemberJoinRequestController::class, 'rejectJoinRequest']);
});

// 조직 사이드바 트리 (좌측 메뉴 그룹 목록 — 전 역할 공통 노출, SETTING 권한과 무관)
Route::prefix('v4/organization')->middleware(['jwt.auth'])->group(function () {
    Route::post('/getSidebarTree', [OrganizationController::class, 'getSidebarTree']);
});

// 조직 관리 (GNB에 별도 메뉴 없음 — 교직설정 페이지 내부 "조직 기준" 탭 — SETTING 권한 재사용)
Route::prefix('v4/organization')->middleware(['jwt.auth', 'permission:SETTING'])->group(function () {
    Route::post('/getList',         [OrganizationController::class, 'getList']);
    Route::post('/getDetail',       [OrganizationController::class, 'getDetail']);
    Route::post('/getMembersByOrg', [OrganizationController::class, 'getMembersByOrg']);
    Route::post('/register',       [OrganizationController::class, 'register']);
    Route::post('/update',         [OrganizationController::class, 'update']);
    Route::post('/delete',         [OrganizationController::class, 'delete']);
    Route::post('/assignMember',   [OrganizationController::class, 'assignMember']);
    Route::post('/unassignMember', [OrganizationController::class, 'unassignMember']);
});

// 가족 관계 (GNB에 별도 메뉴 없음 — 교인 상세 화면에서 관리 — MEMBER 권한 재사용)
Route::prefix('v4/family')->middleware(['jwt.auth', 'permission:MEMBER'])->group(function () {
    Route::post('/getList', [FamilyController::class, 'getList']);
    Route::post('/register', [FamilyController::class, 'register']);
    Route::post('/update',   [FamilyController::class, 'update']);
    Route::post('/delete',   [FamilyController::class, 'delete']);
});

// 예배/모임 정의 (GNB에 별도 메뉴 없음 — 교직설정 페이지 내부 "예배 기준" 탭 — SETTING 권한 재사용)
Route::prefix('v4/worship')->middleware(['jwt.auth', 'permission:SETTING'])->group(function () {
    Route::post('/getList',   [WorshipController::class, 'getList']);
    Route::post('/getDetail', [WorshipController::class, 'getDetail']);
    Route::post('/register', [WorshipController::class, 'register']);
    Route::post('/update',   [WorshipController::class, 'update']);
    Route::post('/delete',   [WorshipController::class, 'delete']);
});

// 관리 > 교회 페이지 "교회소개" (공개 홈페이지 콘텐츠 — gh_church_intro/gh_church_pastor 재사용, WEBSITE 권한)
Route::prefix('v4/intro')->middleware(['jwt.auth', 'permission:WEBSITE'])->group(function () {
    Route::post('/getIntro',          [IntroController::class, 'getIntro']);
    Route::post('/updateIntro',       [IntroController::class, 'updateIntro']);
    Route::post('/updatePastor',      [IntroController::class, 'updatePastor']);
    Route::post('/uploadPastorPhoto', [IntroController::class, 'uploadPastorPhoto']);
    Route::post('/deletePastorPhoto', [IntroController::class, 'deletePastorPhoto']);
});

// 관리 > 교회 페이지 "소식·공지" (공개 홈페이지 콘텐츠 — 공유 게시판 gh_new_board_content 재사용, WEBSITE 권한)
Route::prefix('v4/news')->middleware(['jwt.auth', 'permission:WEBSITE'])->group(function () {
    Route::post('/getList',   [NewsController::class, 'getList']);
    Route::post('/register',  [NewsController::class, 'register']);
    Route::post('/update',    [NewsController::class, 'update']);
    Route::post('/delete',    [NewsController::class, 'delete']);
    Route::post('/togglePin', [NewsController::class, 'togglePin']);

    Route::post('/bulletin/getList',  [NewsController::class, 'bulletinGetList']);
    Route::post('/bulletin/register', [NewsController::class, 'bulletinRegister']);
    Route::post('/bulletin/update',   [NewsController::class, 'bulletinUpdate']);
    Route::post('/bulletin/delete',   [NewsController::class, 'bulletinDelete']);

    Route::post('/qna/getList',  [NewsController::class, 'qnaGetList']);
    Route::post('/qna/register', [NewsController::class, 'qnaRegister']);
    Route::post('/qna/update',   [NewsController::class, 'qnaUpdate']);
    Route::post('/qna/delete',   [NewsController::class, 'qnaDelete']);
    Route::post('/qna/answer',   [NewsController::class, 'qnaAnswer']);

    Route::post('/counseling/getList',   [NewsController::class, 'counselingGetList']);
    Route::post('/counseling/getDetail', [NewsController::class, 'counselingGetDetail']);
    Route::post('/counseling/update',    [NewsController::class, 'counselingUpdate']);
    Route::post('/counseling/delete',    [NewsController::class, 'counselingDelete']);

    Route::post('/testimony/getList', [NewsController::class, 'testimonyGetList']);
    Route::post('/testimony/update',  [NewsController::class, 'testimonyUpdate']);
    Route::post('/testimony/delete',  [NewsController::class, 'testimonyDelete']);
});

// 관리 > 교회 페이지 "미디어" (공개 홈페이지 콘텐츠 — 공유 게시판 gh_new_board_content 재사용, WEBSITE 권한)
Route::prefix('v4/media')->middleware(['jwt.auth', 'permission:WEBSITE'])->group(function () {
    Route::post('/uploadImage', [MediaController::class, 'uploadImage']);

    Route::post('/sermon/getList',          [MediaController::class, 'sermonGetList']);
    Route::post('/sermon/setFeaturedMode',  [MediaController::class, 'sermonSetFeaturedMode']);
    Route::post('/sermon/register',         [MediaController::class, 'sermonRegister']);
    Route::post('/sermon/update',           [MediaController::class, 'sermonUpdate']);
    Route::post('/sermon/delete',           [MediaController::class, 'sermonDelete']);

    Route::post('/churchVideo/getList',  [MediaController::class, 'churchVideoGetList']);
    Route::post('/churchVideo/register', [MediaController::class, 'churchVideoRegister']);
    Route::post('/churchVideo/update',   [MediaController::class, 'churchVideoUpdate']);
    Route::post('/churchVideo/delete',   [MediaController::class, 'churchVideoDelete']);

    Route::post('/gallery/getList',  [MediaController::class, 'galleryGetList']);
    Route::post('/gallery/register', [MediaController::class, 'galleryRegister']);
    Route::post('/gallery/update',   [MediaController::class, 'galleryUpdate']);
    Route::post('/gallery/delete',   [MediaController::class, 'galleryDelete']);

    Route::post('/ministry/getList',  [MediaController::class, 'ministryGetList']);
    Route::post('/ministry/register', [MediaController::class, 'ministryRegister']);
    Route::post('/ministry/update',   [MediaController::class, 'ministryUpdate']);
    Route::post('/ministry/delete',   [MediaController::class, 'ministryDelete']);
});

// 출석 기록
Route::prefix('v4/attendance')->middleware(['jwt.auth', 'permission:ATTENDANCE'])->group(function () {
    Route::post('/getListByService', [AttendanceController::class, 'getListByService']);
    Route::post('/getListByMember',  [AttendanceController::class, 'getListByMember']);
    Route::post('/recordBulk', [AttendanceController::class, 'recordBulk']);
});

// 심방 기록 (add_to_prayer=1 시 reg_prayer_records 자동 생성)
Route::prefix('v4/visit')->middleware(['jwt.auth', 'permission:VISIT'])->group(function () {
    Route::post('/getList',              [VisitController::class, 'getList']);
    Route::post('/getDetail',            [VisitController::class, 'getDetail']);
    Route::post('/getUnvisitedList',     [VisitController::class, 'getUnvisitedList']);
    Route::post('/getAbsenceTargetList', [VisitController::class, 'getAbsenceTargetList']);
    Route::post('/register',          [VisitController::class, 'register']);
    Route::post('/update',            [VisitController::class, 'update']);
    Route::post('/updateStatus',      [VisitController::class, 'updateStatus']);
    Route::post('/bulkCompleteVisit', [VisitController::class, 'bulkCompleteVisit']);
    Route::post('/delete',            [VisitController::class, 'delete']);
});

// 기도 목록
Route::prefix('v4/prayer')->middleware(['jwt.auth', 'permission:PRAYER'])->group(function () {
    Route::post('/getList',   [PrayerController::class, 'getList']);
    Route::post('/getDetail', [PrayerController::class, 'getDetail']);
    Route::post('/getStats',  [PrayerController::class, 'getStats']);
    Route::post('/register',     [PrayerController::class, 'register']);
    Route::post('/update',       [PrayerController::class, 'update']);
    Route::post('/delete',       [PrayerController::class, 'delete']);
    Route::post('/updateStatus', [PrayerController::class, 'updateStatus']);
});

// 봉사 관리 (팀 + 참여자 매핑)
Route::prefix('v4/volunteer')->middleware(['jwt.auth', 'permission:VOLUNTEER'])->group(function () {
    Route::post('/getList',             [VolunteerController::class, 'getList']);
    Route::post('/getDetail',           [VolunteerController::class, 'getDetail']);
    Route::post('/getVolunteersByTeam', [VolunteerController::class, 'getVolunteersByTeam']);
    Route::post('/getTeamsByMember',    [VolunteerController::class, 'getTeamsByMember']);
    Route::post('/getHistory',          [VolunteerController::class, 'getHistory']);
    Route::post('/getTeamHistory',      [VolunteerController::class, 'getTeamHistory']);
    Route::post('/attendance/getSheet',       [VolunteerController::class, 'getAttendanceSheet']);
    Route::post('/attendance/getMemberStats', [VolunteerController::class, 'getMemberAttendanceStats']);
    Route::post('/register',          [VolunteerController::class, 'register']);
    Route::post('/update',            [VolunteerController::class, 'update']);
    Route::post('/delete',            [VolunteerController::class, 'delete']);
    Route::post('/assignVolunteer',   [VolunteerController::class, 'assignVolunteer']);
    Route::post('/unassignVolunteer', [VolunteerController::class, 'unassignVolunteer']);
    Route::post('/attendance/save',   [VolunteerController::class, 'saveAttendance']);
});

// 교육 관리 (과정 템플릿 + 기수 + 수강 기록)
Route::prefix('v4/education')->middleware(['jwt.auth', 'permission:EDUCATION'])->group(function () {
    Route::post('/getCourseList',   [EducationController::class, 'getCourseList']);
    Route::post('/getCourseDetail', [EducationController::class, 'getCourseDetail']);
    Route::post('/session/getList',   [EducationController::class, 'getSessionList']);
    Route::post('/session/getDetail', [EducationController::class, 'getSessionDetail']);
    Route::post('/getEnrollmentsBySession',  [EducationController::class, 'getEnrollmentsBySession']);
    Route::post('/getSessionsByMember',      [EducationController::class, 'getSessionsByMember']);
    Route::post('/attendance/getSheet',       [EducationController::class, 'getAttendanceSheet']);
    Route::post('/attendance/getMemberStats', [EducationController::class, 'getMemberAttendanceStats']);
    Route::post('/registerCourse', [EducationController::class, 'registerCourse']);
    Route::post('/updateCourse',   [EducationController::class, 'updateCourse']);
    Route::post('/deleteCourse',   [EducationController::class, 'deleteCourse']);
    Route::post('/session/register', [EducationController::class, 'registerSession']);
    Route::post('/session/update',   [EducationController::class, 'updateSession']);
    Route::post('/session/delete',   [EducationController::class, 'deleteSession']);
    Route::post('/enroll',         [EducationController::class, 'enroll']);
    Route::post('/updateProgress', [EducationController::class, 'updateProgress']);
    Route::post('/withdraw',       [EducationController::class, 'withdraw']);
    Route::post('/attendance/saveRound', [EducationController::class, 'saveRoundAttendance']);
});

// 헌금 관리
Route::prefix('v4/offering')->middleware(['jwt.auth', 'permission:OFFERING'])->group(function () {
    Route::post('/getList',         [OfferingController::class, 'getList']);
    Route::post('/getDetail',       [OfferingController::class, 'getDetail']);
    Route::post('/getListByMember', [OfferingController::class, 'getListByMember']);
    Route::post('/register', [OfferingController::class, 'register']);
    Route::post('/update',   [OfferingController::class, 'update']);
    Route::post('/delete',   [OfferingController::class, 'delete']);
});

// 지출 관리
Route::prefix('v4/expense')->middleware(['jwt.auth', 'permission:EXPENSE'])->group(function () {
    Route::post('/getList',           [ExpenseController::class, 'getList']);
    Route::post('/getDetail',         [ExpenseController::class, 'getDetail']);
    Route::post('/register',          [ExpenseController::class, 'register']);
    Route::post('/update',            [ExpenseController::class, 'update']);
    Route::post('/delete',            [ExpenseController::class, 'delete']);
    Route::post('/getCategoryStats',  [ExpenseController::class, 'getCategoryStats']);
    Route::post('/getReceiptStats',   [ExpenseController::class, 'getReceiptStats']);
    Route::post('/uploadReceipt',     [ExpenseController::class, 'uploadReceipt']);
});

// 예산 관리
Route::prefix('v4/budget')->middleware(['jwt.auth', 'permission:BUDGET'])->group(function () {
    Route::post('/getStatus', [BudgetController::class, 'getStatus']);
    Route::post('/register',  [BudgetController::class, 'register']);
    Route::post('/update',    [BudgetController::class, 'update']);
    Route::post('/delete',    [BudgetController::class, 'delete']);
});

// 재정 보고서
Route::prefix('v4/financeReport')->middleware(['jwt.auth', 'permission:FINANCE_REPORT'])->group(function () {
    Route::post('/getMonthly', [FinanceReportController::class, 'getMonthly']);
    Route::post('/getAnnual',  [FinanceReportController::class, 'getAnnual']);
});

// 재정 설정 (정책 값만 — 계정과목/초기이월금/결산·마감 실행/영수증 발행은 미구현)
Route::prefix('v4/financeSetting')->middleware(['jwt.auth', 'permission:FINANCE_SETTING'])->group(function () {
    Route::post('/getSettings',  [FinanceSettingController::class, 'getSettings']);
    Route::post('/saveSettings', [FinanceSettingController::class, 'saveSettings']);
    Route::post('/uploadSeal',   [FinanceSettingController::class, 'uploadSeal']);
});

// 재정 계좌관리 (설정 > 계좌관리) — 관리 자체는 FINANCE_SETTING 권한자만
Route::prefix('v4/financeAccount')->middleware(['jwt.auth', 'permission:FINANCE_SETTING'])->group(function () {
    Route::post('/getList',  [FinanceAccountController::class, 'getList']);
    Route::post('/register', [FinanceAccountController::class, 'register']);
    Route::post('/update',   [FinanceAccountController::class, 'update']);
    Route::post('/delete',   [FinanceAccountController::class, 'delete']);
});
// 헌금·지출 입력 화면의 원장 드롭다운 — OFFERING/EXPENSE 권한자도 조회 가능해야 하므로 jwt.auth만 요구
Route::prefix('v4/financeAccount')->middleware(['jwt.auth'])->group(function () {
    Route::post('/getActiveList', [FinanceAccountController::class, 'getActiveList']);
});

// 메시지 발송 (실제 SMS/Push/Email 게이트웨이 연동 미구현)
Route::prefix('v4/message')->middleware(['jwt.auth', 'permission:MESSAGE'])->group(function () {
    Route::post('/getList',   [MessageController::class, 'getList']);
    Route::post('/getDetail', [MessageController::class, 'getDetail']);
    Route::post('/send',   [MessageController::class, 'send']);
    Route::post('/delete', [MessageController::class, 'delete']);

    // 문자 템플릿 (등록/수정/복제/사용여부, 발송화면 드롭다운은 getActiveList)
    Route::post('/template/getList',       [MessageTemplateController::class, 'getList']);
    Route::post('/template/getActiveList', [MessageTemplateController::class, 'getActiveList']);
    Route::post('/template/getDetail',     [MessageTemplateController::class, 'getDetail']);
    Route::post('/template/register',      [MessageTemplateController::class, 'register']);
    Route::post('/template/update',        [MessageTemplateController::class, 'update']);
    Route::post('/template/duplicate',     [MessageTemplateController::class, 'duplicate']);
    Route::post('/template/toggleActive',  [MessageTemplateController::class, 'toggleActive']);

    // 문자 발송 설정 (업체/API키/발신번호/발송정책/수신거부 — 설정값만, 실제 게이트웨이 연동 미구현)
    Route::post('/settings/getSettings',  [MessageSettingController::class, 'getSettings']);
    Route::post('/settings/saveSettings', [MessageSettingController::class, 'saveSettings']);

    // 푸시 알림 (실제 FCM 발송 인프라 없음 — 계정연동/기기토큰/수신동의 기반 도달가능성 계산 후 기록만 저장)
    Route::post('/push/getFilterOptions', [PushNotificationController::class, 'getFilterOptions']);
    Route::post('/push/getTargetSummary', [PushNotificationController::class, 'getTargetSummary']);
    Route::post('/push/send',             [PushNotificationController::class, 'send']);
    Route::post('/push/getDetail',        [PushNotificationController::class, 'getDetail']);
    Route::post('/push/resend',           [PushNotificationController::class, 'resend']);
});

// 코드 관리 (GNB에 별도 메뉴 없음 — 교직설정 페이지 내부 각 기준 탭 — SETTING 권한 재사용)
Route::prefix('v4/code')->middleware(['jwt.auth', 'permission:SETTING'])->group(function () {
    Route::post('/getCodes', [CodeController::class, 'getCodes']);
    Route::post('/registerCode', [CodeController::class, 'registerCode']);
    Route::post('/updateCode',   [CodeController::class, 'updateCode']);
    Route::post('/deleteCode',   [CodeController::class, 'deleteCode']);
});

// 교적 설정 (KPI 기준 / 자동화 규칙)
Route::prefix('v4/setting')->middleware(['jwt.auth', 'permission:SETTING'])->group(function () {
    Route::post('/getSettings', [SettingController::class, 'getSettings']);
    Route::post('/saveSettings', [SettingController::class, 'saveSettings']);
});

// 보고서/통계 (조회 전용)
Route::prefix('v4/report')->middleware(['jwt.auth', 'permission:REPORT'])->group(function () {
    Route::post('/getMemberStats',             [ReportController::class, 'getMemberStats']);
    Route::post('/getAttendanceStats',         [ReportController::class, 'getAttendanceStats']);
    Route::post('/getAttendanceStatsByOrg',    [ReportController::class, 'getAttendanceStatsByOrg']);
    Route::post('/getStatsByService',          [ReportController::class, 'getStatsByService']);
    Route::post('/getMemberRateDistribution',  [ReportController::class, 'getMemberRateDistribution']);
    Route::post('/getOfferingStats',   [ReportController::class, 'getOfferingStats']);
    Route::post('/getVisitStats',      [ReportController::class, 'getVisitStats']);
    Route::post('/getDashboard',       [ReportController::class, 'getDashboard']);
    Route::post('/getFinanceDashboard', [ReportController::class, 'getFinanceDashboard']);
    Route::post('/getFinanceStats',     [ReportController::class, 'getFinanceStats']);
});
