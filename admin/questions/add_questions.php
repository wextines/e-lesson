<?php
$pageTitle = 'Добавить вопросы';
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php';
require '../../php/config.php';
require '../../php/tenant_file.php';

// Получение параметров из GET-запроса
$test_id = $_GET['test_id'] ?? null;
$type = $_GET['type'] ?? null;

// Валидация параметров
if ($test_id && in_array($type, ['daily', 'weekly'])) {
    header('Location: /admin/tests/view.php?test_id=' . (int)$test_id . '&type=' . $type . '&tab=questions');
    exit;
}
header('Location: /admin/courses/');
exit;

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
if (!$test) {
    displayError("Тест не найден.");
}
$course_id = $test['course_id'];
$test_title = $test['title'];

// Получение названия курса
$stmt = $pdo->prepare("SELECT title FROM courses WHERE course_id = ? AND tenant_id = ?");
$stmt->execute([$course_id, Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    displayError("Курс не найден.");
}
$course_title = $course['title'];

// Формирование пути загрузки
$test_type_dir = $type === 'daily' ? 'daily_tests' : 'weekly_tests';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $question_numbers = $_POST['question_number'] ?? [];
    $correct_answers = $_POST['correct_answer'] ?? [];
    $question_files = $_FILES['question_img_url'] ?? [];

    $errors = [];
    $success_count = 0;

    // Проверка, что массивы имеют одинаковую длину
    $count = count($question_numbers);
    if ($count !== count($correct_answers) || $count !== count($question_files['name'])) {
        $errors[] = "Некорректные данные формы.";
    } else {
        for ($i = 0; $i < $count; $i++) {
            $question_number = $question_numbers[$i];
            $correct_answer = $correct_answers[$i];
            $question_img_url = $question_files['name'][$i];
            $question_tmp_name = $question_files['tmp_name'][$i];
            $question_mime_type = $question_files['type'][$i];

            // Валидация
            if (!is_numeric($question_number) || $question_number <= 0) {
                $errors["question_number_$i"] = "№ вопроса #{$i} должен быть положительным числом.";
                continue;
            }
            if (empty($question_img_url) || empty($correct_answer)) {
                $errors["question_$i"] = "Заполните все поля для вопроса #{$i}.";
                continue;
            }
            if (!in_array($correct_answer, ['A', 'B', 'C', 'D', 'E'])) {
                $errors["correct_answer_$i"] = "Недопустимый правильный ответ для вопроса #{$i}.";
                continue;
            }

            // Проверка, что файл является изображением
            if (!str_starts_with($question_mime_type, 'image/')) {
                $errors["question_img_$i"] = "Файл для вопроса #{$i} должен быть изображением.";
                continue;
            }

            // Получение расширения файла
            $extension = strtolower(pathinfo($question_img_url, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                $errors["question_img_$i"] = "Недопустимый формат изображения для вопроса #{$i}.";
                continue;
            }

            // Загрузка файла через TenantFile
            $uploadResult = TenantFile::handleUpload(
                [
                    'tmp_name' => $question_tmp_name,
                    'name' => $question_img_url,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 0,
                ],
                Tenant::getId(),
                'courses',
                $course_id
            );

            if (!$uploadResult) {
                $errors["question_img_$i"] = "Ошибка при загрузке файла для вопроса #{$i}.";
                continue;
            }

            $db_file_path = $uploadResult['relativePath'];

            try {
                $stmt = $pdo->prepare("INSERT INTO $question_table ($test_column, question_img_url, correct_answer) VALUES (?, ?, ?)");
                $stmt->execute([$test_id, $db_file_path, $correct_answer]);
                $success_count++;
            } catch (PDOException $e) {
                error_log("add_question error: " . $e->getMessage());
                $errors["question_$i"] = "Ошибка при добавлении вопроса #{$i}. Попробуйте позже.";
            }
        }
    }

    // Store form data in session
    $_SESSION['form_data'] = $_POST;
    $_SESSION['form_data']['question_img_url'] = array_map('basename', $question_files['name']);

    if ($success_count > 0 || !empty($errors)) {
        $redirect_url = "/admin/tests/view.php?test_id={$test_id}&type={$type}&tab=questions";
        if ($success_count > 0) {
            $redirect_url .= "&message=" . urlencode("Успешно обработано $success_count вопрос(ов).");
        }
        if (!empty($errors)) {
            $redirect_url .= "&errors=" . urlencode(implode('|', $errors));
        }
        header("Location: $redirect_url");
        exit;
    }
}

// Функция для отображения ошибок
function displayError($message)
{
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

<h1 class="page-title">Добавить вопросы к тесту «<?php echo htmlspecialchars($test_title); ?>» курса «<?php echo htmlspecialchars($course_title); ?>»</h1>

<?php if (isset($_GET['message'])): ?>
    <div class="msg msg-ok"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></div>
<?php endif; ?>
<?php if (isset($_GET['errors'])): ?>
    <div class="msg msg-err"><?php echo htmlspecialchars(str_replace('|', '<br>', urldecode($_GET['errors']))); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div id="questions-container">
            <div class="question-group" style="border:1px solid var(--gray-200);padding:14px;margin-bottom:14px;border-radius:var(--radius-md);background:var(--gray-50);">
                <div class="form-group">
                    <label>№ вопроса:</label>
                    <input type="number" name="question_number[]" min="1" required placeholder="Введите № вопроса" value="<?php echo htmlspecialchars($_SESSION['form_data']['question_number'][0] ?? ''); ?>" class="<?php echo isset($_SESSION['form_data']['errors']['question_number_0']) ? 'error' : ''; ?>">
                    <?php if (isset($_SESSION['form_data']['errors']['question_number_0'])): ?>
                        <div class="error-text" style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($_SESSION['form_data']['errors']['question_number_0']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Изображение вопроса:</label>
                    <input type="file" name="question_img_url[]" accept="image/*" required class="<?php echo isset($_SESSION['form_data']['errors']['question_img_0']) ? 'error' : ''; ?>">
                    <?php if (isset($_SESSION['form_data']['errors']['question_img_0'])): ?>
                        <div class="error-text" style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($_SESSION['form_data']['errors']['question_img_0']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Правильный ответ:</label>
                    <select name="correct_answer[]" required class="<?php echo isset($_SESSION['form_data']['errors']['correct_answer_0']) ? 'error' : ''; ?>">
                        <option value="" disabled <?php echo empty($_SESSION['form_data']['correct_answer'][0]) ? 'selected' : ''; ?>>Выберите вариант</option>
                        <option value="A" <?php echo ($_SESSION['form_data']['correct_answer'][0] ?? '') === 'A' ? 'selected' : ''; ?>>A</option>
                        <option value="B" <?php echo ($_SESSION['form_data']['correct_answer'][0] ?? '') === 'B' ? 'selected' : ''; ?>>B</option>
                        <option value="C" <?php echo ($_SESSION['form_data']['correct_answer'][0] ?? '') === 'C' ? 'selected' : ''; ?>>C</option>
                        <option value="D" <?php echo ($_SESSION['form_data']['correct_answer'][0] ?? '') === 'D' ? 'selected' : ''; ?>>D</option>
                        <option value="E" <?php echo ($_SESSION['form_data']['correct_answer'][0] ?? '') === 'E' ? 'selected' : ''; ?>>E</option>
                    </select>
                    <?php if (isset($_SESSION['form_data']['errors']['correct_answer_0'])): ?>
                        <div class="error-text" style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($_SESSION['form_data']['errors']['correct_answer_0']); ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(this)">Удалить</button>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-success btn-sm" onclick="addQuestion()" style="width:100%;margin-bottom:12px;">Добавить вопрос</button>
        <button type="submit" class="btn btn-primary" style="width:100%;">Сохранить вопросы</button>
    </form>
</div>

<a href="/admin/tests/view.php?test_id=<?= $test_id ?>&type=<?= $type ?>&tab=questions" class="back-link">&larr; Back to Questions</a>

<script>
    function addQuestion() {
        const container = document.getElementById('questions-container');
        const questionGroup = document.createElement('div');
        questionGroup.className = 'question-group';
        questionGroup.style.cssText = 'border:1px solid var(--gray-200);padding:14px;margin-bottom:14px;border-radius:var(--radius-md);background:var(--gray-50);';
        questionGroup.innerHTML = `
            <div class="form-group">
                <label>№ вопроса:</label>
                <input type="number" name="question_number[]" min="1" required placeholder="Введите № вопроса">
            </div>
            <div class="form-group">
                <label>Изображение вопроса:</label>
                <input type="file" name="question_img_url[]" accept="image/*" required>
            </div>
            <div class="form-group">
                <label>Правильный ответ:</label>
                <select name="correct_answer[]" required>
                    <option value="" disabled selected>Выберите вариант</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                    <option value="E">E</option>
                </select>
            </div>
            <div style="text-align:right;">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(this)">Удалить</button>
            </div>
        `;
        container.appendChild(questionGroup);
    }

    function removeQuestion(button) {
        button.closest('.question-group').remove();
    }
</script>

<?php unset($_SESSION['form_data']); ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
