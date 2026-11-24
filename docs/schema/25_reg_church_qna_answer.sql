-- ============================================================
-- 묻고답하기 오피스 답변 시각 기록 — 두레이 오픈후-8(K-07-3), 관리자 교회 상세 「기한 내(48시간) 응답률」
--   답변은 질문 글(gh_new_board_content)의 text_val1 에 텍스트로만 저장돼 답변 시각이 없었다.
--   오피스(04 registry API)에서 답변을 등록할 때 시각을 이 테이블에 남긴다(첫 답변 시각 유지, 답변 삭제 시 행 삭제).
--   이 테이블이 생기기 전에 달린 답변은 시각을 알 수 없어 기한 내 응답률 계산에서 제외된다.
--   스테이징 먼저, 확인 후 운영. 테이블이 없어도 답변 등록·삭제는 정상 동작한다(시각만 남지 않음).
-- ============================================================

-- STEP 0. 적용 전 확인 — 기대: 0행
SHOW TABLES LIKE 'reg_church_qna_answer';

-- STEP 1.
CREATE TABLE `reg_church_qna_answer` (
  `content_no`  INT UNSIGNED NOT NULL COMMENT '묻고답하기 질문 — gh_new_board_content.content_no',
  `church_no`   INT UNSIGNED NOT NULL COMMENT '질문이 속한 교회',
  `answered_at` DATETIME NOT NULL COMMENT '오피스에서 처음 답변을 등록한 시각',
  PRIMARY KEY (`content_no`),
  KEY `idx_qna_answer_church` (`church_no`, `answered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='묻고답하기 답변 시각(기한 내 응답률용)';

-- STEP 2. 확인 — 기대: 1행
SHOW TABLES LIKE 'reg_church_qna_answer';

-- 롤백(되돌릴 때만)
-- DROP TABLE IF EXISTS `reg_church_qna_answer`;
