# reg_offering_records 테이블 변경 제안 (미실행)

> HeidiSQL에서 직접 실행. Laravel 마이그레이션 사용 금지.
> 2026-08-08, 헌금관리(`/finance/donation`)·헌금입력(`/finance/donation/input`) 프론트 연동 중
> 원래 mock 화면이 가정한 필드 중 실제 `reg_offering_records`에 없는 3가지를 발견 —
> 화면은 mock 그대로 유지하고 해당 항목만 "데이터 없음"으로 표시해 둔 상태.
> 아래는 실제로 그 항목들을 채우고 싶을 때의 스키마 변경 제안이며, **아직 실행하지 않았음**.

---

## 1. 예배 연동 — `service_id` 컬럼 (전체내역 "예배" 컬럼·필터)

현재 `reg_offering_records`는 어떤 예배에서 걷힌 헌금인지 연결할 방법이 없음.
교적의 출석 관리가 이미 `gh_church_timetable`(예배 정의, [gh_church_alter.md](gh_church_alter.md) 참고)을
단일 소스로 쓰고 있으므로 동일하게 연결하는 것을 제안.

```sql
ALTER TABLE `reg_offering_records`
  ADD COLUMN `service_id` BIGINT(20) NULL DEFAULT NULL COMMENT '예배 — gh_church_timetable.timetable_no'
  AFTER `offer_date`;
```

- 적용되면: 전체내역 탭의 "예배 선택" 필터, "예배" 컬럼이 실동작
- 헌금입력 화면에서 예배 선택 UI가 추가로 필요 (현재는 날짜만 선택)

---

## 2. 비고 — `note` 컬럼 (교인별 조회 "비고" 컬럼)

교인별 조회 탭 원본 mock에 "비고"(예: "생일 감사") 컬럼이 있었으나 대응 필드 없음.

```sql
ALTER TABLE `reg_offering_records`
  ADD COLUMN `note` VARCHAR(200) NULL DEFAULT NULL COMMENT '비고 (예: 생일 감사)'
  AFTER `ledger`;
```

- 적용되면: 헌금입력 화면에 비고 입력란 추가, 목록/교인별 조회에 표시

---

## 3. 정산 상태 — `status` 컬럼 (전체내역 "상태" 완료/보완가능)

원본 mock은 "총액만 입력되고 개인별로 아직 안 나뉜 헌금"을 "⚠ 보완 가능"으로 표시했음.
지금은 `member_id`가 null이면 화면에서 "총액" 배지로만 구분하고(입력방식 컬럼),
"보완이 끝났다/아직 안 끝났다"를 나타내는 상태값 자체는 없음.

```sql
ALTER TABLE `reg_offering_records`
  ADD COLUMN `status` ENUM('completed','pending') NOT NULL DEFAULT 'completed'
    COMMENT '정산 상태 — pending: 총액만 입력되어 개인별 보완 필요'
  AFTER `note`;
```

- **결정 필요**: 이 상태를 누가/언제 바꾸는지 업무 규칙 정의가 먼저 필요함
  (예: `member_id`가 null인 신규 등록 시 자동으로 `pending`? 이후 개인별로 다시 입력하면 자동으로 `completed`로 바뀌는 로직이 있어야 하는지, 아니면 관리자가 수동으로 상태만 바꾸는지)
- 우선순위가 낮다고 판단해 이번에는 컬럼 제안만 하고 화면에서는 "—"로 비워둠

---

## 참고 — 스키마 변경 없이 이번에 처리한 것들

- **입력 방식(개인별/총액/혼합) 배지**: `member_id` 유무로 개인별/총액만 derive, "혼합"은
  한 레코드(row) 단위로는 의미가 없어 제외함(같은 날짜·항목·원장으로 묶인 여러 레코드 중
  일부만 개인 지정된 경우를 "혼합"으로 보려면 위 `status` 설계와 함께 별도 그룹핑 개념이 필요)
- **기간별 조회 피벗 테이블**: 새 컬럼 없이 `ReportRepository::getOfferingCategoryMonthlyPivot()`
  쿼리를 추가해 월×항목 교차표를 구현(`ReportService::getOfferingStats` 응답에 `pivot` 필드로 포함)
- **기간별 조회 카테고리 컬럼**: 원본 mock은 "십일조/감사헌금/선교헌금/건축헌금/주일헌금" 5개
  고정 컬럼이었으나, 실제 `category`는 자유 입력 텍스트라 교회마다 다름 — 컬럼을 실데이터 상위
  5개 항목으로 동적 구성함 (스키마 변경 아님, UI 설계상 변경)

## 아직 스키마 변경으로 제안하지 않은 것

- **기부금영수증 발행** ("교인별 조회" 탭 버튼): 컬럼 하나로 될 일이 아니라 별도 문서 생성
  기능 설계가 필요해 보여 이번엔 버튼만 비활성 상태로 유지. 필요하면 별도로 논의
