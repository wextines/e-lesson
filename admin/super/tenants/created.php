<?php
require_once '../authorization.php';

$tenantId = $_GET['id'] ?? null;
if (!$tenantId || !is_numeric($tenantId)) {
    header("Location: index.php");
    exit;
}

$tenantId = (int)$tenantId;
try {
    $stmt = $pdo->prepare("SELECT t.*, a.username AS admin_username FROM tenants t LEFT JOIN admins a ON a.tenant_id = t.tenant_id AND a.role = 'center_admin' WHERE t.tenant_id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant создан — Super Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="/img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#0a192f;--secondary:#112240;--card:#1e293b;--accent:#64ffda;--text:#f8fafc;--text-sec:#94a3b8;--border:#334155;--success:#22c55e;--danger:#ef4444;}
        [data-theme="light"]{--primary:#f8fafc;--secondary:#e2e8f0;--card:#ffffff;--accent:#3b82f6;--text:#0f172a;--text-sec:#64748b;--border:#e2e8f0;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',sans-serif;background:var(--primary);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;}
        .success-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:40px;max-width:500px;width:90%;text-align:center;}
        .success-icon{font-size:4rem;color:var(--success);margin-bottom:16px;}
        h1{font-family:'Space Grotesk',sans-serif;font-size:1.5rem;margin-bottom:8px;}
        .subtitle{color:var(--text-sec);margin-bottom:24px;}
        .info-box{background:var(--secondary);border:1px solid var(--border);border-radius:10px;padding:16px;text-align:left;margin-bottom:20px;}
        .info-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:0.85rem;}
        .info-row:last-child{border-bottom:none;}
        .info-label{color:var(--text-sec);}
        .info-value{font-weight:600;}
        .btn{padding:10px 20px;border-radius:8px;font-size:0.9rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer;border:none;transition:all 0.2s;}
        .btn-primary{background:var(--accent);color:var(--primary);}
        .btn-primary:hover{opacity:0.9;transform:translateY(-1px);}
        .btn-secondary{background:var(--card);color:var(--text);border:1px solid var(--border);}
        .btn-secondary:hover{background:var(--border);}
        .steps{text-align:left;margin:20px 0;}
        .step{display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;}
        .step-icon{width:28px;height:28px;border-radius:50%;background:rgba(34,197,94,0.15);color:var(--success);display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;}
        .step-text{font-size:0.85rem;line-height:1.4;}
        .step-text strong{display:block;margin-bottom:2px;}
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon"><i class="fas fa-check-circle"></i></div>
        <h1>Tenant создан!</h1>
        <p class="subtitle"><?= htmlspecialchars($tenant['name']) ?> успешно добавлен в платформу.</p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Название</span>
                <span class="info-value"><?= htmlspecialchars($tenant['name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Slug</span>
                <span class="info-value"><?= htmlspecialchars($tenant['slug']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Администратор</span>
                <span class="info-value"><?= htmlspecialchars($tenant['admin_username'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Статус</span>
                <span class="info-value" style="color:var(--success);">Активен</span>
            </div>
        </div>

        <div class="steps">
            <div class="step">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <div class="step-text"><strong>Tenant создан</strong>Запись в базе данных</div>
            </div>
            <div class="step">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <div class="step-text"><strong>Storage создан</strong>Директории для файлов</div>
            </div>
            <div class="step">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <div class="step-text"><strong>Администратор создан</strong>Доступ к панели управления</div>
            </div>
            <div class="step">
                <div class="step-icon" style="background:rgba(100,255,218,0.15);color:var(--accent);">!</div>
                <div class="step-text"><strong>Настройте брендинг</strong>Загрузите логотип и настройте цвета</div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="edit.php?id=<?= $tenant['tenant_id'] ?>" class="btn btn-primary"><i class="fas fa-palette"></i> Настроить брендинг</a>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-list"></i> Список tenant'ов</a>
        </div>
    </div>
</body>
</html>
