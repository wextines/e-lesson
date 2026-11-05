<?php
require_once 'authorization.php';

$stats = [
    'tenants' => 0,
    'active_tenants' => 0,
    'admins' => 0,
    'users' => 0,
    'courses' => 0,
    'new_leads' => 0,
];

try {
    $stats['tenants'] = (int)$pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
    $stats['active_tenants'] = (int)$pdo->query("SELECT COUNT(*) FROM tenants WHERE is_active = 1")->fetchColumn();
    $stats['admins'] = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['courses'] = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $stats['new_leads'] = (int)$pdo->query("SELECT COUNT(*) FROM platform_leads WHERE status = 'new'")->fetchColumn();
} catch (PDOException $e) {
    error_log('[SUPER] Stats error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — e-Lesson</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="/img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0a192f;
            --secondary: #112240;
            --card: #1e293b;
            --accent: #64ffda;
            --text: #f8fafc;
            --text-sec: #94a3b8;
            --border: #334155;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        [data-theme="light"] {
            --primary: #f8fafc;
            --secondary: #e2e8f0;
            --card: #ffffff;
            --accent: #3b82f6;
            --text: #0f172a;
            --text-sec: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary);
            color: var(--text);
            min-height: 100vh;
        }
        .header {
            background: var(--secondary);
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.4rem;
            color: var(--accent);
        }
        .header-actions { display: flex; gap: 12px; align-items: center; }
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--accent); color: var(--primary); }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-secondary { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--border); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { opacity: 0.9; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { opacity: 0.9; }
        .btn-sm { padding: 5px 10px; font-size: 0.78rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            font-family: 'Space Grotesk', sans-serif;
        }
        .stat-card .label {
            color: var(--text-sec);
            font-size: 0.85rem;
            margin-top: 4px;
        }
        .nav-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }
        .nav-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nav-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        .nav-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(100, 255, 218, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: var(--accent);
            flex-shrink: 0;
        }
        .nav-card h3 { font-size: 1rem; margin-bottom: 4px; }
        .nav-card p { font-size: 0.8rem; color: var(--text-sec); }
        .user-info {
            color: var(--text-sec);
            font-size: 0.85rem;
        }
        @media (max-width: 768px) {
            .header { padding: 16px; flex-direction: column; gap: 12px; }
            .container { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-shield-halved"></i> Super Admin</h1>
        <div class="header-actions">
            <span class="user-info"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            <a href="/admin/php/logout.php" class="btn btn-secondary btn-sm"><i class="fas fa-sign-out-alt"></i> Выйти</a>
        </div>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= $stats['tenants'] ?></div>
                <div class="label">Tenant'ов</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['active_tenants'] ?></div>
                <div class="label">Активных</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['admins'] ?></div>
                <div class="label">Администраторов</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['users'] ?></div>
                <div class="label">Пользователей</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['courses'] ?></div>
                <div class="label">Курсов</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $stats['new_leads'] ?></div>
                <div class="label">Новых заявок</div>
            </div>
        </div>

        <div class="nav-cards">
            <a href="tenants/" class="nav-card">
                <div class="icon"><i class="fas fa-building"></i></div>
                <div>
                    <h3>Управление tenant'ами</h3>
                    <p>Создание, редактирование, активация образовательных центров</p>
                </div>
            </a>
            <a href="admins/" class="nav-card">
                <div class="icon"><i class="fas fa-user-gear"></i></div>
                <div>
                    <h3>Управление администраторами</h3>
                    <p>Создание и настройка администраторов tenant'ов</p>
                </div>
            </a>
            <a href="leads.php" class="nav-card">
                <div class="icon"><i class="fas fa-inbox"></i></div>
                <div>
                    <h3>Заявки с сайта</h3>
                    <p>Обращения через форму «Оставить заявку» на главной странице e-Lesson</p>
                </div>
            </a>
        </div>
    </div>
</body>
</html>
