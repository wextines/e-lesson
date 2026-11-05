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

function deleteDir($dirPath)
{
    if (!is_dir($dirPath)) return;
    $items = scandir($dirPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            deleteDir($itemPath);
        } else {
            unlink($itemPath);
        }
    }
    rmdir($dirPath);
}

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if ($course_id) {
    header('Location: /admin/courses/view.php?course_id=' . $course_id . '&tab=tests');
    exit;
}
die("No course specified");

$stmt = $pdo->prepare("SELECT title FROM courses WHERE course_id = ? AND tenant_id = ?");
$stmt->execute([$course_id, Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) die("Курс не найден");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_test') {
    if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        $_SESSION['message'] = '❌ Неверный CSRF-токен';
        header("Location: index_tests.php?course_id={$course_id}");
        exit;
    }

    $deleteId = (int)$_POST['test_id'];
    $type = $_POST['test_type'];
    $message = '';

    if (!in_array($type, ['daily', 'weekly']) || $deleteId <= 0) {
        $message = '❌ Неверный тип теста или ID';
    } else {
        try {
            $test_dir = TenantFile::getFullPath(Tenant::getId(), "courses/{$course_id}");

            try {
                if (is_dir($test_dir)) {
                    deleteDir($test_dir);
                }
            } catch (Exception $e) {
                error_log("delete_test_dir error: " . $e->getMessage());
                $message = '❌ Тест удален, но ошибка при удалении папки.';
            }

            $deleteResults = $pdo->prepare("DELETE FROM test_results WHERE test_id = ? AND test_type = ? AND tenant_id = ?");
            $deleteResults->execute([$deleteId, $type, Tenant::getId()]);

            if ($type === 'daily') {
                $del = $pdo->prepare("DELETE FROM daily_tests WHERE daily_test_id = ? AND course_id = ? AND tenant_id = ?");
            } else {
                $del = $pdo->prepare("DELETE FROM weekly_tests WHERE weekly_test_id = ? AND course_id = ? AND tenant_id = ?");
            }
            $del->execute([$deleteId, $course_id, Tenant::getId()]);

            $message = $message ?: '✅ Тест успешно удален';
        } catch (PDOException $e) {
            error_log("delete_test error: " . $e->getMessage());
            $message = '❌ Ошибка при удалении теста. Попробуйте позже.';
        }
    }

    $_SESSION['message'] = $message;
    header("Location: index_tests.php?course_id={$course_id}");
    exit;
}

$dailyStmt = $pdo->prepare(
    "SELECT d.daily_test_id AS test_id, d.title, d.description, d.start_date, d.end_date, 'daily' AS test_type, m.title AS material_title
     FROM daily_tests d
     LEFT JOIN materials m ON d.material_id = m.material_id AND m.tenant_id = d.tenant_id
     WHERE d.course_id = ? AND d.tenant_id = ?"
);
$dailyStmt->execute([$course_id, Tenant::getId()]);
$daily = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

$weeklyStmt = $pdo->prepare(
    "SELECT weekly_test_id AS test_id, title, description, start_date, end_date, 'weekly' AS test_type
     FROM weekly_tests
     WHERE course_id = ? AND tenant_id = ?"
);
$weeklyStmt->execute([$course_id, Tenant::getId()]);
$weekly = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

$tests = array_merge($daily, $weekly);

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$pageTitle = 'Тесты';
include __DIR__ . '/../includes/header.php';
?>

<a href="../courses/" class="back-link">&larr; Курсы</a>
<h1 class="page-title">Тесты курса «<?= h($course['title']) ?>»</h1>

<?php if ($message): ?>
    <div class="msg <?= strpos($message, '❌') === 0 ? 'msg-err' : 'msg-ok' ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Все тесты</h2>
        <a href="add_tests.php?course_id=<?= $course_id ?>" class="btn btn-success btn-sm">+ Добавить тест</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Тип</th>
                    <th>Название</th>
                    <th>Описание</th>
                    <th>Период</th>
                    <th>Материал</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $test): ?>
                    <tr>
                        <td><?= $test['test_id'] ?></td>
                        <td><span class="badge <?= $test['test_type'] === 'daily' ? 'badge-green' : 'badge-blue' ?>"><?= h(ucfirst($test['test_type'] === 'daily' ? 'Material Test' : 'Course Test')) ?></span></td>
                        <td><strong><?= h($test['title']) ?></strong></td>
                        <td><?= h($test['description']) ?></td>
                        <td><?= h($test['start_date']) ?> — <?= h($test['end_date']) ?></td>
                        <td><?= $test['test_type'] === 'daily' ? h($test['material_title'] ?? '—') : '—' ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="edit_tests.php?test_id=<?= $test['test_id'] ?>&type=<?= $test['test_type'] ?>&course_id=<?= $course_id ?>" class="btn btn-primary btn-sm">Ред.</a>
                                <a href="../questions/index_questions.php?test_id=<?= $test['test_id'] ?>&type=<?= $test['test_type'] ?>&course_id=<?= $course_id ?>" class="btn btn-outline btn-sm">Вопросы</a>
                                <form method="post" style="display:inline" onsubmit="return confirm('Удалить тест #<?= $test['test_id'] ?>?');">
                                    <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                    <input type="hidden" name="action" value="delete_test">
                                    <input type="hidden" name="test_id" value="<?= $test['test_id'] ?>">
                                    <input type="hidden" name="test_type" value="<?= $test['test_type'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Уд.</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tests)): ?>
                    <tr><td colspan="8" class="table-empty"><div class="empty-icon">&#128221;</div>Нет тестов</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
