<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\MemberRepository;
use App\Repositories\MessageRepository;
use App\Repositories\OrganizationRepository;
use Illuminate\Http\JsonResponse;

class MessageService
{
    protected MessageRepository $messageRepository;
    protected MemberRepository $memberRepository;
    protected OrganizationRepository $organizationRepository;

    public function __construct(
        MessageRepository $messageRepository,
        MemberRepository $memberRepository,
        OrganizationRepository $organizationRepository
    ) {
        $this->messageRepository      = $messageRepository;
        $this->memberRepository       = $memberRepository;
        $this->organizationRepository = $organizationRepository;
    }

    /**
     * 메시지 발송: 수신 대상 산출 후 발송 기록 row 1건 저장.
     *
     * 실제 SMS/Push/Email 발송은 게이트웨이 연동 미구현 (TODO).
     */
    public function sendMessage(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $recipientIds = $this->resolveRecipientIds($churchId, $input);
            if ($recipientIds === null) {
                return ApiResponse::fail('NOT_FOUND', '발송 대상 조직을 찾을 수 없습니다.', 404);
            }

            if (empty($recipientIds)) {
                return ApiResponse::fail('VALIDATION_FAILED', '발송 대상이 없습니다.', 400);
            }

            // TODO(외부연동): SMS/Push/Email 게이트웨이 연동.
            // 현재는 발송 기록만 저장하고 실제 발송은 수행하지 않음.

            $newId = $this->messageRepository->insertMessage([
                'church_id'       => $churchId,
                'title'           => $input['title'],
                'content'         => $input['content'],
                'send_type'       => $input['send_type'] ?? 'push',
                'sent_at'         => now(),
                'sent_by'         => $authMemberId,
                'recipient_count' => count($recipientIds),
            ]);

            $sendType = $input['send_type'] ?? 'push';
            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MESSAGE,
                actionType:  AuditActionType::SEND,
                summary:     "메시지 발송({$sendType}): \"{$input['title']}\" → " . count($recipientIds) . "명",
                targetId:    (string) $newId,
                targetLabel: $input['title'],
                detail:      [
                    'send_type'       => $sendType,
                    'target_type'     => $input['target_type'],
                    'recipient_count' => count($recipientIds),
                ],
            );

            return ApiResponse::success([
                'message_id'      => $newId,
                'recipient_count' => count($recipientIds),
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageService] sendMessage error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '메시지 발송 중 오류가 발생했습니다.', 500);
        }
    }

    public function getMessageList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->messageRepository->getMessageList($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageService] getMessageList error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '메시지 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getMessageDetail(int $authMemberId, int $messageId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $message = $this->messageRepository->getMessageById($messageId);
            if ($message === null || (int) $message->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '메시지를 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['message' => $message]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageService] getMessageDetail error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '메시지 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteMessage(int $authMemberId, int $messageId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $message = $this->messageRepository->getMessageById($messageId);
            if ($message === null || (int) $message->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '메시지를 찾을 수 없습니다.', 404);
            }

            $this->messageRepository->deleteMessage($messageId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::MESSAGE,
                summary:     "메시지 발송 기록 삭제(hard): \"{$message->title}\"",
                targetId:    (string) $messageId,
                targetLabel: $message->title,
            );

            return ApiResponse::success(['message_id' => $messageId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[MessageService] deleteMessage error: " . $e->getMessage(), "message");
            return ApiResponse::fail('INTERNAL_ERROR', '메시지 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * target_type 별로 발송 대상 교인 ID 리스트 산출.
     * 조직이 없거나 잘못된 경우 null 반환.
     */
    private function resolveRecipientIds(int $churchId, array $input): ?array
    {
        $targetType = $input['target_type'];

        if ($targetType === 'all') {
            return $this->messageRepository->resolveAllMemberIds($churchId);
        }

        if ($targetType === 'organization') {
            $orgId = (int) ($input['organization_id'] ?? 0);
            $org   = $this->organizationRepository->getOrganizationById($orgId);
            if ($org === null || (int) $org->church_id !== $churchId) {
                return null;
            }
            return $this->messageRepository->resolveOrganizationMemberIds($churchId, $orgId);
        }

        if ($targetType === 'members') {
            $ids = array_map('intval', $input['member_ids'] ?? []);
            return $this->messageRepository->filterValidMemberIds($churchId, $ids);
        }

        return [];
    }
}
