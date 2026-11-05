<?php
require '../../login/Login_functions.php';
initSession();
require '../../php/config.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Заполните все поля!";
    } else {
        $host = parse_url($_SERVER['HTTP_HOST'] ?? '', PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '');
        $isLocal = in_array(explode(':', $host)[0], ['localhost', '127.0.0.1', '::1'], true);

        if ($isLocal) {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND (tenant_id = ? OR role = 'super_admin')");
            $stmt->execute([$username, Tenant::getId()]);
        }
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'] ?? 'center_admin';
            $_SESSION['admin_tenant_id'] = (int)$admin['tenant_id'];
            $_SESSION['tenant_id'] = (int)$admin['tenant_id'];
            Tenant::set((int)$admin['tenant_id']);
            session_regenerate_id(true);

            if ($_SESSION['admin_role'] === 'super_admin') {
                header("Location: /admin/super/");
            } else {
                header("Location: ../");
            }
            exit;
        } else {
            $error = "Неверные данные!";
        }
    }
}

$tenantName = Tenant::getName();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Вход администратора — <?= htmlspecialchars($tenantName) ?></title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background-color: #f4f7fa;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .login-box {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 400px;
    }

    .login-box h2 {
      text-align: center;
      margin-bottom: 24px;
    }

    .tenant-name {
      text-align: center;
      color: #6b7280;
      font-size: 0.9rem;
      margin-bottom: 20px;
    }

    .login-box input {
      width: 100%;
      padding: 12px;
      margin-bottom: 16px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 14px;
      box-sizing: border-box;
    }

    .login-box button {
      width: 100%;
      padding: 12px;
      background-color: #2563eb;
      color: white;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background-color 0.2s ease-in-out;
    }

    .login-box button:hover {
      background-color: #1e40af;
    }

    .error {
      color: red;
      text-align: center;
      margin-bottom: 12px;
    }
  </style>
</head>
<body>
  <div class="login-box">
    <h2>Вход администратора</h2>
    <div class="tenant-name"><?= htmlspecialchars($tenantName) ?></div>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <input type="text" name="username" placeholder="Логин" required>
      <input type="password" name="password" placeholder="Пароль" required>
      <button type="submit">Войти</button>
    </form>
  </div>
</body>
</html>
