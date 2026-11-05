<?php
require_once '../authorization.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'Asia/Tashkent');
    $default_language = trim($_POST['default_language'] ?? 'uz');
    $primary_color = trim($_POST['primary_color'] ?? '#0a192f');
    $secondary_color = trim($_POST['secondary_color'] ?? '#172a45');
    $accent_color = trim($_POST['accent_color'] ?? '#64ffda');
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';

    if (empty($name) || empty($slug) || empty($admin_username) || empty($admin_password)) {
        $error = 'Заполните обязательные поля: название, slug, логин и пароль администратора.';
    } elseif (strlen($admin_password) < 6) {
        $error = 'Пароль администратора должен содержать минимум 6 символов.';
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $error = 'Slug может содержать только строчные буквы, цифры и дефис.';
    } else {
        verify_csrf();
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                $error = 'Tenant с таким slug уже существует.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO tenants (name, slug, domain, email, phone, timezone, default_language, primary_color, secondary_color, accent_color, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$name, $slug, $domain ?: null, $email ?: null, $phone ?: null, $timezone, $default_language, $primary_color, $secondary_color, $accent_color]);
                $tenant_id = $pdo->lastInsertId();

                $hashedPassword = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (tenant_id, username, role, password_hash) VALUES (?, ?, 'center_admin', ?)");
                $stmt->execute([$tenant_id, $admin_username, $hashedPassword]);

                $dirs = [
                    __DIR__ . "/../../../storage/tenants/{$tenant_id}",
                    __DIR__ . "/../../../storage/tenants/{$tenant_id}/logo",
                    __DIR__ . "/../../../storage/tenants/{$tenant_id}/courses",
                    __DIR__ . "/../../../storage/tenants/{$tenant_id}/books",
                    __DIR__ . "/../../../storage/tenants/{$tenant_id}/receipts",
                ];
                foreach ($dirs as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                }

                $pdo->commit();
                header("Location: created.php?id={$tenant_id}");
                exit;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[SUPER] Tenant create error: ' . $e->getMessage());
            $error = 'Ошибка при создании tenant.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать tenant — Super Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="/img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#0a192f;--secondary:#112240;--card:#1e293b;--accent:#64ffda;--text:#f8fafc;--text-sec:#94a3b8;--border:#334155;--success:#22c55e;--danger:#ef4444;--input-bg:#0f172a;}
        [data-theme="light"]{--primary:#f8fafc;--secondary:#e2e8f0;--card:#ffffff;--accent:#3b82f6;--text:#0f172a;--text-sec:#64748b;--border:#e2e8f0;--input-bg:#f1f5f9;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',sans-serif;background:var(--primary);color:var(--text);min-height:100vh;}
        .header{background:var(--secondary);padding:16px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
        .header h1{font-family:'Space Grotesk',sans-serif;font-size:1.2rem;color:var(--accent);}
        .breadcrumb{padding:12px 24px;font-size:0.85rem;color:var(--text-sec);}
        .breadcrumb a{color:var(--accent);text-decoration:none;}
        .breadcrumb a:hover{text-decoration:underline;}
        .btn{padding:8px 16px;border-radius:8px;font-size:0.85rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer;border:none;transition:all 0.2s;}
        .btn-primary{background:var(--accent);color:var(--primary);}
        .btn-primary:hover{opacity:0.9;transform:translateY(-1px);}
        .btn-secondary{background:var(--card);color:var(--text);border:1px solid var(--border);}
        .container{max-width:800px;margin:0 auto;padding:0 24px 24px;}
        .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-top:16px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-weight:600;font-size:0.85rem;margin-bottom:6px;color:var(--text);}
        .form-group input,.form-group select{width:100%;padding:10px 14px;border-radius:8px;border:1px solid var(--border);background:var(--input-bg);color:var(--text);font-size:0.9rem;}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--accent);}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .form-section{font-family:'Space Grotesk',sans-serif;font-size:1rem;color:var(--accent);margin:20px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--border);}
        .color-row{display:flex;gap:12px;align-items:center;}
        .color-row input[type="color"]{width:40px;height:36px;border:1px solid var(--border);border-radius:6px;cursor:pointer;padding:2px;}
        .color-row input[type="text"]{flex:1;}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;}
        .alert-error{background:rgba(239,68,68,0.15);color:var(--danger);border:1px solid rgba(239,68,68,0.3);}
        .alert-success{background:rgba(34,197,94,0.15);color:var(--success);border:1px solid rgba(34,197,94,0.3);}
        .header-actions{display:flex;gap:12px;align-items:center;}
        .user-info{color:var(--text-sec);font-size:0.85rem;}
        @media(max-width:600px){.form-row{grid-template-columns:1fr;}.header{padding:12px;flex-direction:column;gap:10px;}}
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
        <a href="/admin/super/">Dashboard</a> / <a href="index.php">Tenant'ы</a> / Создать
    </div>
    <div class="container">
        <h2 style="font-family:'Space Grotesk',sans-serif;font-size:1.3rem;">Создать образовательный центр</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post">
                <?= csrf_token_html() ?>
                <div class="form-section">Основная информация</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Название *</label>
                        <input type="text" name="name" required placeholder="Например: Test Academy" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Slug * <small style="font-weight:400;color:var(--text-sec);">(латиница, дефис)</small></label>
                        <input type="text" name="slug" required placeholder="test-academy" pattern="[a-z0-9\-]+" value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="info@test-academy.uz" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Телефон</label>
                        <input type="text" name="phone" placeholder="+998901234567" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Домен</label>
                        <input type="text" name="domain" placeholder="test-academy.uz" value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Часовой пояс</label>
                        <select name="timezone">
                            <option value="Asia/Tashkent" selected>Asia/Tashkent (UTC+5)</option>
                            <option value="Asia/Almaty">Asia/Almaty (UTC+6)</option>
                            <option value="Asia/Bishkek">Asia/Bishkek (UTC+6)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Язык по умолчанию</label>
                    <select name="default_language">
                        <option value="uz">O'zbekcha</option>
                        <option value="ru">Русский</option>
                        <option value="en">English</option>
                    </select>
                </div>

                <div class="form-section">Брендинг</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Primary color</label>
                        <div class="color-row">
                            <input type="color" name="primary_color" value="<?= htmlspecialchars($_POST['primary_color'] ?? '#0a192f') ?>">
                            <input type="text" name="primary_color" value="<?= htmlspecialchars($_POST['primary_color'] ?? '#0a192f') ?>" maxlength="7">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Secondary color</label>
                        <div class="color-row">
                            <input type="color" name="secondary_color" value="<?= htmlspecialchars($_POST['secondary_color'] ?? '#172a45') ?>">
                            <input type="text" name="secondary_color" value="<?= htmlspecialchars($_POST['secondary_color'] ?? '#172a45') ?>" maxlength="7">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Accent color</label>
                    <div class="color-row">
                        <input type="color" name="accent_color" value="<?= htmlspecialchars($_POST['accent_color'] ?? '#64ffda') ?>">
                        <input type="text" name="accent_color" value="<?= htmlspecialchars($_POST['accent_color'] ?? '#64ffda') ?>" maxlength="7">
                    </div>
                </div>

                <div class="form-section">Первый администратор</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Логин администратора *</label>
                        <input type="text" name="admin_username" required placeholder="admin" value="<?= htmlspecialchars($_POST['admin_username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Пароль администратора *</label>
                        <input type="password" name="admin_password" required placeholder="Минимум 6 символов">
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Создать tenant</button>
                    <a href="index.php" class="btn btn-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.querySelectorAll('.color-row').forEach(row => {
        const colorInput = row.querySelector('input[type="color"]');
        const textInput = row.querySelector('input[type="text"]');
        if (colorInput && textInput) {
            colorInput.addEventListener('input', () => textInput.value = colorInput.value);
            textInput.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) colorInput.value = textInput.value; });
        }
    });
    </script>
</body>
</html>
