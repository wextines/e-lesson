<?php
require_once '../authorization.php';

$success = '';
$error = '';
$tenant = null;

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: index.php");
    exit;
}

$id = (int)$id;

if (isset($_GET['logo_deleted'])) {
    $success = 'Логотип удалён.';
}

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

    if (empty($name) || empty($slug)) {
        $error = 'Название и slug обязательны.';
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $error = 'Slug может содержать только строчные буквы, цифры и дефис.';
    } else {
        verify_csrf();
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE slug = ? AND tenant_id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Tenant с таким slug уже существует.';
            } else {
                $stmt = $pdo->prepare("UPDATE tenants SET name=?, slug=?, domain=?, email=?, phone=?, timezone=?, default_language=?, primary_color=?, secondary_color=?, accent_color=? WHERE tenant_id=?");
                $stmt->execute([$name, $slug, $domain ?: null, $email ?: null, $phone ?: null, $timezone, $default_language, $primary_color, $secondary_color, $accent_color, $id]);
                $success = 'Tenant обновлён.';
            }
        } catch (PDOException $e) {
            error_log('[SUPER] Tenant update error: ' . $e->getMessage());
            $error = 'Ошибка при обновлении tenant.';
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE tenant_id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: index.php");
    exit;
}

$logoUrl = null;
if ($tenant['logo_path'] && file_exists(__DIR__ . '/../../' . ltrim($tenant['logo_path'], '/'))) {
    $logoUrl = $tenant['logo_path'];
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать tenant — Super Admin</title>
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
        .btn-sm{padding:5px 10px;font-size:0.78rem;}
        .container{max-width:900px;margin:0 auto;padding:0 24px 24px;}
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
        .logo-upload-area{border:2px dashed var(--border);border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:all 0.2s;}
        .logo-upload-area:hover{border-color:var(--accent);background:rgba(100,255,218,0.05);}
        .logo-upload-area.dragover{border-color:var(--accent);background:rgba(100,255,218,0.1);}
        .logo-preview{max-width:200px;max-height:120px;border-radius:8px;margin:0 auto 12px;display:block;}
        .branding-preview{background:var(--primary);border:1px solid var(--border);border-radius:12px;padding:20px;margin-top:16px;}
        .branding-preview .preview-nav{background:var(--secondary);padding:12px 16px;border-radius:8px;display:flex;align-items:center;gap:12px;margin-bottom:12px;}
        .branding-preview .preview-logo{width:32px;height:32px;border-radius:6px;object-fit:contain;}
        .branding-preview .preview-name{font-weight:600;font-size:0.9rem;}
        .branding-preview .preview-btn{padding:6px 14px;border-radius:6px;font-size:0.8rem;font-weight:600;border:none;cursor:pointer;}
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
        <a href="/admin/super/">Dashboard</a> / <a href="index.php">Tenant'ы</a> / <?= htmlspecialchars($tenant['name']) ?>
    </div>
    <div class="container">
        <h2 style="font-family:'Space Grotesk',sans-serif;font-size:1.3rem;">Редактировать: <?= htmlspecialchars($tenant['name']) ?></h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" id="tenantForm">
                <?= csrf_token_html() ?>
                <div class="form-section">Основная информация</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Название *</label>
                        <input type="text" name="name" id="nameInput" required value="<?= htmlspecialchars($tenant['name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Slug * <small style="font-weight:400;color:var(--text-sec);">(латиница, дефис)</small></label>
                        <input type="text" name="slug" required pattern="[a-z0-9\-]+" value="<?= htmlspecialchars($tenant['slug']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($tenant['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Телефон</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($tenant['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Домен</label>
                        <input type="text" name="domain" value="<?= htmlspecialchars($tenant['domain'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Часовой пояс</label>
                        <select name="timezone">
                            <option value="Asia/Tashkent" <?= $tenant['timezone'] === 'Asia/Tashkent' ? 'selected' : '' ?>>Asia/Tashkent (UTC+5)</option>
                            <option value="Asia/Almaty" <?= $tenant['timezone'] === 'Asia/Almaty' ? 'selected' : '' ?>>Asia/Almaty (UTC+6)</option>
                            <option value="Asia/Bishkek" <?= $tenant['timezone'] === 'Asia/Bishkek' ? 'selected' : '' ?>>Asia/Bishkek (UTC+6)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Язык по умолчанию</label>
                    <select name="default_language">
                        <option value="uz" <?= $tenant['default_language'] === 'uz' ? 'selected' : '' ?>>O'zbekcha</option>
                        <option value="ru" <?= $tenant['default_language'] === 'ru' ? 'selected' : '' ?>>Русский</option>
                        <option value="en" <?= $tenant['default_language'] === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>

                <div class="form-section">Логотип</div>
                <div class="logo-upload-area" id="logoDropArea" onclick="document.getElementById('logoInput').click()">
                    <?php if ($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="logo-preview" id="logoPreview">
                        <p style="color:var(--text-sec);font-size:0.8rem;margin-bottom:8px;">Нажмите или перетащите для замены</p>
                        <a href="delete_logo.php" onclick="return confirm('Удалить логотип?')" style="color:var(--danger);font-size:0.8rem;text-decoration:none;"><i class="fas fa-trash"></i> Удалить логотип</a>
                    <?php else: ?>
                        <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:var(--accent);margin-bottom:8px;"></i>
                        <p style="color:var(--text-sec);font-size:0.85rem;">Нажмите или перетащите файл логотипа</p>
                        <p style="color:var(--text-sec);font-size:0.75rem;">PNG, JPG, GIF, WebP, SVG (макс. 2MB)</p>
                    <?php endif; ?>
                </div>
                <input type="file" id="logoInput" name="logo" accept="image/*" style="display:none">

                <div class="form-section">Брендинг</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Primary color</label>
                        <div class="color-row">
                            <input type="color" name="primary_color" id="primaryColor" value="<?= htmlspecialchars($tenant['primary_color'] ?? '#0a192f') ?>">
                            <input type="text" name="primary_color_text" value="<?= htmlspecialchars($tenant['primary_color'] ?? '#0a192f') ?>" maxlength="7">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Secondary color</label>
                        <div class="color-row">
                            <input type="color" name="secondary_color" id="secondaryColor" value="<?= htmlspecialchars($tenant['secondary_color'] ?? '#172a45') ?>">
                            <input type="text" name="secondary_color_text" value="<?= htmlspecialchars($tenant['secondary_color'] ?? '#172a45') ?>" maxlength="7">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Accent color</label>
                    <div class="color-row">
                        <input type="color" name="accent_color" id="accentColor" value="<?= htmlspecialchars($tenant['accent_color'] ?? '#64ffda') ?>">
                        <input type="text" name="accent_color_text" value="<?= htmlspecialchars($tenant['accent_color'] ?? '#64ffda') ?>" maxlength="7">
                    </div>
                </div>

                <div class="form-section">Предпросмотр брендинга</div>
                <div class="branding-preview" id="brandingPreview">
                    <div class="preview-nav">
                        <?php if ($logoUrl): ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="preview-logo" id="previewLogo">
                        <?php else: ?>
                            <div style="width:32px;height:32px;border-radius:6px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;color:var(--primary);" id="previewLogoText"><?= strtoupper(substr($tenant['name'], 0, 2)) ?></div>
                        <?php endif; ?>
                        <span class="preview-name" id="previewName"><?= htmlspecialchars($tenant['name']) ?></span>
                        <div style="flex:1;"></div>
                        <span class="preview-btn" id="previewBtn" style="background:<?= htmlspecialchars($tenant['accent_color'] ?? '#64ffda') ?>;color:<?= htmlspecialchars($tenant['primary_color'] ?? '#0a192f') ?>;">Кабинет</span>
                    </div>
                    <div style="padding:12px;border-radius:8px;background:<?= htmlspecialchars($tenant['primary_color'] ?? '#0a192f') ?>;color:#fff;font-size:0.85rem;">
                        Цвет фона: <span id="previewBg"><?= htmlspecialchars($tenant['primary_color'] ?? '#0a192f') ?></span> |
                        Акцент: <span id="previewAccent" style="color:<?= htmlspecialchars($tenant['accent_color'] ?? '#64ffda') ?>;"><?= htmlspecialchars($tenant['accent_color'] ?? '#64ffda') ?></span>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
                    <a href="index.php" class="btn btn-secondary">Назад</a>
                </div>
            </form>
        </div>

        <div style="margin-top:24px;padding:16px;background:var(--card);border:1px solid var(--border);border-radius:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <strong>Статус tenant'а:</strong>
                    <?php if ($tenant['is_active']): ?>
                        <span style="color:var(--success);">Активен</span>
                    <?php else: ?>
                        <span style="color:var(--danger);">Неактивен</span>
                    <?php endif; ?>
                </div>
                <?php if ($tenant['is_active']): ?>
                    <form method="post" action="toggle.php" onsubmit="return confirm('Деактивировать tenant? Пользователи потеряют доступ.')">
                        <?= csrf_token_html() ?>
                        <input type="hidden" name="tenant_id" value="<?= $tenant['tenant_id'] ?>">
                        <input type="hidden" name="action" value="deactivate">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-ban"></i> Деактивировать</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="toggle.php">
                        <?= csrf_token_html() ?>
                        <input type="hidden" name="tenant_id" value="<?= $tenant['tenant_id'] ?>">
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Активировать</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Color sync
    document.querySelectorAll('.color-row').forEach(row => {
        const colorInput = row.querySelector('input[type="color"]');
        const textInput = row.querySelector('input[type="text"]');
        if (colorInput && textInput) {
            colorInput.addEventListener('input', () => {
                textInput.value = colorInput.value;
                updatePreview();
            });
            textInput.addEventListener('input', () => {
                if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) {
                    colorInput.value = textInput.value;
                    updatePreview();
                }
            });
        }
    });

    // Logo upload
    const dropArea = document.getElementById('logoDropArea');
    const logoInput = document.getElementById('logoInput');

    ['dragenter', 'dragover'].forEach(evt => {
        dropArea.addEventListener(evt, e => { e.preventDefault(); dropArea.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dropArea.addEventListener(evt, e => { e.preventDefault(); dropArea.classList.remove('dragover'); });
    });
    dropArea.addEventListener('drop', e => {
        logoInput.files = e.dataTransfer.files;
        handleLogoUpload(logoInput.files[0]);
    });

    logoInput.addEventListener('change', () => {
        if (logoInput.files[0]) handleLogoUpload(logoInput.files[0]);
    });

    function handleLogoUpload(file) {
        if (!file) return;
        const formData = new FormData();
        formData.append('logo', file);
        formData.append('tenant_id', <?= $id ?>);
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

        fetch('upload_logo.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const preview = document.getElementById('logoPreview');
                    if (preview) {
                        preview.src = data.logo_path;
                    } else {
                        location.reload();
                    }
                    document.getElementById('previewLogo').src = data.logo_path;
                } else {
                    alert(data.error || 'Ошибка загрузки');
                }
            })
            .catch(() => alert('Ошибка сети'));
    }

    // Name input -> preview
    document.getElementById('nameInput').addEventListener('input', function() {
        document.getElementById('previewName').textContent = this.value || 'Tenant';
    });

    function updatePreview() {
        const primary = document.getElementById('primaryColor').value;
        const secondary = document.getElementById('secondaryColor').value;
        const accent = document.getElementById('accentColor').value;

        document.getElementById('previewBtn').style.background = accent;
        document.getElementById('previewBtn').style.color = primary;
        document.getElementById('previewBg').textContent = primary;
        document.getElementById('previewAccent').textContent = accent;
        document.getElementById('previewAccent').style.color = accent;

        const nav = document.querySelector('.preview-nav');
        nav.style.background = secondary;
    }
    </script>
</body>
</html>
