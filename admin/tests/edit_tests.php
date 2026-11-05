<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php'; 
require '../../php/config.php';

function h($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function convertMysqlToDatetimeLocal($mysqlDate)
{
    if (!$mysqlDate) return '';
    try {
        $date = new DateTime($mysqlDate);
        return $date->format('Y-m-d\TH:i');
    } catch (Exception $e) {
        return '';
    }
}

function convertDatetimeLocalToMysql($dt)
{
    if (!$dt) return null;
    return str_replace('T', ' ', $dt) . ':00';
}

$test_id   = isset($_GET['test_id'])   ? (int)$_GET['test_id']   : 0;
$test_type = isset($_GET['type'])      ? $_GET['type']           : '';
if (!$test_id || !in_array($test_type, ['daily', 'weekly'])) {
    die('Не указан ID теста или тип');
}

if ($test_type === 'daily') {
    $table = 'daily_tests';
} else {
    $table = 'weekly_tests';
}

$stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$test_type}_test_id = ? AND tenant_id = ?");
$stmt->execute([$test_id, Tenant::getId()]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) die('Тест не найден');

$stmt = $pdo->prepare("SELECT title FROM courses WHERE course_id = ? AND tenant_id = ?");
$stmt->execute([$test['course_id'], Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) die('Курс не найден');

$message = '';

$materials = [];
if ($test_type === 'daily') {
    $mt = $pdo->prepare("SELECT material_id, title FROM materials WHERE course_id = ? AND tenant_id = ?");
    $mt->execute([$test['course_id'], Tenant::getId()]);
    $materials = $mt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title       = trim($_POST['title'] ?? '');
    $descr       = trim($_POST['description'] ?? '');
    $price       = 0;
    $min_score   = (int)($_POST['min_score'] ?? 0);
    $max_score   = (int)($_POST['max_score'] ?? 100);
    $start_date  = convertDatetimeLocalToMysql($_POST['start_date'] ?? '');
    $end_date    = convertDatetimeLocalToMysql($_POST['end_date'] ?? '');

    if (!$title) {
        $message = '❌ Укажите название теста';
    } elseif (!$descr) {
        $message = '❌ Укажите описание';
    } elseif ($min_score < 0) {
        $message = '❌ Минимальный балл не может быть отрицательным';
    } elseif ($max_score < 0) {
        $message = '❌ Максимальный балл не может быть отрицательным';
    } elseif ($min_score > $max_score) {
        $message = '❌ Минимальный балл не может быть больше максимального';
    } elseif (!$start_date) {
        $message = '❌ Укажите дату начала';
    } elseif (!$end_date) {
        $message = '❌ Укажите дату окончания';
    } elseif (strtotime($start_date) >= strtotime($end_date)) {
        $message = '❌ Дата начала должна быть раньше даты окончания';
    }

    if ($test_type === 'daily') {
        $material_id = !empty($_POST['material_id']) ? (int)$_POST['material_id'] : null;
        if ($material_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE material_id = ? AND course_id = ? AND tenant_id = ?");
            $stmt->execute([$material_id, $test['course_id'], Tenant::getId()]);
            if ($stmt->fetchColumn() == 0) {
                $message = '❌ Выбранный материал не существует';
            }
        }
    }

    if (!$message) {
        try {
            if ($test_type === 'daily') {
                $upd = $pdo->prepare(
                    "UPDATE daily_tests SET
                        material_id = ?, title = ?, description = ?, start_date = ?, end_date = ?, min_score = ?, max_score = ?
                     WHERE daily_test_id = ? AND tenant_id = ?"
                );
                $upd->execute([
                    $material_id,
                    $title,
                    $descr,
                    $start_date,
                    $end_date,
                    $min_score,
                    $max_score,
                    $test_id,
                    Tenant::getId()
                ]);
            } else {
                $upd = $pdo->prepare(
                    "UPDATE weekly_tests SET
                        title = ?, description = ?, start_date = ?, end_date = ?, min_score = ?, max_score = ?
                     WHERE weekly_test_id = ? AND tenant_id = ?"
                );
                $upd->execute([
                    $title,
                    $descr,
                    $start_date,
                    $end_date,
                    $min_score,
                    $max_score,
                    $test_id,
                    Tenant::getId()
                ]);
            }
                header("Location: /admin/tests/view.php?test_id={$test_id}&type={$test_type}&tab=edit");
                exit;
        } catch (PDOException $e) {
            error_log("edit_test error: " . $e->getMessage());
            $message = '❌ Ошибка при сохранении. Попробуйте позже.';
        }
    }
}

$pageTitle = 'Редактировать тест';
include __DIR__ . '/../includes/header.php';
?>

<a href="/admin/tests/view.php?test_id=<?= $test_id ?>&type=<?= $test_type ?>&tab=edit" class="back-link">&larr; Back to Test</a>
<h1 class="page-title">Редактировать <?= $test_type === 'daily' ? 'ежедневный' : 'еженедельный' ?> тест — <?= h($test['title']) ?></h1>

<?php if ($message): ?>
    <div class="msg <?= strpos($message, '❌') === 0 ? 'msg-err' : 'msg-ok' ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <?php if ($test_type === 'daily'): ?>
            <div class="form-group">
                <label>Материал</label>
                <select name="material_id">
                    <option disabled selected>-- Без материала --</option>
                    <?php foreach ($materials as $mat): ?>
                        <option value="<?= $mat['material_id'] ?>" <?= $mat['material_id'] == $test['material_id'] ? 'selected' : '' ?>><?= h($mat['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Название теста *</label>
            <input type="text" name="title" value="<?= h($test['title']) ?>" required>
        </div>

        <div class="form-group">
            <label>Описание *</label>
            <textarea name="description" rows="4" required><?= h($test['description']) ?></textarea>
        </div>

        <input type="hidden" name="price" value="0">

        <div class="form-row">
            <div class="form-group">
                <label>Минимальный балл *</label>
                <input type="number" name="min_score" min="0" value="<?= h($test['min_score'] ?? 0) ?>" required>
            </div>
            <div class="form-group">
                <label>Максимальный балл *</label>
                <input type="number" name="max_score" min="0" value="<?= h($test['max_score'] ?? 100) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Дата и время начала *</label>
                <input type="datetime-local" name="start_date"
                    value="<?= h(convertMysqlToDatetimeLocal($test['start_date'])) ?>" required>
            </div>
            <div class="form-group">
                <label>Дата и время конца *</label>
                <input type="datetime-local" name="end_date"
                    value="<?= h(convertMysqlToDatetimeLocal($test['end_date'])) ?>" required>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-success">Сохранить изменения</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
