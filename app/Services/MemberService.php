<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Constants\FamilyRelation;
use App\Helpers\ApiResponse;
use App\Helpers\AppLinkStatusHelper;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Helpers\S3FileHelper;
use App\Repositories\FamilyRepository;
use App\Repositories\MemberRepository;
use App\Repositories\OrganizationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberService
{
    protected MemberRepository $memberRepository;
    protected OrganizationRepository $organizationRepository;
    protected FamilyRepository $familyRepository;

    /** 앱 초대 링크 유효기간(일) */
    private const INVITE_TTL_DAYS = 30;

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
        'baptism_church', 'registered_at', 'registration_type', 'welcomed_at', 'previous_church',
        'leader_member_id', 'marriage_status', 'is_household_head',
        'household_relation', 'workplace', 'occupation',
        'custom_field_1', 'custom_field_2', 'custom_field_3',
    ];

    public function __construct(
        MemberRepository $memberRepository,
        OrganizationRepository $organizationRepository,
        FamilyRepository $familyRepository
    ) {
        $this->memberRepository       = $memberRepository;
        $this->organizationRepository = $organizationRepository;
        $this->familyRepository       = $familyRepository;
    }

    public function getMemberList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->memberRepository->getMemberList($churchId, $filters);

            // 앱 연결 상태 (공용 판정 — 등록 완료·상세 화면과 동일 규칙)
            $ctx = $this->memberRepository->getAppLinkContext($churchId, $data['list']);
            foreach ($data['list'] as $row) {
                $status = AppLinkStatusHelper::resolve($row, $ctx[$row->id]['account'], $ctx[$row->id]['candidate_count'], $ctx[$row->id]['invite_joined']);
                $row->app_link_status = $status;
                $row->app_link_label  = AppLinkStatusHelper::label($status);
            }

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
                'member'        => $member,
                'profile'       => $profile,
                'app_link'      => $this->resolveAppLink($churchId, $member),
                'latest_invite' => $this->memberRepository->getLatestInvite($memberId),
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] getMemberDetail error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 동일인 확인 — 이름·휴대폰이 겹치는 기존 교적 후보 (자동 차단 없음, 판단은 관리자)
     */
    public function checkDuplicate(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $rows = $this->memberRepository->findDuplicateCandidates(
                $churchId,
                trim($input['name']),
                AppLinkStatusHelper::digits($input['phone'] ?? null),
                $input['birth_date'] ?? null,
            );

            $list = array_map(fn ($r) => [
                'id'          => (int) $r->id,
                'name'        => $r->name,
                'gender'      => $r->gender,
                'birth_date'  => $r->birth_date,
                'phone'       => $this->maskPhone($r->phone),
                'org_label'   => $r->org_name
                    ? ($r->parent_org_name ? "{$r->parent_org_name} > {$r->org_name}" : $r->org_name)
                    : null,
                'name_match'  => (bool) $r->name_match,
                'phone_match' => (bool) $r->phone_match,
                'birth_match' => (bool) $r->birth_match,
            ], $rows);

            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] checkDuplicate error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '동일인 확인 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerMember(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            if (!empty($input['email']) && $this->memberRepository->existsByEmailInChurch($churchId, $input['email'])) {
                return ApiResponse::fail('DUPLICATE_DATA', '이미 다른 교인이 사용 중인 이메일입니다.', 400);
            }

            // 부서·교구 (선택)
            $organizationId = !empty($input['organization_id']) ? (int) $input['organization_id'] : null;
            if ($organizationId !== null) {
                $org = $this->organizationRepository->getOrganizationById($organizationId);
                if ($org === null || (int) $org->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '선택한 부서·교구를 찾을 수 없습니다.', 404);
                }
            }

            // 가족 — 세대주 + 관계 (선택)
            $householdRelation = $input['household_relation'] ?? null;
            $isSelfHead        = $householdRelation === FamilyRelation::HOUSEHOLD_SELF;
            $headId            = (!$isSelfHead && !empty($input['household_head_member_id']))
                ? (int) $input['household_head_member_id'] : null;
            if ($headId !== null) {
                $head = $this->memberRepository->getMemberById($headId);
                if ($head === null || (int) $head->church_id !== $churchId) {
                    return ApiResponse::fail('NOT_FOUND', '선택한 세대주를 찾을 수 없습니다.', 404);
                }
                if (empty($householdRelation)) {
                    return ApiResponse::fail('VALIDATION_FAILED', '세대주와의 관계를 선택해주세요.', 400);
                }
            }

            $memberData = $this->pickFillable($input, self::MEMBER_FILLABLE);
            unset($memberData['nickname']); // 앱 프로필 값 — 교적에서 관리하지 않음
            if (empty($memberData['email'])) {
                unset($memberData['email']);
            }
            $memberData['church_id'] = $churchId;
            $memberData['member_no'] = $this->generateMemberNo();

            $profileData = $this->pickFillable($input, self::PROFILE_FILLABLE);
            $profileData['registered_at'] = $profileData['registered_at'] ?? date('Y-m-d');
            if ($isSelfHead) {
                $profileData['is_household_head']  = 1;
                $profileData['household_relation'] = FamilyRelation::HOUSEHOLD_SELF;
            } elseif ($headId === null) {
                unset($profileData['household_relation']);
            }

            $newMemberId = DB::transaction(function () use ($memberData, $profileData, $organizationId, $headId, $householdRelation) {
                $id = $this->memberRepository->insertMember($memberData);
                $profileData['member_id'] = $id;
                $this->memberRepository->insertMemberProfile($profileData);

                if ($organizationId !== null) {
                    $this->organizationRepository->insertMapping([
                        'member_id'       => $id,
                        'organization_id' => $organizationId,
                        'is_primary'      => 1,
                        'joined_at'       => $profileData['registered_at'],
                    ]);
                }

                if ($headId !== null) {
                    // "신규 교인은 세대주의 {관계}" → (세대주, 신규, 관계) + 반대편 (신규, 세대주, 역관계)
                    $relation = FamilyRelation::fromHousehold($householdRelation);
                    $this->familyRepository->insertPair([
                        'member_id'         => $headId,
                        'related_member_id' => $id,
                        'relation_type'     => $relation,
                        'family_note'       => null,
                    ]);
                    $this->familyRepository->insertPair([
                        'member_id'         => $id,
                        'related_member_id' => $headId,
                        'relation_type'     => FamilyRelation::REVERSE[$relation] ?? '기타',
                        'family_note'       => null,
                    ]);
                }
                return $id;
            });

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER,
                summary:     "교인 등록: {$memberData['name']} ({$memberData['member_no']})",
                targetId:    (string) $newMemberId,
                targetLabel: $memberData['name'],
                created:     [
                    'member_no'          => $memberData['member_no'],
                    'name'               => $memberData['name'],
                    'phone'              => $memberData['phone'] ?? null,
                    'email'              => $memberData['email'] ?? null,
                    'status'             => $memberData['status'] ?? 'active',
                    'registration_type'  => $profileData['registration_type'] ?? null,
                    'organization_id'    => $organizationId,
                    'household_head_id'  => $headId,
                    'household_relation' => $profileData['household_relation'] ?? null,
                ],
            );

            $created = $this->memberRepository->getMemberById($newMemberId);

            return ApiResponse::success([
                'member_id'     => $newMemberId,
                'member_no'     => $memberData['member_no'],
                'registered_at' => $profileData['registered_at'],
                'app_link'      => $created ? $this->resolveAppLink($churchId, $created) : null,
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
     * 교회로 앱 초대 — 불투명 토큰 링크 생성 + 기록 저장.
     * 문자 게이트웨이 미연동이라 실제 문자는 보내지 않고 status=pending 으로만 기록한다.
     */
    public function sendInvite(int $authMemberId, int $memberId): JsonResponse
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
            if (AppLinkStatusHelper::digits($member->phone) === '') {
                return ApiResponse::fail('VALIDATION_FAILED', '휴대전화가 없는 교인은 초대할 수 없습니다.', 400);
            }
            if (!empty($member->churchero_user_id)) {
                return ApiResponse::fail('DUPLICATE_DATA', '이미 교회로 앱과 연결된 교인입니다.', 400);
            }

            $token     = bin2hex(random_bytes(20));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::INVITE_TTL_DAYS . ' days'));

            $inviteId = $this->memberRepository->insertInvite([
                'church_id'    => $churchId,
                'member_id'    => $memberId,
                'phone'        => $member->phone,
                'invite_token' => $token,
                'status'       => 'pending',
                'expires_at'   => $expiresAt,
                'sent_by'      => $authMemberId,
            ]);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MEMBER,
                actionType:  AuditActionType::SEND,
                summary:     "앱 초대 기록: {$member->name} (" . $this->maskPhone($member->phone) . ")",
                targetId:    (string) $memberId,
                targetLabel: $member->name,
                detail:      ['invite_id' => $inviteId, 'status' => 'pending', 'expires_at' => $expiresAt],
            );

            return ApiResponse::success([
                'invite_id'  => $inviteId,
                'invite_url' => rtrim((string) config('app.invite_base_url'), '/') . '?invite=' . $token,
                'status'     => 'pending',
                'expires_at' => $expiresAt,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] sendInvite error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '초대 처리 중 오류가 발생했습니다.', 500);
        }
    }

    public function uploadProfileImage(UploadedFile $file, int $churchId): JsonResponse
    {
        $err = S3FileHelper::validate($file, ['png', 'jpg', 'jpeg'], '프로필 사진');
        if ($err !== null) {
            return ApiResponse::fail('INVALID_FILE_TYPE', $err, 400);
        }

        try {
            $upload = S3FileHelper::upload($file, 'uploads/member_profiles', "church_{$churchId}_member_" . time() . '_' . uniqid());
            S3FileHelper::cleanupLocalTemp($upload['local_path']);
            return ApiResponse::success(['url' => $upload['s3_url']]);
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('UPLOAD_FAILED', '프로필 사진 업로드에 실패했습니다.', 500);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MemberService] uploadProfileImage error: " . $e->getMessage(), "member");
            return ApiResponse::fail('INTERNAL_ERROR', '프로필 사진 업로드 중 오류가 발생했습니다.', 500);
        }
    }

    private function resolveAppLink(int $churchId, object $member): array
    {
        $ctx    = $this->memberRepository->getAppLinkContext($churchId, [$member])[$member->id];
        $status = AppLinkStatusHelper::resolve($member, $ctx['account'], $ctx['candidate_count'], $ctx['invite_joined']);
        return ['status' => $status, 'label' => AppLinkStatusHelper::label($status)];
    }

    /**
     * 휴대폰 뒤 4자리 마스킹 (010-7143-6522 → 010-7143-****)
     */
    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $chars  = str_split($phone);
        $masked = 0;
        for ($i = count($chars) - 1; $i >= 0 && $masked < 4; $i--) {
            if (ctype_digit($chars[$i])) {
                $chars[$i] = '*';
                $masked++;
            }
        }
        return implode('', $chars);
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
