<?php
require_once __DIR__ . '/../authorization.php';
require_admin_role(['center_admin', 'super_admin']);
require_once __DIR__ . '/../../php/config.php';

$pageTitle = 'Настройки';
$tid = Tenant::getId();
$messageUser = '';
$messagePass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['update_username'])) {
        $newUsername = trim($_POST['username'] ?? '');
        if (empty($newUsername)) {
            $messageUser = 'Логин не может быть пустым';
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET username = ? WHERE username = ? AND tenant_id = ?");
            if ($stmt->execute([$newUsername, $_SESSION['admin_username'], $tid])) {
                $_SESSION['admin_username'] = $newUsername;
                $messageUser = 'Логин обновлён';
            } else {
                $messageUser = 'Ошибка обновления логина';
            }
        }
    }
    if (isset($_POST['update_password'])) {
        $newPassword = $_POST['password'] ?? '';
        if (strlen($newPassword) < 6) {
            $messagePass = 'Пароль должен содержать минимум 6 символов';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = ? AND tenant_id = ?");
            if ($stmt->execute([$hashedPassword, $_SESSION['admin_username'], $tid])) {
                $messagePass = 'Пароль обновлён';
            } else {
                $messagePass = 'Ошибка обновления пароля';
            }
        }
    }
    if (isset($_POST['update_payment_settings'])) {
        // Unchecked checkboxes are simply absent from the POST body, so the presence of
        // the field is the value.
        $val = isset($_POST['allow_receipt_uploads']) ? '1' : '0';
        $messagePayments = Tenant::setConfig('allow_receipt_uploads', $val)
            ? 'Настройки оплаты обновлены'
            : 'Ошибка сохранения настроек оплаты';
        $_SESSION['settings_msg_payments'] = $messagePayments;
    }
    $_SESSION['settings_msg_user'] = $messageUser;
    $_SESSION['settings_msg_pass'] = $messagePass;
    header('Location: ');
    exit;
}

$messageUser = $_SESSION['settings_msg_user'] ?? '';
$messagePass = $_SESSION['settings_msg_pass'] ?? '';
$messagePayments = $_SESSION['settings_msg_payments'] ?? '';
unset($_SESSION['settings_msg_user'], $_SESSION['settings_msg_pass'], $_SESSION['settings_msg_payments']);

$tenant = Tenant::get();

// Offline-first default: a center only sees the online receipt flow if it opts in.
$allowUploads = Tenant::getConfig('allow_receipt_uploads', '0') === '1';

// Receipts already awaiting moderation, so turning the toggle off never strands them.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND status = 'pending' AND deleted_at IS NULL");
$stmt->execute([$tid]);
$pendingReceipts = (int)$stmt->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Настройки</h1>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <h2>Профиль администратора</h2>
        <table>
            <tr><td><strong>Логин</strong></td><td><?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?></td></tr>
            <tr><td><strong>Роль</strong></td><td><span class="badge badge-blue"><?= htmlspecialchars($_SESSION['admin_role'] ?? '') ?></span></td></tr>
            <tr><td><strong>Tenant</strong></td><td><?= htmlspecialchars($tenant['name'] ?? '') ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Информация о центре</h2>
        <table>
            <tr><td><strong>Название</strong></td><td><?= htmlspecialchars($tenant['name'] ?? '') ?></td></tr>
            <tr><td><strong>Slug</strong></td><td><?= htmlspecialchars($tenant['slug'] ?? '') ?></td></tr>
            <tr><td><strong>Домен</strong></td><td><?= htmlspecialchars($tenant['domain'] ?? '—') ?></td></tr>
            <tr><td><strong>Email</strong></td><td><?= htmlspecialchars($tenant['email'] ?? '—') ?></td></tr>
            <tr><td><strong>Телефон</strong></td><td><?= htmlspecialchars($tenant['phone'] ?? '—') ?></td></tr>
            <tr><td><strong>Часовой пояс</strong></td><td><?= htmlspecialchars($tenant['timezone'] ?? '—') ?></td></tr>
            <tr><td><strong>Язык</strong></td><td><?= htmlspecialchars($tenant['default_language'] ?? '—') ?></td></tr>
        </table>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <h2>Изменение логина</h2>
        <?php if ($messageUser): ?>
            <div class="msg <?= strpos($messageUser, 'Ошибка') !== false ? 'msg-err' : 'msg-ok' ?>"><?= htmlspecialchars($messageUser) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Новый логин</label>
                <input type="text" name="username" placeholder="Новый логин" required>
            </div>
            <button type="submit" name="update_username" class="btn btn-primary">Обновить логин</button>
        </form>
    </div>

    <div class="card">
        <h2>Изменение пароля</h2>
        <?php if ($messagePass): ?>
            <div class="msg <?= strpos($messagePass, 'Ошибка') !== false || strpos($messagePass, 'минимум') !== false ? 'msg-err' : 'msg-ok' ?>"><?= htmlspecialchars($messagePass) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Новый пароль</label>
                <input type="password" name="password" placeholder="Минимум 6 символов" required minlength="6">
            </div>
            <button type="submit" name="update_password" class="btn btn-primary">Обновить пароль</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <h2>Приём оплаты</h2>
    <?php if ($messagePayments): ?>
        <div class="msg <?= strpos($messagePayments, 'Ошибка') !== false ? 'msg-err' : 'msg-ok' ?>"><?= htmlspecialchars($messagePayments) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;margin-bottom:16px;">
            <input type="checkbox" name="allow_receipt_uploads" value="1" <?= $allowUploads ? 'checked' : '' ?> style="margin-top:3px;width:18px;height:18px;cursor:pointer;flex-shrink:0;">
            <span>
                <strong style="display:block;color:#111827;">Разрешить студентам загружать чеки онлайн</strong>
                <span style="font-size:13px;color:#6b7280;">
                    Если выключено, студент видит в кабинете только историю платежей и контакт администратора,
                    а раздел «Чеки на проверку» скрывается из меню. Оплата принимается очно.
                </span>
            </span>
        </label>

        <?php if (!$allowUploads && $pendingReceipts > 0): ?>
            <div class="msg msg-err" style="margin-bottom:16px;">
                Есть непроверенные чеки: <strong><?= $pendingReceipts ?></strong>.
                Раздел «Чеки на проверку» остаётся в меню, пока они не будут обработаны.
            </div>
        <?php endif; ?>

        <button type="submit" name="update_payment_settings" class="btn btn-primary">Сохранить</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
