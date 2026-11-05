<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../login/Login_functions.php';

if (!isLoggedIn()) {
    header("Location: /login/");
    exit();
}

$user_id = getSessionUserId();
$tenant_id = Tenant::getId();

// 1. Fetch user data
$stmtUser = $pdo->prepare("SELECT user_id, phone, class, balance, first_name, last_name, points, slug, email, password FROM users WHERE user_id = ? AND tenant_id = ?");
$stmtUser->execute([$user_id, $tenant_id]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$feedbackMsg = '';
$feedbackType = '';

// Handle Personal Info Update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    validateCsrfToken();
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $class = trim($_POST['class'] ?? '');

    if (empty($firstName)) {
        $feedbackMsg = 'Имя обязательно для заполнения.';
        $feedbackType = 'error';
    } else {
        try {
            $stmtUpd = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, class = ? WHERE user_id = ? AND tenant_id = ?");
            $stmtUpd->execute([$firstName, $lastName, $phone, $class, $user_id, $tenant_id]);
            
            // Update session
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['phone'] = $phone;

            // Refresh user
            $user['first_name'] = $firstName;
            $user['last_name'] = $lastName;
            $user['phone'] = $phone;
            $user['class'] = $class;

            $feedbackMsg = 'Личные данные успешно сохранены!';
            $feedbackType = 'success';
        } catch (Exception $e) {
            $feedbackMsg = 'Ошибка при обновлении: ' . $e->getMessage();
            $feedbackType = 'error';
        }
    }
}

// Handle Password Change (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    validateCsrfToken();
    $currentPw = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (empty($currentPw) || empty($newPw)) {
        $feedbackMsg = 'Пожалуйста, заполните все поля пароля.';
        $feedbackType = 'error';
    } elseif ($newPw !== $confirmPw) {
        $feedbackMsg = 'Новый пароль и подтверждение не совпадают.';
        $feedbackType = 'error';
    } elseif (strlen($newPw) < 6) {
        $feedbackMsg = 'Пароль должен содержать не менее 6 символов.';
        $feedbackType = 'error';
    } elseif (!empty($user['password']) && !password_verify($currentPw, $user['password'])) {
        $feedbackMsg = 'Текущий пароль указан неверно.';
        $feedbackType = 'error';
    } else {
        try {
            $newHash = password_hash($newPw, PASSWORD_DEFAULT);
            $stmtPw = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ? AND tenant_id = ?");
            $stmtPw->execute([$newHash, $user_id, $tenant_id]);
            $feedbackMsg = 'Пароль успешно изменён!';
            $feedbackType = 'success';
        } catch (Exception $e) {
            $feedbackMsg = 'Ошибка при смене пароля: ' . $e->getMessage();
            $feedbackType = 'error';
        }
    }
}

// 2. Fetch stats & courses
$stmtSubs = $pdo->prepare("SELECT
    s.subscription_id,
    c.course_id,
    c.title,
    c.subject,
    c.price,
    s.access_expires_at,
    s.status,
    DATEDIFF(s.access_expires_at, NOW()) as days_left
FROM course_subs s
JOIN courses c ON s.course_id = c.course_id
WHERE s.user_id = ? AND s.tenant_id = ? AND c.tenant_id = ?
ORDER BY s.access_expires_at DESC");
$stmtSubs->execute([$user_id, $tenant_id, $tenant_id]);
$subscriptions = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

$courseColors = ['#1B5EE8', '#059669', '#7C3AED', '#D97706', '#2563EB', '#0D9488'];
$months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

// Tab handling: 'general' (personal data & security) or 'payments' (subscriptions & receipts)
$currentTab = $_GET['tab'] ?? 'general';
if (!in_array($currentTab, ['general', 'payments'], true)) {
    $currentTab = 'general';
}
$uploadSuccess = ($currentTab === 'payments' && ($_GET['success'] ?? '') === '1');

$paymentsHistory = [];
$allowUploads = false;
$allCourses = [];
$tenant = null;
if ($currentTab === 'payments') {
    $tenant = Tenant::get();
    $allowUploads = Tenant::getConfig('allow_receipt_uploads', '0') === '1';

    $stmtPay = $pdo->prepare("SELECT
        p.payment_id,
        p.amount,
        p.status,
        p.payment_date,
        p.payment_method,
        p.receipt_path,
        p.comment,
        c.title as course_title,
        c.course_id
    FROM payments p
    LEFT JOIN courses c ON p.course_id = c.course_id AND c.tenant_id = p.tenant_id
    WHERE p.user_id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
    ORDER BY p.payment_date DESC");
    $stmtPay->execute([$user_id, $tenant_id]);
    $paymentsHistory = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

    if ($allowUploads) {
        $stmtAllCourses = $pdo->prepare("SELECT course_id, title, price FROM courses WHERE tenant_id = ? AND is_active = 1 ORDER BY title ASC");
        $stmtAllCourses->execute([$tenant_id]);
        $allCourses = $stmtAllCourses->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Completed tests count & avg score
$testsCount = 0;
$avgScore = 0;
if (!empty($user['slug'])) {
    $stmtTR = $pdo->prepare("SELECT score FROM test_results WHERE slug = ? AND tenant_id = ?");
    $stmtTR->execute([$user['slug'], $tenant_id]);
    $scores = $stmtTR->fetchAll(PDO::FETCH_COLUMN);
    $testsCount = count($scores);
    if ($testsCount > 0) {
        $sum = 0;
        foreach ($scores as $sc) {
            if (strpos($sc, '/') !== false) {
                [$c, $t] = explode('/', $sc);
                $sum += (int)$t > 0 ? round(((int)$c / (int)$t) * 100) : 0;
            } else {
                $sum += (int)$sc;
            }
        }
        $avgScore = round($sum / $testsCount);
    }
}

$pageTitle = 'Профиль';
$activeTab = 'profile';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Column -->
<div class="flex-1 flex flex-col min-w-0 h-full">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto px-4 sm:px-6 pt-4 sm:pt-6 pb-16 lg:pb-6">
        <div class="max-w-4xl mx-auto space-y-6">
            
            <!-- Feedback alert -->
            <?php if (!empty($feedbackMsg)): ?>
                <div class="p-4 rounded-xl text-sm font-medium border <?= $feedbackType === 'success' ? 'bg-[#D1FAE5] text-[#059669] border-[#A7F3D0]' : 'bg-[#FEE2E2] text-[#DC2626] border-[#FECACA]' ?>">
                    <?= htmlspecialchars($feedbackMsg) ?>
                </div>
            <?php endif; ?>

            <!-- Profile Header Card -->
            <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] overflow-hidden shadow-sm">
                <div class="h-1 bg-[#1B5EE8] w-full"></div>
                <div class="p-5 sm:p-6 flex items-start gap-4 sm:gap-5">
                    <div class="relative shrink-0">
                        <div class="w-14 h-14 rounded-2xl bg-[#1B5EE8] text-white text-xl font-bold flex items-center justify-center select-none font-mono">
                            <?= htmlspecialchars($userInitials) ?>
                        </div>
                        <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-[#059669] border-2 border-[#FAFAF8]" title="В сети"></span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-[#0D1B2A] leading-tight">
                            <?= htmlspecialchars($userFullName) ?>
                        </h2>
                        <p class="text-sm text-[#7A8899] mt-0.5 mb-2.5">
                            Студент · <?= !empty($user['class']) ? htmlspecialchars($user['class'] . ' класс') : 'Учебный центр' ?>
                        </p>

                        <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-[#4A607A]">
                            <span class="flex items-center gap-1.5">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="text-[#7A8899]">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <?= htmlspecialchars($user['email']) ?>
                            </span>
                            <?php if (!empty($user['phone'])): ?>
                                <span class="flex items-center gap-1.5">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="text-[#7A8899]">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.61 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6 6l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 16a2 2 0 0 1 .27.92z"></path>
                                    </svg>
                                    <?= htmlspecialchars($user['phone']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Strip inside header card -->
                <div class="border-t border-[#E4E1DA] bg-[#F7F5F1] px-5 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 text-center">
                    <div>
                        <p class="text-lg sm:text-xl font-bold font-[var(--font-mono)] text-[#0D1B2A] leading-none"><?= count($subscriptions) ?></p>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#7A8899] mt-1">Курсов</p>
                    </div>
                    <div class="h-6 w-px bg-[#E4E1DA]"></div>
                    <div>
                        <p class="text-lg sm:text-xl font-bold font-[var(--font-mono)] text-[#0D1B2A] leading-none"><?= $testsCount ?></p>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#7A8899] mt-1">Тестов сдано</p>
                    </div>
                    <div class="h-6 w-px bg-[#E4E1DA]"></div>
                    <div>
                        <p class="text-lg sm:text-xl font-bold font-[var(--font-mono)] text-[#059669] leading-none"><?= $avgScore ?>%</p>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#7A8899] mt-1">Средний балл</p>
                    </div>
                    <div class="h-6 w-px bg-[#E4E1DA]"></div>
                    <div>
                        <p class="text-lg sm:text-xl font-bold font-[var(--font-mono)] text-[#1B5EE8] leading-none"><?= (int)($user['points'] ?? 0) ?></p>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#7A8899] mt-1">Баллов</p>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="flex items-center gap-2 border-b border-slate-200">
                <a href="?tab=general" class="pb-3 px-2 text-sm font-semibold border-b-2 transition-colors <?= $currentTab === 'general' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    Личные данные и безопасность
                </a>
                <a href="?tab=payments" class="pb-3 px-2 text-sm font-semibold border-b-2 transition-colors <?= $currentTab === 'payments' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    Подписки и оплаты
                </a>
            </div>

            <?php if ($currentTab === 'general'): ?>

            <!-- Личные данные (Personal data) -->
            <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-5 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base sm:text-lg font-bold text-[#0D1B2A]">Личные данные</h3>
                    <button type="button" onclick="toggleEditMode()" id="edit-toggle-btn" class="text-xs sm:text-sm text-[#1B5EE8] font-medium hover:underline">
                        Редактировать
                    </button>
                </div>

                <!-- View Mode -->
                <div id="view-mode" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Имя</span>
                        <p class="text-sm sm:text-base text-[#0D1B2A] font-medium"><?= htmlspecialchars($user['first_name'] ?: '—') ?></p>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Фамилия</span>
                        <p class="text-sm sm:text-base text-[#0D1B2A] font-medium"><?= htmlspecialchars($user['last_name'] ?: '—') ?></p>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Телефон</span>
                        <p class="text-sm sm:text-base text-[#0D1B2A] font-medium font-mono"><?= htmlspecialchars($user['phone'] ?: '—') ?></p>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Класс</span>
                        <p class="text-sm sm:text-base text-[#0D1B2A] font-medium"><?= !empty($user['class']) ? htmlspecialchars($user['class'] . ' класс') : '—' ?></p>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Email (Логин)</span>
                        <p class="text-sm sm:text-base text-[#7A8899] font-mono"><?= htmlspecialchars($user['email']) ?> <span class="text-xs text-[#A0AEC0]">(только чтение)</span></p>
                    </div>
                </div>

                <!-- Edit Form Mode (Hidden by default) -->
                <form id="edit-form" method="POST" action="" class="hidden space-y-4 pt-1">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Имя</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Фамилия</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Номер телефона</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+998 90 123 45 67" class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">Класс</label>
                            <input type="text" name="class" value="<?= htmlspecialchars($user['class'] ?? '') ?>" placeholder="Например: 9 или 1-4" class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#E4E1DA]">
                        <button type="button" onclick="toggleEditMode()" class="px-4 py-2 text-xs sm:text-sm font-medium text-[#4A607A] bg-[#FAFAF8] border border-[#E4E1DA] rounded-xl hover:bg-[#F0EDE8]">
                            Отмена
                        </button>
                        <button type="submit" class="px-4 py-2 text-xs sm:text-sm font-medium text-white bg-[#1B5EE8] rounded-xl hover:bg-[#1650CC]">
                            Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>

            <!-- Безопасность / Смена пароля -->
            <div id="security" class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-5 sm:p-6 shadow-sm">
                <h3 class="text-base sm:text-lg font-bold text-[#0D1B2A] mb-0.5">Безопасность</h3>
                <p class="text-sm text-[#7A8899] mb-4">Обновите пароль для защиты вашего аккаунта.</p>

                <form method="POST" action="" class="space-y-4 max-w-md">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                    <div>
                        <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1.5">Текущий пароль</label>
                        <input type="password" name="current_password" required class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1.5">Новый пароль</label>
                        <input type="password" name="new_password" required minlength="6" class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1.5">Подтверждение нового пароля</label>
                        <input type="password" name="confirm_password" required minlength="6" class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                    </div>

                    <button type="submit" class="px-4 py-2 text-xs sm:text-sm font-medium text-white bg-[#0D1B2A] rounded-xl hover:bg-[#1B2A3D] transition-colors">
                        Изменить пароль
                    </button>
                </form>
            </div>

            <?php endif; ?>

            <?php if ($currentTab === 'payments'): ?>

            <!-- Upload success banner -->
            <?php if ($uploadSuccess): ?>
                <div class="p-4 rounded-xl text-sm font-medium border bg-[#D1FAE5] text-[#059669] border-[#A7F3D0]">
                    Квитанция успешно отправлена! Администратор проверит платёж и активирует доступ к курсу.
                </div>
            <?php endif; ?>

            <!-- Мои подписки -->
            <div>
                <h3 class="text-base sm:text-lg font-bold text-[#0D1B2A] mb-3">Мои подписки</h3>
                <?php if (empty($subscriptions)): ?>
                    <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-6 text-center text-sm text-[#7A8899]">
                        У вас пока нет активных подписок.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($subscriptions as $idx => $s):
                            $color = $courseColors[$idx % count($courseColors)];
                            $hasRealAccess = ($s['status'] ?? '') === 'active' && !empty($s['access_expires_at']);
                            $daysLeft = $hasRealAccess ? (int)$s['days_left'] : 0;
                            $expDate = '';
                            if (!empty($s['access_expires_at'])) {
                                $ts = strtotime($s['access_expires_at']);
                                $expDate = date('j', $ts) . ' ' . ($months[(int)date('n', $ts) - 1] ?? '') . ' ' . date('Y', $ts);
                            }
                        ?>
                            <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 shadow-sm">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-1.5 h-8 rounded-full shrink-0" style="background-color: <?= htmlspecialchars($color) ?>;"></div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-[#0D1B2A] truncate"><?= htmlspecialchars($s['title']) ?></p>
                                        <p class="text-xs text-[#7A8899]"><?= htmlspecialchars($s['subject'] ?: 'Курс') ?></p>
                                    </div>
                                    <span class="text-xs font-medium px-2.5 py-1 rounded-md shrink-0 <?= $hasRealAccess ? 'bg-[#D1FAE5] text-[#059669]' : 'bg-[#F0EDE8] text-[#7A8899]' ?>">
                                        <?= $hasRealAccess ? 'Активен' : 'Ожидает оплаты' ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-[#7A8899] pt-2 border-t border-[#F0EDE8]">
                                    <span class="font-[var(--font-mono)]"><?= number_format((int)$s['price'], 0, '', ' ') ?> сум</span>
                                    <span>
                                        <?php if ($hasRealAccess): ?>
                                            до <?= htmlspecialchars($expDate) ?> · <?= $daysLeft >= 0 ? $daysLeft . ' дн.' : 'истёк' ?>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- История платежей -->
            <div>
                <h3 class="text-base sm:text-lg font-bold text-[#0D1B2A] mb-3">История платежей</h3>
                <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] px-4 sm:px-5 py-0.5 shadow-sm">
                    <?php if (empty($paymentsHistory)): ?>
                        <div class="py-10 text-center text-sm text-[#7A8899]">
                            История платежей пуста.
                        </div>
                    <?php else: ?>
                        <?php foreach ($paymentsHistory as $idx => $p):
                            $isLast = ($idx === count($paymentsHistory) - 1);
                            $pDate = $p['payment_date'] ? strtotime($p['payment_date']) : time();
                            $dateStr = date('j', $pDate) . ' ' . ($months[(int)date('n', $pDate) - 1] ?? '') . ' ' . date('Y', $pDate);

                            $statusStyle = 'bg-[#FDE68A] text-[#D97706]';
                            $statusLabel = 'На проверке';
                            if ($p['status'] === 'accepted') {
                                $statusStyle = 'bg-[#D1FAE5] text-[#059669]';
                                $statusLabel = 'Одобрен';
                            } elseif ($p['status'] === 'rejected') {
                                $statusStyle = 'bg-[#FEE2E2] text-[#DC2626]';
                                $statusLabel = 'Отклонён';
                            }
                        ?>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 py-3 sm:py-3.5 <?= !$isLast ? 'border-b border-[#F0EDE8]' : '' ?>">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm sm:text-base font-semibold text-[#0D1B2A] truncate">
                                        <?= htmlspecialchars($p['course_title'] ?: 'Оплата за курс') ?>
                                    </p>
                                    <p class="text-xs text-[#7A8899] mt-0.5"><?= $dateStr ?></p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-sm sm:text-base font-semibold font-[var(--font-mono)] text-[#0D1B2A]">
                                        <?= number_format((int)$p['amount'], 0, '', ' ') ?> сум
                                    </span>
                                    <span class="text-xs font-medium px-2.5 py-1 rounded-md <?= $statusStyle ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                    <?php if (!empty($p['receipt_path'])): ?>
                                        <a href="/file.php?type=receipt&id=<?= $p['payment_id'] ?>" target="_blank" class="text-xs sm:text-sm text-[#1B5EE8] hover:underline">
                                            Чек ↗
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Прикрепить чек об оплате -->
            <?php if ($allowUploads): ?>
                <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-5 sm:p-6 shadow-sm">
                    <h3 class="text-base sm:text-lg font-bold text-[#0D1B2A] mb-0.5">Прикрепить чек об оплате</h3>
                    <p class="text-sm text-[#7A8899] mb-4">Загрузите квитанцию — администратор проверит платёж и активирует доступ.</p>

                    <form method="POST" action="/profile/payments.php" enctype="multipart/form-data" class="space-y-4 max-w-md">
                        <input type="hidden" name="action" value="upload_receipt">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                        <div>
                            <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1.5">Курс</label>
                            <select name="course_id" required class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                                <option value="">-- Выберите курс --</option>
                                <?php foreach ($allCourses as $ac): ?>
                                    <option value="<?= $ac['course_id'] ?>" data-price="<?= (int)$ac['price'] ?>">
                                        <?= htmlspecialchars($ac['title']) ?> (<?= number_format((int)$ac['price'], 0, '', ' ') ?> сум)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1.5">Сумма оплаты (сум)</label>
                            <input type="number" name="amount" required placeholder="Например: 250000" class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1.5">Квитанция (JPG, PNG, PDF, WebP)</label>
                            <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf,.webp" required class="w-full text-xs text-[#7A8899] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-[#EEF3FD] file:text-[#1B5EE8] hover:file:bg-[#E0ECFD]">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1.5">Комментарий (необязательно)</label>
                            <textarea name="comment" rows="3" placeholder="Номер транзакции или ФИО плательщика" class="w-full px-3.5 py-2 text-sm text-[#0D1B2A] bg-[#F7F5F1] border border-[#E4E1DA] rounded-xl focus:outline-none focus:border-[#1B5EE8]"></textarea>
                        </div>

                        <button type="submit" class="px-4 py-2 text-xs sm:text-sm font-medium text-white bg-[#1B5EE8] rounded-xl hover:bg-[#1650CC]">
                            Отправить квитанцию
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="border border-[#E4E1DA] rounded-xl bg-[#F7F5F1] p-5 sm:p-6 flex items-start gap-3">
                    <span class="text-[#1B5EE8] text-base leading-none mt-0.5">&#9432;</span>
                    <p class="text-xs sm:text-sm text-[#4A607A] leading-relaxed m-0">
                        Оплата обучения принимается у администратора учебного центра.
                        <?php if (!empty($tenant['phone'])): ?>
                            Телефон: <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', (string)$tenant['phone'])) ?>" class="text-[#1B5EE8] font-medium hover:underline"><?= htmlspecialchars($tenant['phone']) ?></a>.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php endif; ?>

            <!-- Logout action -->
            <div class="pt-1 flex justify-between items-center">
                <span class="text-xs sm:text-sm text-[#7A8899]">e-Lesson ID: #<?= $user['user_id'] ?></span>
                <a href="/login/logout.php" class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-medium text-[#DC2626] bg-[#FEE2E2] hover:bg-[#FECACA] rounded-xl transition-colors">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9"></path>
                    </svg>
                    <span>Выйти из аккаунта</span>
                </a>
            </div>

            <div class="h-2"></div>
        </div>
    </main>

<script>
function toggleEditMode() {
    const view = document.getElementById('view-mode');
    const form = document.getElementById('edit-form');
    const btn = document.getElementById('edit-toggle-btn');
    if (!view || !form) return;

    if (view.classList.contains('hidden')) {
        view.classList.remove('hidden');
        form.classList.add('hidden');
        if (btn) btn.textContent = 'Редактировать';
    } else {
        view.classList.add('hidden');
        form.classList.remove('hidden');
        if (btn) btn.textContent = 'Отмена';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
