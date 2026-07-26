# 교적 샘플 데이터 이식 가이드

로컬 DB의 교적(reg_*) 실제 데이터를 다른 환경(dev 등)으로 옮길 때 사용하는 이식용 시드 스크립트 안내.

- **시드 파일:** [`seed_registry_full.sql`](seed_registry_full.sql)
- **원본:** local DB `church_id = 33632` 실제 데이터 덤프
- **현재 설정값:** `SET @church_id = 19715;` (행복샘교회 2 dev)

---

## 1. 무엇이 들어있나

FK 순서대로 11개 테이블, 총 481건.

| 테이블 | 건수 | 테이블 | 건수 |
|---|---|---|---|
| reg_organizations | 11 | reg_visit_records | 31 |
| reg_members | 53 | reg_prayer_records | 10 |
| reg_member_profiles | 53 | reg_education_courses | 30 |
| reg_member_organizations | 50 | reg_education_sessions | 51 |
| reg_services | 8 | reg_education_records | 3 |
| reg_attendance_records | 241 | | |

**제외 항목**
- `church_id=1` orphan 조직 5개 (과거 시드 찌꺼기)
- `reg_audit_logs` (앱 사용 시 자동 재생성)
- 데이터 없는 테이블: reg_families, reg_service_teams, reg_service_members, reg_offering_records, reg_messages

---

## 2. 설계 원리

- **`id` 값을 원본 그대로 보존** → member ↔ profile ↔ 조직 ↔ 출석 ↔ 예배 ↔ 교육 간 FK 정합성이 그대로 유지됨 (자연키 재매핑 불필요).
- **`church_id` 값만 `@church_id` 변수로 치환** → local/dev가 동일하고 이 한 줄만 바꾸면 됨.
  - 원본 `33632` 값 435건이 전부 `@church_id`로 치환됨 (문자열 안이 아닌 순수 컬럼값만 안전 치환).
- `churchero_user_id`는 전부 NULL → 교회로 `users` 테이블 의존성 없음.

---

## 3. ⚠️ 핵심 전제 — church_id 는 관리자 계정의 church_no 와 일치해야 함

앱은 관리자 로그인 시 JWT의 `churchId`를 **관리자 계정의 `church_no`**로 세팅하고
([AuthService.php](../../app/Services/AuthService.php) `$churchId = (int) $admin->church_no;`),
모든 reg_* 조회를 이 값으로 스코프한다.

> 즉 **seed 의 `@church_id` == 로그인할 관리자 계정의 `gh_church_admin.church_no`** 여야 데이터가 앱에 보인다.

`gh_church_admin`은 `admin_no`(계정 번호)와 `church_no`(소속 교회)가 별개 컬럼임에 주의.

**적재 전 확인 쿼리 (대상 DB에서 실행):**

```sql
-- 로그인할 관리자 계정의 소속 교회(church_no) 확인
SELECT admin_no, church_no, id, name FROM gh_church_admin WHERE admin_no = <관리자 계정 no>;
```

- 여기서 나온 `church_no` 값을 seed 의 `SET @church_id` 에 넣으면 된다.

### dev 적재 사례 (2026-07)
- 대상 교회: 행복샘교회 2 dev → `church_no = 19715`
- 로그인 관리자 계정: `admin_no = 19682`
- → `SET @church_id = 19715;` 로 적재 완료, 정상 확인.

---

## 4. 실행 방법

1. 대상 DB에서 위 3번 확인 쿼리로 `church_no` 확정.
2. `seed_registry_full.sql` 상단의 값 설정:
   ```sql
   SET @church_id = <대상 church_no>;
   ```
3. (필요 시) 파일 상단 `-- USE ...;` 주석을 대상 DB명에 맞게 수정.
4. 대상 DB 선택 후 파일 **전체 실행**.
5. 파일 끝의 확인 쿼리로 건수 검증.

---

## 5. 실행 전 체크리스트

- [ ] `@church_id` = 로그인 관리자 계정의 `church_no` 와 일치하는가
- [ ] 대상 DB의 해당 `church_id` `reg_*` 데이터가 **비어 있는가**
      → id를 원본 보존하므로 동일 id가 있으면 **PK 충돌**.
      재적재/초기화 시 파일 상단 **CLEAN 블록**(주석 처리됨) 주석 해제 후 실행.
- [ ] `member_no`(`M-2026001`~`M-2026050`)가 대상 DB 어느 교회에도 **중복되지 않는가**
      → `member_no`는 **전역 UNIQUE**. 이미 있으면 충돌.

---

## 6. 시드 파일 재생성 방법 (참고)

원본 데이터가 바뀌어 시드를 다시 뽑아야 할 때:

1. local DB에서 `church_id=33632` 대상으로 각 reg_* 테이블을
   `mysqldump --no-create-info --complete-insert --skip-extended-insert --skip-lock-tables --where="church_id=33632"` 로 덤프.
   (child 테이블 profiles/member_organizations/education_records 는 `member_id IN (SELECT id FROM reg_members WHERE church_id=33632)` 로 필터)
2. 순수 컬럼값 `33632` 를 `@church_id` 로 치환: `sed -E 's/([,(])33632([,)])/\1@church_id\2/g'`
   (문자열 내부가 아닌 정수 컬럼값만 매칭되어 안전)
3. FK 순서대로 concat + 헤더(`SET @church_id`, `SET FOREIGN_KEY_CHECKS=0`) / 푸터(`=1`, 확인 쿼리) 부착.

> 검증은 빈 임시 DB에 라이브 스키마를 `--no-data` 로 복제한 뒤 시드를 실행해
> 건수·FK 조인·orphan 0건을 확인하면 된다.
