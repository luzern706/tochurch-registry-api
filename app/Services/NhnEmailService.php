<?php

namespace App\Services;

use App\Helpers\LogHelper;
use Illuminate\Support\Facades\Http;

/**
 * NHN Cloud Email 인증 메일(auth-mail) 발송 — 교적 비밀번호 재설정 링크 전용.
 * 02_gh_admin_api 의 NhnEmailService 와 같은 구조(키·발신 주소·로고·문의처도 같은 NHN_EMAIL_* 값).
 * 템플릿은 resources/views/emails/nhn/*.html 을 서버에서 치환해 body 로 전달한다. 수신자 1명만, 첨부 불가.
 */
class NhnEmailService
{
    public function isConfigured(): bool
    {
        return !empty(config('services.nhn_email.app_key'))
            && !empty(config('services.nhn_email.secret_key'))
            && !empty(config('services.nhn_email.logo_url'))
            && !empty(config('services.nhn_email.sender_address'))
            && !empty(config('services.nhn_email.contact_email'));
    }

    /**
     * 교적(교회로오피스) 비밀번호 재설정 링크 메일 발송.
     *
     * @return array{success: bool, request_id: ?string, message: string}
     */
    public function sendRegistryPasswordReset(
        string $email,
        string $churchName,
        string $adminName,
        string $loginId,
        string $setupUrl,
        int $validHours
    ): array {
        $vars = [
            'church_name'    => $churchName,
            'applicant_name' => $adminName,
            'login_id'       => $loginId,
            'setup_url'      => $setupUrl,
            'valid_hours'    => (string) $validHours,
            'logo_url'       => (string) config('services.nhn_email.logo_url'),
            'contact_email'  => (string) config('services.nhn_email.contact_email'),
        ];

        try {
            $html = $this->renderTemplate('registry-password-reset', $vars);
        } catch (\Throwable $e) {
            LogHelper::logWrite('[NhnEmailService] template error: ' . $e->getMessage(), 'mail');
            return ['success' => false, 'request_id' => null, 'message' => 'template error'];
        }

        // 제목은 일반 텍스트 필드라 HTML 이스케이프하지 않는다. 줄바꿈만 제거.
        $title = "[교회로] '" . trim(preg_replace('/\s+/u', ' ', $churchName)) . "' 교회로오피스 비밀번호 재설정 링크";

        return $this->sendAuthMail($email, $title, $html, 'registry-password-reset');
    }

    /**
     * {{변수}} 치환. 값은 모두 HTML 이스케이프. 템플릿에 남은 {{...}} 가 있으면 예외.
     */
    public function renderTemplate(string $name, array $vars): string
    {
        $path = resource_path("views/emails/nhn/{$name}.html");
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException("template not found: {$name}");
        }

        $pairs = [];
        foreach ($vars as $key => $value) {
            $pairs['{{' . $key . '}}'] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $html = strtr($html, $pairs);

        if (preg_match('/\{\{\s*\w+\s*\}\}/', $html, $m)) {
            throw new \RuntimeException("unreplaced variable in template {$name}: {$m[0]}");
        }

        return $html;
    }

    /**
     * auth-mail 호출.
     *
     * @return array{success: bool, request_id: ?string, message: string}
     */
    public function sendAuthMail(string $email, string $title, string $html, ?string $userId = null): array
    {
        $cfg = config('services.nhn_email');
        if (empty($cfg['app_key']) || empty($cfg['secret_key'])) {
            LogHelper::logWrite('[NhnEmailService] not configured (app_key/secret_key missing)', 'mail');
            return ['success' => false, 'request_id' => null, 'message' => 'not configured'];
        }

        $url = rtrim($cfg['base_url'], '/') . "/email/v2.1/appKeys/{$cfg['app_key']}/sender/auth-mail";

        $payload = [
            'senderAddress' => $cfg['sender_address'],
            'senderName'    => $cfg['sender_name'],
            'title'         => $title,
            'body'          => $html,
            'receiver'      => ['receiveMailAddr' => $email],
        ];
        if ($userId) {
            $payload['userId'] = $userId;
        }

        try {
            $response = Http::withHeaders(['X-Secret-Key' => $cfg['secret_key']])
                ->timeout((int) $cfg['timeout'])
                ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE), 'application/json;charset=UTF-8')
                ->post($url);
        } catch (\Throwable $e) {
            // 예외 메시지에 요청 URL(appKey 포함)이 들어갈 수 있어 마스킹
            $msg = str_replace((string) $cfg['app_key'], '***', $e->getMessage());
            LogHelper::logWrite("[NhnEmailService] http error: {$msg}", 'mail');
            return ['success' => false, 'request_id' => null, 'message' => 'http error'];
        }

        $json = $response->json() ?? [];
        $header = $json['header'] ?? [];
        $data = $json['body']['data'] ?? [];
        $requestId = $data['requestId'] ?? null;
        $resultCode = $data['results'][0]['resultCode'] ?? null;

        $ok = $response->successful()
            && ($header['isSuccessful'] ?? false) === true
            && ($resultCode === null || (int) $resultCode === 0);

        $summary = sprintf(
            'status=%d header_rc=%s msg=%s requestId=%s receiver_rc=%s',
            $response->status(),
            $header['resultCode'] ?? '-',
            $header['resultMessage'] ?? '-',
            $requestId ?? '-',
            $resultCode ?? '-'
        );

        // 수신 주소·본문(재설정 링크 토큰 포함)은 로그에 남기지 않는다
        if (!$ok) {
            LogHelper::logWrite("[NhnEmailService] send failed: {$summary}", 'mail');
            return ['success' => false, 'request_id' => $requestId, 'message' => $header['resultMessage'] ?? 'send failed'];
        }

        LogHelper::logWrite("[NhnEmailService] sent: {$summary}", 'mail');
        return ['success' => true, 'request_id' => $requestId, 'message' => 'success'];
    }
}
