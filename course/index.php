<?php
date_default_timezone_set('Asia/Tashkent');
require '../php/config.php';
require '../login/Login_functions.php';

function h($str)
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function format_date($date)
{
    if (!$date || strtotime($date) === false) {
        return '<span data-i18n="date_not_specified"></span>';
    }
    return date('d.m.Y H:i', strtotime($date));
}

function showErrorAndRedirect($messageKey)
{
    if (ob_get_length()) ob_clean();

    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title data-i18n='redirecting'></title>
        <script>
            alert(document.querySelector('[data-i18n=\"{$messageKey}\"]')?.textContent || '{$messageKey}');
            window.location.href = '/';
        </script>
    </head>
    <body>
    </body>
    </html>";
    exit();
}

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: /');
    exit();
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if (!$course_id) {
    showErrorAndRedirect('error_no_course_id');
}

$pdo->exec("SET time_zone = '+05:00';");
$user_id = getSessionUserId();

// Получаем данные курса
$stmt = $pdo->prepare('SELECT title, subject, description, classLevel, price FROM courses WHERE course_id = ? AND tenant_id = ?');
$stmt->execute([$course_id, Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    showErrorAndRedirect('error_course_not_found');
}

// Получаем данные пользователя
$user_stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ? AND tenant_id = ?');
$user_stmt->execute([$user_id, Tenant::getId()]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Проверяем активную подписку на курс
$subscription_stmt = $pdo->prepare('SELECT 1 FROM course_subs WHERE user_id = ? AND course_id = ? AND status = \'active\' AND tenant_id = ? LIMIT 1');
$subscription_stmt->execute([$user_id, $course_id, Tenant::getId()]);
$has_subscription = (bool)$subscription_stmt->fetch();

// Логика доступа
$has_access = false;

if ($has_subscription) {
    $has_access = true;
} elseif ($course['price'] == 0) {
    if ($course['classLevel'] === $user['class']) {
        $has_access = true;
    } else {
        header("Location: /classes?class=" . urlencode($user['class']));
        exit();
    }
}

if (!$has_access) {
    showErrorAndRedirect("error_no_access");
}

// Получаем материалы курса
$materials_stmt = $pdo->prepare('SELECT * FROM materials WHERE course_id = ? AND tenant_id = ? ORDER BY lesson_id ASC');
$materials_stmt->execute([$course_id, Tenant::getId()]);
$materials = $materials_stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем ежедневные тесты для каждого материала
$daily_tests = [];
foreach ($materials as $material) {
    $t = $pdo->prepare('SELECT * FROM daily_tests WHERE material_id = ? AND tenant_id = ? ORDER BY start_date ASC');
    $t->execute([$material['material_id'], Tenant::getId()]);
    $daily_tests[$material['material_id']] = $t->fetchAll(PDO::FETCH_ASSOC);
}

// Получаем еженедельные тесты
$w = $pdo->prepare('SELECT * FROM weekly_tests WHERE course_id = ? AND tenant_id = ? ORDER BY start_date ASC');
$w->execute([$course_id, Tenant::getId()]);
$weekly_tests = $w->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="uz" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="<?= htmlspecialchars(Tenant::getPrimaryColor()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title data-i18n="page_title">Kurs: <?= h($course['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>

<body>
    <?php require_once '../php/navbar.php'; ?>

    <div class="container">
        <div class="header">
            <h1><?= h($course['title']) ?></h1>
        </div>

        <div class="course-details">
            <p><strong data-i18n="subject"></strong> <?= h($course['subject']) ?></p>
            <p><strong data-i18n="description"></strong> <?= h($course['description'] ?: '<span data-i18n="no_description"></span>') ?></p>
            <p>
                <strong data-i18n="<?= $course['classLevel'] === "1-4" ? "course" : "class" ?>"></strong>
                <?= h($course['classLevel'] === "1-4" ? "" : $course['classLevel']) ?>
            </p>
        </div>

        <h2><i class="fas fa-video"></i> <span data-i18n="online_lessons"></span></h2>
        <?php if (empty($materials)): ?>
            <p class="no-data" data-i18n="no_lessons"></p>
        <?php else: ?>
            <div class="lessons-grid">
                <?php foreach ($materials as $material): ?>
                    <div class="material-card">
                        <div class="cover" data-src="/file.php?type=material&id=<?= $material['material_id'] ?>" style="background-image:url('/file.php?type=preview&id=<?= $material['material_id'] ?>')">
                            <div class="play-icon" aria-label="Video ijro etish" role="img">
                                <svg style="enable-background:new 0 0 512 512;" version="1.1" viewBox="0 0 512 512" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <path d="M405.2,232.9L126.8,67.2c-3.4-2-6.9-3.2-10.9-3.2c-10.9,0-19.8,9-19.8,20H96v344h0.1c0,11,8.9,20,19.8,20  c4.1,0,7.5-1.4,11.2-3.4l278.1-165.5c6.6-5.5,10.8-13.8,10.8-23.1C416,246.7,411.8,238.5,405.2,232.9z" />
                                </svg>
                            </div>
                        </div>
                        <div class="video-container" style="display:none; margin-top: 8px;"></div>

                        <h3><?= h($material['lesson_id']) ?>. <?= h($material['title']) ?></h3>
                        <p><strong data-i18n="description"></strong> <?= h($material['description'] ?: '<span data-i18n="no_description"></span>') ?></p>
                        <p><strong data-i18n="duration"></strong> <?= $material['duration'] ? h($material['duration']) . ' <span data-i18n="minutes"></span>' : '<span data-i18n="not_specified"></span>' ?></p> <button class="test-toggle-btn" data-material-id="<?= $material['material_id'] ?>">
                            <i class="fas fa-clipboard-list"></i> <span data-i18n="show_tests"></span>
                        </button>
                        <div class="tests-container" id="tests-<?= $material['material_id'] ?>">
                            <?php if (empty($daily_tests[$material['material_id']])): ?>
                                <p class="no-data" data-i18n="no_tests_for_lesson"></p>
                            <?php else: ?>
                                <?php foreach ($daily_tests[$material['material_id']] as $test): ?>
                                    <div class="test-card">
                                        <p><strong data-i18n="test_name"></strong> <?= h($test['title']) ?></p>
                                        <p><strong data-i18n="description"></strong> <?= h($test['description'] ?: '<span data-i18n="no_description"></span>') ?></p>
                                        <p><strong data-i18n="start_date"></strong> <?= format_date($test['start_date']) ?></p>
                                        <p><strong data-i18n="end_date"></strong> <?= format_date($test['end_date']) ?></p>
                                        <div class="test-actions">
                                            <?php if (strtotime($test['end_date']) !== false && strtotime($test['end_date']) < time()): ?>
                                                <span class="action-btn disabled"><i class="fas fa-clock"></i> <span data-i18n="test_expired"></span></span>
                                                <a href="tests/results.php?type=daily&test_id=<?= $test['daily_test_id'] ?>" class="action-btn secondary"><i class="fas fa-chart-bar"></i> <span data-i18n="results"></span></a>
                                            <?php elseif (strtotime($test['start_date']) !== false && strtotime($test['start_date']) <= time()): ?>
                                                <a href="tests/index.php?type=daily&test_id=<?= $test['daily_test_id'] ?>&material_id=<?= $test['material_id'] ?>" class="action-btn"><i class="fas fa-play"></i> <span data-i18n="take_test"></span></a>
                                                <a href="tests/results.php?type=daily&test_id=<?= $test['daily_test_id'] ?>" class="action-btn secondary"><i class="fas fa-chart-bar"></i> <span data-i18n="results"></span></a>
                                            <?php else: ?>
                                                <span class="action-btn disabled"><i class="fas fa-clock"></i> <span data-i18n="test_starts_at"></span> <?= format_date($test['start_date']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="weekly-tests">
            <h2><i class="fas fa-calendar-alt"></i> <span data-i18n="weekly_tests"></span></h2>
            <?php if (empty($weekly_tests)): ?>
                <p class="no-data" data-i18n="no_weekly_tests"></p>
            <?php else: ?>
                <?php foreach ($weekly_tests as $test): ?>
                    <div class="weekly-test-card">
                        <h3><?= h($test['title']) ?></h3>
                        <p><strong data-i18n="description"></strong> <?= h($test['description'] ?: '<span data-i18n="no_description"></span>') ?></p>
                        <p><strong data-i18n="start_date"></strong> <?= format_date($test['start_date']) ?></p>
                        <p><strong data-i18n="end_date"></strong> <?= format_date($test['end_date']) ?></p>
                        <div class="test-actions">
                            <?php if (strtotime($test['end_date']) !== false && strtotime($test['end_date']) < time()): ?>
                                <span class="action-btn disabled"><i class="fas fa-clock"></i> <span data-i18n="test_expired"></span></span>
                                <a href="tests/results.php?type=weekly&test_id=<?= $test['weekly_test_id'] ?>" class="action-btn secondary"><i class="fas fa-chart-bar"></i> <span data-i18n="results"></span></a>
                            <?php elseif (strtotime($test['start_date']) !== false && strtotime($test['start_date']) <= time()): ?>
                                <a href="tests/index.php?type=weekly&test_id=<?= $test['weekly_test_id'] ?>" class="action-btn"><i class="fas fa-play"></i> <span data-i18n="take_test"></span></a>
                                <a href="tests/results.php?type=weekly&test_id=<?= $test['weekly_test_id'] ?>" class="action-btn secondary"><i class="fas fa-chart-bar"></i> <span data-i18n="results"></span></a>
                            <?php else: ?>
                                <span class="action-btn disabled"><i class="fas fa-clock"></i> <span data-i18n="test_starts_at"></span> <?= format_date($test['start_date']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="videoModal">
        <video id="modalVideo" controls></video>
    </div>

    <script>
        // Обработка кнопок тестов
        document.querySelectorAll('.test-toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-material-id');
                const cont = document.getElementById('tests-' + id);
                cont.classList.toggle('active');

                const showText = document.querySelector('[data-i18n="show_tests"]').textContent;
                const hideText = document.querySelector('[data-i18n="hide_tests"]').textContent;

                btn.innerHTML = cont.classList.contains('active') ?
                    `<i class="fas fa-eye-slash"></i> ${hideText}` :
                    `<i class="fas fa-clipboard-list"></i> ${showText}`;
            });
        });

        // Обработка видео
        document.querySelectorAll('.material-card').forEach(card => {
            const cover = card.querySelector('.cover');
            const videoContainer = card.querySelector('.video-container');

            cover.addEventListener('click', () => {
                if (videoContainer.style.display === 'block') {
                    const video = videoContainer.querySelector('video');
                    if (video) {
                        video.pause();
                        videoContainer.innerHTML = '';
                    }
                    videoContainer.style.display = 'none';
                    cover.style.display = 'block';
                } else {
                    cover.style.display = 'none';
                    const src = cover.dataset.src;
                    const videoElem = document.createElement('video');
                    videoElem.src = src;
                    videoElem.controls = true;
                    videoElem.autoplay = true;
                    videoElem.style.width = '100%';

                    videoContainer.innerHTML = '';
                    videoContainer.appendChild(videoElem);
                    videoContainer.style.display = 'block';

                    videoElem.addEventListener('play', () => {
                        document.querySelectorAll('.material-card video').forEach(otherVideo => {
                            if (otherVideo !== videoElem) {
                                otherVideo.pause();
                            }
                        });
                    });
                }
            });
        });

        // Модальное окно видео
        document.getElementById('videoModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) {
                const modal = e.currentTarget;
                const vid = document.getElementById('modalVideo');
                vid.pause();
                modal.classList.remove('active');
            }
        });
    </script>
</body>

</html>