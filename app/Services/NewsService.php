<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\ChurchBoardNo;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\ChurchBoardRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * 관리 > 교회 페이지 "소식·공지" — gh_new_board_content 재사용.
 * board_no 매핑은 App\Constants\ChurchBoardNo, 필드 매핑은 docs/schema/10_church_board_types.md 참조.
 */
class NewsService
{
    protected ChurchBoardRepository $boardRepository;

    public function __construct(ChurchBoardRepository $boardRepository)
    {
        $this->boardRepository = $boardRepository;
    }

    private function churchId(): ?int
    {
        return JwtHelper::getChurchIdFromRequest();
    }

    private function ownedByChurch($row, int $churchId): bool
    {
        return $row !== null && (int) $row->church_no === $churchId;
    }

    // ───────────────────────── 교회 소식 ─────────────────────────

    public function getNewsList(array $filters): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $result = $this->boardRepository->getList(ChurchBoardNo::NEWS, $churchId, $filters);
            return ApiResponse::success($result);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] getNewsList error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '교회 소식 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerNews(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $data = [
                'board_no' => ChurchBoardNo::NEWS,
                'church_no' => $churchId,
                'admin_no' => $authMemberId,
                'title' => $input['title'],
                'content' => $input['content'] ?? '',
                'is_top' => !empty($input['is_top']) ? 'Y' : 'N',
            ];
            $newId = $this->boardRepository->insert($data);

            AuditLogHelper::logCreate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "교회 소식 등록: {$input['title']}", targetId: (string) $newId, targetLabel: $input['title'], created: $data,
            );

            return ApiResponse::success(['content_no' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] registerNews error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '교회 소식 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateNews(int $authMemberId, int $contentNo, array $input): JsonResponse
    {
        return $this->genericUpdate($authMemberId, $contentNo, $input, ['title', 'content', 'is_top'], '교회 소식', function ($v) {
            if (isset($v['is_top'])) $v['is_top'] = $v['is_top'] ? 'Y' : 'N';
            return $v;
        });
    }

    public function deleteNews(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '교회 소식');
    }

    public function togglePinNews(int $authMemberId, int $contentNo, bool $isTop): JsonResponse
    {
        return $this->updateNews($authMemberId, $contentNo, ['is_top' => $isTop]);
    }

    // ───────────────────────── 주보 게시판 ─────────────────────────

    public function getBulletinList(array $filters): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $result = $this->boardRepository->getList(ChurchBoardNo::BULLETIN, $churchId, $filters);
            return ApiResponse::success($result);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] getBulletinList error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '주보 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerBulletin(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $data = [
                'board_no' => ChurchBoardNo::BULLETIN,
                'church_no' => $churchId,
                'admin_no' => $authMemberId,
                'title' => $input['title'],
                'content' => '',
                'files' => isset($input['files']) ? json_encode($input['files'], JSON_UNESCAPED_UNICODE) : null,
            ];
            if (!empty($input['date'])) $data['registered'] = $input['date'];

            $newId = $this->boardRepository->insert($data);

            AuditLogHelper::logCreate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "주보 등록: {$input['title']}", targetId: (string) $newId, targetLabel: $input['title'], created: $data,
            );

            return ApiResponse::success(['content_no' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] registerBulletin error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '주보 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateBulletin(int $authMemberId, int $contentNo, array $input): JsonResponse
    {
        return $this->genericUpdate($authMemberId, $contentNo, $input, ['title', 'files', 'date'], '주보', function ($v) {
            if (isset($v['files'])) $v['files'] = json_encode($v['files'], JSON_UNESCAPED_UNICODE);
            if (isset($v['date'])) { $v['registered'] = $v['date']; unset($v['date']); }
            return $v;
        });
    }

    public function deleteBulletin(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '주보');
    }

    // ───────────────────────── 묻고 답하기 ─────────────────────────

    public function getQnaList(array $filters): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $result = $this->boardRepository->getList(ChurchBoardNo::QNA, $churchId, $filters);
            $result['list'] = array_map(function ($row) {
                $row->answer = $row->text_val1;
                $row->is_answered = !empty($row->text_val1);
                return $row;
            }, $result['list']);

            return ApiResponse::success($result);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] getQnaList error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '묻고답하기 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerQna(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $data = [
                'board_no' => ChurchBoardNo::QNA,
                'church_no' => $churchId,
                'admin_no' => $authMemberId,
                'title' => $input['title'],
                'content' => $input['content'] ?? '',
                'int_val1' => !empty($input['is_public']) ? 1 : 0,
            ];
            $newId = $this->boardRepository->insert($data);

            AuditLogHelper::logCreate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "묻고답하기 등록: {$input['title']}", targetId: (string) $newId, targetLabel: $input['title'], created: $data,
            );

            return ApiResponse::success(['content_no' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] registerQna error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '묻고답하기 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateQna(int $authMemberId, int $contentNo, array $input): JsonResponse
    {
        return $this->genericUpdate($authMemberId, $contentNo, $input, ['title', 'content', 'is_public'], '묻고답하기', function ($v) {
            if (isset($v['is_public'])) { $v['int_val1'] = $v['is_public'] ? 1 : 0; unset($v['is_public']); }
            return $v;
        });
    }

    public function deleteQna(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '묻고답하기');
    }

    public function answerQna(int $authMemberId, int $contentNo, string $comment): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $row = $this->boardRepository->getById($contentNo);
            if (!$this->ownedByChurch($row, $churchId)) return ApiResponse::fail('NOT_FOUND', '질문을 찾을 수 없습니다.', 404);

            // gh_new_board_comment.account_no 는 gh_account FK(NOT NULL) — 관리자 작성 댓글을
            // 넣을 수 없어(플레이스홀더 계정 없음) 답변은 같은 글의 text_val1에 직접 저장한다.
            $this->boardRepository->update($contentNo, ['text_val1' => $comment]);

            AuditLogHelper::logUpdate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "묻고답하기 답변 등록: {$row->title}", targetId: (string) $contentNo, targetLabel: $row->title,
                before: [], after: ['answer' => $comment], compareFields: ['answer'],
            );

            return ApiResponse::success(['content_no' => $contentNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] answerQna error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '답변 등록 중 오류가 발생했습니다.', 500);
        }
    }

    // ───────────────────────── 상담 신청 ─────────────────────────

    public function getCounselingList(array $filters): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $query = DB::table('gh_new_board_content as c')
                ->leftJoin('gh_church_admin as a', 'a.admin_no', '=', 'c.int_val1')
                ->where('c.board_no', ChurchBoardNo::COUNSELING)
                ->where('c.church_no', $churchId)
                ->where('c.status', 'ALIVE');

            $total = (clone $query)->count();
            $page = max(1, (int) ($filters['page'] ?? 1));
            $size = max(1, (int) ($filters['size'] ?? 20));

            $list = $query
                ->orderBy('c.content_no', 'desc')
                ->forPage($page, $size)
                ->get([
                    'c.content_no', 'c.title', 'c.content', 'c.contact_name', 'c.phone1', 'c.email',
                    'c.str_val1 as status', 'c.int_val1 as assignee_admin_no', 'a.name as assignee_name',
                    'c.text_val1 as memo', 'c.text_val2 as reply', 'c.registered',
                ]);

            return ApiResponse::success(['total' => $total, 'list' => $list]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] getCounselingList error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '상담신청 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getCounselingDetail(int $contentNo): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $row = $this->boardRepository->getById($contentNo);
            if (!$this->ownedByChurch($row, $churchId)) return ApiResponse::fail('NOT_FOUND', '상담신청을 찾을 수 없습니다.', 404);

            return ApiResponse::success(['item' => $row]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] getCounselingDetail error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '상담신청 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateCounseling(int $authMemberId, int $contentNo, array $input): JsonResponse
    {
        return $this->genericUpdate($authMemberId, $contentNo, $input, ['status', 'assignee_admin_no', 'memo', 'reply'], '상담신청', function ($v) {
            if (isset($v['status'])) { $v['str_val1'] = $v['status']; unset($v['status']); }
            if (isset($v['assignee_admin_no'])) { $v['int_val1'] = $v['assignee_admin_no']; unset($v['assignee_admin_no']); }
            if (isset($v['memo'])) { $v['text_val1'] = $v['memo']; unset($v['memo']); }
            if (isset($v['reply'])) { $v['text_val2'] = $v['reply']; unset($v['reply']); }
            return $v;
        });
    }

    public function deleteCounseling(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '상담신청');
    }

    // ───────────────────────── 성도한마디 ─────────────────────────

    public function getTestimonyList(array $filters): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $result = $this->boardRepository->getList(ChurchBoardNo::TESTIMONY, $churchId, $filters);
            return ApiResponse::success($result);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] getTestimonyList error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', '성도한마디 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateTestimonyStatus(int $authMemberId, int $contentNo, bool $approved): JsonResponse
    {
        return $this->genericUpdate($authMemberId, $contentNo, ['is_selected' => $approved], ['is_selected'], '성도한마디', function ($v) {
            $v['is_selected'] = $v['is_selected'] ? 'Y' : 'N';
            return $v;
        });
    }

    public function deleteTestimony(int $authMemberId, int $contentNo): JsonResponse
    {
        return $this->genericDelete($authMemberId, $contentNo, '성도한마디');
    }

    // ───────────────────────── 공통 헬퍼 ─────────────────────────

    private function genericUpdate(int $authMemberId, int $contentNo, array $input, array $fillable, string $label, callable $transform): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $row = $this->boardRepository->getById($contentNo);
            if (!$this->ownedByChurch($row, $churchId)) return ApiResponse::fail('NOT_FOUND', "{$label}을(를) 찾을 수 없습니다.", 404);

            $data = array_intersect_key($input, array_flip($fillable));
            $data = $transform($data);
            // gh_new_board_content.title/content 는 NOT NULL — 빈 입력이 ConvertEmptyStringsToNull
            // 미들웨어를 거쳐 null 로 들어와도 DB 제약 위반이 나지 않도록 빈 문자열로 보정.
            foreach (['title', 'content'] as $notNullField) {
                if (array_key_exists($notNullField, $data) && $data[$notNullField] === null) {
                    $data[$notNullField] = '';
                }
            }
            if (!empty($data)) {
                $this->boardRepository->update($contentNo, $data);
            }

            $titleLabel = $row->title ?: ($row->content ?? $label);
            AuditLogHelper::logUpdate(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "{$label} 수정: {$titleLabel}", targetId: (string) $contentNo, targetLabel: $titleLabel,
                before: (array) $row, after: array_merge((array) $row, $data), compareFields: array_keys($data),
            );

            return ApiResponse::success(['content_no' => $contentNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] genericUpdate({$label}) error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', "{$label} 수정 중 오류가 발생했습니다.", 500);
        }
    }

    private function genericDelete(int $authMemberId, int $contentNo, string $label): JsonResponse
    {
        try {
            $churchId = $this->churchId();
            if ($churchId === null) return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);

            $row = $this->boardRepository->getById($contentNo);
            if (!$this->ownedByChurch($row, $churchId)) return ApiResponse::fail('NOT_FOUND', "{$label}을(를) 찾을 수 없습니다.", 404);

            $this->boardRepository->softDelete($contentNo);

            $titleLabel = $row->title ?: ($row->content ?? $label);
            AuditLogHelper::logDelete(
                memberId: $authMemberId, churchId: $churchId, menuCode: AuditMenuCode::WEBSITE,
                summary: "{$label} 삭제: {$titleLabel}", targetId: (string) $contentNo, targetLabel: $titleLabel,
            );

            return ApiResponse::success(['content_no' => $contentNo]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[NewsService] genericDelete({$label}) error: " . $e->getMessage(), "news");
            return ApiResponse::fail('INTERNAL_ERROR', "{$label} 삭제 중 오류가 발생했습니다.", 500);
        }
    }
}
