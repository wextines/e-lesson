<?php
require_once '../authorization.php';

$tenants = [];
try {
    $stmt = $pdo->query("
        SELECT t.tenant_id, t.name, t.slug, t.domain, t.email, t.is_active, t.created_at,
               (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.tenant_id) AS user_count,
               (SELECT COUNT(*) FROM courses c WHERE c.tenant_id = t.tenant_id) AS course_count,
               (SELECT COUNT(*) FROM admins a WHERE a.tenant_id = t.tenant_id) AS admin_count
        FROM tenants t
        ORDER BY t.tenant_id ASC
    ");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[SUPER] Tenants list error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant'ы — Super Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="/img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#0a192f;--secondary:#112240;--card:#1e293b;--accent:#64ffda;--text:#f8fafc;--text-sec:#94a3b8;--border:#334155;--success:#22c55e;--warning:#f59e0b;--danger:#ef4444; }
        [data-theme="light"] { --primary:#f8fafc;--secondary:#e2e8f0;--card:#ffffff;--accent:#3b82f6;--text:#0f172a;--text-sec:#64748b;--border:#e2e8f0; }
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',sans-serif;background:var(--primary);color:var(--text);min-height:100vh;}
        .header{background:var(--secondary);padding:16px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
        .header h1{font-family:'Space Grotesk',sans-serif;font-size:1.2rem;color:var(--accent);}
        .header-actions{display:flex;gap:12px;align-items:center;}
        .breadcrumb{padding:12px 24px;font-size:0.85rem;color:var(--text-sec);}
        .breadcrumb a{color:var(--accent);text-decoration:none;}
        .breadcrumb a:hover{text-decoration:underline;}
        .btn{padding:8px 16px;border-radius:8px;font-size:0.85rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer;border:none;transition:all 0.2s;}
        .btn-primary{background:var(--accent);color:var(--primary);}
        .btn-primary:hover{opacity:0.9;transform:translateY(-1px);}
        .btn-secondary{background:var(--card);color:var(--text);border:1px solid var(--border);}
        .btn-secondary:hover{background:var(--border);}
        .btn-danger{background:var(--danger);color:white;}
        .btn-danger:hover{opacity:0.9;}
        .btn-success{background:var(--success);color:white;}
        .btn-success:hover{opacity:0.9;}
        .btn-sm{padding:5px 10px;font-size:0.78rem;}
        .container{max-width:1200px;margin:0 auto;padding:0 24px 24px;}
        .table-wrap{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-top:16px;}
        table{width:100%;border-collapse:collapse;}
        th{background:var(--secondary);color:var(--accent);padding:12px 16px;text-align:left;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;}
        td{padding:12px 16px;border-bottom:1px solid var(--border);font-size:0.85rem;}
        tr:last-child td{border-bottom:none;}
        tr:hover{background:rgba(255,255,255,0.03);}
        .badge{padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;}
        .badge-active{background:rgba(34,197,94,0.15);color:var(--success);}
        .badge-inactive{background:rgba(239,68,68,0.15);color:var(--danger);}
        .actions{display:flex;gap:6px;}
        .empty{text-align:center;padding:40px;color:var(--text-sec);}
        .user-info{color:var(--text-sec);font-size:0.85rem;}
        @media(max-width:768px){.table-wrap{overflow-x:auto;}th,td{padding:8px 10px;font-size:0.78rem;}.header{padding:12px;flex-direction:column;gap:10px;}}
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
    <div class="breadcrumb">
        <a href="/admin/super/">Dashboard</a> / Tenant'ы
    </div>
    <div class="container">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h2 style="font-family:'Space Grotesk',sans-serif;font-size:1.3rem;">Образовательные центры</h2>
            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Создать tenant</a>
        </div>

        <?php if (empty($tenants)): ?>
            <div class="empty"><i class="fas fa-building" style="font-size:2rem;margin-bottom:10px;display:block;"></i> Tenant'ов пока нет</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Slug</th>
                        <th>Email</th>
                        <th>Пользователи</th>
                        <th>Курсы</th>
                        <th>Админы</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                    <tr>
                        <td><?= $t['tenant_id'] ?></td>
                        <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                        <td><?= htmlspecialchars($t['slug']) ?></td>
                        <td><?= htmlspecialchars($t['email'] ?? '—') ?></td>
                        <td><?= $t['user_count'] ?></td>
                        <td><?= $t['course_count'] ?></td>
                        <td><?= $t['admin_count'] ?></td>
                        <td>
                            <?php if ($t['is_active']): ?>
                                <span class="badge badge-active">Активен</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Неактивен</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="edit.php?id=<?= $t['tenant_id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                <?php if ($t['is_active']): ?>
                                    <form method="post" action="toggle.php" style="display:inline;" onsubmit="return confirm('Деактивировать tenant?')">
                                        <?= csrf_token_html() ?>
                                        <input type="hidden" name="tenant_id" value="<?= $t['tenant_id'] ?>">
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-ban"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="toggle.php" style="display:inline;">
                                        <?= csrf_token_html() ?>
                                        <input type="hidden" name="tenant_id" value="<?= $t['tenant_id'] ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
