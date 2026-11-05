<?php
session_start();
require '../../login/Login_functions.php';
initSession();

if (!isLoggedIn()) {
    header("Location: /login/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /");
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    die('CSRF token mismatch');
}

$testId = $_POST['test_id'] ?? null;

if ($testId) {
    unset($_SESSION['test'][$testId]);
}

header("Location: index.php?test_id=" . urlencode($testId));
exit;
