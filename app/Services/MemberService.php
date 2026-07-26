<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberService
{
    protected MemberRepository $memberRepository;

    /**
     * reg_members 직접 입력 가능 컬럼 화이트리스트
     */
    private const MEMBER_FILLABLE = [
        'email', 'name', 'nickname', 'phone', 'gender',
        'birth_date', 'birth_type', 'address_zip', 'address_main', 'address_detail',
        'profile_image', 'status', 'memo', 'churchero_user_id',
    ];

    /**
     * reg_member_profiles 직접 입력 가능 컬럼 화이트리스트
     */
    private const PROFILE_FILLABLE = [
        'member_type', 'member_type_source', 'position', 'baptism_grade',
        'attendance_grade', 'ordained_at', 'ordained_church', 'baptism_at',
        'baptism_church', 'registered_at', 'welcomed_at', 'previous_church',
        'leader_member_id', 'marriage_status', 'is_household_head',
        'household_relation', 'workplace',
        'custom_field_1', 'custom_field_2', 'custom_field_3',
    ];

    public function __construct(MemberRepository $memberRepository)
    {
        $this->memberRepository = $memberRepository;
    }

    public function getMemberList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->memberRepository->getMemberList($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] getMemberList error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getMemberDetail(int $authMemberId, int $memberId): JsonResponse
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

            unset($member->password);
            $profile = $this->memberRepository->getMemberProfileByMemberId($memberId);

            return ApiResponse::success([
                'member'  => $member,
                'profile' => $profile,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] getMemberDetail error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function checkEmail(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $isDuplicate = $this->memberRepository->existsByEmailInChurch($churchId, $input['email']);

            return ApiResponse::success([
                'available' => !$isDuplicate,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] checkEmail error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '이메일 확인 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerMember(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            if ($this->memberRepository->existsByEmailInChurch($churchId, $input['email'])) {
                return ApiResponse::fail('DUPLICATE_DATA', '이미 사용 중인 이메일입니다.', 400);
            }

            $memberData = $this->pickFillable($input, self::MEMBER_FILLABLE);
            $memberData['church_id'] = $churchId;
            $memberData['password']  = Hash::make($input['password']);
            $memberData['member_no'] = $this->generateMemberNo();

            $profileData = $this->pickFillable($input, self::PROFILE_FILLABLE);

            $newMemberId = DB::transaction(function () use ($memberData, $profileData) {
                $id = $this->memberRepository->insertMember($memberData);
                $profileData['member_id'] = $id;
                $this->memberRepository->insertMemberProfile($profileData);
                return $id;
            });

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER,
                summary:     "교인 등록: {$memberData['name']} ({$memberData['member_no']})",
                targetId:    (string) $newMemberId,
                targetLabel: $memberData['name'],
                created:     ['member_no' => $memberData['member_no'], 'email' => $memberData['email'], 'name' => $memberData['name']],
            );

            return ApiResponse::success([
                'member_id' => $newMemberId,
                'member_no' => $memberData['member_no'],
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] registerMember error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '교인 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateMember(int $authMemberId, int $memberId, array $input): JsonResponse
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

            if (!empty($input['email']) && $input['email'] !== $member->email) {
                if ($this->memberRepository->existsByEmailInChurch($churchId, $input['email'], $memberId)) {
                    return ApiResponse::fail('DUPLICATE_DATA', '이미 사용 중인 이메일입니다.', 400);
                }
            }

            $memberData = $this->pickFillable($input, self::MEMBER_FILLABLE);
            if (!empty($input['password'])) {
                $memberData['password'] = Hash::make($input['password']);
            }

            $profileData = $this->pickFillable($input, self::PROFILE_FILLABLE);

            DB::transaction(function () use ($memberId, $memberData, $profileData) {
                if (!empty($memberData)) {
                    $this->memberRepository->updateMember($memberId, $memberData);
                }
                if (!empty($profileData)) {
                    $existing = $this->memberRepository->getMemberProfileByMemberId($memberId);
                    if ($existing) {
                        $this->memberRepository->updateMemberProfile($memberId, $profileData);
                    } else {
                        $profileData['member_id'] = $memberId;
                        $this->memberRepository->insertMemberProfile($profileData);
                    }
                }
            });

            $beforeArr = (array) $member;
            $afterArr  = array_merge($beforeArr, $memberData);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::MEMBER,
                summary:       "교인 수정: {$member->name} ({$member->member_no})",
                targetId:      (string) $memberId,
                targetLabel:   $member->name,
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys($memberData),
            );

            return ApiResponse::success(['member_id' => $memberId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] updateMember error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '교인 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteMember(int $authMemberId, int $memberId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            if ($memberId === $authMemberId) {
                return ApiResponse::fail('INSUFFICIENT_PERMISSION', '본인 계정은 삭제할 수 없습니다.', 403);
            }

            $member = $this->memberRepository->getMemberById($memberId);
            if ($member === null || (int) $member->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $this->memberRepository->softDeleteMember($memberId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER,
                summary:     "교인 삭제(soft): {$member->name} ({$member->member_no})",
                targetId:    (string) $memberId,
                targetLabel: $member->name,
                deleted:     ['member_no' => $member->member_no, 'email' => $member->email, 'name' => $member->name],
            );

            return ApiResponse::success(['member_id' => $memberId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] deleteMember error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '교인 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 입력 배열에서 화이트리스트에 포함된 키만 추출
     */
    private function pickFillable(array $input, array $allowed): array
    {
        return array_intersect_key($input, array_flip($allowed));
    }

    /**
     * member_no 발급: M-YYYYMMDD-NNNN (오늘자 prefix 기준 카운트+1)
     */
    private function generateMemberNo(): string
    {
        $prefix = 'M-' . date('Ymd');
        $count  = $this->memberRepository->countMemberNoByPrefix($prefix);
        return sprintf('%s-%04d', $prefix, $count + 1);
    }
}
