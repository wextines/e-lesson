<?php
require '../../php/config.php';
require '../../login/Login_functions.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
date_default_timezone_set('Asia/Tashkent');

// Проверяем, передан ли ID теста
if (!isset($_GET['test_id']) || !is_numeric($_GET['test_id'])) {
    die("<span data-i18n='test_id_not_specified'></span>");
}

$type = isset($_GET['type']) && in_array($_GET['type'], ['daily', 'weekly']) ? $_GET['type'] : 'weekly';
$testId = (int)$_GET['test_id'];
$userSlug = getSessionSlug() ?: null;

$testTable = $type === 'daily' ? 'daily_tests' : 'weekly_tests';
$questionTable = $type === 'daily' ? 'daily_questions' : 'weekly_questions';

$sql = "
    SELECT 
        t.{$type}_test_id AS test_id,
        t.title,
        t.description,
        t.price,
        COUNT(q.{$type}_question_id) AS question_count,
        c.course_id,
        c.title AS course_title
    FROM {$testTable} t
    LEFT JOIN {$questionTable} q ON t.{$type}_test_id = q.{$type}_test_id
    LEFT JOIN courses c ON t.course_id = c.course_id
    WHERE t.{$type}_test_id = :test_id AND t.tenant_id = :tenant_id AND c.tenant_id = :tenant_id
    GROUP BY t.{$type}_test_id, t.title, t.description, t.price, c.course_id, c.title
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['test_id' => $testId, 'tenant_id' => Tenant::getId()]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die("<span data-i18n='test_not_found'></span>");
}

$isTestDone = false;
$testScore = null;
if ($userSlug) {
    $sqlDone = "
        SELECT score 
        FROM test_results 
        WHERE slug = :slug AND test_type = :type AND test_id = :test_id AND tenant_id = :tenant_id
        LIMIT 1
    ";
    $stmtDone = $pdo->prepare($sqlDone);
    $stmtDone->execute([
        'slug' => $userSlug,
        'type' => $type,
        'test_id' => $testId,
        'tenant_id' => Tenant::getId()
    ]);
    $result = $stmtDone->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $isTestDone = true;
        $testScore = $result['score'];
    }
}

$hasAccess = false;
if ($userSlug) {
    $sqlAccess = "
        SELECT 1 
        FROM course_subs 
        WHERE user_id = (SELECT user_id FROM users WHERE slug = :slug AND tenant_id = :tenant_id)
        AND course_id = :course_id
        AND status = 'active'
        AND tenant_id = :tenant_id
        LIMIT 1
    ";
    $stmtAccess = $pdo->prepare($sqlAccess);
    $stmtAccess->execute([
        'slug' => $userSlug,
        'course_id' => $test['course_id'],
        'tenant_id' => Tenant::getId()
    ]);
    $hasAccess = (bool)$stmtAccess->fetch();
}
$hasQuestions = $test['question_count'] > 0;
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= htmlspecialchars(Tenant::getPrimaryColor()) ?>">
    <title><?= htmlspecialchars(Tenant::getName()) ?></title>
    <link rel="icon" href="../../../img/homepage/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0f172a;
            --secondary-color: #1e293b;
            --accent-color: #64ffda;
            --text-color: #f8fafc;
            --text-secondary: #94a3b8;
            --card-bg: #1e293b;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        [data-theme="light"] {
            --primary-color: #f8fafc;
            --secondary-color: #e2e8f0;
            --accent-color: #3b82f6;
            --text-color: #0f172a;
            --text-secondary: #64748b;
            --card-bg: #ffffff;
        }

        body {
            font-family: "Baloo 2", -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--primary-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            line-height: 1.5;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-title {
            text-align: center;
            color: var(--accent-color);
            margin: 20px 0;
            font-size: 2rem;
        }

        .test-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin: 30px 0;
        }

        .test-card h2 {
            font-size: 1.5rem;
            color: var(--accent-color);
            margin-bottom: 15px;
        }

        .test-card p {
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .test-meta {
            display: flex;
            justify-content: space-between;
            margin: 25px 0;
            padding: 15px 0;
            border-top: 1px solid var(--secondary-color);
            border-bottom: 1px solid var(--secondary-color);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }

        .price-tag {
            background-color: var(--accent-color);
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-weight: bold;
        }

        .btn-course {
            display: inline-block;
            padding: 12px 25px;
            background-color: var(--accent-color);
            color: var(--primary-color);
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            text-align: center;
            margin-top: 8px;
        }

        .btn-course:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .btn-disabled {
            background-color: var(--secondary-color);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        .btn-disabled:hover {
            transform: none;
            opacity: 1;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: var(--card-bg);
            color: var(--text-color);
            border-radius: var(--border-radius);
            text-decoration: none;
            margin: 20px 0;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
        }

        .score-display {
            text-align: center;
            font-size: 1.2rem;
            margin: 20px 0;
            padding: 15px;
            background-color: var(--secondary-color);
            border-radius: var(--border-radius);
        }

        .score-value {
            color: var(--accent-color);
            font-weight: bold;
            font-size: 1.4rem;
        }

        .course-link {
            display: block;
            margin-top: 15px;
            color: var(--accent-color);
            text-decoration: none;
        }

        .course-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .test-card {
                padding: 20px;
            }

            .test-card h2 {
                font-size: 1.3rem;
            }

            .test-meta {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <?php require_once '../../php/navbar.php' ?>

    <div class="container">
        <h1 class="page-title"><span data-i18n="<?= $type === 'daily' ? 'daily_test' : 'weekly_test' ?>"></span></h1>

        <a href="/profile/" class="back-btn">
            <i class="fas fa-arrow-left"></i> <span data-i18n="back"></span>
        </a>

        <div class="test-card">
            <h2><?= htmlspecialchars($test['title']) ?></h2>
            <p><?= nl2br(htmlspecialchars($test['description'])) ?></p>

            <div class="test-meta">
                <div class="meta-item">
                    <i class="fas fa-question-circle"></i>
                    <span><?= $test['question_count'] ?> <span data-i18n="questions"></span></span>
                </div>

                </div>

                <?php if ($test['course_id']): ?>
                    <div class="meta-item">
                        <i class="fas fa-book"></i>
                        <span><?= htmlspecialchars($test['course_title']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($isTestDone): ?>
                <div class="score-display">
                    <span data-i18n="test_already_completed"></span>
                    <span class="score-value"><?= htmlspecialchars($testScore) ?></span>
                </div>
            <?php elseif ($hasAccess): ?>
                <?php if ($hasQuestions): ?>
                    <a href="solve.php?type=<?= $type ?>&test_id=<?= $test['test_id'] ?>" class="btn-course" style="margin-top: 8px;">
                        <i class="fas fa-play"></i> <span data-i18n="start_test"></span>
                    </a>
                <?php else: ?>
                    <a href="#" class="btn-course btn-disabled" style="margin-top: 8px;" onclick="return false;">
                        <i class="fas fa-exclamation-circle"></i> <span data-i18n="no_questions_in_test"></span>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($userSlug): ?>
                    <a href="#" class="btn-course btn-disabled" style="margin-top: 8px;" onclick="alert(document.querySelector('[data-i18n=\'no_access_to_test\']').textContent); return false;">
                        <i class="fas fa-lock"></i> <span data-i18n="no_access_to_test"></span>
                    </a>

                    <a href="/classes/subscriptions/course_subs.php?course_id=<?= $test['course_id'] ?>" class="btn-course" style="margin-top: 8px;">
                        <i class="fas fa-shopping-cart"></i> <span data-i18n="subscribe_to_course"></span>
                    </a>
                <?php else: ?>
                    <a href="../../auth/login.php" class="btn-course" style="margin-top: 8px;">
                        <i class="fas fa-sign-in-alt"></i> <span data-i18n="login_to_start_test"></span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>