<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\VolunteerRequest;
use App\Services\VolunteerService;
use Illuminate\Http\JsonResponse;

class VolunteerController extends Controller
{
    protected VolunteerService $volunteerService;

    public function __construct(VolunteerService $volunteerService)
    {
        $this->volunteerService = $volunteerService;
    }

    // ─────────────── 봉사 팀 ───────────────

    public function getList(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->volunteerService->getTeamList($authMemberId, $request->validated());
    }

    public function getDetail(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->volunteerService->getTeamDetail($authMemberId, (int) $validated['team_id']);
    }

    public function register(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        return $this->volunteerService->registerTeam($authMemberId, $request->validated());
    }

    public function update(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        $teamId    = (int) $validated['team_id'];
        unset($validated['team_id']);
        return $this->volunteerService->updateTeam($authMemberId, $teamId, $validated);
    }

    public function delete(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->volunteerService->deleteTeam($authMemberId, (int) $validated['team_id']);
    }

    // ─────────────── 참여자 매핑 ───────────────

    public function assignVolunteer(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->volunteerService->assignVolunteer(
            $authMemberId,
            (int) $validated['team_id'],
            (int) $validated['member_id'],
            $validated
        );
    }

    public function unassignVolunteer(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->volunteerService->unassignVolunteer(
            $authMemberId,
            (int) $validated['team_id'],
            (int) $validated['member_id']
        );
    }

    public function getVolunteersByTeam(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        $teamId    = (int) $validated['team_id'];
        unset($validated['team_id']);
        return $this->volunteerService->getVolunteersByTeam($authMemberId, $teamId, $validated);
    }

    public function getTeamsByMember(VolunteerRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }
        $validated = $request->validated();
        return $this->volunteerService->getTeamsByMember($authMemberId, (int) $validated['member_id']);
    }
}
