<?php
$pageTitle = 'Редактирование курса';
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php'; 
require '../../php/config.php';

function h($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}


$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if (!$course_id) {
    die('Не указан ID курса');
}

$stmt = $pdo->prepare('SELECT * FROM courses WHERE course_id = ? AND tenant_id = ?');
$stmt->execute([$course_id, Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    die('Курс не найден');
}

$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_course') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $classLevel = $_POST['classLevel'] ?? '';
    $subscription_days = (int)($_POST['subscription_days'] ?? 0);

    // Validation
    if (empty($title)) {
        $errors['title'] = 'Название курса обязательно';
    }
    if (empty($subject)) {
        $errors['subject'] = 'Предмет обязателен';
    }
    if (empty($description)) {
        $errors['description'] = 'Описание обязательно';
    }
    if ($price < 0) {
        $errors['price'] = 'Цена не может быть отрицательной';
    }
    if (!in_array($classLevel, ['1-2', '3-4', '5-6', '7-8', '9-11', '1-4'])) {
        $errors['classLevel'] = 'Выберите уровень класса';
    }
    if ($subscription_days <= 0) {
        $errors['subscription_days'] = 'Дней доступа должно быть больше 0';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE courses SET
                    title = ?,
                    subject = ?,
                    description = ?,
                    price = ?,
                    classLevel = ?,
                    subscription_days = ?
                 WHERE course_id = ? AND tenant_id = ?'
            );
            $stmt->execute([
                $title,
                $subject,
                $description,
                $price,
                $classLevel,
                $subscription_days,
                $course_id,
                Tenant::getId()
            ]);
            $message = 'Курс успешно обновлен';
            header('Location: /admin/courses/view.php?course_id=' . $course_id);
            exit;
        } catch (PDOException $e) {
            error_log("edit_course error: " . $e->getMessage());
            $message = 'Ошибка при обновлении курса. Попробуйте позже.';
            $errors['database'] = $message;
        }
    } else {
        $message = 'Пожалуйста, исправьте ошибки в форме';
        $_SESSION['form_data'] = $_POST;
    }
}

$materials = $pdo->prepare('SELECT material_id, title FROM materials WHERE course_id = ? AND tenant_id = ?');
$materials->execute([$course_id, Tenant::getId()]);
$materialsList = $materials->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Редактирование курса: <b><?= h($course['title']) ?></b></h1>

<?php if ($message): ?>
    <div class="msg <?= empty($errors) ? 'msg-ok' : 'msg-err' ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_course">

        <div class="form-row">
            <div class="form-group">
                <label>Предмет</label>
                <input type="text" name="subject" value="<?= h($_SESSION['form_data']['subject'] ?? $course['subject']) ?>" required placeholder="Введите предмет" class="<?= isset($errors['subject']) ? 'error' : '' ?>">
                <?php if (isset($errors['subject'])): ?>
                    <div class="error-text"><?= h($errors['subject']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Название курса</label>
                <input type="text" name="title" value="<?= h($_SESSION['form_data']['title'] ?? $course['title']) ?>" required placeholder="Введите название курса" class="<?= isset($errors['title']) ? 'error' : '' ?>">
                <?php if (isset($errors['title'])): ?>
                    <div class="error-text"><?= h($errors['title']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label>Описание</label>
            <textarea name="description" rows="4" required placeholder="Опишите курс"><?= h($_SESSION['form_data']['description'] ?? $course['description']) ?></textarea>
            <?php if (isset($errors['description'])): ?>
                <div class="error-text"><?= h($errors['description']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Цена (Sum)</label>
                <input type="number" name="price" step="0.01" min="0" value="<?= h($_SESSION['form_data']['price'] ?? $course['price']) ?>" required placeholder="Введите цену" class="<?= isset($errors['price']) ? 'error' : '' ?>">
                <?php if (isset($errors['price'])): ?>
                    <div class="error-text"><?= h($errors['price']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Класс</label>
                <select name="classLevel" required class="<?= isset($errors['classLevel']) ? 'error' : '' ?>">
                    <?php
                    foreach (["1-2", "3-4", "5-6", "7-8", "9-11", "1-4"] as $level):
                        $selected = (($_SESSION['form_data']['classLevel'] ?? $course['classLevel']) === $level) ? 'selected' : '';
                        $text = ($level === '1-4') ? "$level курс" : "$level";
                    ?>
                        <option value="<?= $level ?>" <?= $selected ?>><?= $text ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['classLevel'])): ?>
                    <div class="error-text"><?= h($errors['classLevel']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Дней доступа</label>
                <input type="number" name="subscription_days" min="1" value="<?= h($_SESSION['form_data']['subscription_days'] ?? $course['subscription_days']) ?>" required placeholder="Введите количество дней" class="<?= isset($errors['subscription_days']) ? 'error' : '' ?>">
                <?php if (isset($errors['subscription_days'])): ?>
                    <div class="error-text"><?= h($errors['subscription_days']) ?></div>
                <?php endif; ?>
            </div>
        </div>


        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
    </form>
</div>

<a href="/admin/courses/view.php?course_id=<?= $course_id ?>" class="btn btn-secondary">&larr; Back to Course</a>

<?php unset($_SESSION['form_data']); ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
