<?php
$pageTitle = 'Вопросы';
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../authorization.php'; 
require '../../login/Login_functions.php';
initSession();
require '../../php/config.php';
require '../../php/tenant_file.php';

// Получение параметров из GET-запроса
$test_id = $_GET['test_id'] ?? null;
$type = $_GET['type'] ?? null;
$course_id = $_GET['course_id'] ?? null;

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

// Получение списка вопросов
if ($type === 'daily') {
    $stmt = $pdo->prepare("SELECT daily_question_id, question_img_url, correct_answer FROM daily_questions WHERE daily_test_id = ? ORDER BY daily_question_id");
} else {
    $stmt = $pdo->prepare("SELECT weekly_question_id, question_img_url, correct_answer FROM weekly_questions WHERE weekly_test_id = ? ORDER BY weekly_question_id");
}
$stmt->execute([$test_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question_number'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    $delete_question_number = $_POST['delete_question_number'];

    try {
        // Получение пути к файлу
        $stmt = $pdo->prepare("SELECT question_img_url FROM $question_table WHERE $id_column = ? AND $test_column = ?");
        $stmt->execute([$delete_question_number, $test_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($question) {
            // Удаление вопроса из базы данных
            $stmt = $pdo->prepare("DELETE FROM $question_table WHERE $id_column = ? AND $test_column = ?");
            $stmt->execute([$delete_question_number, $test_id]);

            // Удаление файла с сервера
            TenantFile::deleteFile(Tenant::getId(), $question['question_img_url']);

            $message = "Вопрос успешно удалён.";
            // Перезагрузка страницы для обновления списка
            header("Location: index_questions.php?test_id=$test_id&type=$type&course_id=$course_id");
            exit;
        } else {
            $errors[] = "Вопрос не найден.";
        }
    } catch (PDOException $e) {
        error_log("delete_question error: " . $e->getMessage());
        $errors[] = "Ошибка при удалении вопроса. Попробуйте позже.";
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

<h1 class="page-title">Вопросы теста «<?php echo htmlspecialchars($test_title); ?>» курса «<?php echo htmlspecialchars($course_title); ?>»</h1>

<?php if (isset($message)): ?>
    <div class="msg msg-ok"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="msg msg-err"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
<?php endif; ?>

<a href="add_questions.php?test_id=<?php echo $test_id; ?>&type=<?php echo $type; ?>&course_id=<?php echo $course_id; ?>" class="btn btn-success btn-sm" style="margin-bottom:16px;">
    Добавить новый вопрос
</a>

<?php if (empty($questions)): ?>
    <p class="table-empty">Вопросы отсутствуют.</p>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>№ вопроса</th>
                        <th>Изображение</th>
                        <th>Правильный ответ</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td data-label="№ вопроса"><b><?php echo htmlspecialchars($question[$id_column]); ?></b></td>
                            <td data-label="Изображение"><img src="/file.php?type=question&id=<?php echo $question[$id_column]; ?>" alt="Вопрос" class="img-thumb" style="max-width:80px;height:auto;border-radius:4px;cursor:pointer;" onclick="openImageModal(this.src)"></td>
                            <td data-label="Правильный ответ"><b><?php echo htmlspecialchars($question['correct_answer']); ?></b></td>
                            <td data-label="Действия">
                                <div class="btn-group">
                                    <a href="edit_questions.php?test_id=<?php echo $test_id; ?>&type=<?php echo $type; ?>&course_id=<?php echo $course_id; ?>&question_number=<?php echo $question[$id_column]; ?>" class="btn btn-primary btn-sm">Редактировать</a>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="delete_question_number" value="<?php echo $question[$id_column]; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Вы уверены, что хотите удалить этот вопрос?');">Удалить</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<a href="../tests/index_tests.php?course_id=<?php echo $course_id; ?>" class="back-link">← Назад к тестам</a>

<style>
.image-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}
.image-modal.active {
    opacity: 1;
    visibility: visible;
}
.image-modal img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 8px;
}
.image-modal .close-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    background: none;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding: 8px;
}
</style>

<script>
    function openImageModal(src) {
        const modal = document.createElement('div');
        modal.className = 'image-modal';
        modal.innerHTML = `
            <img src="${src}" alt="Полноэкранное изображение">
            <button class="close-btn" onclick="this.parentElement.remove()">✕</button>
        `;
        modal.onclick = (e) => {
            if (e.target === modal) modal.remove();
        };
        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('active'), 10);
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
