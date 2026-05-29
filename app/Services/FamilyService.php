<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\FamilyRepository;
use App\Repositories\MemberRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FamilyService
{
    protected FamilyRepository $familyRepository;
    protected MemberRepository $memberRepository;

    /**
     * 양방향 자동 변환표
     *  배우자 ↔ 배우자
     *  부모   ↔ 자녀
     *  자녀   ↔ 부모
     *  형제자매 ↔ 형제자매
     *  기타   ↔ 기타
     */
    private const REVERSE_RELATION = [
        '배우자'   => '배우자',
        '부모'     => '자녀',
        '자녀'     => '부모',
        '형제자매' => '형제자매',
        '기타'     => '기타',
    ];

    public function __construct(
        FamilyRepository $familyRepository,
        MemberRepository $memberRepository
    ) {
        $this->familyRepository = $familyRepository;
        $this->memberRepository = $memberRepository;
    }

    public function getFamiliesByMember(int $authMemberId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $member = $this->memberRepository->getMemberById($memberId);
            if ($member === null || (int) $member->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $list = $this->familyRepository->getFamiliesByMember($memberId);

            return ApiResponse::success([
                'member_id' => $memberId,
                'list'      => $list,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FamilyService] getFamiliesByMember error: " . $e->getMessage(), "family");
            return ApiResponse::fail('INTERNAL_ERROR', '가족 관계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function addFamily(int $authMemberId, int $memberId, int $relatedMemberId, string $relationType, ?string $familyNote): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            if ($memberId === $relatedMemberId) {
                return ApiResponse::fail('VALIDATION_FAILED', '자기 자신은 가족으로 등록할 수 없습니다.', 400);
            }

            $reverse = self::REVERSE_RELATION[$relationType] ?? null;
            if ($reverse === null) {
                return ApiResponse::fail('VALIDATION_FAILED', '허용되지 않는 가족 관계입니다.', 400);
            }

            [$member, $related] = $this->fetchBothMembers($memberId, $relatedMemberId, $churchId);
            if ($member === null || $related === null) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            DB::transaction(function () use ($memberId, $relatedMemberId, $relationType, $reverse, $familyNote) {
                $this->upsertPair($memberId, $relatedMemberId, $relationType, $familyNote);
                $this->upsertPair($relatedMemberId, $memberId, $reverse, $familyNote);
            });

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::FAMILY,
                summary:     "가족 등록(양방향): {$member->name} - {$relationType} - {$related->name}",
                targetId:    (string) $memberId,
                targetLabel: $member->name,
                created:     ['related_member_id' => $relatedMemberId, 'relation_type' => $relationType, 'reverse' => $reverse],
            );

            return ApiResponse::success([
                'member_id'         => $memberId,
                'related_member_id' => $relatedMemberId,
                'relation_type'     => $relationType,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FamilyService] addFamily error: " . $e->getMessage(), "family");
            return ApiResponse::fail('INTERNAL_ERROR', '가족 관계 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateFamily(int $authMemberId, int $memberId, int $relatedMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            [$member, $related] = $this->fetchBothMembers($memberId, $relatedMemberId, $churchId);
            if ($member === null || $related === null) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $pair = $this->familyRepository->getPair($memberId, $relatedMemberId);
            if ($pair === null) {
                return ApiResponse::fail('NOT_FOUND', '가족 관계를 찾을 수 없습니다.', 404);
            }

            $relationType = $input['relation_type'] ?? $pair->relation_type;
            $reverse      = self::REVERSE_RELATION[$relationType] ?? null;
            if ($reverse === null) {
                return ApiResponse::fail('VALIDATION_FAILED', '허용되지 않는 가족 관계입니다.', 400);
            }
            $familyNote = array_key_exists('family_note', $input) ? $input['family_note'] : $pair->family_note;

            DB::transaction(function () use ($memberId, $relatedMemberId, $relationType, $reverse, $familyNote) {
                $this->upsertPair($memberId, $relatedMemberId, $relationType, $familyNote);
                $this->upsertPair($relatedMemberId, $memberId, $reverse, $familyNote);
            });

            AuditLogHelper::logUpdate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::FAMILY,
                summary:     "가족 수정: {$member->name} - {$relationType} - {$related->name}",
                targetId:    (string) $memberId,
                targetLabel: $member->name,
                before:      ['relation_type' => $pair->relation_type, 'family_note' => $pair->family_note],
                after:       ['relation_type' => $relationType, 'family_note' => $familyNote],
            );

            return ApiResponse::success([
                'member_id'         => $memberId,
                'related_member_id' => $relatedMemberId,
                'relation_type'     => $relationType,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FamilyService] updateFamily error: " . $e->getMessage(), "family");
            return ApiResponse::fail('INTERNAL_ERROR', '가족 관계 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function removeFamily(int $authMemberId, int $memberId, int $relatedMemberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            [$member, $related] = $this->fetchBothMembers($memberId, $relatedMemberId, $churchId);
            if ($member === null || $related === null) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $affected = DB::transaction(function () use ($memberId, $relatedMemberId) {
                $a = $this->familyRepository->deletePair($memberId, $relatedMemberId);
                $b = $this->familyRepository->deletePair($relatedMemberId, $memberId);
                return $a + $b;
            });

            if ($affected === 0) {
                return ApiResponse::fail('NOT_FOUND', '가족 관계를 찾을 수 없습니다.', 404);
            }

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::FAMILY,
                summary:     "가족 삭제(양방향): {$member->name} ↛ {$related->name}",
                targetId:    (string) $memberId,
                targetLabel: $member->name,
                deleted:     ['related_member_id' => $relatedMemberId],
            );

            return ApiResponse::success([
                'member_id'         => $memberId,
                'related_member_id' => $relatedMemberId,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[FamilyService] removeFamily error: " . $e->getMessage(), "family");
            return ApiResponse::fail('INTERNAL_ERROR', '가족 관계 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 두 교인을 같은 교회 소속으로 검증해서 반환. 한 쪽이라도 없으면 [null, null].
     *
     * @return array{0: \stdClass|null, 1: \stdClass|null}
     */
    private function fetchBothMembers(int $memberId, int $relatedMemberId, int $churchId): array
    {
        $member  = $this->memberRepository->getMemberById($memberId);
        $related = $this->memberRepository->getMemberById($relatedMemberId);

        $memberOk  = $member  !== null && (int) $member->church_id  === $churchId;
        $relatedOk = $related !== null && (int) $related->church_id === $churchId;

        return [$memberOk ? $member : null, $relatedOk ? $related : null];
    }

    /**
     * 존재 시 update, 없으면 insert (양방향 동기화 안전 처리)
     */
    private function upsertPair(int $memberId, int $relatedMemberId, string $relationType, ?string $familyNote): void
    {
        $existing = $this->familyRepository->getPair($memberId, $relatedMemberId);

        if ($existing) {
            $this->familyRepository->updatePair($memberId, $relatedMemberId, [
                'relation_type' => $relationType,
                'family_note'   => $familyNote,
            ]);
        } else {
            $this->familyRepository->insertPair([
                'member_id'         => $memberId,
                'related_member_id' => $relatedMemberId,
                'relation_type'     => $relationType,
                'family_note'       => $familyNote,
            ]);
        }
    }
}
