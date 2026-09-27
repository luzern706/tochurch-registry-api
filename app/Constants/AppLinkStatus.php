<?php

namespace App\Constants;

/**
 * 교적 ↔ 교회로 앱 계정 연결 상태 (저장 컬럼 없음 — AppLinkStatusHelper 가 매번 파생)
 *
 *  unlinked  미가입   : 연결 없음 + 휴대폰이 일치하는 미연동 교회로 계정 없음
 *  pending   가입대기 : 연결 없음 + (초대 링크로 가입해 관리자 승인 대기 중 OR 같은 교회 미연동 계정 중 휴대폰(숫자만 비교) 일치 1개)
 *  duplicate 중복의심 : 연결 없음 + 일치 계정 2개 이상
 *  mismatch  불일치   : churchero_user_id 연결됨 + 계정 이름·휴대폰이 교적과 다름(또는 계정 없음)
 *  linked    연동완료 : churchero_user_id 연결됨 + 일치
 *  released  연동해제 : 연결 없음 + unlinked_at 이력 있음 (후보 계정이 있으면 가입대기/중복의심이 우선)
 */
class AppLinkStatus
{
    const UNLINKED  = 'unlinked';
    const PENDING   = 'pending';
    const DUPLICATE = 'duplicate';
    const MISMATCH  = 'mismatch';
    const LINKED    = 'linked';
    const RELEASED  = 'released';

    public static function labels(): array
    {
        return [
            self::UNLINKED  => '미가입',
            self::PENDING   => '가입대기',
            self::LINKED    => '연동완료',
            self::MISMATCH  => '불일치',
            self::DUPLICATE => '중복의심',
            self::RELEASED  => '연동해제',
        ];
    }
}
