<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php'; 
require '../../login/Login_functions.php';
initSession();
require '../../php/config.php';

function h($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if ($courseId) {
    header('Location: /admin/courses/view.php?course_id=' . $courseId . '&tab=content');
    exit;
}
die('No course specified');
// Получаем название курса
$stmt = $pdo->prepare("SELECT title FROM courses WHERE course_id = ? AND tenant_id = ?");
$stmt->execute([$courseId, Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    die('Курс не найден');
}
// Получаем материалы
$stmt = $pdo->prepare("SELECT * FROM materials WHERE course_id = ? AND tenant_id = ? ORDER BY lesson_id ASC");
$stmt->execute([$courseId, Tenant::getId()]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Материалы курса «' . h($course['title']) . '»';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Материалы курса «<?= h($course['title']) ?>»</h1>

<?php if (empty($materials)): ?>
    <div class="card">
        <div style="text-align:center;padding:32px 0;font-size:14px;color:#6b7280">
            <p>У этого курса еще нет материалов.</p>
            <a href="add_materials.php?course_id=<?= $courseId ?>" class="btn btn-primary">Добавить материал</a>
        </div>
    </div>
<?php else: ?>
    <a href="add_materials.php?course_id=<?= $courseId ?>" class="btn btn-primary">Добавить новый материал</a>
    <?php foreach ($materials as $mat): ?>
        <div class="card">
            <h2 style="margin:0 0 8px;font-size:20px;word-wrap:break-word"><?= h($mat['lesson_id']) ?>. <?= h($mat['title']) ?></h2>
            <?php if ($mat['description']): ?>
                <p style="margin:4px 0;color:#555;font-size:16px"><?= nl2br(h($mat['description'])) ?></p>
            <?php endif; ?>
            <p style="margin:4px 0;color:#555"><strong>Загружен:</strong> <?= h(date('d.m.Y H:i', strtotime($mat['uploaded_at']))) ?></p>
            <p style="margin:4px 0;color:#555">
                <strong>Материал:</strong>
                <?php
                $filePath = $mat['file_path'];
                $thumbnailPath = $mat['thumbnail_path'];
                if ($thumbnailPath && $filePath): ?>
                    <div style="display:flex;align-items:center;justify-content:flex-start;margin-top:8px;width:100%">
                        <a href="/file.php?type=material&id=<?= $mat['material_id'] ?>" target="_blank">
                            <img src="/file.php?type=preview&id=<?= $mat['material_id'] ?>" alt="<?= h($mat['title']) ?>" style="max-width:250px;max-height:200px;width:auto;height:auto;object-fit:cover;border-radius:6px;border:1px solid #e8ecef">
                        </a>
                    </div>
                <?php elseif ($filePath): ?>
                    <a href="/file.php?type=material&id=<?= $mat['material_id'] ?>" class="btn btn-primary btn-sm" target="_blank">
                        <?= h(basename($filePath)) ?>
                    </a>
                <?php else: ?>
                    <span style="color:#555;background:#f4f4f4;padding:6px 12px;border-radius:6px;display:inline-block">Файл отсутствует</span>
                <?php endif; ?>
            </p>
            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <a href="edit_materials.php?material_id=<?= $mat['material_id'] ?>&course_id=<?= $courseId ?>" class="btn btn-primary btn-sm">Редактировать</a>
                <form method="post" action="delete_materials.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="material_id" value="<?= $mat['material_id'] ?>">
                    <input type="hidden" name="course_id" value="<?= $courseId ?>">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Удалить материал «<?= h(addslashes($mat['title'])) ?>»?')">Удалить</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<a href="../courses/index.php" class="btn btn-secondary">← Назад к курсам</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
