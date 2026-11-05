<?php
require '../php/config.php';
require 'Login_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Noto\'g\'ri so\'rov!']);
    exit;
}

initSession();

// Check if tenant is active
$tenant = Tenant::get();
if (!$tenant) {
    echo json_encode(['error' => 'Образовательный центр неактивен.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // STEP 1: Check if email is available
    case 'check_email':
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');

        if (empty($first_name)) {
            echo json_encode(['error' => 'Iltimos, ismni kiriting']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Email noto\'g\'ri kiritilgan']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ?");
        $stmt->execute([$email, Tenant::getId()]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'Bu email allaqachon ro\'yxatdan o\'tgan. Tizimga kiring.']);
            exit;
        }

        // Store registration state in session
        $_SESSION['reg_first_name'] = $first_name;
        $_SESSION['reg_last_name'] = trim($_POST['last_name'] ?? '');
        $_SESSION['reg_email'] = $email;
        $_SESSION['reg_email_verified'] = false;

        echo json_encode(['success' => true]);
        break;

    // STEP 2: Verify email code
    case 'verify_email':
        $code = $_POST['code'] ?? '';

        if (empty($code)) {
            echo json_encode(['error' => 'Tasdiqlash kodini kiriting']);
            exit;
        }

        // Check registration state exists
        if (empty($_SESSION['reg_email']) || empty($_SESSION['verification_code'])) {
            echo json_encode(['error' => 'Ro\'yxatdan o\'tish sessioni topilmadi. Qaytadan boshlang.']);
            exit;
        }

        // Check purpose
        if (($_SESSION['verification_purpose'] ?? '') !== 'registration') {
            echo json_encode(['error' => 'Noto\'g\'ri so\'rov']);
            exit;
        }

        // Check TTL
        if (time() > ($_SESSION['verification_expires'] ?? 0)) {
            echo json_encode(['error' => 'Tasdiqlash kodi muddati o\'tgan. Yangi kod oling.']);
            exit;
        }

        // Check attempts (max 5)
        $attempts = $_SESSION['verification_attempts'] ?? 0;
        if ($attempts >= 5) {
            echo json_encode(['error' => 'Juda ko\'p urinish. Yangi kod oling.']);
            exit;
        }

        // Verify code
        if ((int)$code !== (int)$_SESSION['verification_code']) {
            $_SESSION['verification_attempts'] = $attempts + 1;
            $remaining = 5 - ($_SESSION['verification_attempts']);
            echo json_encode(['error' => "Tasdiqlash kodi noto'g'ri. Qoldi: $remaining"]);
            exit;
        }

        // Code correct — mark email as verified
        $_SESSION['reg_email_verified'] = true;

        // Clear verification code (no longer needed)
        unset($_SESSION['verification_code'], $_SESSION['verification_expires'], $_SESSION['verification_attempts']);

        echo json_encode(['success' => true]);
        break;

    // STEP 3: Create account (email must be verified in session)
    case 'create_account':
        // Check email was verified
        if (empty($_SESSION['reg_email_verified']) || $_SESSION['reg_email_verified'] !== true) {
            echo json_encode(['error' => 'Email hali tasdiqlanmagan']);
            exit;
        }

        $first_name = $_SESSION['reg_first_name'] ?? '';
        $last_name = $_SESSION['reg_last_name'] ?? '';
        $email = $_SESSION['reg_email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($first_name)) {
            echo json_encode(['error' => 'Ism topilmadi. Qaytadan boshlang.']);
            exit;
        }
        if (empty($email)) {
            echo json_encode(['error' => 'Email topilmadi. Qaytadan boshlang.']);
            exit;
        }
        if (empty($password) || strlen($password) < 6) {
            echo json_encode(['error' => 'Parol kamida 6 ta belgi bo\'lishi kerak']);
            exit;
        }
        if ($password !== $confirm_password) {
            echo json_encode(['error' => 'Parollar mos emas']);
            exit;
        }

        $tenantId = Tenant::getId();

        // Check if user already exists (pre-check for clear error message)
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND tenant_id = ?");
        $stmt->execute([$email, $tenantId]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'Bu email allaqachon ro\'yxatdan o\'tgan']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $last_name_clean = $last_name ?: '';

            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, phone, points, tenant_id) VALUES (?, ?, ?, ?, '', 0, ?)");
            $stmt->execute([$first_name, $last_name_clean, $email, $hashedPassword, $tenantId]);

            $user_id = $pdo->lastInsertId();
            $slug = generateSlug($pdo, $first_name, $last_name_clean ?: 'user');
            $stmt = $pdo->prepare("UPDATE users SET slug = ? WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$slug, $user_id, $tenantId]);

            $pdo->commit();

            // Auto-login
            loginUser([
                'user_id' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name_clean,
                'slug' => $slug,
                'email' => $email,
                'phone' => '',
                'points' => 0,
            ]);

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Clear registration state
            unset($_SESSION['reg_first_name'], $_SESSION['reg_last_name'], $_SESSION['reg_email'], $_SESSION['reg_email_verified']);
            unset($_SESSION['verification_code'], $_SESSION['verification_email'], $_SESSION['verification_expires'], $_SESSION['verification_purpose']);

            echo json_encode(['success' => true, 'first_name' => $first_name]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Duplicate email from concurrent request (double-submit race condition)
            // UNIQUE constraint on (email, tenant_id) triggered — user was created by the other request
            // Return success to the caller (idempotent: account exists, treat as success)
            $duplicateCheck = $pdo->prepare("SELECT user_id, first_name, last_name, slug, email, phone, points FROM users WHERE email = ? AND tenant_id = ?");
            $duplicateCheck->execute([$email, $tenantId]);
            $existing = $duplicateCheck->fetch();

            if ($existing) {
                loginUser($existing);
                session_regenerate_id(true);
                unset($_SESSION['reg_first_name'], $_SESSION['reg_last_name'], $_SESSION['reg_email'], $_SESSION['reg_email_verified']);
                unset($_SESSION['verification_code'], $_SESSION['verification_email'], $_SESSION['verification_expires'], $_SESSION['verification_purpose']);
                echo json_encode(['success' => true, 'first_name' => $existing['first_name']]);
                exit;
            }

            error_log('[AUTH] Registration error: ' . $e->getMessage());
            echo json_encode(['error' => 'Xatolik yuz berdi. Qaytadan urinib ko\'ring.']);
        }
        break;

    default:
        echo json_encode(['error' => 'Noto\'g\'ri so\'rov!']);
}
