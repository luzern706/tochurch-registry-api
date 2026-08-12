<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * "교회로 공지 센터" — 02_gh_admin_api(플랫폼 관리자)가 작성하는 gh_notice_v4* 를 읽기 전용으로 조회.
 * 같은 물리 DB를 공유하는 기존 테이블이라 스키마는 절대 건드리지 않음(CLAUDE.md 절대금지 사항).
 * 유일한 write는 gh_notice_v4_read(교적 관리자별 읽음 기록) — 이 테이블도 admin 쪽 마이그레이션 소유,
 * 교적은 행 삽입/갱신만 수행.
 */
class NoticeRepository
{
    private const TYPES = ['notice', 'update', 'check', 'guide'];

    public function getList(int $churchAdminNo, ?string $type): array
    {
        $where  = "WHERE n.is_deleted = 0 AND n.status = 'ACTIVE'";
        $params = [$churchAdminNo];

        if ($type !== null && in_array($type, self::TYPES, true)) {
            $where .= " AND n.type = ?";
            $params[] = $type;
        }

        return DB::select("
            SELECT
                n.notice_no, n.type, n.title, n.summary, n.is_pinned, n.created_at,
                (r.read_no IS NOT NULL) AS is_read
            FROM gh_notice_v4 n
            LEFT JOIN gh_notice_v4_read r
                ON r.notice_no = n.notice_no AND r.church_admin_no = ?
            {$where}
            ORDER BY n.is_pinned DESC, n.notice_no DESC
        ", $params);
    }

    public function getResourceList(): array
    {
        return DB::select("
            SELECT resource_no, title, description, file_url, file_type, file_size
            FROM gh_notice_v4_resource
            WHERE is_deleted = 0 AND status = 'ACTIVE'
            ORDER BY sort_order ASC, resource_no DESC
        ");
    }

    public function findByNo(int $noticeNo): ?stdClass
    {
        return DB::selectOne("
            SELECT notice_no, type, title, summary, content, is_pinned, created_at
            FROM gh_notice_v4
            WHERE notice_no = ? AND is_deleted = 0 AND status = 'ACTIVE'
            LIMIT 1
        ", [$noticeNo]);
    }

    /**
     * 상세 화면의 이전글/다음글 — 목록과 동일 정렬(고정 우선, notice_no 내림차순) 기준 인접 글
     */
    public function findAdjacent(int $noticeNo): array
    {
        $prev = DB::selectOne("
            SELECT notice_no, title FROM gh_notice_v4
            WHERE is_deleted = 0 AND status = 'ACTIVE' AND notice_no > ?
            ORDER BY notice_no ASC LIMIT 1
        ", [$noticeNo]);

        $next = DB::selectOne("
            SELECT notice_no, title FROM gh_notice_v4
            WHERE is_deleted = 0 AND status = 'ACTIVE' AND notice_no < ?
            ORDER BY notice_no DESC LIMIT 1
        ", [$noticeNo]);

        return ['prev' => $prev, 'next' => $next];
    }

    public function countReaders(int $noticeNo): int
    {
        return (int) DB::table('gh_notice_v4_read')->where('notice_no', $noticeNo)->count();
    }

    public function markRead(int $noticeNo, int $churchAdminNo): void
    {
        DB::table('gh_notice_v4_read')->updateOrInsert(
            ['notice_no' => $noticeNo, 'church_admin_no' => $churchAdminNo],
            ['read_at' => now()]
        );
    }
}
