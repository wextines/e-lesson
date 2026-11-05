<?php
require '../../php/config.php';
require '../../login/Login_functions.php';
initSession();

if (!isLoggedIn()) {
    header("Location: /login/login.php");
    exit();
}

// Initialize variables
$results = [];
$error = '';
$testType = isset($_GET['test_type']) ? $_GET['test_type'] : 'all';
$testId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

try {
    $query = "SELECT tr.*, 
                     u.first_name, 
                     u.last_name,
                     CONCAT(u.first_name, ' ', u.last_name) AS full_name
              FROM test_results tr
              JOIN users u ON tr.slug = u.slug AND tr.tenant_id = u.tenant_id
              WHERE tr.tenant_id = :tenant_id";

    $conditions = [];
    $params = ['tenant_id' => Tenant::getId()];

    if ($testType !== 'all') {
        $conditions[] = "tr.test_type = :test_type";
        $params[':test_type'] = $testType;
    }

    if ($testId > 0) {
        $conditions[] = "tr.test_id = :test_id";
        $params[':test_id'] = $testId;
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY tr.get_points DESC, tr.completed_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "database_error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="uz" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Tenant::getName()) ?></title>
    <meta name="theme-color" content="<?= htmlspecialchars(Tenant::getPrimaryColor()) ?>">
    <link rel="apple-touch-icon" href="../../img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="../../img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--primary-color);
            color: var(--text-color);
            line-height: 1.5;
            transition: background-color 0.3s ease, color 0.3s ease;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            padding: 10px;
        }

        .results-container {
            width: 100%;
            margin: 10px auto;
            padding: 10px;
        }

        .results-header {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 16px;
        }

        .page-title {
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.5rem;
            color: var(--accent-color);
            font-weight: 600;
            margin: 0;
        }

        .filters {
            display: flex;
            gap: 8px;
            align-items: center;
            width: 100%;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background-color: var(--input-bg);
            color: var(--text-color);
            font-size: 0.85rem;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 14px;
            flex: 1 1 120px;
            min-width: 0;
        }

        .results-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            width: 100%;
            overflow-x: auto;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .results-table th {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--accent-color);
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .results-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .results-table tr:last-child td {
            border-bottom: none;
        }

        .results-table tr:hover {
            background-color: var(--hover-bg);
        }

        .rank {
            font-weight: 600;
            color: var(--accent-color);
            width: 30px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar {
            display: none;
        }

        .score {
            font-weight: 600;
            text-align: center;
        }

        .score-high {
            color: var(--success-color);
        }

        .score-medium {
            color: var(--warning-color);
        }

        .score-low {
            color: var(--danger-color);
        }

        .points {
            text-align: right;
            font-weight: 600;
        }

        .date {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }

        .no-results {
            text-align: center;
            padding: 20px 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .error-message {
            color: var(--error-color);
            background-color: var(--error-bg);
            padding: 10px;
            border-radius: var(--border-radius);
            margin-bottom: 16px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        @media (min-width: 480px) {
            .container {
                padding: 15px;
            }

            .results-container {
                padding: 15px;
            }

            .results-table {
                min-width: 0;
            }

            .results-table th,
            .results-table td {
                padding: 10px 12px;
                font-size: 0.85rem;
            }

            .user-avatar {
                display: block;
                width: 30px;
                height: 30px;
            }
        }

        @media (min-width: 768px) {
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }

            .results-container {
                max-width: 1000px;
                margin: 20px auto;
                padding: 20px;
            }

            .results-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 24px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .filters {
                flex-wrap: nowrap;
                width: auto;
                gap: 12px;
            }

            .filter-select {
                padding: 10px 14px;
                font-size: 0.9rem;
            }

            .results-card {
                padding: 20px;
            }

            .results-table th,
            .results-table td {
                padding: 12px 16px;
                font-size: 0.9rem;
            }

            .user-avatar {
                width: 36px;
                height: 36px;
            }
        }

        [data-theme="dark"] .filter-select option {
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        [data-theme="light"] .filter-select option {
            background-color: var(--white);
            color: var(--text-color);
        }
    </style>
</head>

<body>
    <?php require_once '../../php/navbar.php' ?>

    <div class="container">
        <div class="results-container">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span data-i18n="<?= htmlspecialchars($error) ?>"></span>
                </div>
            <?php endif; ?>

            <div class="results-header">
                <h1 class="page-title"><span data-i18n="test_results"></span></h1>
            </div>

            <div class="results-card">
                <?php if (count($results) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><span data-i18n="user"></span></th>
                                    <th><span data-i18n="type"></span></th>
                                    <th>ID</th>
                                    <th><span data-i18n="result"></span></th>
                                    <th><span data-i18n="score"></span></th>
                                    <th><span data-i18n="time"></span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $index => $row): ?>
                                    <?php
                                    $scoreClass = 'score-medium';
                                    if ($row['get_points'] >= 80) {
                                        $scoreClass = 'score-high';
                                    } elseif ($row['get_points'] < 40) {
                                        $scoreClass = 'score-low';
                                    }
                                    ?>
                                    <tr>
                                        <td class="rank"><?= $index + 1 ?></td>
                                        <td>
                                            <div class="user-info">
                                                <span><?= htmlspecialchars($row['full_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span data-i18n="<?= $row['test_type'] === 'daily' ? 'daily' : 'weekly' ?>"></span></td>
                                        <td><?= $row['test_id'] ?></td>
                                        <td class="score"><?= htmlspecialchars($row['score']) ?></td>
                                        <td class="points <?= $scoreClass ?>"><?= $row['get_points'] ?></td>
                                        <td class="date"><?= date('d.m.Y H:i', strtotime($row['completed_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-info-circle" style="font-size: 1.5rem; margin-bottom: 8px;"></i>
                        <p><span data-i18n="no_results_found"></span></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const currentTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', currentTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>
</body>

</html>