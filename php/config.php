<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'db';
$username = getenv('DB_USER') ?: 'iq_user';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: 'iq_creative';

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// e-Lesson: tenant context
require_once __DIR__ . '/tenant.php';

// Resolve tenant from HTTP_HOST (after PDO is available)
Tenant::resolveFromRequest();

// Telegram integration (per-tenant in future)
$chat_id = getenv('TELEGRAM_CHAT_ID') ?: '';
$bot_token = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$cardNumber = getenv('PAYMENT_CARD_NUMBER') ?: '0000 0000 0000 0000';
$cardHolderName = getenv('PAYMENT_CARD_HOLDER') ?: 'Demo account';

// SMTP integration (per-tenant in future)
$smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$smtp_port = (int)(getenv('SMTP_PORT') ?: 587);
$smtp_username = getenv('SMTP_USERNAME') ?: '';
$smtp_password = getenv('SMTP_PASSWORD') ?: '';
$smtp_from_email = getenv('SMTP_FROM_EMAIL') ?: '';
$smtp_from_name = getenv('SMTP_FROM_NAME') ?: (Tenant::getName() ?: 'e-Lesson');

require_once __DIR__ . '/video_helper.php';
