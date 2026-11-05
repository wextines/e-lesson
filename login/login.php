<?php
require '../php/config.php';
require 'Login_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Noto\'g\'ri so\'rov!']);
    exit;
}

initSession();

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['error' => 'Email va parol kiritilishi shart!']);
    exit;
}

try {
    // Check if tenant is active
    $tenant = Tenant::get();
    if (!$tenant) {
        echo json_encode(['error' => 'Образовательный центр неактивен.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT user_id, slug, first_name, last_name, phone, email, points, password FROM users WHERE email = ? AND tenant_id = ? AND is_active = 1");
    $stmt->execute([$email, Tenant::getId()]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        loginUser($user);

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        echo json_encode(['success' => true]);
    } else {
        // Generic error — don't reveal whether email exists
        echo json_encode(['error' => 'Noto\'g\'ri email yoki parol!']);
    }
} catch (PDOException $e) {
    error_log('[AUTH] Login error: ' . $e->getMessage());
    echo json_encode(['error' => 'Xatolik yuz berdi.']);
}
