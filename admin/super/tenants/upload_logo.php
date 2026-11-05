<?php
require_once '../authorization.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token mismatch']);
    exit;
}

if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Файл не загружен']);
    exit;
}

$tenantId = $_POST['tenant_id'] ?? null;
if (!$tenantId || !is_numeric($tenantId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный tenant_id']);
    exit;
}

$tenantId = (int)$tenantId;
$file = $_FILES['logo'];

// Validate file size (max 2MB)
$maxSize = 2 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'Файл слишком большой. Максимум 2MB.']);
    exit;
}

// Validate MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedMimes = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
];

if (!isset($allowedMimes[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Недопустимый тип файла. Разрешены: PNG, JPG, GIF, WebP, SVG.']);
    exit;
}

// Additional SVG safety check
if ($mime === 'image/svg+xml') {
    $content = file_get_contents($file['tmp_name']);
    if (preg_match('/<script\b/i', $content) || preg_match('/on\w+\s*=/i', $content) || preg_match('/<iframe/i', $content)) {
        http_response_code(400);
        echo json_encode(['error' => 'SVG содержит небезопасный код.']);
        exit;
    }
}

// Get current tenant to check existing logo
$stmt = $pdo->prepare("SELECT logo_path FROM tenants WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tenant) {
    http_response_code(404);
    echo json_encode(['error' => 'Tenant не найден']);
    exit;
}

// Create storage directory
$logoDir = __DIR__ . "/../../storage/tenants/{$tenantId}/logo";
if (!is_dir($logoDir)) {
    mkdir($logoDir, 0755, true);
}

// Delete old logo if exists
if ($tenant['logo_path']) {
    $oldFile = __DIR__ . '/../../' . ltrim($tenant['logo_path'], '/');
    if (file_exists($oldFile) && strpos(realpath($oldFile), realpath($logoDir)) === 0) {
        unlink($oldFile);
    }
}

// Generate safe filename
$ext = $allowedMimes[$mime];
$filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
$filepath = $logoDir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сохранения файла']);
    exit;
}

// Update database
$relativePath = "/storage/tenants/{$tenantId}/logo/{$filename}";
$stmt = $pdo->prepare("UPDATE tenants SET logo_path = ? WHERE tenant_id = ?");
$stmt->execute([$relativePath, $tenantId]);

echo json_encode([
    'success' => true,
    'logo_path' => $relativePath,
    'message' => 'Логотип успешно загружен'
]);
