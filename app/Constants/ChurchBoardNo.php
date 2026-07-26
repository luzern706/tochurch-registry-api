<?php

namespace App\Constants;

/**
 * gh_new_board_type.board_no 상수 — 관리 > 교회 페이지(공개 홈페이지 콘텐츠) 전용 구간.
 * docs/schema/10_church_board_types.md 참조. 전역 게시판 시스템 재사용이라 다른 용도의
 * board_no와 뒤섞이지 않도록 이 상수만 사용할 것.
 */
class ChurchBoardNo
{
    /** 교회 소식 (공지사항) */
    const NEWS = 59;

    /** 교회사진 (사진 갤러리) */
    const CHURCH_PHOTO = 60;

    /** 주보 게시판 */
    const BULLETIN = 85;

    /** 묻고 답하기 */
    const QNA = 86;

    /** 유튜브 (교회 영상) */
    const CHURCH_VIDEO = 87;

    /** 행사사진 */
    const EVENT_PHOTO = 88;

    /** 사람들 */
    const PEOPLE = 89;

    /** 상담신청 (신규 추가) */
    const COUNSELING = 94;

    /** 성도한마디 (신규 추가) */
    const TESTIMONY = 95;

    /** 설교영상 (신규 추가) */
    const SERMON = 96;
}
