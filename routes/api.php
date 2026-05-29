<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EducationController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OfferingController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\VolunteerController;
use App\Http\Controllers\Api\WorshipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 인증 (로그인은 jwt.auth 제외)
Route::prefix('v4/auth')->group(function () {
    Route::post('/signIn',      [AuthController::class, 'signIn']);
    Route::post('/adminSignIn', [AuthController::class, 'adminSignIn']);
});

Route::prefix('v4/auth')->middleware('jwt.auth')->group(function () {
    Route::post('/signOut', [AuthController::class, 'signOut']);
});

// 교인 CRUD
Route::prefix('v4/member')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',   [MemberController::class, 'getList']);
    Route::post('/getDetail', [MemberController::class, 'getDetail']);
    Route::post('/register',  [MemberController::class, 'register']);
    Route::post('/update',    [MemberController::class, 'update']);
    Route::post('/delete',    [MemberController::class, 'delete']);
});

// 조직 관리
Route::prefix('v4/organization')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',          [OrganizationController::class, 'getList']);
    Route::post('/getDetail',        [OrganizationController::class, 'getDetail']);
    Route::post('/register',         [OrganizationController::class, 'register']);
    Route::post('/update',           [OrganizationController::class, 'update']);
    Route::post('/delete',           [OrganizationController::class, 'delete']);
    Route::post('/assignMember',     [OrganizationController::class, 'assignMember']);
    Route::post('/unassignMember',   [OrganizationController::class, 'unassignMember']);
    Route::post('/getMembersByOrg',  [OrganizationController::class, 'getMembersByOrg']);
});

// 가족 관계 (양방향 자동 동기화)
Route::prefix('v4/family')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',  [FamilyController::class, 'getList']);
    Route::post('/register', [FamilyController::class, 'register']);
    Route::post('/update',   [FamilyController::class, 'update']);
    Route::post('/delete',   [FamilyController::class, 'delete']);
});

// 예배/모임 정의 (reg_services)
Route::prefix('v4/worship')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',   [WorshipController::class, 'getList']);
    Route::post('/getDetail', [WorshipController::class, 'getDetail']);
    Route::post('/register',  [WorshipController::class, 'register']);
    Route::post('/update',    [WorshipController::class, 'update']);
    Route::post('/delete',    [WorshipController::class, 'delete']);
});

// 출석 기록
Route::prefix('v4/attendance')->middleware('jwt.auth')->group(function () {
    Route::post('/recordBulk',       [AttendanceController::class, 'recordBulk']);
    Route::post('/getListByService', [AttendanceController::class, 'getListByService']);
    Route::post('/getListByMember',  [AttendanceController::class, 'getListByMember']);
});

// 심방 기록 (add_to_prayer=1 시 reg_prayer_records 자동 생성)
Route::prefix('v4/visit')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',   [VisitController::class, 'getList']);
    Route::post('/getDetail', [VisitController::class, 'getDetail']);
    Route::post('/register',  [VisitController::class, 'register']);
    Route::post('/update',    [VisitController::class, 'update']);
    Route::post('/delete',    [VisitController::class, 'delete']);
});

// 봉사 관리 (팀 + 참여자 매핑)
Route::prefix('v4/volunteer')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',             [VolunteerController::class, 'getList']);
    Route::post('/getDetail',           [VolunteerController::class, 'getDetail']);
    Route::post('/register',            [VolunteerController::class, 'register']);
    Route::post('/update',              [VolunteerController::class, 'update']);
    Route::post('/delete',              [VolunteerController::class, 'delete']);
    Route::post('/assignVolunteer',     [VolunteerController::class, 'assignVolunteer']);
    Route::post('/unassignVolunteer',   [VolunteerController::class, 'unassignVolunteer']);
    Route::post('/getVolunteersByTeam', [VolunteerController::class, 'getVolunteersByTeam']);
    Route::post('/getTeamsByMember',    [VolunteerController::class, 'getTeamsByMember']);
});

// 교육 관리 (과정 + 수강 기록)
Route::prefix('v4/education')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',                [EducationController::class, 'getList']);
    Route::post('/getDetail',              [EducationController::class, 'getDetail']);
    Route::post('/register',               [EducationController::class, 'register']);
    Route::post('/update',                 [EducationController::class, 'update']);
    Route::post('/delete',                 [EducationController::class, 'delete']);
    Route::post('/enroll',                 [EducationController::class, 'enroll']);
    Route::post('/updateProgress',         [EducationController::class, 'updateProgress']);
    Route::post('/withdraw',               [EducationController::class, 'withdraw']);
    Route::post('/getEnrollmentsByCourse', [EducationController::class, 'getEnrollmentsByCourse']);
    Route::post('/getCoursesByMember',     [EducationController::class, 'getCoursesByMember']);
});

// 헌금 관리
Route::prefix('v4/offering')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',         [OfferingController::class, 'getList']);
    Route::post('/getDetail',       [OfferingController::class, 'getDetail']);
    Route::post('/register',        [OfferingController::class, 'register']);
    Route::post('/update',          [OfferingController::class, 'update']);
    Route::post('/delete',          [OfferingController::class, 'delete']);
    Route::post('/getListByMember', [OfferingController::class, 'getListByMember']);
});

// 메시지 발송 (실제 SMS/Push/Email 게이트웨이 연동 미구현)
Route::prefix('v4/message')->middleware('jwt.auth')->group(function () {
    Route::post('/getList',   [MessageController::class, 'getList']);
    Route::post('/getDetail', [MessageController::class, 'getDetail']);
    Route::post('/send',      [MessageController::class, 'send']);
    Route::post('/delete',    [MessageController::class, 'delete']);
});

// 보고서/통계 (조회 전용)
Route::prefix('v4/report')->middleware('jwt.auth')->group(function () {
    Route::post('/getMemberStats',     [ReportController::class, 'getMemberStats']);
    Route::post('/getAttendanceStats', [ReportController::class, 'getAttendanceStats']);
    Route::post('/getOfferingStats',   [ReportController::class, 'getOfferingStats']);
    Route::post('/getVisitStats',      [ReportController::class, 'getVisitStats']);
    Route::post('/getDashboard',       [ReportController::class, 'getDashboard']);
});
