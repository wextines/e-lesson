<?php
require '../php/config.php';
require 'Login_functions.php';
require 'email_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Noto\'g\'ri so\'rov!']);
    exit;
}

initSession();

$email = trim($_POST['email'] ?? '');
$purpose = $_POST['purpose'] ?? 'registration'; // 'registration' or 'password_reset'

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Email noto\'g\'ri kiritilgan']);
    exit;
}

// Rate limiting: max 5 attempts per email per purpose within 10 minutes
$rateKey = 'rate_' . $purpose . '_' . md5($email);
if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'first_at' => time()];
}
$rate = &$_SESSION[$rateKey];

if (time() - $rate['first_at'] > 600) {
    $rate = ['count' => 0, 'first_at' => time()];
}

if ($rate['count'] >= 5) {
    echo json_encode(['error' => 'Juda ko\'p so\'rov yuborildi. 10 daqiqadan keyin qayta urinib ko\'ring.']);
    exit;
}

// Min 30 seconds between sends
$lastSentKey = 'last_sent_' . $purpose . '_' . md5($email);
if (isset($_SESSION[$lastSentKey]) && time() - $_SESSION[$lastSentKey] < 30) {
    echo json_encode(['error' => 'Kod juda yaqinda yuborilgan. 30 soniya kuting.']);
    exit;
}

// For registration: check email is NOT taken
// For password reset: check email IS taken and is_active
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ?");
$stmt->execute([$email, Tenant::getId()]);
$emailExists = $stmt->fetchColumn() > 0;

if ($purpose === 'registration' && $emailExists) {
    echo json_encode(['error' => 'Bu email allaqachon ro\'yxatdan o\'tgan. Tizimga kiring.']);
    exit;
}

if ($purpose === 'password_reset' && !$emailExists) {
    echo json_encode(['success' => true, 'email' => $email]);
    exit;
}

if ($purpose === 'password_reset' && $emailExists) {
    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE email = ? AND tenant_id = ?");
    $stmt->execute([$email, Tenant::getId()]);
    $isActive = $stmt->fetchColumn();
    if (!$isActive) {
        echo json_encode(['error' => 'Account deactivated. Contact administrator.']);
        exit;
    }
}

// Generate 6-digit code
$code = random_int(100000, 999999);
$ttl = 600; // 10 minutes

// Store in session (server-side only — never sent to client)
$_SESSION['verification_code'] = $code;
$_SESSION['verification_email'] = $email;
$_SESSION['verification_expires'] = time() + $ttl;
$_SESSION['verification_purpose'] = $purpose;
$_SESSION['verification_attempts'] = 0;

// Update rate limiting
$rate['count']++;
$_SESSION[$lastSentKey] = time();

// Get user name for email
$userName = 'Foydalanuvchi';
if ($purpose === 'password_reset') {
    $stmt = $pdo->prepare("SELECT first_name FROM users WHERE email = ? AND tenant_id = ?");
    $stmt->execute([$email, Tenant::getId()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['first_name']) {
        $userName = $row['first_name'];
    }
}

// Build email
$tenantName = Tenant::getName();
$tenantPrimary = Tenant::getPrimaryColor();
$tenantSecondary = Tenant::getSecondaryColor();
$tenantAccent = Tenant::getAccentColor();
if ($purpose === 'password_reset') {
    $subject = $tenantName . ' — Parolni tiklash';
    $html = renderEmailTemplate('reset_password', [
        'name' => $userName,
        'code' => (string) $code,
        'minutes' => (string) ($ttl / 60),
        'tenant_name' => $tenantName,
        'primary_color' => $tenantPrimary,
        'secondary_color' => $tenantSecondary,
        'accent_color' => $tenantAccent,
    ]);
} else {
    $subject = $tenantName . ' — Email tasdiqlash';
    $html = renderEmailTemplate('verification', [
        'name' => $userName,
        'code' => (string) $code,
        'minutes' => (string) ($ttl / 60),
        'tenant_name' => $tenantName,
        'primary_color' => $tenantPrimary,
        'secondary_color' => $tenantSecondary,
        'accent_color' => $tenantAccent,
    ]);
}

// Send email (or fallback to dev mode)
$sent = sendEmail($email, $subject, $html);

if (!$sent && !isSmtpConfigured()) {
    // Dev fallback: save code to temp file so developers can test
    saveDevCode($email, $code, $purpose);
    echo json_encode(['success' => true, 'email' => $email]);
    exit;
}

if ($sent) {
    echo json_encode(['success' => true, 'email' => $email]);
} else {
    echo json_encode(['error' => 'Email yuborishda xatolik. Qaytadan urinib ko\'ring.']);
}
