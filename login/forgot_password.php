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

    // Verify reset code
    case 'verify_code':
        $email = trim($_POST['email'] ?? '');
        $code = $_POST['code'] ?? '';

        if (empty($code)) {
            echo json_encode(['error' => 'Tasdiqlash kodini kiriting']);
            exit;
        }

        if (empty($_SESSION['verification_code'])
            || ($_SESSION['verification_email'] ?? '') !== $email
            || ($_SESSION['verification_purpose'] ?? '') !== 'password_reset'
        ) {
            echo json_encode(['error' => 'Tasdiqlash kodi topilmadi. Qaytadan yuboring.']);
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

        // Code correct
        $_SESSION['reset_verified'] = true;

        // Clear verification code
        unset($_SESSION['verification_code'], $_SESSION['verification_expires'], $_SESSION['verification_attempts']);

        echo json_encode(['success' => true]);
        break;

    // Set new password
    case 'reset_password':
        // Check email was verified
        if (empty($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
            echo json_encode(['error' => 'Tasdiqlash hali o\'tmagan']);
            exit;
        }

        $email = $_SESSION['verification_email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($email)) {
            echo json_encode(['error' => 'Session topilmadi. Qaytadan boshlang.']);
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

        // Clear reset state
        unset($_SESSION['reset_verified'], $_SESSION['verification_code'], $_SESSION['verification_email'], $_SESSION['verification_expires'], $_SESSION['verification_purpose']);

        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND tenant_id = ?");
            $stmt->execute([$hashedPassword, $email, Tenant::getId()]);

            if ($stmt->rowCount() === 0) {
                echo json_encode(['error' => 'Foydalanuvchi topilmadi']);
                exit;
            }

            // Regenerate session for security
            session_regenerate_id(true);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('[AUTH] Password reset error: ' . $e->getMessage());
            echo json_encode(['error' => 'Xatolik yuz berdi']);
        }
        break;

    default:
        echo json_encode(['error' => 'Noto\'g\'ri so\'rov!']);
}
