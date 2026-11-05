<?php
require_once '../authorization.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();

$tenantId = $_POST['tenant_id'] ?? null;
if (!$tenantId || !is_numeric($tenantId)) {
    header("Location: index.php");
    exit;
}

$tenantId = (int)$tenantId;

$stmt = $pdo->prepare("SELECT logo_path FROM tenants WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant && $tenant['logo_path']) {
    $file = __DIR__ . '/../../' . ltrim($tenant['logo_path'], '/');
    if (file_exists($file)) {
        unlink($file);
    }
    $stmt = $pdo->prepare("UPDATE tenants SET logo_path = NULL WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
}

header("Location: edit.php?id={$tenantId}&logo_deleted=1");
exit;
