<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php'; 
require '../../php/config.php';

function h($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function uploadFile($file, $type, $courseId)
{
    require_once __DIR__ . '/../../php/tenant_file.php';
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $category = 'courses'; 
        $result = TenantFile::handleUpload($file, Tenant::getId(), $category, $courseId);
        if ($result) {
            return $result['relativePath'];
        }
    }
    return null;
}

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$materialId = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
if (!$courseId || !$materialId) die('Не указан курс или материал');

// Fetch material
$stmt = $pdo->prepare("SELECT * FROM materials WHERE material_id = ? AND course_id = ? AND tenant_id = ?");
$stmt->execute([$materialId, $courseId, Tenant::getId()]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$mat) die('Материал не найден');

$errors = [];
$success = false;

$currentSourceType = VideoHelper::isYouTube($mat['file_path'] ?? null) ? 'youtube' : 'file';
$sourceType = $currentSourceType;
$youtubeUrl = VideoHelper::isYouTube($mat['file_path'] ?? null) ? $mat['file_path'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
    $lessonId = !empty($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : null;

    $sourceType = (isset($_POST['source_type']) && $_POST['source_type'] === 'youtube') ? 'youtube' : 'file';
    $youtubeUrl = isset($_POST['youtube_url']) ? trim($_POST['youtube_url']) : '';

    // Upload new files / resolve YouTube link, depending on the chosen source
    $file_path = null;
    $thumb_path = null;
    if ($sourceType === 'youtube') {
        $ytId = VideoHelper::extractYouTubeId($youtubeUrl);
        if (!$ytId) {
            $errors[] = 'Укажите корректную ссылку на YouTube видео.';
        } else {
            $file_path = $youtubeUrl;
        }
        $thumb_path = uploadFile($_FILES['thumbnail_path'], 'preview', $courseId);
        if (!$thumb_path && $ytId) {
            $thumb_path = VideoHelper::getThumbnailUrl($ytId);
        }
    } else {
        $file_path = uploadFile($_FILES['file_path'], 'resources', $courseId);
        $thumb_path = uploadFile($_FILES['thumbnail_path'], 'preview', $courseId);
    }

    // Validate inputs
    if (!$title) $errors[] = 'Введите название видео.';
    if (!$lessonId) $errors[] = 'Введите ID урока.';
    if ($lessonId) {
        // Check if lesson_id is unique for the course, excluding the current material
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE lesson_id = ? AND course_id = ? AND material_id != ? AND tenant_id = ?");
        $stmt->execute([$lessonId, $courseId, $materialId, Tenant::getId()]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Урок с таким ID уже существует для этого курса.';
        }
    }

    if (empty($errors)) {
        // Prepare update
        $sql = "UPDATE materials SET title = :title, description = :description, duration = :duration, lesson_id = :lesson_id";
        $params = [
            ':title' => $title,
            ':description' => $description,
            ':duration' => $duration,
            ':lesson_id' => $lessonId,
            ':mid' => $materialId,
            ':tenant_id' => Tenant::getId()
        ];
        if ($file_path) {
            $sql .= ", file_path = :file_path";
            $params[':file_path'] = $file_path;
        }
        if ($thumb_path) {
            $sql .= ", thumbnail_path = :thumb_path";
            $params[':thumb_path'] = $thumb_path;
        }
        $sql .= " WHERE material_id = :mid AND tenant_id = :tenant_id";
        $upd = $pdo->prepare($sql);
        $upd->execute($params);
        $success = true;
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM materials WHERE material_id = ? AND course_id = ? AND tenant_id = ?");
        $stmt->execute([$materialId, $courseId, Tenant::getId()]);
        $mat = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$pageTitle = 'Редактировать материал — ' . h($mat['title']);
include __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Редактировать материал</h1>

<?php if ($errors): ?>
    <div class="msg msg-err">
        <ul style="margin:0;padding:0 0 0 20px"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php elseif ($success): ?>
    <div class="msg msg-ok">Изменения сохранены.</div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>ID урока</label>
            <input type="number" name="lesson_id" min="1" value="<?= h($mat['lesson_id']) ?>" required>
        </div>

        <div class="form-group">
            <label>Название</label>
            <input type="text" name="title" value="<?= h($mat['title']) ?>" required>
        </div>

        <div class="form-group">
            <label>Описание</label>
            <textarea name="description" rows="4"><?= h($mat['description']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Источник видео</label>
            <div style="display:flex;gap:16px;margin-top:4px">
                <label style="display:flex;align-items:center;gap:6px;font-weight:400">
                    <input type="radio" name="source_type" id="source_type_file" value="file" <?= $sourceType !== 'youtube' ? 'checked' : '' ?>>
                    Файл / PDF
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-weight:400">
                    <input type="radio" name="source_type" id="source_type_youtube" value="youtube" <?= $sourceType === 'youtube' ? 'checked' : '' ?>>
                    Ссылка на YouTube
                </label>
            </div>
        </div>

        <div class="form-group" id="file_upload_group">
            <label>Новый видеофайл (если нужно)</label>
            <input type="file" name="file_path" accept="video/*">
            <?php if ($currentSourceType === 'file' && !empty($mat['file_path'])): ?>
                <p class="hint">Текущий файл: <?= h($mat['file_path']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group" id="youtube_url_group">
            <label>Ссылка на YouTube</label>
            <input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..." value="<?= h($youtubeUrl) ?>">
        </div>

        <div class="form-group">
            <label>Новая обложка (если нужно)</label>
            <input type="file" name="thumbnail_path" accept="image/*">
            <p class="hint">Для YouTube, если не указано, автоматически используется обложка видео.</p>
        </div>

        <div class="form-group">
            <label>Продолжительность (минут)</label>
            <input type="number" name="duration" min="0" value="<?= h($mat['duration']) ?>">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:16px">Сохранить</button>
    </form>
</div>
<script>
    (function () {
        var fileRadio = document.getElementById('source_type_file');
        var ytRadio = document.getElementById('source_type_youtube');
        var fileGroup = document.getElementById('file_upload_group');
        var ytGroup = document.getElementById('youtube_url_group');

        function sync() {
            var isYoutube = ytRadio.checked;
            ytGroup.style.display = isYoutube ? '' : 'none';
            fileGroup.style.display = isYoutube ? 'none' : '';
        }

        fileRadio.addEventListener('change', sync);
        ytRadio.addEventListener('change', sync);
        sync();
    })();
</script>

<a href="/admin/courses/view.php?course_id=<?= $courseId ?>&tab=content" class="btn btn-secondary">&larr; Back to Materials</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
