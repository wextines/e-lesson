<?php
require '../php/config.php';
require '../login/Login_functions.php';
initSession();

$current_user_id = getSessionUserId();

// Получаем класс из GET параметра или из данных пользователя
if (isset($_GET['course'])) {
    $current_class = $_GET['course'];
} elseif (isset($_GET['class'])) {
    $current_class = $_GET['class'];
} else {
    // Если класс не передан в GET, получаем класс текущего пользователя
    try {
        $stmt = $pdo->prepare("SELECT class FROM users WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$current_user_id, Tenant::getId()]);
        $current_user = $stmt->fetch();
        $current_class = $current_user ? $current_user['class'] : '1-2';
    } catch (PDOException $e) {
        $current_class = '1-2';
    }
}

// Подключение к базе данных с помощью PDO
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Запрос для получения данных лидерборда (только пользователи с указанным классом)
    $query = "
        SELECT 
            user_id, 
            first_name, 
            last_name, 
            points, 
            slug,
            class,
            DENSE_RANK() OVER (ORDER BY points DESC) AS rank_position
        FROM 
            users
        WHERE 
            points IS NOT NULL 
            AND points > 0
            AND class = :class
            AND tenant_id = :tenant_id
        ORDER BY 
            points DESC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':class', $current_class);
    $stmt->bindParam(':tenant_id', Tenant::getId());
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("[Classes] Error fetching users: " . $e->getMessage());
    die("Произошла ошибка. Попробуйте позже.");
}

$class_value = htmlspecialchars($current_class);
$class_display = $current_class;
$is_course = isset($_GET['course']) ? 'true' : 'false';
$date = new DateTime();


?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Tenant::getName()) ?></title>
    <meta name="theme-color" content="<?= htmlspecialchars(Tenant::getPrimaryColor()) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--primary-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            line-height: 1.5;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: calc(var(--nav-height, 72px) + 1.5rem) 1.5rem 2rem;
        }

        .page-title {
            text-align: center;
            color: var(--accent-color);
            margin: 0 0 0.5rem;
            font-size: 1.75rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
        }

        .page-nav-container {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin: 1.5rem 0;
        }

        .page-nav-button {
            background-color: var(--card-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color-strong);
            padding: 0.65rem 1.25rem;
            border-radius: var(--radius-sm, 6px);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition, all 0.3s ease);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Inter', sans-serif;
        }

        .page-nav-button:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
            border-color: var(--accent-color);
            transform: translateY(-1px);
        }

        .leaderboard-header {
            text-align: center;
            margin: 1.5rem 0;
        }

        .leaderboard-title {
            font-size: 1.5rem;
            color: var(--accent-color);
            margin-bottom: 0.35rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
        }

        .leaderboard-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background-color: var(--card-bg);
            border-radius: var(--radius-md, 10px);
            overflow: hidden;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.15));
            border: 1px solid var(--border-color);
        }

        .leaderboard-table th {
            background-color: var(--secondary-color);
            color: var(--accent-color);
            padding: 0.85rem 1rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .leaderboard-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
            vertical-align: middle;
        }

        .leaderboard-table tr:last-child td {
            border-bottom: none;
        }

        .leaderboard-table tr:hover {
            background-color: var(--accent-color-muted);
        }

        .rank-cell {
            font-weight: bold;
            width: 70px;
            font-size: 0.9rem;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            justify-content: flex-start;
            text-align: left;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: var(--accent-color-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--accent-color);
            flex-shrink: 0;
            font-size: 0.8rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--heading-color);
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .user-class {
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .points-cell {
            font-weight: bold;
            color: var(--accent-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
        }

        .points-value {
            font-size: 1rem;
        }

        .medal {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        .gold { background-color: gold; }
        .silver { background-color: silver; }
        .bronze { background-color: #cd7f32; }

        .last-update {
            text-align: right;
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin-top: 1rem;
        }

        .no-results {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-secondary);
            font-size: 1rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-md, 10px);
            border: 1px dashed var(--border-color-strong);
        }

        .refresh-btn {
            display: block;
            margin: 1.5rem auto 0;
            padding: 0.65rem 1.5rem;
            background-color: var(--accent-color);
            color: var(--primary-color);
            border: none;
            border-radius: var(--radius-sm, 6px);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition, all 0.3s ease);
            font-family: 'Inter', sans-serif;
        }

        .refresh-btn:hover {
            background-color: var(--accent-color-hover);
            transform: translateY(-1px);
        }

        .profile-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background-color: var(--accent-color);
            color: var(--primary-color);
            border-radius: var(--radius-sm, 4px);
            font-size: 0.7rem;
            text-decoration: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .leaderboard-table {
                font-size: 0.8rem;
            }

            .leaderboard-table th,
            .leaderboard-table td {
                padding: 0.6rem 0.4rem;
            }

            .user-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.7rem;
            }

            .user-name {
                font-size: 0.82rem;
                max-width: 100px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .user-class {
                font-size: 0.72rem;
            }

            .points-cell {
                font-size: 0.85rem;
            }

            .rank-cell {
                width: 45px;
            }

            .medal {
                width: 14px;
                height: 14px;
                margin-right: 0.25rem;
            }

            .leaderboard-title {
                font-size: 1.3rem;
            }

            .leaderboard-subtitle {
                font-size: 0.85rem;
            }

            .refresh-btn {
                padding: 0.55rem 1.25rem;
                font-size: 0.85rem;
            }

            .page-nav-button {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }

            .page-title {
                font-size: 1.4rem;
            }
        }
    </style>
</head>

<body>
    <!-- Навигация -->
    <?php require_once '../php/navbar.php' ?>

    <div class="container">
        <h1 class="page-title"
            data-i18n="class_display"
            data-value="<?= htmlspecialchars($current_class) ?>"
            data-course="<?= isset($_GET['course']) ? 'true' : 'false' ?>">
        </h1>
        <div class="page-nav-container">
            <a href="books/?class=<?= htmlspecialchars($current_class) ?>" class="page-nav-button" data-i18n="books_button">
                <i class="fas fa-book"></i>
            </a>
            <a href="courses/index.php?class=<?= htmlspecialchars($current_class) ?>" class="page-nav-button" data-i18n="competition_button">
                <i class="fas fa-trophy"></i>
            </a>
        </div>

        <div class="leaderboard-header">
            <h2 class="leaderboard-title" data-i18n="leaderboard_title"></h2>
            <p class="leaderboard-subtitle" data-i18n="leaderboard_subtitle" data-value="<?= htmlspecialchars($class_display) ?>"></p>
        </div>

        <?php if (!empty($users)): ?>
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th style="text-align: center;" data-i18n="table_rank"></th>
                        <th style="text-align: left;" data-i18n="table_user"></th>
                        <th style="text-align: center;" data-i18n="table_points"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="rank-cell">
                                <?php
                                if ($user['rank_position'] == 1) {
                                    echo '<span class="medal gold"></span>';
                                } elseif ($user['rank_position'] == 2) {
                                    echo '<span class="medal silver"></span>';
                                } elseif ($user['rank_position'] == 3) {
                                    echo '<span class="medal bronze"></span>';
                                }
                                echo $user['rank_position'];
                                ?>
                            </td>
                            <td>
                                <a href="/profile/?user_id=<?= $user['user_id'] ?>">
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <?php if ($user['user_id'] == $current_user_id): ?>
                                                <b><span class="user-name" style="color: var(--accent-color);"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                        – <span class="user-class" style="color: var(--accent-color);"><span data-i18n="you_label"></span>
                                                        </span></b>
                                            <?php else: ?>
                                                <span class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </td>
                            <td class="points-cell">
                                <span class="points-value"><?= number_format($user['points'], 0, '', ' ') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="last-update" data-i18n="updated_at" data-value="<?= $date->format('d.m.Y H:i') ?>"></div>

            <button class="refresh-btn" onclick="window.location.reload()" data-i18n="refresh_button">
                <i class="fas fa-sync-alt"></i>
            </button>
        <?php else: ?>
            <div class="no-results" data-i18n="no_results" data-value="<?= htmlspecialchars($class_display) ?>"></div>
        <?php endif; ?>
    </div>
</body>

</html>