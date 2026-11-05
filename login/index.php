<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/Login_functions.php';

initSession();

// If already authenticated, redirect to profile immediately
$redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? '/profile/');
if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
    $redirect = '/profile/';
}

if (isLoggedIn()) {
    header("Location: " . $redirect);
    exit();
}

$tenantId = Tenant::getId();
$tenant = Tenant::get();
$tenantName = Tenant::getName() ?: 'e-Lesson';
$tenantLogo = Tenant::getLogoUrl() ?: '';
$tenantPrimary = Tenant::getPrimaryColor() ?: '#1B5EE8';

$error = '';
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token if provided
    if (!empty($_POST['csrf_token'])) {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            $error = 'Сессия устарела. Обновите страницу.';
        }
    }

    if (!$error) {
        if (empty($email) || empty($password)) {
            $error = 'Введите email и пароль!';
        } else {
            try {
                if (!$tenant) {
                    $error = 'Образовательный центр неактивен.';
                } else {
                    $stmt = $pdo->prepare("SELECT user_id, slug, first_name, last_name, phone, email, points, password, tenant_id FROM users WHERE email = ? AND tenant_id = ? AND is_active = 1");
                    $stmt->execute([$email, $tenantId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && password_verify($password, $user['password'])) {
                        loginUser($user);
                        session_regenerate_id(true);

                        if ($isAjax) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'redirect' => $redirect]);
                            exit();
                        }

                        header("Location: " . $redirect);
                        exit();
                    } else {
                        $error = 'Неверный email или пароль!';
                    }
                }
            } catch (PDOException $e) {
                error_log('[AUTH] Login error: ' . $e->getMessage());
                $error = 'Произошла системная ошибка. Повторите попытку позже.';
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $error]);
        exit();
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Вход в кабинет — <?= htmlspecialchars($tenantName) ?></title>
    <?php if ($tenantLogo): ?>
        <link rel="icon" href="<?= htmlspecialchars($tenantLogo) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '<?= htmlspecialchars($tenantPrimary) ?>'
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #FAFAF8;
            color: #0D1B2A;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between items-center px-4 py-8 bg-[#FAFAF8] antialiased">
    <!-- Header / Brand -->
    <header class="w-full max-w-md flex justify-between items-center py-2 mb-4">
        <a href="/" class="flex items-center gap-2.5 group">
            <?php if (!empty($tenantLogo)): ?>
                <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="<?= htmlspecialchars($tenantName) ?>" class="w-8 h-8 rounded-lg object-contain">
            <?php else: ?>
                <div class="w-8 h-8 rounded-lg bg-brand flex items-center justify-center text-white font-bold text-sm shadow-sm" style="background-color: <?= htmlspecialchars($tenantPrimary) ?>;">
                    <?= mb_substr($tenantName, 0, 1) ?>
                </div>
            <?php endif; ?>
            <span class="font-semibold text-[#0D1B2A] text-lg tracking-tight group-hover:text-opacity-80 transition-colors">
                <?= htmlspecialchars($tenantName) ?>
            </span>
        </a>

        <a href="/" class="text-xs font-medium text-[#7A8899] hover:text-[#0D1B2A] transition-colors flex items-center gap-1">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            На главную
        </a>
    </header>

    <!-- Main Card -->
    <main class="w-full max-w-md bg-white border border-[#E4E1DA] rounded-2xl p-6 sm:p-8 shadow-sm">
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-[#0D1B2A] tracking-tight">Вход в кабинет</h1>
            <p class="text-xs sm:text-sm text-[#7A8899] mt-1.5">
                Введите данные вашего аккаунта для доступа к курсам и тестам
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div id="errorMessage" class="mb-5 p-3.5 bg-red-50 border border-red-200 rounded-xl flex items-start gap-2.5 text-xs sm:text-sm text-red-700">
                <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="/login/" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div>
                <label for="email" class="block text-xs font-semibold text-[#0D1B2A] uppercase tracking-wider mb-1.5">
                    Email
                </label>
                <div class="relative">
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        autofocus
                        value="<?= htmlspecialchars($email) ?>"
                        placeholder="student@example.com"
                        class="w-full px-3.5 py-2.5 bg-[#FAFAF8] border border-[#E4E1DA] rounded-xl text-sm text-[#0D1B2A] placeholder-[#7A8899] focus:outline-none focus:bg-white focus:border-[#1B5EE8] focus:ring-2 focus:ring-[#1B5EE8]/15 transition-all"
                    >
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-semibold text-[#0D1B2A] uppercase tracking-wider">
                        Пароль
                    </label>
                    <a href="/login/forgot_password.php" class="text-xs text-[#1B5EE8] hover:underline">
                        Забыли пароль?
                    </a>
                </div>
                <div class="relative">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        placeholder="••••••••"
                        class="w-full px-3.5 py-2.5 pr-10 bg-[#FAFAF8] border border-[#E4E1DA] rounded-xl text-sm text-[#0D1B2A] placeholder-[#7A8899] focus:outline-none focus:bg-white focus:border-[#1B5EE8] focus:ring-2 focus:ring-[#1B5EE8]/15 transition-all"
                    >
                    <button 
                        type="button" 
                        id="togglePassword"
                        onclick="togglePasswordVisibility()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#7A8899] hover:text-[#0D1B2A] transition-colors p-1"
                        aria-label="Показать пароль"
                    >
                        <svg id="eyeIcon" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg id="eyeSlashIcon" class="w-4 h-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="pt-2">
                <button 
                    type="submit" 
                    id="submitBtn"
                    class="w-full py-2.5 px-4 rounded-xl text-white font-semibold text-sm transition-all duration-150 shadow-sm active:scale-[0.99] flex items-center justify-center gap-2"
                    style="background-color: <?= htmlspecialchars($tenantPrimary) ?>;"
                >
                    <span>Войти в кабинет</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </div>
        </form>
    </main>

    <!-- Footer -->
    <footer class="mt-8 text-center text-xs text-[#7A8899]">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($tenantName) ?>. Все права защищены.
    </footer>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeSlashIcon = document.getElementById('eyeSlashIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeSlashIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeSlashIcon.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
