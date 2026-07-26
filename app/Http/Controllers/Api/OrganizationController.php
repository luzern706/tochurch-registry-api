<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationRequest;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    protected OrganizationService $organizationService;

    public function __construct(OrganizationService $organizationService)
    {
        $this->organizationService = $organizationService;
    }

    public function getList(OrganizationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->organizationService->getOrganizationList($authMemberId, $request->validated());
    }

    public function getSidebarTree(Request $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->organizationService->getSidebarTree();
    }

    public function getDetail(OrganizationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated = $request->validated();
        return $this->organizationService->getOrganizationDetail(
            $authMemberId,
            (int) $validated['organization_id']
        );
    }

    public function register(OrganizationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        return $this->organizationService->registerOrganization($authMemberId, $request->validated());
    }

    public function update(OrganizationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated       = $request->validated();
        $organizationId  = (int) $validated['organization_id'];
        unset($validated['organization_id']);

        return $this->organizationService->updateOrganization($authMemberId, $organizationId, $validated);
    }

    public function delete(OrganizationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated = $request->validated();
        return $this->organizationService->deleteOrganization(
            $authMemberId,
            (int) $validated['organization_id']
        );
    }

    public function assignMember(OrganizationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated = $request->validated();
        return $this->organizationService->assignMember(
            $authMemberId,
            (int) $validated['member_id'],
            (int) $validated['organization_id'],
            $validated
        );
    }

    public function unassignMember(OrganizationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated = $request->validated();
        return $this->organizationService->unassignMember(
            $authMemberId,
            (int) $validated['member_id'],
            (int) $validated['organization_id']
        );
    }

    public function getMembersByOrg(OrganizationRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated      = $request->validated();
        $organizationId = (int) $validated['organization_id'];
        unset($validated['organization_id']);

        return $this->organizationService->getMembersByOrganization($authMemberId, $organizationId, $validated);
    }
}
