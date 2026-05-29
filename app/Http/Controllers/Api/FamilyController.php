<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Helpers\JwtHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FamilyRequest;
use App\Services\FamilyService;
use Illuminate\Http\JsonResponse;

class FamilyController extends Controller
{
    protected FamilyService $familyService;

    public function __construct(FamilyService $familyService)
    {
        $this->familyService = $familyService;
    }

    public function getList(FamilyRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated = $request->validated();
        return $this->familyService->getFamiliesByMember($authMemberId, (int) $validated['member_id']);
    }

    public function register(FamilyRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated = $request->validated();
        return $this->familyService->addFamily(
            $authMemberId,
            (int) $validated['member_id'],
            (int) $validated['related_member_id'],
            $validated['relation_type'],
            $validated['family_note'] ?? null
        );
    }

    public function update(FamilyRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated = $request->validated();
        $memberId        = (int) $validated['member_id'];
        $relatedMemberId = (int) $validated['related_member_id'];
        unset($validated['member_id'], $validated['related_member_id']);

        return $this->familyService->updateFamily($authMemberId, $memberId, $relatedMemberId, $validated);
    }

    public function delete(FamilyRequest $request): JsonResponse
    {
        $authMemberId = JwtHelper::getAdminNoFromRequest($request);
        if ($authMemberId === null) {
            return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
        }

        $validated = $request->validated();
        return $this->familyService->removeFamily(
            $authMemberId,
            (int) $validated['member_id'],
            (int) $validated['related_member_id']
        );
    }
}
