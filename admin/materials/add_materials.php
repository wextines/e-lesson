<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../authorization.php'; 
require '../../php/config.php';
require '../../php/tenant_file.php';

function prepareFile($file, $type, $courseId)
{
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $result = TenantFile::handleUpload(
            $file,
            Tenant::getId(),
            'courses',
            $courseId
        );
        if ($result) {
            return [
                'tmp_name' => $file['tmp_name'],
                'destination' => $result['fullPath'],
                'relative_path' => $result['relativePath']
            ];
        }
    }
    return null;
}

function uploadFiles($fileData, $thumbData)
{
    $filePath = null;
    $thumbPath = null;

    // Files are already uploaded by TenantFile::handleUpload
    if ($fileData) {
        $filePath = $fileData['relative_path'];
    }
    if ($thumbData) {
        $thumbPath = $thumbData['relative_path'];
    }

    return [$filePath, $thumbPath];
}

$errors = [];
$success = false;

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

// Сохранение данных формы для отображения при ошибке
$lessonId = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : '';
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$duration = isset($_POST['duration']) ? (int)$_POST['duration'] : '';

$courseTitle = null;

if ($courseId) {
    $stmt = $pdo->prepare("SELECT title FROM courses WHERE course_id = ? AND tenant_id = ?");
    $stmt->execute([$courseId, Tenant::getId()]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($course) {
        $courseTitle = $course['title'];
    } else {
        $errors[] = 'Курс не найден.';
        $courseId = null;
    }
} else {
    $errors[] = 'Не указан идентификатор курса.';
}

$sourceType = (isset($_POST['source_type']) && $_POST['source_type'] === 'youtube') ? 'youtube' : 'file';
$youtubeUrl = isset($_POST['youtube_url']) ? trim($_POST['youtube_url']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $courseId) {
    verify_csrf();
    // Валидация
    if (!$title) $errors[] = 'Введите название видео.';
    if (!$lessonId) $errors[] = 'Введите ID урока.';

    $ytId = null;
    if ($sourceType === 'youtube') {
        $ytId = VideoHelper::extractYouTubeId($youtubeUrl);
        if (!$ytId) {
            $errors[] = 'Укажите корректную ссылку на YouTube видео.';
        }
    } elseif (!isset($_FILES['file_path']) || $_FILES['file_path']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Не удалось загрузить видеофайл.';
    }

    // Проверка существования lesson_id
    if ($lessonId && empty($errors)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE lesson_id = ? AND course_id = ? AND tenant_id = ?");
    $stmt->execute([$lessonId, $courseId, Tenant::getId()]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Урок с таким ID уже существует для этого курса.';
        }
    }

    // Подготовка файлов
    $fileData = null;
    $thumbData = null;
    if (empty($errors)) {
        if ($sourceType === 'file') {
            $fileData = prepareFile($_FILES['file_path'], 'resources', $courseId);
            if (!$fileData) {
                $errors[] = 'Ошибка обработки видеофайла.';
            }
        }
        $thumbData = prepareFile($_FILES['thumbnail_path'], 'preview', $courseId);
    }

    // Загрузка файлов
    $filePath = null;
    $thumbPath = null;
    if (empty($errors)) {
        if ($sourceType === 'youtube') {
            $filePath = $youtubeUrl;
            $thumbPath = $thumbData ? $thumbData['relative_path'] : VideoHelper::getThumbnailUrl($ytId);
        } else {
            list($filePath, $thumbPath) = uploadFiles($fileData, $thumbData);
            if (!$filePath) {
                $errors[] = 'Ошибка при сохранении файлов.';
            }
        }
    }

    // Вставка в базу данных
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO materials (tenant_id, course_id, lesson_id, title, description, file_path, thumbnail_path, duration)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            Tenant::getId(),
            $courseId,
            $lessonId,
            $title,
            $description,
            $filePath,
            $thumbPath,
            $duration
        ]);

        $success = true;
        // Сброс полей при успехе
        $lessonId = '';
        $title = '';
        $description = '';
        $duration = '';

        header("Location: /admin/courses/view.php?course_id=" . urlencode((string)$courseId) . "&tab=content");
        exit;
    }
}

$pageTitle = 'Добавить материал';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Добавить материал к курсу — <?= htmlspecialchars($courseTitle) ?></h1>

<?php if ($errors): ?>
    <div class="msg msg-err">
        <ul style="margin:0;padding:0 0 0 20px">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($success): ?>
    <div class="msg msg-ok">Материал успешно загружен!</div>
<?php endif; ?>

<?php if ($courseId): ?>
    <div class="card">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="lesson_id">ID урока</label>
                <input type="number" id="lesson_id" name="lesson_id" min="1" value="<?= htmlspecialchars($lessonId) ?>" required>
            </div>

            <div class="form-group">
                <label for="title">Название видео</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="form-group">
                <label>Источник видео</label>
                <div style="display:flex;gap:16px;margin-top:4px">
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400">
                        <input type="radio" name="source_type" id="source_type_file" value="file" <?= $sourceType !== 'youtube' ? 'checked' : '' ?>>
                        Загрузить видеофайл / PDF
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-weight:400">
                        <input type="radio" name="source_type" id="source_type_youtube" value="youtube" <?= $sourceType === 'youtube' ? 'checked' : '' ?>>
                        Ссылка на YouTube
                    </label>
                </div>
            </div>

            <div class="form-group" id="file_upload_group">
                <label for="file_path">Файл видео</label>
                <input type="file" id="file_path" name="file_path" accept="video/*">
            </div>

            <div class="form-group" id="youtube_url_group">
                <label for="youtube_url">Ссылка на YouTube</label>
                <input type="url" id="youtube_url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..." value="<?= htmlspecialchars($youtubeUrl) ?>">
            </div>

            <div class="form-group">
                <label for="thumbnail_path">Превью (необязательно)</label>
                <input type="file" id="thumbnail_path" name="thumbnail_path" accept="image/*">
                <p class="hint">Для YouTube, если не указано, автоматически используется обложка видео.</p>
            </div>

            <div class="form-group">
                <label for="duration">Продолжительность (минут)</label>
                <input type="number" id="duration" name="duration" min="0" value="<?= htmlspecialchars($duration) ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:16px">Загрузить</button>
        </form>
    </div>
    <script>
        (function () {
            var fileRadio = document.getElementById('source_type_file');
            var ytRadio = document.getElementById('source_type_youtube');
            var fileGroup = document.getElementById('file_upload_group');
            var ytGroup = document.getElementById('youtube_url_group');
            var fileInput = document.getElementById('file_path');

            function sync() {
                var isYoutube = ytRadio.checked;
                ytGroup.style.display = isYoutube ? '' : 'none';
                fileGroup.style.display = isYoutube ? 'none' : '';
                fileInput.required = !isYoutube;
            }

            fileRadio.addEventListener('change', sync);
            ytRadio.addEventListener('change', sync);
            sync();
        })();
    </script>
<?php endif; ?>

<a href="/admin/courses/view.php?course_id=<?= htmlspecialchars($courseId) ?>&tab=materials" class="btn btn-secondary">&larr; Back to Materials</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
