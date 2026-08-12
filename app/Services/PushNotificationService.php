<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MessageRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\PushNotificationRepository;
use Illuminate\Http\JsonResponse;

class PushNotificationService
{
    protected PushNotificationRepository $pushRepository;
    protected MessageRepository $messageRepository;
    protected OrganizationRepository $organizationRepository;

    public function __construct(
        PushNotificationRepository $pushRepository,
        MessageRepository $messageRepository,
        OrganizationRepository $organizationRepository
    ) {
        $this->pushRepository         = $pushRepository;
        $this->messageRepository      = $messageRepository;
        $this->organizationRepository = $organizationRepository;
    }

    public function getFilterOptions(): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $organizations = $this->organizationRepository->getOrganizationList($churchId, ['is_active' => 1]);
            $profileValues = $this->pushRepository->getDistinctProfileValues($churchId);

            return ApiResponse::success([
                'organizations'  => $organizations,
                'positions'      => $profileValues['positions'],
                'member_types'   => $profileValues['member_types'],
                'baptism_grades' => $profileValues['baptism_grades'],
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[PushNotificationService] getFilterOptions error: " . $e->getMessage(), "push");
            return ApiResponse::fail('INTERNAL_ERROR', '필터 옵션 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getTargetSummary(array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $memberIds = $this->resolveTargetMemberIds($churchId, $input);
            if (empty($memberIds)) {
                return ApiResponse::success([
                    'total'       => 0,
                    'reachable'   => 0,
                    'unreachable' => ['no_account' => 0, 'no_token' => 0, 'optout' => 0],
                ]);
            }

            $snapshots = $this->pushRepository->getReachabilitySnapshots($churchId, $memberIds);
            $reachable   = 0;
            $unreachable = ['no_account' => 0, 'no_token' => 0, 'optout' => 0];
            foreach ($snapshots as $s) {
                if ($s->reachable) {
                    $reachable++;
                } elseif (isset($unreachable[$s->skip_reason])) {
                    $unreachable[$s->skip_reason]++;
                }
            }

            return ApiResponse::success([
                'total'       => count($snapshots),
                'reachable'   => $reachable,
                'unreachable' => $unreachable,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[PushNotificationService] getTargetSummary error: " . $e->getMessage(), "push");
            return ApiResponse::fail('INTERNAL_ERROR', '대상 요약 계산 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 푸시 발송: 대상 산출 → 도달가능성 계산 → reg_messages(send_type=push) + reg_push_recipients 스냅샷 저장.
     * 실제 FCM 전송은 이 프로젝트에 인프라가 없어 수행하지 않는다(문자발송과 동일한 "기록만 저장" 원칙) —
     * 단, 도달가능성 자체는 실제 계정연동/토큰/수신동의를 조회한 결과다.
     */
    public function sendPush(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $memberIds = $this->resolveTargetMemberIds($churchId, $input);
            if (empty($memberIds)) {
                return ApiResponse::fail('VALIDATION_FAILED', '발송 대상이 없습니다.', 400);
            }

            $snapshots      = $this->pushRepository->getReachabilitySnapshots($churchId, $memberIds);
            $reachableCount = count(array_filter($snapshots, fn ($s) => $s->reachable));
            if ($reachableCount === 0) {
                return ApiResponse::fail('VALIDATION_FAILED', '발송 가능한 대상이 없습니다(계정 미연동/토큰 없음/수신거부).', 400);
            }

            $messageId = $this->messageRepository->insertMessage([
                'church_id'       => $churchId,
                'title'           => $input['title'],
                'content'         => $input['content'],
                'send_type'       => 'push',
                'deep_link'       => $input['deep_link'] ?? null,
                'resend_of'       => null,
                'sent_at'         => now(),
                'sent_by'         => $authMemberId,
                'recipient_count' => $reachableCount,
            ]);

            $this->pushRepository->insertRecipients($messageId, $snapshots);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MESSAGE,
                actionType:  AuditActionType::SEND,
                summary:     "푸시 발송: \"{$input['title']}\" → 발송대상 {$reachableCount}명(전체 대상 " . count($snapshots) . "명)",
                targetId:    (string) $messageId,
                targetLabel: $input['title'],
                detail:      [
                    'target_type'     => $input['target_type'],
                    'total_targeted'  => count($snapshots),
                    'reachable_count' => $reachableCount,
                ],
            );

            return ApiResponse::success([
                'message_id'      => $messageId,
                'total'           => count($snapshots),
                'reachable_count' => $reachableCount,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[PushNotificationService] sendPush error: " . $e->getMessage(), "push");
            return ApiResponse::fail('INTERNAL_ERROR', '푸시 발송 중 오류가 발생했습니다.', 500);
        }
    }

    public function getPushDetail(int $messageId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $message = $this->messageRepository->getMessageById($messageId);
            if ($message === null || (int) $message->church_id !== $churchId || $message->send_type !== 'push') {
                return ApiResponse::fail('NOT_FOUND', '푸시 발송 기록을 찾을 수 없습니다.', 404);
            }

            $recipients = $this->pushRepository->getRecipientsByMessage($messageId);
            $summary    = $this->pushRepository->countByReachable($messageId);

            $resendOf = null;
            if (!empty($message->resend_of)) {
                $orig = $this->messageRepository->getMessageById((int) $message->resend_of);
                if ($orig !== null) {
                    $resendOf = ['id' => $orig->id, 'title' => $orig->title];
                }
            }

            return ApiResponse::success([
                'message'    => $message,
                'summary'    => $summary,
                'recipients' => $recipients,
                'resend_of'  => $resendOf,
                'resent_to'  => $this->messageRepository->getResendChildren($messageId),
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[PushNotificationService] getPushDetail error: " . $e->getMessage(), "push");
            return ApiResponse::fail('INTERNAL_ERROR', '푸시 발송 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 실패자만 재발송: 원본 발송에서 제외됐던 대상의 도달가능성을 지금 시점 기준으로 재계산해
     * 그 사이 조건이 해소된(예: 기기 토큰이 새로 등록된) 인원에게만 새 발송 기록을 만든다.
     * 실제 전송을 재시도하는 것이 아니라 "재계산 후 재기록" — 이 프로젝트에 실제 FCM 재시도 인프라가 없기 때문.
     */
    public function resendPush(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $messageId = (int) $input['message_id'];
            $original  = $this->messageRepository->getMessageById($messageId);
            if ($original === null || (int) $original->church_id !== $churchId || $original->send_type !== 'push') {
                return ApiResponse::fail('NOT_FOUND', '원본 푸시 발송 기록을 찾을 수 없습니다.', 404);
            }

            $includePermanent = (bool) ($input['include_permanent'] ?? false);
            $memberIds        = $this->pushRepository->getSkippedMemberIds($messageId, $includePermanent);
            if (empty($memberIds)) {
                return ApiResponse::fail('VALIDATION_FAILED', '재발송 대상이 없습니다.', 400);
            }

            $snapshots      = $this->pushRepository->getReachabilitySnapshots($churchId, $memberIds);
            $reachableCount = count(array_filter($snapshots, fn ($s) => $s->reachable));
            if ($reachableCount === 0) {
                return ApiResponse::fail('VALIDATION_FAILED', '현재 기준으로도 발송 가능한 대상이 없습니다. 계정연동·토큰등록·수신동의 상태가 여전히 해결되지 않았습니다.', 400);
            }

            $title   = trim((string) ($input['title'] ?? '')) !== '' ? $input['title'] : $original->title;
            $content = trim((string) ($input['content'] ?? '')) !== '' ? $input['content'] : $original->content;

            $newMessageId = $this->messageRepository->insertMessage([
                'church_id'       => $churchId,
                'title'           => $title,
                'content'         => $content,
                'send_type'       => 'push',
                'deep_link'       => $original->deep_link,
                'resend_of'       => $messageId,
                'sent_at'         => now(),
                'sent_by'         => $authMemberId,
                'recipient_count' => $reachableCount,
            ]);

            $this->pushRepository->insertRecipients($newMessageId, $snapshots);

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MESSAGE,
                actionType:  AuditActionType::SEND,
                summary:     "푸시 재발송(원본 #{$messageId}): \"{$title}\" → 발송대상 {$reachableCount}명",
                targetId:    (string) $newMessageId,
                targetLabel: $title,
                detail:      [
                    'resend_of'         => $messageId,
                    'include_permanent' => $includePermanent,
                    'reachable_count'   => $reachableCount,
                ],
            );

            return ApiResponse::success([
                'message_id'      => $newMessageId,
                'total'           => count($snapshots),
                'reachable_count' => $reachableCount,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[PushNotificationService] resendPush error: " . $e->getMessage(), "push");
            return ApiResponse::fail('INTERNAL_ERROR', '푸시 재발송 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * target_type 별로 발송 대상 교인 ID 리스트 산출 (MessageService::resolveRecipientIds 와 동일 골격 + group 추가)
     */
    private function resolveTargetMemberIds(int $churchId, array $input): array
    {
        $targetType = $input['target_type'];

        if ($targetType === 'all') {
            return $this->messageRepository->resolveAllMemberIds($churchId);
        }

        if ($targetType === 'organization') {
            $orgId = (int) ($input['organization_id'] ?? 0);
            $org   = $this->organizationRepository->getOrganizationById($orgId);
            if ($org === null || (int) $org->church_id !== $churchId) {
                return [];
            }
            return $this->messageRepository->resolveOrganizationMemberIds($churchId, $orgId);
        }

        if ($targetType === 'group') {
            return $this->pushRepository->resolveGroupMemberIds($churchId, [
                'org_ids'        => $input['org_ids'] ?? [],
                'positions'      => $input['positions'] ?? [],
                'member_types'   => $input['member_types'] ?? [],
                'baptism_grades' => $input['baptism_grades'] ?? [],
            ]);
        }

        if ($targetType === 'members') {
            $ids = array_map('intval', $input['member_ids'] ?? []);
            return $this->messageRepository->filterValidMemberIds($churchId, $ids);
        }

        return [];
    }
}
