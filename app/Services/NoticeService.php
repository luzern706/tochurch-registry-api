<?php

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Helpers\LogHelper;
use App\Repositories\NoticeRepository;
use Illuminate\Http\JsonResponse;

class NoticeService
{
    protected NoticeRepository $noticeRepository;

    public function __construct(NoticeRepository $noticeRepository)
    {
        $this->noticeRepository = $noticeRepository;
    }

    public function getNoticeList(int $churchAdminNo, ?string $type): JsonResponse
    {
        try {
            $list = $this->noticeRepository->getList($churchAdminNo, $type);
            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NoticeService] getNoticeList error: " . $e->getMessage(), "notice");
            return ApiResponse::fail('INTERNAL_ERROR', '공지 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getResourceList(): JsonResponse
    {
        try {
            $list = $this->noticeRepository->getResourceList();
            return ApiResponse::success(['list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NoticeService] getResourceList error: " . $e->getMessage(), "notice");
            return ApiResponse::fail('INTERNAL_ERROR', '자료 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 상세 조회 시 자동으로 읽음 처리(조회=SEND/CREATE 같은 write 액션이 아니라 단순 열람 기록이라
     * CLAUDE.md의 "조회(GET) 액션엔 audit 안 함" 원칙과 동일하게 별도 감사로그는 남기지 않음)
     */
    public function getNoticeDetail(int $churchAdminNo, int $noticeNo): JsonResponse
    {
        try {
            $notice = $this->noticeRepository->findByNo($noticeNo);
            if ($notice === null) {
                return ApiResponse::fail('NOT_FOUND', '공지를 찾을 수 없습니다.', 404);
            }

            $this->noticeRepository->markRead($noticeNo, $churchAdminNo);

            $adjacent = $this->noticeRepository->findAdjacent($noticeNo);

            return ApiResponse::success([
                'notice'      => $notice,
                'read_count'  => $this->noticeRepository->countReaders($noticeNo),
                'prev'        => $adjacent['prev'],
                'next'        => $adjacent['next'],
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NoticeService] getNoticeDetail error: " . $e->getMessage(), "notice");
            return ApiResponse::fail('INTERNAL_ERROR', '공지 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
