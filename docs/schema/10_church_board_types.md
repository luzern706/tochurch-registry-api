# 관리 > 교회 페이지 "소식·공지" / "미디어" — 게시판 테이블 재사용

> 2026-07-17. 새 `reg_` 테이블을 만들지 않고, 이미 운영 중인 공유 게시판 시스템
> (`gh_new_board_type` + `gh_new_board_content`)을 재사용한다. 이 테이블은 교적 시스템
> 전용이 아니라 교회로 플랫폼 전체가 공유하는 범용 게시판(구인구직/임대/커뮤니티 등도 같은
> 테이블 사용) — 개별 게시글은 `gh_new_board_content.church_no`로 교회별로 스코프된다.
> 공개 홈페이지의 `usp_church_view` 프로시저가 이미 이 중 7개 board_no를 교회별로 읽고 있음
> (`category_no = 10` = "교회 홈페이지 콘텐츠" 그룹).

## board_no 매핑 (category_no = 10)

| board_no | 이름 (gh_new_board_type.name) | 화면 | 비고 |
|---|---|---|---|
| 59 | 전하는말 | 소식·공지 > 교회 소식 | `usp_church_view`가 "공지사항"으로 주석 |
| 60 | 교회사진 | 미디어 > 사진 갤러리 | 앨범 그룹핑 개념 없음 (flat list) |
| 85 | 주보 | 소식·공지 > 주보 게시판 | `files` 컬럼(JSON)에 첨부파일 |
| 86 | 묻고 답하기 | 소식·공지 > 묻고 답하기 | 답변은 `gh_new_board_content`가 아니라 `gh_new_board_comment`에 등록 |
| 87 | 유튜브 | 미디어 > 교회 영상 | |
| 88 | 행사사진 | 미디어 > 사역&활동 사진 | 89(사람들)도 후보였으나 미디어 화면엔 탭이 1개뿐이라 88만 사용, 89는 이번 범위에서 미사용(추후 "봉사자/사역자 소개" 등에 쓸 수 있게 남겨둠) |
| **94** | **상담신청** | 소식·공지 > 상담 신청 | **신규 추가** — 공개 홈페이지에서 방문자가 제출(관리자는 조회/처리만, 등록 없음) |
| **95** | **성도한마디** | 소식·공지 > 성도한마디 | **신규 추가** — 공개 홈페이지에서 방문자가 제출(관리자는 승인/숨김만, 등록 없음) |
| **96** | **설교영상** | 미디어 > 설교 영상 | **신규 추가** — 87(유튜브/교회영상)과 분리, 설교자·본문·시리즈 등 전용 필드 사용 |

94/95/96은 `usp_church_view`가 아직 읽지 않음 — 공개 홈페이지(03_gh_www_api)에서 이 탭들을
노출하려면 그쪽 프로시저/컨트롤러에 별도로 반영 필요(이번 작업 범위 밖, 교적 관리자 화면만).

> **[2026-07-24 후속] board_no 91~98 → 90~97 일괄 renumber**: 로컬 dev DB의 `gh_new_board_type`이
> dev/live DB(90=기업채용까지만 존재)와 어긋나 91부터 시작하고 있던 드리프트를 사용자 요청으로 교정 —
> 상담신청/성도한마디/설교영상은 최초 INSERT 시점엔 95/96/97 이었으나 최종 94/95/96 으로 확정됨. 이
> 문서와 `app/Constants/ChurchBoardNo.php`(COUNSELING/TESTIMONY/SERMON)를 최종 번호로 갱신했다.
> 아래 INSERT 문·확인 쿼리도 최종 번호 기준으로 갱신했으므로 다른 DB 인스턴스에 적용 시 그대로
> 실행하면 된다(94/95/96으로 바로 INSERT, 이후 renumber 불필요).

## gh_new_board_content 필드 재사용 매핑

공통: `content_no`(PK) / `board_no` / `church_no` / `title` / `content` / `status`(ALIVE/DEAD, soft delete) /
`registered` / `updated` / `admin_no`(작성 관리자, 교적에서 작성하는 글은 `account_no` 대신 이 컬럼 사용).

| 화면 | 매핑 |
|---|---|
| 교회 소식 | `title`, `content`(요약), `is_top`(고정 📌) |
| 주보 | `title`(주차 라벨), `registered`(날짜, insert 시 임의 지정 가능), `files`(JSON 첨부파일 배열) |
| 묻고 답하기 | `title`(질문), `int_val1`(1/0, 공개여부), `text_val1`(관리자 답변) — 원래 `usp_church_view`처럼 `gh_new_board_comment`에 답변을 넣으려 했으나, 그 테이블의 `account_no`가 `gh_account` FK(NOT NULL, 기본값 없음)라 관리자 작성 댓글을 넣을 계정이 없어 실패(SQLSTATE 23000). 대신 같은 글의 `text_val1`에 직접 저장 |
| 상담신청 | `title`, `content`, `contact_name`, `phone1`, `email`, `str_val1`(상태: 접수/처리중/완료), `int_val1`(담당 admin_no), `text_val1`(내부메모), `text_val2`(회신 내용) |
| 성도한마디 | `content`, `nickname`(작성자, "익명" 가능), `is_selected`('Y'=승인/'N'=대기, QnA 채택과 동일 컬럼 재사용) |
| 설교영상 | `title`, `youtubeUrl`, `str_val1`(설교자), `str_val2`(설교본문), `str_val3`(시리즈), `str_val4`(태그, 콤마구분), `is_top`(대표 설교 수동 지정) — "대표 설교 자동/수동" 전환 자체는 게시글이 아니라 교회 단위 설정이라 `reg_church_settings`(`media_sermon_featured_mode`)에 저장 |
| 교회 영상 | `title`, `content`(설명), `youtubeUrl`, `category1`(소개/찬양/선교/간증/기타) |
| 사진 갤러리 | `title`(앨범명), `content`(설명), `rep_img`(대표이미지 URL), `files`(JSON, 사진 URL 배열 — 앨범 개념이 없는 테이블이라 게시글 1건 = 앨범 1개로 흉내냄), `int_val1`(1/0, 공개여부) |
| 사역&활동 사진 | `title`(사역명), `content`(설명), `rep_img`(대표이미지 URL), `files`(JSON, 사진 URL 배열) |

**이미지 업로드**: 갤러리/사역 사진은 실제 파일 업로드가 핵심이라(주보의 prompt 기반 URL 입력과 달리)
`S3FileHelper`를 재사용한 `POST /v4/media/uploadImage` 범용 업로드 엔드포인트를 추가 — 업로드 후 반환된
URL을 `rep_img`/`files`에 저장. `gh_file` 테이블 등록 없이 S3 URL만 직접 저장(교회 프로필의 file_no
참조 패턴과 달리, 이 게시판 필드들은 애초에 URL 문자열 컬럼이라 그대로 재사용).

## gh_new_board_type 신규 INSERT (실행 완료 — 이 세션의 DB 연결로 직접 실행함, 사용자 승인)

```sql
INSERT INTO gh_new_board_type
  (board_no, sort_no, category_no, name, rep_img, pre_message, lbs, price, question,
   non_read, semi_read, mem_read, semi_write, mem_write, semi_comment, mem_comment,
   church_admin, status, registered, updated, board_list_type, point, imagelink, regist_only)
VALUES
  (94, 8, 10, '상담신청', 'N', '', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'Y', 'ALIVE', NOW(), NOW(), 1, 0, '', 0),
  (95, 9, 10, '성도한마디', 'N', '', 'N', 'N', 'N', 'Y', 'Y', 'Y', 'Y', 'Y', 'N', 'N', 'Y', 'ALIVE', NOW(), NOW(), 1, 0, '', 0),
  (96, 10, 10, '설교영상', 'Y', '', 'N', 'N', 'N', 'Y', 'Y', 'Y', 'N', 'N', 'Y', 'Y', 'Y', 'ALIVE', NOW(), NOW(), 6, 0, '', 0);
```

⚠️ **다른 DB 인스턴스(원격 dev 서버 등)가 있다면 거기에도 동일 INSERT 필요** — 이 Laravel 앱이
연결하는 DB(이 세션에서 실행한 곳)에만 적용됨.

확인 쿼리:

```sql
SELECT board_no, name, category_no, status FROM gh_new_board_type WHERE board_no IN (94, 95, 96);
```
