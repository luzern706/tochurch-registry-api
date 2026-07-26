# reg_members / reg_member_profiles 테이블 변경 이력

> HeidiSQL에서 직접 실행. Laravel 마이그레이션 사용 금지.

---

## [2026-06-28] reg_members 정렬 성능 인덱스 추가

교인 목록 API (`/v4/member/getList`) 의 `sort_by=name` / `sort_by=birth_date` 정렬 성능 개선.

**배경:**
- `name` (VARCHAR), `birth_date` (DATE) 컬럼에 인덱스가 없어 ORDER BY 시 풀스캔 발생
- `LIKE '%keyword%'` 검색은 앞 와일드카드로 인해 인덱스 효과 없음 (검색은 풀스캔 유지)
- ORDER BY 정렬에만 인덱스 적용됨

```sql
ALTER TABLE `reg_members`
  ADD INDEX `idx_name`       (`name`),
  ADD INDEX `idx_birth_date` (`birth_date`);
```

**적용 후 sort_by 동작:**

| sort_by 값 | ORDER BY | 방향 |
|---|---|---|
| `created_at` (기본값) | `id` | DESC |
| `name` | `name` | ASC |
| `birth_date` | `birth_date` | ASC |
