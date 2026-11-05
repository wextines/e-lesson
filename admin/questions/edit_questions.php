<?php
$pageTitle = 'Редактировать вопросы';
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php'; 
require '../../php/config.php';
require '../../php/tenant_file.php';

// Получение параметров из GET-запроса
$test_id = $_GET['test_id'] ?? null;
$type = $_GET['type'] ?? null;
$course_id = $_GET['course_id'] ?? null;
$question_number = $_GET['question_number'] ?? null;

// Валидация параметров
if (!$test_id || !in_array($type, ['daily', 'weekly']) || !$course_id) {
    displayError("Неверные параметры.");
}

// Определение таблиц и столбцов
if ($type === 'daily') {
    $question_table = 'daily_questions';
    $test_column = 'daily_test_id';
    $id_column = 'daily_question_id';
    $test_table = 'daily_tests';
} else {
    $question_table = 'weekly_questions';
    $test_column = 'weekly_test_id';
    $id_column = 'weekly_question_id';
    $test_table = 'weekly_tests';
}

// Получение course_id и названия теста
$stmt = $pdo->prepare("SELECT course_id, title FROM $test_table WHERE $test_column = ? AND tenant_id = ?");
$stmt->execute([$test_id, Tenant::getId()]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test || $test['course_id'] != $course_id) {
    displayError("Тест не найден или не соответствует курсу.");
}
$test_title = $test['title'];

// Получение названия курса
$stmt = $pdo->prepare("SELECT title FROM courses WHERE course_id = ? AND tenant_id = ?");
$stmt->execute([$course_id, Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    displayError("Курс не найден.");
}
$course_title = $course['title'];

// Получение данных вопроса, если редактирование
$question = null;
if ($question_number) {
    if ($type === 'daily') {
        $stmt = $pdo->prepare("SELECT daily_question_id, question_text, question_img_url, correct_answer FROM daily_questions WHERE daily_question_id = ? AND daily_test_id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT weekly_question_id, question_text, question_img_url, correct_answer FROM weekly_questions WHERE weekly_question_id = ? AND weekly_test_id = ?");
    }
    $stmt->execute([$question_number, $test_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$question) {
        displayError("Вопрос не найден.");
    }
}

// Формирование пути загрузки
$test_type_dir = $type === 'daily' ? 'daily_tests' : 'weekly_tests';

require_once '../../php/question_service.php';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $question_text = trim($_POST['question_text'] ?? '');
    $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? ''));
    $question_file = (!empty($_FILES['question_img_url']['name'])) ? $_FILES['question_img_url'] : null;
    $remove_image = !empty($_POST['remove_image']) ? true : false;

    $errors = [];

    if (!in_array($correct_answer, ['A', 'B', 'C', 'D', 'E'])) {
        $errors[] = "Недопустимый правильный ответ (A-E).";
    }

    if (empty($errors)) {
        if ($question) {
            $res = QuestionService::updateQuestion(
                $pdo,
                Tenant::getId(),
                $type,
                (int)$test_id,
                (int)$course_id,
                (int)$question_number,
                $question_text,
                $question_file,
                $remove_image,
                $correct_answer
            );
        } else {
            $res = QuestionService::addQuestion(
                $pdo,
                Tenant::getId(),
                $type,
                (int)$test_id,
                (int)$course_id,
                $question_text,
                $question_file,
                $correct_answer
            );
        }

        if ($res['success']) {
            header("Location: /admin/tests/view.php?test_id=$test_id&type=$type&tab=questions");
            exit;
        } else {
            $errors[] = $res['error'] ?? "Ошибка при сохранении вопроса.";
        }
    }
}

// Функция для отображения ошибок
function displayError($message) {
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ошибка</title>
</head>
<body>
    <div class="container">
        <h1 class="page-title">Ошибка</h1>
        <div class="msg msg-err"><?= htmlspecialchars($message) ?></div>
        <a href="javascript:history.back()" class="btn btn-secondary">Вернуться назад</a>
    </div>
</body>
</html>
<?php
    exit;
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h1 class="page-title"><?php echo $question ? 'Редактировать вопрос' : 'Добавить вопрос'; ?> теста «<?php echo htmlspecialchars($test_title); ?>» курса «<?php echo htmlspecialchars($course_title); ?>»</h1>

<?php if (!empty($errors)): ?>
    <div class="msg msg-err"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>№ вопроса:</label>
            <input type="number" name="question_number" min="1" value="<?php echo $question ? htmlspecialchars($question[$id_column]) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label>Текст вопроса:</label>
            <textarea name="question_text" rows="3" placeholder="Введите формулировку вопроса..." style="width:100%; border:1px solid var(--border-color); border-radius:var(--radius-md); padding:8px 12px; font-family:inherit; font-size:14px;"><?php echo $question ? htmlspecialchars($question['question_text'] ?? '') : ''; ?></textarea>
        </div>
        <div class="form-group">
            <label>Текущее изображение:</label>
            <?php if ($question && $question['question_img_url']): ?>
                <div style="margin-bottom:8px;">
                    <img src="/file.php?type=question&id=<?php echo $question[$type === 'daily' ? 'daily_question_id' : 'weekly_question_id']; ?>" alt="Текущее изображение" class="img-preview" style="max-width:260px;height:auto;border-radius:var(--radius-md);border:1px solid #e2e8f0;display:block;">
                </div>
                <label style="color:#dc2626; font-size:13px; display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="remove_image" value="1">
                    Удалить текущее изображение
                </label>
            <?php else: ?>
                <p style="color:var(--gray-400);font-size:13px; margin:0;">Изображение отсутствует.</p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Загрузить новое изображение (опционально):</label>
            <input type="file" name="question_img_url" accept="image/*">
        </div>
        <div class="form-group">
            <label>Правильный ответ:</label>
            <select name="correct_answer" required>
                <option value="" disabled <?php echo !$question ? 'selected' : ''; ?>>Выберите вариант</option>
                <?php foreach (['A', 'B', 'C', 'D', 'E'] as $option): ?>
                    <option value="<?php echo $option; ?>" <?php echo $question && $question['correct_answer'] === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Сохранить</button>
    </form>
</div>

<a href="/admin/tests/view.php?test_id=<?= $test_id ?>&type=<?= $type ?>&tab=questions" class="back-link">&larr; Back to Questions</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
