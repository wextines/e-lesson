<?php
require_once '../authorization.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();

$tenant_id = $_POST['tenant_id'] ?? null;
$action = $_POST['action'] ?? '';

if (!$tenant_id || !is_numeric($tenant_id)) {
    header("Location: index.php");
    exit;
}

$tenant_id = (int)$tenant_id;

if ($action === 'activate') {
    $stmt = $pdo->prepare("UPDATE tenants SET is_active = 1 WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    header("Location: index.php?activated={$tenant_id}");
    exit;
} elseif ($action === 'deactivate') {
    $stmt = $pdo->prepare("UPDATE tenants SET is_active = 0 WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    header("Location: index.php?deactivated={$tenant_id}");
    exit;
}

header("Location: index.php");
