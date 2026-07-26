# 12. 보고서/통계 (Report)

URL prefix: `{{ base_url }}/v4/report`
모두 **인증 필요**, **조회 전용** (DB 변경 없음)

| # | 메서드 | URL | 설명 |
|---|---|---|---|
| 1 | POST | `/v4/report/getMemberStats` | 교인 통계 (총원, 성별, 연령대, 직분, 등급, 상태) |
| 2 | POST | `/v4/report/getAttendanceStats` | 출석 통계 (출석률, 일별 추이, 예배/조직 필터) |
| 3 | POST | `/v4/report/getAttendanceStatsByOrg` | 조직별 출석 통계 (조직별 출석률/평균 인원) |
| 4 | POST | `/v4/report/getStatsByService` | 예배별 출석 통계 (예배별 평균 출석/출석률) |
| 5 | POST | `/v4/report/getMemberRateDistribution` | 개인 출석률 분포 (5구간 버킷) |
| 6 | POST | `/v4/report/getOfferingStats` | 헌금 통계 (총액, 카테고리별, 월별 추이) |
| 7 | POST | `/v4/report/getVisitStats` | 심방 통계 (건수, 심방자 Top 10, 유형별) |
| 8 | POST | `/v4/report/getDashboard` | 종합 요약 (교인 수, 이번 주 출석률, 이번 달 헌금/심방) |

---

## 1. POST `/v4/report/getMemberStats`

### Request Body
```json
{}
```
(파라미터 없음 — 인증된 사용자의 church_id 기준)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 156,
    "by_gender": [
      { "gender": "M", "count": 72 },
      { "gender": "F", "count": 84 }
    ],
    "by_age_group": [
      { "age_group": 10, "count": 12 },
      { "age_group": 20, "count": 28 },
      { "age_group": 30, "count": 35 },
      { "age_group": 40, "count": 40 },
      { "age_group": 50, "count": 25 },
      { "age_group": 60, "count": 16 }
    ],
    "by_position": [
      { "position": "성도", "count": 100 },
      { "position": "집사", "count": 30 },
      { "position": "장로", "count": 8 },
      { "position": "권사", "count": 12 }
    ],
    "by_attendance_grade": [
      { "attendance_grade": "우수", "count": 90 },
      { "attendance_grade": "보통", "count": 45 },
      { "attendance_grade": "미흡", "count": 21 }
    ],
    "by_status": [
      { "status": "active", "count": 140 },
      { "status": "inactive", "count": 10 },
      { "status": "unknown", "count": 6 }
    ]
  }
}
```

---

## 2. POST `/v4/report/getAttendanceStats`

### Request Body
```json
{
  "from_date": "2026-05-01",
  "to_date": "2026-05-31",
  "service_id": 1,
  "organization_id": null
}
```

**파라미터:**
- `from_date`, `to_date` (date, required, to_date >= from_date)
- `service_id` (integer, min:1, optional)
- `organization_id` (integer, min:1, optional)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "from_date": "2026-05-01",
    "to_date": "2026-05-31",
    "service_id": 1,
    "organization_id": null,
    "by_status": [
      { "status": "present", "count": 480 },
      { "status": "absent",  "count": 60 }
    ],
    "attendance_rate": 88.89,
    "present_count": 480,
    "absent_count": 60,
    "daily_trend": [
      { "attend_date": "2026-05-03", "present_count": 120, "absent_count": 15, "total_count": 135 },
      { "attend_date": "2026-05-10", "present_count": 122, "absent_count": 14, "total_count": 136 },
      { "attend_date": "2026-05-17", "present_count": 119, "absent_count": 16, "total_count": 135 },
      { "attend_date": "2026-05-24", "present_count": 119, "absent_count": 15, "total_count": 134 }
    ]
  }
}
```
- `attendance_rate`: `present / (present + absent) * 100`. 분모 0일 때 `null`.

---

## 3. POST `/v4/report/getAttendanceStatsByOrg`

### Request Body
```json
{
  "from_date": "2026-05-01",
  "to_date": "2026-05-31",
  "service_id": null,
  "organization_id": null
}
```

**파라미터:**
- `from_date`, `to_date` (date, required)
- `service_id` (integer, optional) — 특정 예배 필터
- `organization_id` (integer, optional) — 해당 조직 + 하위 조직만 필터

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "list": [
      {
        "org_id": 1,
        "org_name": "청년부",
        "present_count": 120,
        "absent_count": 20,
        "member_count": 35,
        "date_count": 4,
        "avg_rate": 85.7,
        "avg_present": 30.0
      }
    ]
  }
}
```
- `org_name`: 상위 조직 있을 시 `"상위 > 하위"` 형식
- `avg_rate`: `present / (present + absent) * 100`, 분모 0이면 `null`
- `avg_present`: `present_count / date_count`, date_count 0이면 `null`

---

## 4. POST `/v4/report/getStatsByService`

### Request Body
```json
{
  "from_date": "2026-05-01",
  "to_date": "2026-05-31"
}
```

**파라미터:**
- `from_date`, `to_date` (date, required)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "list": [
      { "service_id": 1, "service_name": "주일 1부", "avg_present": 142.5, "rate": 89.1 },
      { "service_id": 2, "service_name": "주일 2부", "avg_present": 98.0,  "rate": 83.2 }
    ]
  }
}
```
- `avg_present`: `present_count / date_count`, 날짜 없으면 `null`
- `rate`: `present / (present + absent) * 100`, 분모 0이면 `null`

---

## 5. POST `/v4/report/getMemberRateDistribution`

### Request Body
```json
{
  "from_date": "2026-05-01",
  "to_date": "2026-05-31"
}
```

**파라미터:**
- `from_date`, `to_date` (date, required)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "total": 156,
    "list": [
      { "bucket": "90_plus",  "count": 60, "percent": 38.5 },
      { "bucket": "80_89",    "count": 30, "percent": 19.2 },
      { "bucket": "70_79",    "count": 20, "percent": 12.8 },
      { "bucket": "60_69",    "count": 15, "percent": 9.6  },
      { "bucket": "under_60", "count": 20, "percent": 12.8 },
      { "bucket": "no_record","count": 11, "percent": 7.1  }
    ]
  }
}
```
- `total`: 전체 활성 교인 수
- `bucket` 값: `90_plus` / `80_89` / `70_79` / `60_69` / `under_60` / `no_record`

---

## 6. POST `/v4/report/getOfferingStats`  <!-- 구 3번 -->

### Request Body
```json
{
  "from_date": "2026-01-01",
  "to_date": "2026-05-31",
  "category": null
}
```

**파라미터:**
- `from_date`, `to_date` (date, required)
- `category` (string, max:50, optional)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "from_date": "2026-01-01",
    "to_date": "2026-05-31",
    "category": null,
    "count": 240,
    "total_amount": 32500000,
    "by_category": [
      { "category": "주일헌금", "count": 120, "total_amount": 18000000 },
      { "category": "감사헌금", "count": 80,  "total_amount": 9500000  },
      { "category": "선교헌금", "count": 40,  "total_amount": 5000000  }
    ],
    "monthly_trend": [
      { "ym": "2026-01", "count": 50, "total_amount": 6500000 },
      { "ym": "2026-02", "count": 45, "total_amount": 5800000 },
      { "ym": "2026-03", "count": 48, "total_amount": 6200000 },
      { "ym": "2026-04", "count": 50, "total_amount": 7000000 },
      { "ym": "2026-05", "count": 47, "total_amount": 7000000 }
    ]
  }
}
```

---

## 4. POST `/v4/report/getVisitStats`

### Request Body
```json
{
  "from_date": "2026-01-01",
  "to_date": "2026-05-31",
  "visitor_member_id": null
}
```

**파라미터:**
- `from_date`, `to_date` (date, required)
- `visitor_member_id` (integer, min:1, optional)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "from_date": "2026-01-01",
    "to_date": "2026-05-31",
    "visitor_member_id": null,
    "count": 85,
    "top_visitors": [
      { "visitor_member_id": 2, "visitor_name": "김장로", "count": 24 },
      { "visitor_member_id": 5, "visitor_name": "박집사", "count": 18 }
    ],
    "by_type": [
      { "visit_type": "annual", "count": 60 },
      { "visit_type": "event",  "count": 25 }
    ]
  }
}
```

---

## 5. POST `/v4/report/getDashboard`

### Request Body
```json
{}
```
(파라미터 없음 — 인증된 사용자의 church_id 기준, 이번 주/이번 달 자동 산출)

### Response — 성공
```json
{
  "status": "success", ..., "data": {
    "as_of_date": "2026-05-18",
    "member_total": 156,
    "this_week": {
      "from": "2026-05-18",
      "to": "2026-05-18",
      "attendance_rate": null,
      "present_count": 0,
      "absent_count": 0
    },
    "this_month": {
      "from": "2026-05-01",
      "to": "2026-05-31",
      "offering_total": 7000000,
      "offering_count": 47,
      "visit_count": 18
    }
  }
}
```

**기간 계산 (서버 시각 기준):**
- `this_week.from` = 이번 주 월요일, `this_week.to` = 오늘
- `this_month.from` = 이번 달 1일, `this_month.to` = 이번 달 말일
