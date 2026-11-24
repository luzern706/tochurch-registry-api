<?php
// 교회 게시판 신고 처리 기준 — 교회 처리 기한(일). 02 관리자 API 가 gh_report_policy(id=1)에 저장한 값을 읽는다(설정은 관리자 화면에서만).
// 표가 없거나 값이 이상하면 기본 3일(docs/68 적용 전에도 기존 동작 유지).

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class ReportPolicy
{
    public const DEFAULT_DAYS = 3;
    public const MIN_DAYS = 1;
    public const MAX_DAYS = 7;

    /** 교회가 신고를 처리해야 하는 기한(일) */
    public static function churchDeadlineDays(): int
    {
        try {
            $v = DB::table('gh_report_policy')->where('id', 1)->value('church_deadline_days');
        } catch (\Throwable $e) {
            return self::DEFAULT_DAYS;
        }
        $n = (int) $v;

        return ($n >= self::MIN_DAYS && $n <= self::MAX_DAYS) ? $n : self::DEFAULT_DAYS;
    }
}
