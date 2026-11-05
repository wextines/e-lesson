<?php
session_start();
require '../../php/config.php';
require '../../login/Login_functions.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Массив всех текстовых элементов для перевода
$translation = [
    // Системные сообщения
    'errors' => [
        'registration_required' => "Ro'yxatdan o'tish kerak",
        'test_parameters_missing' => "Test turi yoki ID ko'rsatilmagan",
        'test_not_found' => "Test topilmadi!",
        'invalid_user_identifier' => "Foydalanuvchi identifikatori noto'g'ri",
        'user_not_found' => "Foydalanuvchi bazadan topilmadi. Iltimos, qayta kiring.",
        'database_error' => "Bazada xatolik yuz berdi. Iltimos, keyinroq urunib ko'ring.",
        'no_questions' => "Ushbu test uchun savollar yo'q.",
        'results_save_error' => "Test natijalarini saqlashda xatolik yuz berdi. Iltimos, qayta urunib ko'ring."
    ],

    // Интерфейс
    'ui' => [
        'page_title' => json_encode(Tenant::getName()),
        'test_completed' => "Test yakunlandi!",
        'correct_answers' => "To'g'ri javoblar",
        'incorrect_answers' => "Noto'g'ri javoblar",
        'question_prefix' => "Savol",
        'completed_yet' => "Bajarildi",
        'question' => 'savol',
        'return_home' => "Bosh sahifaga qaytish",
        'return_to_course' => "Kursga qaytish",
        'go_to_profile' => "Profilga o'tish",
        'previous_question' => "Orqaga",
        'next_question' => "Keyingi",
        'finish_test' => "Yakunlash"
    ]
];

// Функция для безопасного вывода текста
function __($key, $replace = [])
{
    global $translation;
    $keys = explode('.', $key);
    $text = $translation;

    foreach ($keys as $k) {
        if (!isset($text[$k])) {
            return $key; // Возвращаем ключ, если текст не найден
        }
        $text = $text[$k];
    }

    // Замена плейсхолдеров
    foreach ($replace as $placeholder => $value) {
        $text = str_replace("%$placeholder%", $value, $text);
    }

    return htmlspecialchars($text);
}

// Проверка авторизации пользователя
if (!isLoggedIn()) {
?>
    <!DOCTYPE html>
    <html lang="uz">

    <head>
        <meta charset="UTF-8">
        <title><?= __('errors.registration_required') ?></title>
        <meta name="theme-color" content="<?= htmlspecialchars(Tenant::getPrimaryColor()) ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: "Baloo 2", -apple-system, BlinkMacSystemFont, sans-serif;
                background-color: var(--primary-color);
                color: var(--text-color);
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }

            .message-box {
                background: var(--card-bg);
                padding: 30px;
                border-radius: 12px;
                box-shadow: var(--shadow);
                text-align: center;
                max-width: 500px;
                width: 90%;
            }

            .message-box h1 {
                color: var(--accent-color);
                font-size: 1.5rem;
                margin-bottom: 20px;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 24px;
                background: var(--accent-color);
                color: var(--primary-color);
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
            }

            .button:hover {
                opacity: 0.9;
                transform: translateY(-2px);
            }
        </style>
    </head>

    <body>
        <div class="message-box">
            <h1 data-i18n="errors.registration_required"><?= __('errors.registration_required') ?></h1>
            <a href="/" class="button">
                <i class="fas fa-home"></i> <span data-i18n="return_home"><?= __('return_home') ?></span>
            </a>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Валидация параметров
$type = isset($_GET['type']) && in_array($_GET['type'], ['daily', 'weekly']) ? $_GET['type'] : null;
$testId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;

if (!$type || !$testId) {
    die(__('errors.test_parameters_missing'));
}

// Проверка существования теста
$testTable = $type === 'daily' ? 'daily_tests' : 'weekly_tests';
$testCheck = $pdo->prepare("SELECT COUNT(*) FROM {$testTable} WHERE {$type}_test_id = ? AND tenant_id = ?");
$testCheck->execute([$testId, Tenant::getId()]);

if ($testCheck->fetchColumn() == 0) {
    die(__('errors.test_not_found'));
}

// Проверка пользователя
$userSlug = getSessionSlug();
if (empty($userSlug)) {
    die(__('errors.invalid_user_identifier'));
}

try {
    $checkUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE slug = :slug AND tenant_id = :tenant_id");
    $checkUser->execute(['slug' => $userSlug, 'tenant_id' => Tenant::getId()]);
    if ($checkUser->fetchColumn() == 0) {
        die(__('errors.user_not_found'));
    }
} catch (PDOException $e) {
    die(__('errors.database_error'));
}

// Проверка доступа к курсу (tenant-scoped, status = 'active' — see php/access_service.php)
if ($type === 'daily') {
    $cStmt = $pdo->prepare("SELECT m.course_id FROM daily_tests dt JOIN materials m ON dt.material_id = m.material_id AND m.tenant_id = dt.tenant_id WHERE dt.daily_test_id = ? AND dt.tenant_id = ?");
    $cStmt->execute([$testId, Tenant::getId()]);
    $courseId = (int)$cStmt->fetchColumn();
} else {
    $cStmt = $pdo->prepare("SELECT wt.course_id FROM weekly_tests wt WHERE wt.weekly_test_id = ? AND wt.tenant_id = ?");
    $cStmt->execute([$testId, Tenant::getId()]);
    $courseId = (int)$cStmt->fetchColumn();
}

if ($courseId > 0) {
    $stmtAccess = $pdo->prepare("
        SELECT 1 FROM course_subs 
        WHERE user_id = (SELECT user_id FROM users WHERE slug = :slug AND tenant_id = :tenant_id)
        AND course_id = :course_id
        AND status = 'active'
        AND tenant_id = :tenant_id
        LIMIT 1
    ");
    $stmtAccess->execute([
        'slug' => $userSlug,
        'course_id' => $courseId,
        'tenant_id' => Tenant::getId()
    ]);
    if (!$stmtAccess->fetch()) {
        header('Location: /course/tests/index.php?type=' . urlencode($type) . '&test_id=' . $testId);
        exit;
    }
}

// Проверка прохождения теста
$stmt = $pdo->prepare("SELECT result_id FROM test_results WHERE test_type=:type AND test_id=:id AND slug=:slug AND tenant_id=:tenant_id");
$stmt->execute(['type' => $type, 'id' => $testId, 'slug' => $userSlug, 'tenant_id' => Tenant::getId()]);
if ($stmt->fetchColumn()) {
    // Пользователь уже проходил тест
    header('Location: /profile');
    exit;
}

// Загрузка вопросов
$questionsTable = $type === 'daily' ? 'daily_questions' : 'weekly_questions';
$idCol = $type === 'daily' ? 'daily_question_id' : 'weekly_question_id';

if (!isset($_SESSION['test'][$type][$testId])) {
    $qStmt = $pdo->prepare("SELECT * FROM {$questionsTable} WHERE {$type}_test_id=:id ORDER BY sort_order ASC, {$idCol} ASC");
    $qStmt->execute(['id' => $testId]);
    $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$questions) {
        die(__('errors.no_questions'));
    }

    $_SESSION['test'][$type][$testId] = [
        'questions' => $questions,
        'current' => 0,
        'completed' => false,
        'answers' => []
    ];
}

$session = &$_SESSION['test'][$type][$testId];

// Обработка ответов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['answer']) || isset($_POST['next'])) {
        $idx = $session['current'];
        $q = $session['questions'][$idx];
        $qid = (int)$q[$idCol];
        $answer = isset($_POST['answer']) ? $_POST['answer'] : ($session['answers'][$qid] ?? '');
        $session['answers'][$qid] = $answer;
        $session['current']++;

        if ($session['current'] >= count($session['questions'])) {
            $session['completed'] = true;
        }
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'prev') {
            $session['current'] = max(0, $session['current'] - 1);
        }
        if ($_POST['action'] === 'finish') {
            $session['completed'] = true;
        }
    }

    // Обработка завершения теста
    if ($session['completed']) {
        $correct = 0;
        $total = count($session['questions']);

        foreach ($session['questions'] as $q) {
            $qid = (int)$q[$idCol];
            if (($session['answers'][$qid] ?? '') === strtoupper(trim($q['correct_answer']))) {
                $correct++;
            }
        }

        $wrong = $total - $correct;
        $score = "{$correct}/{$total}";
        $percentage = round(($correct / $total) * 100);

        // Получение информации о тесте
        $testInfoStmt = $pdo->prepare("SELECT min_score, max_score, course_id FROM {$testTable} WHERE {$type}_test_id = :id AND tenant_id = :tenant_id");
        $testInfoStmt->execute(['id' => $testId, 'tenant_id' => Tenant::getId()]);
        $testData = $testInfoStmt->fetch(PDO::FETCH_ASSOC);

        $minScore = $testData['min_score'] ?? 0;
        $maxScore = $testData['max_score'] ?? 100;
        $courseId = $testData['course_id'] ?? null;

        // Расчет баллов
        $pointsEarned = $correct === 0 ? 0 : round($minScore + ($maxScore - $minScore) * ($correct / $total));
        if ($pointsEarned > $maxScore) {
            $pointsEarned = $maxScore;
        }

        // Сохранение результатов
        try {
            $pdo->beginTransaction();

            $ins = $pdo->prepare("INSERT INTO test_results (slug, test_type, test_id, score, get_points, completed_at, tenant_id)
                                VALUES (:slug, :type, :id, :score, :get_points, NOW(), :tenant_id)");
            $ins->execute([
                'slug' => $userSlug,
                'type' => $type,
                'id' => $testId,
                'score' => $score,
                'get_points' => $pointsEarned,
                'tenant_id' => Tenant::getId()
            ]);

            $upd = $pdo->prepare("UPDATE users SET points = points + :pts WHERE slug = :slug AND tenant_id = :tenant_id");
            $upd->execute(['pts' => $pointsEarned, 'slug' => $userSlug, 'tenant_id' => Tenant::getId()]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            die(__('errors.results_save_error'));
        }
    }
}

$completed = $session['completed'];
$questions = $session['questions'];
$currentQuestionIndex = $session['current'];
$answers = $session['answers'];

// Расчет результатов для отображения
if ($completed) {
    $correctAnswers = 0;
    $totalQuestions = count($questions);

    foreach ($questions as $q) {
        $qid = $q["{$type}_question_number"];
        if (($answers[$qid] ?? '') === strtoupper(trim($q['correct_answer']))) {
            $correctAnswers++;
        }
    }

    $wrongAnswers = $totalQuestions - $correctAnswers;
    $percentageCorrect = round(($correctAnswers / $totalQuestions) * 100);
}
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="page_title"><?= __('page_title') ?></title>
    <link rel="icon" href="../../../img/homepage/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Baloo 2", -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--primary-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            line-height: 1.5;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 80px;
        }

        .test-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .test-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .test-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .test-title {
            color: var(--accent-color);
            font-size: 1.75rem;
            margin-bottom: 10px;
        }

        .progress-container {
            width: 100%;
            background-color: #f1f3f5;
            border-radius: 10px;
            height: 10px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--accent-color);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .progress-text {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #f1f3f5;
            padding: 8px 16px;
            border-radius: 20px;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .time-remaining {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #fff4e6;
            padding: 8px 16px;
            border-radius: 20px;
            color: var(--warning-color);
            font-size: 0.95rem;
            font-weight: 600;
        }

        .question-img-container {
            display: flex;
            justify-content: center;
            margin: 25px 0;
            min-height: 200px;
        }

        .question-img {
            max-width: 100%;
            max-height: 400px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            object-fit: contain;
            transition: transform 0.3s ease;
            cursor: zoom-in;
        }

        .question-img:hover {
            transform: scale(1.02);
        }

        .question-text {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 25px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .option-btn {
            padding: 15px;
            background-color: var(--primary-color);
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60px;
            position: relative;
            overflow: hidden;
        }

        .option-btn:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
        }

        .option-selected {
            background-color: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
        }

        [data-theme='dark'] .option-selected {
            background-color: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
            color: var(--primary-color) !important;
        }

        .option-letter {
            position: absolute;
            left: 10px;
            font-weight: 700;
        }

        .nav-buttons {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 20px;
        }

        .nav-btn {
            flex: 1;
            padding: 14px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        [data-theme='dark'] .nav-btn {
            background-color: var(--secondary-color);

        }

        .nav-btn:hover {
            transform: translateY(-2px);
        }

        .nav-btn:disabled {
            background-color: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .result-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            text-align: center;
            margin-top: 20px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-title {
            color: var(--accent-color);
            font-size: 1.75rem;
            margin-bottom: 20px;
        }

        .result-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-item {
            padding: 20px;
            border-radius: var(--border-radius);
        }

        .stat-correct {
            background-color: rgba(43, 138, 62, 0.1);
            border: 1px solid var(--success-color);
        }

        .stat-incorrect {
            background-color: rgba(201, 42, 42, 0.1);
            border: 1px solid var(--error-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .correct-value {
            color: var(--success-color);
        }

        .incorrect-value {
            color: var(--error-color);
        }

        .percentage-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(var(--accent-color) 0% <?= $perc ?? 0 ?>%, var(--secondary-color) <?= $perc ?? 0 ?>% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
        }

        .percentage-inner {
            width: 90px;
            height: 90px;
            background-color: var(--card-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-color);
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .action-btn {
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .primary-btn {
            background-color: var(--secondary-color);
            color: var(--text-color);
        }

        .primary-btn:hover {
            transform: translateY(-2px);
        }

        .secondary-btn {
            background-color: var(--secondary-color);
            color: var(--text-color);
        }

        .secondary-btn:hover {
            transform: translateY(-2px);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f1f3f5;
            border-top-color: var(--accent-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .modal.active {
            opacity: 1;
            pointer-events: all;
        }

        .modal-content {
            background-color: white;
            border-radius: var(--border-radius);
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            position: relative;
        }

        .modal-img {
            max-width: 100%;
            max-height: 80vh;
            display: block;
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
                padding-top: 70px;
            }

            .test-card {
                padding: 20px;
            }

            .test-title {
                font-size: 1.5rem;
            }

            .nav-buttons {
                flex-direction: column;
            }

            .result-stats {
                grid-template-columns: 1fr;
            }

            .percentage-circle {
                width: 100px;
                height: 100px;
            }

            .percentage-inner {
                width: 70px;
                height: 70px;
                font-size: 1.2rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            /* Фиксированно 5 колонок */
            gap: 15px;
            margin-bottom: 30px;
            overflow-x: auto;
            /* Добавляем горизонтальную прокрутку на маленьких экранах */
        }

        .option-btn {
            padding: 15px;
            background-color: var(--primary-color);
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            /* min-height: 60px; */
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            /* Текст не переносится */
            min-width: 0;
            /* Позволяет тексту обрезаться если не помещается */
        }

        @media (max-width: 768px) {
            .options-grid {
                grid-template-columns: repeat(5, minmax(45px, 1fr));
                width: 100%;
                padding-bottom: 10px;
            }

            .option-btn {
                font-size: 0.9rem;
                padding: 5px;
                aspect-ratio: 1 / 1;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Навигация -->
    <?php require_once '../../php/navbar.php' ?>

    <div class="container">
        <div class="test-card">
            <?php if ($completed): ?>
                <div class="result-card">
                    <h1 class="result-title" data-i18n="test_completed"><?= __('test_completed') ?></h1>

                    <div class="percentage-circle">
                        <div class="percentage-inner"><?= $percentageCorrect ?>%</div>
                    </div>

                    <div class="result-stats">
                        <div class="stat-item stat-correct">
                            <div class="stat-value correct-value"><?= $correctAnswers ?></div>
                            <div class="stat-label" data-i18n="correct_answers"><?= __('correct_answers') ?></div>
                        </div>
                        <div class="stat-item stat-incorrect">
                            <div class="stat-value incorrect-value"><?= $wrongAnswers ?></div>
                            <div class="stat-label" data-i18n="incorrect_answers"><?= __('incorrect_answers') ?></div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <?php if ($courseId): ?>
                            <a href="/course?course_id=<?= $courseId ?>" class="action-btn secondary-btn">
                                <i class="fas fa-list"></i> <span data-i18n="return_to_course"><?= __('return_to_course') ?></span>
                            </a>
                        <?php endif; ?>
                        <a href="/profile" class="action-btn primary-btn">
                            <i class="fas fa-user"></i> <span data-i18n="go_to_profile"><?= __('go_to_profile') ?></span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="test-header">
                    <h1 class="test-title"><span data-i18n="question_prefix"><?= __('question_prefix') ?></span> <?= ($currentQuestionIndex + 1) ?>/<?= count($questions) ?></h1>

                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= (($currentQuestionIndex + 1) / count($questions)) * 100 ?>%"></div>
                    </div>

                    <div class="progress-info">
                        <div class="progress-text">
                            <i class="fas fa-tasks"></i>
                            <span><span data-i18n="completed_yet"></span>: <?= $idx + 1 ?>/<?= count($questions) ?> <span data-i18n="question"></span></span>
                        </div>
                    </div>
                </div>
        </div>

        <?php
                $currentQuestion = $questions[$currentQuestionIndex];
                $currentQId = (int)$currentQuestion[$idCol];
                $questionNumber = $currentQuestionIndex + 1;
        ?>

        <?php if (!empty($currentQuestion['question_text'])): ?>
            <div class="question-text">
                <?= nl2br(htmlspecialchars($currentQuestion['question_text'])) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($currentQuestion['question_img_url'])): 
            $qImgUrl = "/file.php?type=question&id={$currentQId}&v=" . time();
        ?>
            <div class="question-img-container">
                <img src="<?= htmlspecialchars($qImgUrl) ?>"
                    class="question-img"
                    alt="Savol #<?= $questionNumber ?>"
                    onclick="openModal(this.src)">
            </div>
        <?php endif; ?>

        <form method="post" id="testForm">
            <div class="options-grid">
                <?php foreach (['A', 'B', 'C', 'D', 'E'] as $letter):
                    $isSelected = isset($answers[$currentQId]) && $answers[$currentQId] === $letter;
                    $optionText = !empty($currentQuestion["option_{$letter}"]) ?
                        htmlspecialchars($currentQuestion["option_{$letter}"]) :
                        $letter;
                ?>
                    <button type="submit" name="answer" value="<?= $letter ?>"
                        class="option-btn <?= $isSelected ? 'option-selected' : '' ?>">
                        <span class="option-letter"></span>
                        <?= $optionText ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="nav-buttons">
                <button type="submit" name="action" value="prev" class="nav-btn" <?= $currentQuestionIndex == 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-arrow-left"></i> <span data-i18n="previous_question"><?= __('previous_question') ?></span>
                </button>

                <?php if ($currentQuestionIndex < count($questions) - 1): ?>
                    <button type="submit" name="next" value="1" class="nav-btn">
                        <span data-i18n="next_question"><?= __('next_question') ?></span> <i class="fas fa-arrow-right"></i>
                    </button>
                <?php else: ?>
                    <button type="submit" name="action" value="finish" class="nav-btn">
                        <i class="fas fa-flag-checkered"></i> <span data-i18n="finish_test"><?= __('finish_test') ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
    </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="modal" id="imageModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">&times;</button>
            <img src="" class="modal-img" id="modalImage">
        </div>
    </div>

    <script>
        // Показать индикатор загрузки при отправке формы
        document.getElementById('testForm')?.addEventListener('submit', function() {
            document.getElementById('loadingOverlay').classList.add('active');
        });

        // Функции для модального окна с изображением
        function openModal(imgSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modalImg.src = imgSrc.replace(/\?v=\d+$/, '');
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        // Закрытие модального окна при клике вне изображения
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Инициализация системы перевода
        document.addEventListener('DOMContentLoaded', function() {
            // Здесь будет код для применения переводов
            // Например: document.querySelector('[data-i18n]').textContent = translations[element.dataset.i18n];
        });
    </script>
</body>

</html>