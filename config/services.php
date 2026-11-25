<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'kakao' => [
        'rest_api_key' => env('KAKAO_REST_API_KEY'),
    ],

    // NHN Cloud Email — 교적 비밀번호 재설정 링크 메일 (02_gh_admin_api·03_gh_www_api 와 같은 NHN_EMAIL_* 값). 5개 값이 모두 있어야 발송된다
    'nhn_email' => [
        'base_url' => env('NHN_EMAIL_BASE_URL', 'https://email.api.nhncloudservice.com'),
        'app_key' => env('NHN_EMAIL_APP_KEY'),
        'secret_key' => env('NHN_EMAIL_SECRET_KEY'),
        'sender_address' => env('NHN_EMAIL_SENDER_ADDRESS'),
        'contact_email' => env('NHN_EMAIL_CONTACT_ADDRESS'),
        'sender_name' => env('NHN_EMAIL_SENDER_NAME', '교회로'),
        'logo_url' => env('NHN_EMAIL_LOGO_URL'),
        'timeout' => 10,
    ],

    // 교적 비밀번호 재설정(로그인 화면 「비밀번호 찾기」) — 메일 링크가 여는 화면(/set-password) 전체 주소와 제한값
    'registry_password_reset' => [
        'front_url' => env('REGISTRY_SETUP_FRONT_URL'),
        'valid_hours' => 24,
        'min_interval_seconds' => 60, // 같은 계정 연속 요청 간격
        'max_per_hour' => 5,          // 같은 계정 1시간 최대 발송 횟수
    ],

];
