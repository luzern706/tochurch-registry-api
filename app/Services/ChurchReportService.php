<?php

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Helpers\LogHelper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 교회 게시글 신고함 — 교회 홈페이지 게시판 글 신고를 해당 교회 관리자가 1차 처리한다.
 * 데이터는 gh_report(02_gh_admin_api docs/53 의 church_no·route_to·church_due_at 칸)를 그대로 쓴다.
 *  - route_to=CHURCH  : 교회가 처리(숨김·삭제·무시)
 *  - route_to=OPERATOR: 긴급 등급 — 운영자가 처리, 교회는 열람·통지만(처리 버튼 없음)
 * 처리 결과는 신고자에게, 숨김·삭제는 작성자에게 앱 알림으로 알린다. 처리는 gh_report 에 남는다(처리자·시각·사유).
 */
class ChurchReportService
{
    private const URGENT_CODES = ['R05', 'R06', 'R09', 'R10', 'R11'];
    private const LOW_CODES = ['R03', 'R04', 'R99'];

    /** 53번 SQL 적용 여부 — 없으면 신고함은 빈 화면(enabled=false) */
    private function enabled(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = Schema::hasColumn('gh_report', 'church_no') && Schema::hasColumn('gh_report', 'route_to')
                    && Schema::hasColumn('gh_report', 'church_due_at') && Schema::hasColumn('gh_report', 'church_handled_by');
            } catch (Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** docs/54 의 escalated_at 칸 존재 여부 — 없으면 이관 표시는 항상 null */
    private function hasEscalatedColumn(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = Schema::hasColumn('gh_report', 'escalated_at');
            } catch (Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    private function grade(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        if (in_array($code, self::URGENT_CODES, true)) {
            return 'URGENT';
        }
        return in_array($code, self::LOW_CODES, true) ? 'LOW' : 'NORMAL';
    }

    private function base(int $churchId)
    {
        return DB::table('gh_report as r')
            ->where('r.church_no', $churchId)
            ->where('r.target_type', 'post')
            ->whereIn('r.route_to', ['CHURCH', 'OPERATOR']);
    }

    public function getSummary(int $churchId): JsonResponse
    {
        if (!$this->enabled()) {
            return ApiResponse::success(['enabled' => false, 'new_count' => 0, 'due_soon' => 0, 'month_done' => 0]);
        }
        $pending = fn () => $this->base($churchId)->whereIn('r.case_status', ['WAIT', 'IN_PROGRESS']);

        return ApiResponse::success([
            'enabled'    => true,
            'new_count'  => (int) $pending()->count(),
            // 기한 임박 = 교회 처리 건 중 기한이 1일 이내이거나 이미 지난 것(D-1 이하)
            'due_soon'   => (int) $pending()->where('r.route_to', 'CHURCH')->where('r.church_due_at', '<=', now()->addDay())->count(),
            'month_done' => (int) $this->base($churchId)->whereIn('r.case_status', ['COMPLETED', 'REJECTED'])
                ->where('r.resolved_at', '>=', now()->startOfMonth())->count(),
        ]);
    }

    public function getList(int $churchId, array $filters): JsonResponse
    {
        if (!$this->enabled()) {
            return ApiResponse::success(['enabled' => false, 'total' => 0, 'list' => []]);
        }
        $tab = $filters['tab'] ?? 'pending';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $q = $this->base($churchId)
            ->join('gh_new_board_content as c', 'c.content_no', '=', 'r.content_no')
            ->leftJoin('gh_new_board_type as t', 't.board_no', '=', 'c.board_no');

        if ($tab === 'done') {
            $q->whereIn('r.case_status', ['COMPLETED', 'REJECTED', 'CANCELED']);
        } else {
            $q->whereIn('r.case_status', ['WAIT', 'IN_PROGRESS']);
        }

        $total = (clone $q)->count();

        $q->select([
            'r.seq', 'r.reportType', 'r.reason_code', 'r.contents', 'r.registDate', 'r.case_status', 'r.route_to',
            'r.church_due_at', 'r.action_type', 'r.action_reason', 'r.resolved_at', 'r.church_handled_at',
            'c.content_no', 'c.title as post_title', 'c.status as post_status', 'c.board_no', 't.name as board_name',
        ]);
        if ($this->hasEscalatedColumn()) {
            $q->addSelect('r.escalated_at');
        }
        if ($tab === 'done') {
            $q->orderByDesc('r.seq');
        } else {
            // 교회 처리 건은 기한이 급한 순, 운영자 직행 건은 뒤로
            $q->orderByRaw("r.route_to = 'CHURCH' DESC")->orderByRaw('r.church_due_at IS NULL')->orderBy('r.church_due_at')->orderBy('r.seq');
        }

        $rows = $q->forPage($page, $size)->get();
        $today = Carbon::today();
        $list = $rows->map(function ($r) use ($today) {
            $dday = null;
            if ($r->church_due_at) {
                $dday = (int) $today->diffInDays(Carbon::parse($r->church_due_at)->startOfDay(), false);
            }
            $pending = in_array($r->case_status, ['WAIT', 'IN_PROGRESS'], true);
            return [
                'seq'           => (int) $r->seq,
                'content_no'    => (int) $r->content_no,
                'board_name'    => $r->board_name,
                'post_title'    => $r->post_title,
                'post_status'   => $r->post_status,
                'reason'        => $r->reportType,
                'reason_code'   => $r->reason_code,
                'grade'         => $this->grade($r->reason_code),
                'detail'        => $r->contents,
                'registered'    => $r->registDate,
                'route_to'      => $r->route_to,
                'case_status'   => $r->case_status,
                'due_at'        => $r->church_due_at,
                'd_day'         => $pending ? $dday : null,
                'action_type'   => $r->action_type,
                'action_reason' => $r->action_reason,
                'handled_at'    => $r->church_handled_at ?: $r->resolved_at,
                // 교회가 처리 기한(3일) 안에 처리하지 않아 운영자에게 이관된 시각(이관 건은 처리 버튼 없음)
                'escalated_at'  => $r->escalated_at ?? null,
                // 교회 처리 건만 처리 버튼 — 긴급(운영자 직행) 건은 열람만
                'can_process'   => $pending && $r->route_to === 'CHURCH' && in_array($r->post_status, ['ALIVE', 'BLIND'], true),
            ];
        })->all();

        return ApiResponse::success(['enabled' => true, 'total' => $total, 'page' => $page, 'size' => $size, 'list' => $list]);
    }

    /**
     * @param string $action hide(숨김=블라인드) | delete(삭제) | dismiss(무시=반려)
     */
    public function process(int $churchId, int $adminNo, int $seq, string $action, string $reason): JsonResponse
    {
        if (!$this->enabled()) {
            return ApiResponse::fail('NOT_READY', '게시글 신고함 기능이 아직 준비되지 않았습니다.', 409);
        }
        $report = $this->base($churchId)->where('r.seq', $seq)->first(['r.seq', 'r.content_no', 'r.route_to', 'r.case_status']);
        if (!$report) {
            return ApiResponse::fail('NOT_FOUND', '신고를 찾을 수 없습니다.', 404);
        }
        if ($report->route_to !== 'CHURCH') {
            return ApiResponse::fail('FORBIDDEN', '긴급 신고이거나 처리 기한(3일)이 지나 운영자에게 이관된 신고입니다. 운영자가 처리합니다.', 403);
        }
        if (!in_array($report->case_status, ['WAIT', 'IN_PROGRESS'], true)) {
            return ApiResponse::fail('ALREADY_DONE', '이미 처리된 신고입니다.', 409);
        }
        $post = DB::table('gh_new_board_content')->where('content_no', $report->content_no)->where('church_no', $churchId)
            ->first(['content_no', 'account_no', 'status', 'title', 'board_no']);
        if (!$post) {
            return ApiResponse::fail('NOT_FOUND', '신고된 글을 찾을 수 없습니다.', 404);
        }

        $actionType = ['hide' => 'blind', 'delete' => 'delete', 'dismiss' => 'reject'][$action];
        $caseStatus = $action === 'dismiss' ? 'REJECTED' : 'COMPLETED';
        $now = now();
        $handledSeqs = [$seq];

        try {
            DB::transaction(function () use ($action, $churchId, $adminNo, $seq, $reason, $actionType, $caseStatus, $post, $now, &$handledSeqs) {
                if ($action === 'hide' && $post->status === 'ALIVE') {
                    DB::table('gh_new_board_content')->where('content_no', $post->content_no)->update(['status' => 'BLIND', 'updated' => DB::raw('updated')]);
                } elseif ($action === 'delete' && $post->status !== 'DEAD') {
                    DB::table('gh_new_board_content')->where('content_no', $post->content_no)->update(['status' => 'DEAD', 'updated' => DB::raw('updated')]);
                }

                // 숨김·삭제는 같은 글의 다른 교회 처리 대기 신고도 함께 닫는다(글이 이미 가려졌으므로). 무시는 이 신고만.
                if ($action !== 'dismiss') {
                    $others = $this->base($churchId)->where('r.content_no', $post->content_no)->where('r.route_to', 'CHURCH')
                        ->whereIn('r.case_status', ['WAIT', 'IN_PROGRESS'])->where('r.seq', '!=', $seq)->pluck('r.seq')->all();
                    $handledSeqs = array_merge($handledSeqs, array_map('intval', $others));
                }

                DB::table('gh_report')->whereIn('seq', $handledSeqs)->update([
                    'case_status'       => $caseStatus,
                    'action_type'       => $actionType,
                    'action_reason'     => '[교회 처리] ' . $reason,
                    'resolved_at'       => $now,
                    'church_handled_by' => $adminNo,
                    'church_handled_at' => $now,
                ]);
            });
        } catch (Throwable $e) {
            LogHelper::logWrite('[ChurchReportService] process 실패: ' . $e->getMessage(), 'church_report');
            return ApiResponse::fail('INTERNAL_ERROR', '신고 처리 중 오류가 발생했습니다.', 500);
        }

        $this->notifyReporters($handledSeqs);
        if ($action !== 'dismiss' && $post->account_no) {
            $message = $action === 'hide'
                ? '작성하신 글이 신고되어 교회 관리자가 숨김 처리했습니다. 사유: ' . $reason
                : '작성하신 글이 신고되어 교회 관리자가 삭제했습니다. 사유: ' . $reason;
            $this->notifyAccount((int) $post->account_no, 'church_post_' . $actionType, $message, (int) $post->board_no, (int) $post->content_no, (string) $post->title);
        }

        return ApiResponse::success(['processed' => count($handledSeqs), 'seqs' => $handledSeqs]);
    }

    private function notifyReporters(array $seqs): void
    {
        try {
            foreach (DB::table('gh_report')->whereIn('seq', $seqs)->whereNotNull('account_no')->pluck('account_no')->unique() as $accountNo) {
                $last = DB::table('gh_notifications')->where('account_no', $accountNo)->where('type', 'report_resolved')->orderByDesc('registered')->first();
                if ($last && !$last->is_read) {
                    continue; // 읽지 않은 같은 종류 알림이 있으면 중복 발송 안 함(운영자 처리와 동일 규칙)
                }
                $this->notifyAccount((int) $accountNo, 'report_resolved', '신고해 주신 내용을 확인해 처리했습니다.');
            }
        } catch (Throwable $e) {
            LogHelper::logWrite('[ChurchReportService] notifyReporters 실패: ' . $e->getMessage(), 'church_report');
        }
    }

    private function notifyAccount(int $accountNo, string $type, string $message, ?int $boardNo = null, ?int $contentNo = null, ?string $title = null): void
    {
        try {
            DB::table('gh_notifications')->insert([
                'account_no'       => $accountNo,
                'type'             => $type,
                'source'           => 'moderation',
                'actor_account_no' => null,
                'actor_nickname'   => '교회 관리자',
                'board_no'         => $boardNo,
                'content_no'       => $contentNo,
                'content_title'    => $title,
                'comment_excerpt'  => mb_substr($message, 0, 255),
                'is_read'          => 0,
                'registered'       => now(),
            ]);
        } catch (Throwable $e) {
            LogHelper::logWrite("[ChurchReportService] notify({$type}) 실패: " . $e->getMessage(), 'church_report');
        }
    }
}
