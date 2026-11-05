<?php
declare(strict_types=1);

/**
 * Public lead-capture endpoint for the e-Lesson platform homepage
 * (marketing/home.php). Unauthenticated by design — a prospective center
 * owner has no account yet. Spam defenses: a honeypot field and a light
 * per-session throttle, since there is no user session to forge via CSRF here.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../php/config.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, ['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$input = json_decode((string)$raw, true);
if (!is_array($input)) {
    respond(false, ['error' => 'Некорректный запрос.'], 400);
}

// Honeypot: a real visitor never fills this hidden field. Pretend success
// so a bot doesn't learn to avoid the field, but never write the row.
if (trim((string)($input['website'] ?? '')) !== '') {
    respond(true);
}

// Light throttle: one submission per 15 seconds per session, independent of
// any login state (this form has none).
$now = time();
$lastSubmit = (int)($_SESSION['lead_last_submit'] ?? 0);
if ($now - $lastSubmit < 15) {
    respond(false, ['error' => 'Пожалуйста, подождите немного перед повторной отправкой.'], 429);
}

$name = trim((string)($input['name'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$centerName = trim((string)($input['center_name'] ?? ''));
$message = trim((string)($input['message'] ?? ''));

if (mb_strlen($name) < 2 || mb_strlen($name) > 255) {
    respond(false, ['error' => 'Укажите имя.'], 422);
}

$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen((string)$phoneDigits) < 9) {
    respond(false, ['error' => 'Укажите корректный номер телефона.'], 422);
}
if (strlen($phone) > 32) {
    $phone = substr($phone, 0, 32);
}

if (mb_strlen($centerName) > 255) {
    $centerName = mb_substr($centerName, 0, 255);
}
if (mb_strlen($message) > 500) {
    $message = mb_substr($message, 0, 500);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO platform_leads (name, phone, center_name, message, source_page)
        VALUES (?, ?, ?, ?, 'home')
    ");
    $stmt->execute([
        $name,
        $phone,
        $centerName !== '' ? $centerName : null,
        $message !== '' ? $message : null,
    ]);
} catch (PDOException $e) {
    error_log('[marketing/submit_lead] insert failed: ' . $e->getMessage());
    respond(false, ['error' => 'Не удалось сохранить заявку. Попробуйте ещё раз.'], 500);
}

$_SESSION['lead_last_submit'] = $now;

respond(true);
