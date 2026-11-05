<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php';
require '../../php/config.php';
require '../../php/tenant_file.php';

function h($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function convertDatetimeLocalToMysql($datetimeLocal)
{
    if (!$datetimeLocal) return null;
    return str_replace('T', ' ', $datetimeLocal) . ':00';
}

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if (!$course_id) {
    die("Не указан ID курса");
}

$stmt = $pdo->prepare("SELECT title FROM courses WHERE course_id = ? AND tenant_id = ?");
$stmt->execute([$course_id, Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    die("Курс не найден");
}

$materials = [];
$stmt = $pdo->prepare("SELECT material_id, title FROM materials WHERE course_id = ? AND tenant_id = ?");
$stmt->execute([$course_id, Tenant::getId()]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        $errors['form'] = 'Неверный CSRF-токен';
    } else {
        $title = trim($_POST['title'] ?? '');
        $descr = trim($_POST['description'] ?? '');
        $type = $_POST['test_type'] ?? '';
        $price = 0;
        $min_score = (int)($_POST['min_score'] ?? 0);
        $max_score = (int)($_POST['max_score'] ?? 100);
        $start_date = convertDatetimeLocalToMysql($_POST['start_date'] ?? '');
        $end_date = convertDatetimeLocalToMysql($_POST['end_date'] ?? '');
        $material_id = ($type === 'daily' && !empty($_POST['material_id'])) ? (int)$_POST['material_id'] : null;

        if ($title === '') {
            $errors['title'] = 'Название теста обязательно';
        }
        if ($descr === '') {
            $errors['description'] = 'Описание обязательно';
        }
        if (!in_array($type, ['daily', 'weekly'])) {
            $errors['test_type'] = 'Выберите тип теста';
        }
        if ($min_score < 0) {
            $errors['min_score'] = 'Минимальный балл не может быть отрицательным';
        }
        if ($max_score < 0) {
            $errors['max_score'] = 'Максимальный балл не может быть отрицательным';
        }
        if ($min_score > $max_score) {
            $errors['scores'] = 'Минимальный балл не может быть больше максимального';
        }
        if (!$start_date) {
            $errors['start_date'] = 'Укажите дату начала';
        }
        if (!$end_date) {
            $errors['end_date'] = 'Укажите дату окончания';
        }
        if ($start_date && $end_date && strtotime($start_date) >= strtotime($end_date)) {
            $errors['dates'] = 'Дата начала должна быть раньше даты окончания';
        }
        if ($type === 'daily' && empty($material_id)) {
            $errors['material_id'] = 'Выберите материал для ежедневного теста';
        } elseif ($type === 'daily' && $material_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE material_id = ? AND course_id = ? AND tenant_id = ?");
            $stmt->execute([$material_id, $course_id, Tenant::getId()]);
            if ($stmt->fetchColumn() == 0) {
                $errors['material_id'] = 'Выбранный материал не существует';
            } else {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM daily_tests WHERE material_id = ? AND tenant_id = ?");
                $stmtCheck->execute([$material_id, Tenant::getId()]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $errors['material_id'] = 'Для этого материала уже существует тест. Разрешен только один тест на материал.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                if ($type === 'daily') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO daily_tests
                            (tenant_id, course_id, material_id, title, description, start_date, end_date, min_score, max_score)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([
                        Tenant::getId(),
                        $course_id,
                        $material_id,
                        $title,
                        $descr,
                        $start_date,
                        $end_date,
                        $min_score,
                        $max_score
                    ]);
                    $test_id = $pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO weekly_tests
                            (tenant_id, course_id, title, description, start_date, end_date, min_score, max_score)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([
                        Tenant::getId(),
                        $course_id,
                        $title,
                        $descr,
                        $start_date,
                        $end_date,
                        $min_score,
                        $max_score
                    ]);
                    $test_id = $pdo->lastInsertId();
                }

                $test_dir = TenantFile::getFullPath(Tenant::getId(), "courses/{$course_id}");

                try {
                    if (!is_dir($test_dir)) {
                        if (!mkdir($test_dir, 0755, true)) {
                            throw new Exception("Не удалось создать папку: {$test_dir}");
                        }
                    }
                } catch (Exception $e) {
                    error_log("create_test_dir error: " . $e->getMessage());
                    $message = '✅ Тест сохранен, но ошибка при создании папки.';
                }

                $pdo->commit();
                header("Location: view.php?test_id={$test_id}&type={$type}&tab=questions");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("add_test error: " . $e->getMessage());
                $errors['database'] = 'Ошибка при сохранении теста. Попробуйте позже.';
            }
        }
    }
    $_SESSION['form_data'] = $_POST;
}

$pageTitle = 'Добавить тест';
include __DIR__ . '/../includes/header.php';
?>

<a href="index_tests.php?course_id=<?= $course_id ?>" class="back-link">&larr; К тестам</a>
<h1 class="page-title">Добавление теста к курсу «<?= h($course['title']) ?>»</h1>

<?php if ($message): ?>
    <div class="msg <?= strpos($message, '✅') === 0 ? 'msg-ok' : 'msg-err' ?>"><?= h($message) ?></div>
<?php endif; ?>
<?php if (!empty($errors['form'])): ?>
    <div class="msg msg-err"><?= h($errors['form']) ?></div>
<?php endif; ?>
<?php if (!empty($errors['database'])): ?>
    <div class="msg msg-err"><?= h($errors['database']) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">

        <div class="form-group">
            <label>Тип теста *</label>
            <select name="test_type" required>
                <option value="">-- Выберите --</option>
                <option value="daily" <?= ($_SESSION['form_data']['test_type'] ?? '') === 'daily' ? 'selected' : '' ?>>Ежедневный</option>
                <option value="weekly" <?= ($_SESSION['form_data']['test_type'] ?? '') === 'weekly' ? 'selected' : '' ?>>Еженедельный</option>
            </select>
            <?php if (isset($errors['test_type'])): ?>
                <div class="error-text"><?= h($errors['test_type']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group" id="material-label" style="display:none">
            <label>Материал *</label>
            <select name="material_id" required>
                <option selected disabled>-- Выберите материал --</option>
                <?php foreach ($materials as $mat): ?>
                    <option value="<?= $mat['material_id'] ?>" <?= ($_SESSION['form_data']['material_id'] ?? '') == $mat['material_id'] ? 'selected' : '' ?>>
                        <?= h($mat['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['material_id'])): ?>
                <div class="error-text"><?= h($errors['material_id']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Название теста *</label>
            <input type="text" name="title" value="<?= h($_SESSION['form_data']['title'] ?? '') ?>" required placeholder="Введите название теста">
            <?php if (isset($errors['title'])): ?>
                <div class="error-text"><?= h($errors['title']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Описание *</label>
            <textarea name="description" rows="4" required placeholder="Опишите тест"><?= h($_SESSION['form_data']['description'] ?? '') ?></textarea>
            <?php if (isset($errors['description'])): ?>
                <div class="error-text"><?= h($errors['description']) ?></div>
            <?php endif; ?>
        </div>

        <input type="hidden" name="price" value="0">

        <div class="form-row">
            <div class="form-group">
                <label>Минимальный балл *</label>
                <input type="number" name="min_score" min="0" value="<?= h($_SESSION['form_data']['min_score'] ?? 0) ?>" required placeholder="Мин. балл">
                <?php if (isset($errors['min_score'])): ?>
                    <div class="error-text"><?= h($errors['min_score']) ?></div>
                <?php elseif (isset($errors['scores'])): ?>
                    <div class="error-text"><?= h($errors['scores']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Максимальный балл *</label>
                <input type="number" name="max_score" min="0" value="<?= h($_SESSION['form_data']['max_score'] ?? 100) ?>" required placeholder="Макс. балл">
                <?php if (isset($errors['max_score'])): ?>
                    <div class="error-text"><?= h($errors['max_score']) ?></div>
                <?php elseif (isset($errors['scores'])): ?>
                    <div class="error-text"><?= h($errors['scores']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Дата и время начала *</label>
                <input type="datetime-local" name="start_date" value="<?= h($_SESSION['form_data']['start_date'] ?? '') ?>" required>
                <?php if (isset($errors['start_date'])): ?>
                    <div class="error-text"><?= h($errors['start_date']) ?></div>
                <?php elseif (isset($errors['dates'])): ?>
                    <div class="error-text"><?= h($errors['dates']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Дата и время конца *</label>
                <input type="datetime-local" name="end_date" value="<?= h($_SESSION['form_data']['end_date'] ?? '') ?>" required>
                <?php if (isset($errors['end_date'])): ?>
                    <div class="error-text"><?= h($errors['end_date']) ?></div>
                <?php elseif (isset($errors['dates'])): ?>
                    <div class="error-text"><?= h($errors['dates']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-success">Сохранить тест</button>
        </div>
    </form>
</div>

<script>
const testTypeSelect = document.querySelector('select[name="test_type"]');
const materialLabel = document.getElementById('material-label');
const priceLabel = document.getElementById('price-label');
const dailyPriceInput = document.getElementById('daily-price');

function toggleFields() {
    const isDaily = testTypeSelect.value === 'daily';
    materialLabel.style.display = isDaily ? 'block' : 'none';
    priceLabel.style.display = isDaily ? 'none' : 'block';
    dailyPriceInput.disabled = !isDaily;
}

testTypeSelect.addEventListener('change', toggleFields);
window.addEventListener('DOMContentLoaded', toggleFields);
</script>
<?php unset($_SESSION['form_data']); ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
