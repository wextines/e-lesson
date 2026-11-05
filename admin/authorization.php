<?php
require_once __DIR__ . '/../php/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: /admin/php/login.php");
    exit;
}

// Set tenant context from session (never from GET/POST)
if (isset($_SESSION['admin_tenant_id'])) {
    Tenant::set((int)$_SESSION['admin_tenant_id']);
}

// Verify tenant is still active (skip for super_admin with tenant_id=0)
$adminTenantId = (int)($_SESSION['admin_tenant_id'] ?? 0);
$adminRole = $_SESSION['admin_role'] ?? '';
if ($adminTenantId > 0 && $adminRole !== 'super_admin') {
    $tenant = Tenant::get();
    if (!$tenant) {
        // Tenant is inactive — destroy session and redirect
        session_destroy();
        header("Location: /admin/php/login.php?error=tenant_inactive");
        exit;
    }
}

// CSRF token generation and helpers
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string {
        return $_SESSION['csrf_token'] ?? '';
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
    }
}

if (!function_exists('csrfTokenHtml')) {
    function csrfTokenHtml(): string {
        return csrf_field();
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $token = $_POST['csrf_token'] ?? '';
        if (empty($sessionToken) || empty($token) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            die('CSRF token mismatch.');
        }
    }
}

if (!function_exists('require_admin_role')) {
    function require_admin_role(array $allowedRoles): void {
        $role = $_SESSION['admin_role'] ?? 'center_admin';
        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(403);
            die('Доступ запрещён для роли ' . htmlspecialchars($role));
        }
    }
}
