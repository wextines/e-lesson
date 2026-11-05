<?php
require_once '../authorization.php';

$success = '';
$error = '';
$admin = null;

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: index.php");
    exit;
}
$id = (int)$id;

$tenants = [];
try {
    $tenants = $pdo->query("SELECT tenant_id, name FROM tenants ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[SUPER] Admin edit: tenants error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? 'update';

    if ($action === 'update') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'center_admin';
        $tenant_id = $_POST['tenant_id'] ?? null;

        if (empty($username)) {
            $error = 'Логин обязателен.';
        } elseif (!in_array($role, ['super_admin', 'center_admin'])) {
            $error = 'Некорректная роль.';
    } elseif ($role === 'center_admin' && (!$tenant_id || !is_numeric($tenant_id))) {
        $error = 'Для center_admin необходимо выбрать tenant.';
    } else {
        verify_csrf();
        try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? AND id != ?");
                $stmt->execute([$username, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Администратор с таким логином уже существует.';
                } else {
                    $finalTenantId = $role === 'super_admin' ? 0 : (int)$tenant_id;
                    $stmt = $pdo->prepare("UPDATE admins SET username=?, role=?, tenant_id=? WHERE id=?");
                    $stmt->execute([$username, $role, $finalTenantId, $id]);
                    $success = 'Администратор обновлён.';
                }
            } catch (PDOException $e) {
                error_log('[SUPER] Admin update error: ' . $e->getMessage());
                $error = 'Ошибка при обновлении.';
            }
        }
    } elseif ($action === 'reset_password') {
        $new_password = $_POST['new_password'] ?? '';
        if (strlen($new_password) < 6) {
            $error = 'Пароль должен содержать минимум 6 символов.';
        } else {
            verify_csrf();
            try {
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET password_hash=? WHERE id=?");
                $stmt->execute([$hashedPassword, $id]);
                $success = 'Пароль администратора обновлён.';
            } catch (PDOException $e) {
                error_log('[SUPER] Admin password reset error: ' . $e->getMessage());
                $error = 'Ошибка при сбросе пароля.';
            }
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT a.*, t.name AS tenant_name FROM admins a LEFT JOIN tenants t ON a.tenant_id = t.tenant_id WHERE a.id = ?");
    $stmt->execute([$id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
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
    <title>Редактировать администратора — Super Admin</title>
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
        .btn-danger{background:var(--danger);color:white;}
        .btn-danger:hover{opacity:0.9;}
        .container{max-width:700px;margin:0 auto;padding:0 24px 24px;}
        .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-top:16px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-weight:600;font-size:0.85rem;margin-bottom:6px;color:var(--text);}
        .form-group input,.form-group select{width:100%;padding:10px 14px;border-radius:8px;border:1px solid var(--border);background:var(--input-bg);color:var(--text);font-size:0.9rem;}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--accent);}
        .form-section{font-family:'Space Grotesk',sans-serif;font-size:1rem;color:var(--accent);margin:20px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--border);}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;}
        .alert-error{background:rgba(239,68,68,0.15);color:var(--danger);border:1px solid rgba(239,68,68,0.3);}
        .alert-success{background:rgba(34,197,94,0.15);color:var(--success);border:1px solid rgba(34,197,94,0.3);}
        .header-actions{display:flex;gap:12px;align-items:center;}
        .user-info{color:var(--text-sec);font-size:0.85rem;}
        .info-row{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);}
        .info-row:last-child{border-bottom:none;}
        .info-label{color:var(--text-sec);min-width:120px;font-size:0.85rem;}
        .info-value{font-size:0.85rem;}
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
        <a href="/admin/super/">Dashboard</a> / <a href="index.php">Администраторы</a> / <?= htmlspecialchars($admin['username']) ?>
    </div>
    <div class="container">
        <h2 style="font-family:'Space Grotesk',sans-serif;font-size:1.3rem;">Редактировать: <?= htmlspecialchars($admin['username']) ?></h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="form-section">Информация</div>
            <div class="info-row">
                <span class="info-label">ID:</span>
                <span class="info-value"><?= $admin['id'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Создан:</span>
                <span class="info-value"><?= $admin['created_at'] ?? '—' ?></span>
            </div>

            <form method="post" style="margin-top:16px;">
                <?= csrf_token_html() ?>
                <input type="hidden" name="form_action" value="update">
                <div class="form-section">Редактирование</div>
                <div class="form-group">
                    <label>Логин *</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($admin['username']) ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($admin['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Роль</label>
                    <select name="role" id="roleSelect">
                        <option value="center_admin" <?= $admin['role'] === 'center_admin' ? 'selected' : '' ?>>Center Admin</option>
                        <option value="super_admin" <?= $admin['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                </div>
                <div class="form-group" id="tenantGroup" style="<?= $admin['role'] === 'super_admin' ? 'display:none' : '' ?>">
                    <label>Tenant</label>
                    <select name="tenant_id" id="tenantSelect">
                        <option value="">— Выберите tenant —</option>
                        <?php foreach ($tenants as $t): ?>
                            <option value="<?= $t['tenant_id'] ?>" <?= $admin['tenant_id'] == $t['tenant_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
                    <a href="index.php" class="btn btn-secondary">Назад</a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:16px;">
            <form method="post">
                <?= csrf_token_html() ?>
                <input type="hidden" name="form_action" value="reset_password">
                <div class="form-section">Сброс пароля</div>
                <div class="form-group">
                    <label>Новый пароль</label>
                    <input type="password" name="new_password" required placeholder="Минимум 6 символов">
                </div>
                <button type="submit" class="btn btn-danger"><i class="fas fa-key"></i> Сбросить пароль</button>
            </form>
        </div>
    </div>

    <script>
    const roleSelect = document.getElementById('roleSelect');
    const tenantGroup = document.getElementById('tenantGroup');
    roleSelect.addEventListener('change', () => {
        tenantGroup.style.display = roleSelect.value === 'super_admin' ? 'none' : 'block';
    });
    </script>
</body>
</html>
