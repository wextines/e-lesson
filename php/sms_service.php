<?php
declare(strict_types=1);

/**
 * e-Lesson SMS Service (P0-4)
 *
 * Sends payment-reminder SMS via Eskiz.uz (https://notify.eskiz.uz), with three
 * safety nets baked into the single send() entry point:
 *   - Deduplication: the same (tenant, subscription, trigger) pair is never
 *     re-sent within DEDUP_WINDOW_DAYS, so a cron re-run or an overlapping
 *     manual send can't spam a student.
 *   - Mock mode: when ESKIZ_EMAIL/ESKIZ_PASSWORD are absent from .env, or
 *     ESKIZ_MOCK=true is set, no real HTTP call is made — the send is logged
 *     as 'mock_sent' so the whole reminder flow can be demoed and tested
 *     without a live Eskiz account.
 *   - Every outcome (sent, mock_sent, failed) is written to sms_logs, which
 *     is also what admin/finance/debtors.php reads to show "last SMS sent".
 *
 * PDO is passed explicitly (matching PaymentService's convention) rather than
 * pulled from a global, so this class has no hidden dependency on include order.
 */

class SmsService
{
    private const ESKIZ_BASE_URL = 'https://notify.eskiz.uz/api';
    private const DEDUP_WINDOW_DAYS = 25;
    private const TOKEN_CACHE_FILE = __DIR__ . '/../storage/cache/eskiz_token.json';

    public static function send(PDO $pdo, int $tenantId, int $userId, int $subId, string $phone, string $message, string $triggerType): array
    {
        $dedupStmt = $pdo->prepare("
            SELECT COUNT(*) FROM sms_logs
            WHERE tenant_id = ? AND course_sub_id = ? AND trigger_type = ?
              AND status IN ('sent', 'mock_sent')
              AND sent_at >= DATE_SUB(NOW(), INTERVAL " . self::DEDUP_WINDOW_DAYS . " DAY)
        ");
        $dedupStmt->execute([$tenantId, $subId, $triggerType]);
        if ((int)$dedupStmt->fetchColumn() > 0) {
            return ['success' => false, 'reason' => 'already_sent'];
        }

        $eskizEmail = trim((string)(getenv('ESKIZ_EMAIL') ?: ''));
        $eskizPassword = trim((string)(getenv('ESKIZ_PASSWORD') ?: ''));
        $forceMock = filter_var(getenv('ESKIZ_MOCK') ?: '', FILTER_VALIDATE_BOOLEAN);

        if ($eskizEmail === '' || $eskizPassword === '' || $forceMock) {
            self::logSms($pdo, $tenantId, $userId, $subId, $phone, $message, $triggerType, 'mock_sent', null);
            return ['success' => true, 'mock' => true];
        }

        try {
            $token = self::getToken($eskizEmail, $eskizPassword);
            self::sendViaEskiz($token, $phone, $message);
            self::logSms($pdo, $tenantId, $userId, $subId, $phone, $message, $triggerType, 'sent', null);
            return ['success' => true, 'mock' => false];
        } catch (Throwable $e) {
            self::logSms($pdo, $tenantId, $userId, $subId, $phone, $message, $triggerType, 'failed', $e->getMessage());
            return ['success' => false, 'reason' => 'send_failed', 'error' => $e->getMessage()];
        }
    }

    private static function logSms(PDO $pdo, int $tenantId, int $userId, int $subId, string $phone, string $message, string $triggerType, string $status, ?string $errorMessage): void
    {
        $ins = $pdo->prepare("
            INSERT INTO sms_logs (tenant_id, user_id, course_sub_id, phone, message, trigger_type, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$tenantId, $userId, $subId, $phone, $message, $triggerType, $status, $errorMessage]);
    }

    /** Eskiz tokens are valid for 30 days; cached locally so the cron doesn't re-auth every run. */
    private static function getToken(string $email, string $password): string
    {
        if (is_file(self::TOKEN_CACHE_FILE)) {
            $cached = json_decode((string)file_get_contents(self::TOKEN_CACHE_FILE), true);
            if (is_array($cached) && !empty($cached['token']) && (int)($cached['expires_at'] ?? 0) > time()) {
                return (string)$cached['token'];
            }
        }

        [$httpCode, $response, $curlError] = self::httpPost(self::ESKIZ_BASE_URL . '/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        if ($response === false) {
            throw new RuntimeException("Eskiz auth request failed: {$curlError}");
        }
        $data = json_decode($response, true);
        $token = $data['data']['token'] ?? null;
        if ($httpCode !== 200 || !$token) {
            throw new RuntimeException("Eskiz auth failed (HTTP {$httpCode}): {$response}");
        }

        $cacheDir = dirname(self::TOKEN_CACHE_FILE);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        file_put_contents(self::TOKEN_CACHE_FILE, json_encode(['token' => $token, 'expires_at' => time() + 25 * 86400]));

        return (string)$token;
    }

    private static function sendViaEskiz(string $token, string $phone, string $message): void
    {
        $cleanPhone = preg_replace('/\D/', '', $phone) ?? '';
        [$httpCode, $response, $curlError] = self::httpPost(
            self::ESKIZ_BASE_URL . '/message/sms/send',
            [
                'mobile_phone' => $cleanPhone,
                'message' => $message,
                'from' => getenv('ESKIZ_FROM') ?: '4546',
            ],
            ['Authorization: Bearer ' . $token]
        );

        if ($response === false) {
            throw new RuntimeException("Eskiz send request failed: {$curlError}");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Eskiz send failed (HTTP {$httpCode}): {$response}");
        }
    }

    /** @return array{0: int, 1: string|false, 2: string} [httpCode, response, curlError] */
    private static function httpPost(string $url, array $fields, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [$httpCode, $response, $curlError];
    }
}
