<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php'; 
require '../../login/Login_functions.php';
initSession();
require '../../php/config.php';
require '../../php/tenant_file.php';

// CSRF validation
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    die('CSRF token mismatch');
}

// Проверяем переданные параметры
$materialId = isset($_POST['material_id']) ? (int)$_POST['material_id'] : 0;
$courseId   = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;

if (!$materialId || !$courseId) {
    die('Не указан идентификатор материала или курса.');
}

// Получаем информацию о материале
$stmt = $pdo->prepare("SELECT file_path, thumbnail_path FROM materials WHERE material_id = ? AND course_id = ? AND tenant_id = ?");
$stmt->execute([$materialId, $courseId, Tenant::getId()]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    die('Материал не найден.');
}

// Удаляем файлы
foreach (['file_path', 'thumbnail_path'] as $key) {
    if (!empty($material[$key])) {
        TenantFile::deleteFile(Tenant::getId(), $material[$key]);
    }
}

// Удаляем запись из базы данных
$deleteStmt = $pdo->prepare("DELETE FROM materials WHERE material_id = ? AND course_id = ? AND tenant_id = ?");
$deleteStmt->execute([$materialId, $courseId, Tenant::getId()]);

// Перенаправление обратно
header("Location: index_materials.php?course_id=" . $courseId);
exit;
?>
