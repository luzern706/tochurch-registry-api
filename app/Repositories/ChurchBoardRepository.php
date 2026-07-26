<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * 공유 게시판(gh_new_board_type/gh_new_board_content) 범용 CRUD.
 * board_no로 용도(교회소식/주보/QnA/상담신청/성도한마디 등)를 구분하고 church_no로
 * 교회별 스코프 — docs/schema/10_church_board_types.md 참조. 다른 시스템(www 공개
 * 홈페이지, 커뮤니티 게시판 등)도 같은 테이블을 쓰므로 board_no 상수는
 * App\Constants\ChurchBoardNo 만 사용할 것.
 */
class ChurchBoardRepository
{
    public function getList(int $boardNo, int $churchId, array $filters): array
    {
        $query = DB::table('gh_new_board_content')
            ->where('board_no', $boardNo)
            ->where('church_no', $churchId)
            ->where('status', 'ALIVE');

        if (!empty($filters['keyword'])) {
            $query->where('title', 'LIKE', '%' . $filters['keyword'] . '%');
        }
        if (array_key_exists('is_top', $filters) && $filters['is_top'] !== null) {
            $query->where('is_top', $filters['is_top'] ? 'Y' : 'N');
        }
        if (array_key_exists('is_selected', $filters) && $filters['is_selected'] !== null) {
            $query->where('is_selected', $filters['is_selected'] ? 'Y' : 'N');
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, (int) ($filters['size'] ?? 20));

        $list = $query
            ->orderBy('is_top', 'desc')
            ->orderBy('content_no', 'desc')
            ->forPage($page, $size)
            ->get();

        return ['total' => $total, 'list' => $list->toArray()];
    }

    public function getById(int $contentNo): ?stdClass
    {
        return DB::table('gh_new_board_content')
            ->where('content_no', $contentNo)
            ->where('status', 'ALIVE')
            ->first();
    }

    public function insert(array $data): int
    {
        $data['registered'] = $data['registered'] ?? now();
        $data['updated']    = now();
        $data['status']     = 'ALIVE';
        // title/content 는 NOT NULL, 기본값 없음 — 호출부가 안 넘기면 빈 문자열로 보정.
        $data['title']   = $data['title'] ?? '';
        $data['content'] = $data['content'] ?? '';
        return (int) DB::table('gh_new_board_content')->insertGetId($data, 'content_no');
    }

    public function update(int $contentNo, array $data): int
    {
        $data['updated'] = now();
        return DB::table('gh_new_board_content')
            ->where('content_no', $contentNo)
            ->update($data);
    }

    public function softDelete(int $contentNo): int
    {
        return DB::table('gh_new_board_content')
            ->where('content_no', $contentNo)
            ->update(['status' => 'DEAD', 'updated' => now()]);
    }
}
