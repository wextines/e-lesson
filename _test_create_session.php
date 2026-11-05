<?php
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 7,
    'path' => '/',
    'httponly' => true,
    'secure' => false,
    'samesite' => 'Lax',
]);
session_start();
if (isset($_GET['tenant_id'])) {
    $_SESSION['tenant_id'] = (int)$_GET['tenant_id'];
}
if (isset($_GET['user_id'])) {
    $_SESSION['user_id'] = (int)$_GET['user_id'];
}
$_SESSION['reg_first_name'] = 'Test';
$_SESSION['reg_last_name'] = 'User';
$_SESSION['reg_email'] = 'test@example.com';
$_SESSION['reg_email_verified'] = true;
session_write_close();
echo session_id();
