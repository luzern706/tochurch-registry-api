<?php

namespace App\Constants;

/**
 * 권한 매트릭스(설정 > 권한설정) 전용 대메뉴 > 중메뉴 > 소메뉴(실제 화면) 트리.
 *
 * GNB 실제 내비게이션 구조(HeaderGnb.jsx NAV) 기준 — AuditMenuCode의 내부
 * 기능-도메인 분류(ORGANIZATION/WORSHIP/FAMILY/CODE 등)와는 다르다. 저 4개는
 * 화면상 별도 메뉴가 아니라 "설정"(교직설정) 또는 "교인" 페이지 내부 탭/기능이라
 * 여기서는 별도 행을 만들지 않고 SETTING/MEMBER 권한에 흡수시킨다.
 *
 * 저장/실제 권한 판단 단위는 소메뉴(페이지, leaf) — reg_role_permissions.page_code.
 * 중메뉴는 화면 그룹핑 + 백엔드 API 미들웨어(CheckPermissionMiddleware)가 라우트를
 * 보호하는 단위로만 쓰인다(하위 페이지 중 하나라도 접근 가능하면 그 메뉴의 API 전체 허용).
 * 하위 페이지가 없는 메뉴(게시판, 재정/관리 미구현 항목)는 메뉴 코드 자신이 유일한
 * page_code 가 된다.
 *
 * finance(재정)/management(관리) 대메뉴는 대부분 백엔드가 아직 없어 권한 매트릭스에는
 * 표시되지만(향후 대비, 사용자 승인) 미들웨어가 걸린 라우트는 없다 — 토글은 저장되지만
 * 아직 아무 API도 차단하지 않는다.
 */
class PermissionMenuTree
{
    public static function tree(): array
    {
        return [
            [
                'key' => 'member', 'label' => '교적',
                'menus' => [
                    self::menu(AuditMenuCode::MEMBER, [
                        ['code' => 'MEMBER_LIST',     'label' => '교인목록'],
                        ['code' => 'MEMBER_APPROVAL', 'label' => '가입승인'],
                    ]),
                    self::menu(AuditMenuCode::ATTENDANCE, [
                        ['code' => 'ATTENDANCE_INPUT',      'label' => '출석입력'],
                        ['code' => 'ATTENDANCE_STATUS',     'label' => '출석현황'],
                        ['code' => 'ATTENDANCE_STATISTICS', 'label' => '출석통계'],
                    ]),
                    self::menu(AuditMenuCode::VISIT, [
                        ['code' => 'VISIT_LIST',         'label' => '심방목록'],
                        ['code' => 'VISIT_REGISTER',     'label' => '심방등록'],
                        ['code' => 'VISIT_TARGETS',      'label' => '미심방목록'],
                        ['code' => 'VISIT_AUTO_TARGETS', 'label' => '심방대상관리'],
                    ]),
                    self::menu(AuditMenuCode::PRAYER, [
                        ['code' => 'PRAYER_LIST',     'label' => '기도목록'],
                        ['code' => 'PRAYER_REGISTER', 'label' => '기도등록'],
                    ]),
                    self::menu(AuditMenuCode::EDUCATION, [
                        ['code' => 'EDUCATION_CURRICULUM', 'label' => '교육과정 목록'],
                        ['code' => 'EDUCATION_REGISTER',   'label' => '교육과정 등록'],
                        ['code' => 'EDUCATION_ONGOING',    'label' => '진행중 교육'],
                        ['code' => 'EDUCATION_HISTORY',    'label' => '교육 이력'],
                        ['code' => 'EDUCATION_STATISTICS', 'label' => '교육출석통계'],
                    ]),
                    self::menu(AuditMenuCode::VOLUNTEER, [
                        ['code' => 'VOLUNTEER_STATUS',   'label' => '봉사현황'],
                        ['code' => 'VOLUNTEER_REGISTER', 'label' => '봉사등록'],
                        ['code' => 'VOLUNTEER_HISTORY',  'label' => '봉사이력'],
                    ]),
                    self::menu(AuditMenuCode::REPORT, [
                        ['code' => 'REPORT_WEEKLY',        'label' => '주간보고'],
                        ['code' => 'REPORT_COMPREHENSIVE', 'label' => '종합보고'],
                    ]),
                    self::menu(AuditMenuCode::MESSAGE, [
                        ['code' => 'MESSAGE_PUSH',      'label' => '푸시알림'],
                        ['code' => 'MESSAGE_COMPOSE',   'label' => '문자발송'],
                        ['code' => 'MESSAGE_LOG',       'label' => '발송로그'],
                        ['code' => 'MESSAGE_SETTINGS',  'label' => '문자설정'],
                        ['code' => 'MESSAGE_TEMPLATES', 'label' => '템플릿'],
                    ]),
                    self::menu(AuditMenuCode::BOARD, []),
                    self::menu(AuditMenuCode::SETTING, [
                        ['code' => 'SETTING_PROFILE',  'label' => '기본정보'],
                        ['code' => 'SETTING_CRITERIA', 'label' => '교직설정'],
                    ]),
                ],
            ],
            [
                'key' => 'finance', 'label' => '재정',
                'menus' => [
                    self::menu(AuditMenuCode::OFFERING, []),
                    self::menu(AuditMenuCode::EXPENSE, []),
                    self::menu(AuditMenuCode::FINANCE_STATS, []),
                    self::menu(AuditMenuCode::BUDGET, []),
                    self::menu(AuditMenuCode::FINANCE_REPORT, []),
                    self::menu(AuditMenuCode::FINANCE_SETTING, []),
                ],
            ],
            [
                'key' => 'management', 'label' => '관리',
                'menus' => [
                    self::menu(AuditMenuCode::WEBSITE, []),
                ],
            ],
        ];
    }

    /** 하위 페이지가 없으면 메뉴 코드 자신을 유일한 페이지로 사용(단일 화면) */
    private static function menu(string $code, array $pages): array
    {
        return [
            'code'  => $code,
            'label' => AuditMenuCode::label($code),
            'pages' => $pages ?: [['code' => $code, 'label' => AuditMenuCode::label($code)]],
        ];
    }

    /** 실제 저장/판단 단위인 전체 소메뉴(페이지) 코드 목록 — saveRole 검증, getMatrix 순회용 */
    public static function allPages(): array
    {
        $codes = [];
        foreach (self::tree() as $group) {
            foreach ($group['menus'] as $menu) {
                foreach ($menu['pages'] as $page) {
                    $codes[] = $page['code'];
                }
            }
        }
        return $codes;
    }

    /** 소메뉴(페이지) 코드 → 소속 중메뉴 코드 (기본 정책 조회용) */
    public static function parentOf(string $pageCode): ?string
    {
        foreach (self::tree() as $group) {
            foreach ($group['menus'] as $menu) {
                foreach ($menu['pages'] as $page) {
                    if ($page['code'] === $pageCode) {
                        return $menu['code'];
                    }
                }
            }
        }
        return null;
    }

    /** 중메뉴 코드 → 하위 소메뉴(페이지) 코드 목록 — 백엔드 API 미들웨어의 메뉴 단위 체크용 */
    public static function pagesOfMenu(string $menuCode): array
    {
        foreach (self::tree() as $group) {
            foreach ($group['menus'] as $menu) {
                if ($menu['code'] === $menuCode) {
                    return array_column($menu['pages'], 'code');
                }
            }
        }
        return [];
    }
}
