<?php
require_once __DIR__ . '/../../php/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: /admin/php/login.php");
    exit;
}

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: /admin/");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function verify_csrf() {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($token) || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}

function csrf_token_html() {
    return csrf_field();
}
