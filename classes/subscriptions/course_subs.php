<?php
require '../../php/config.php';
require '../../login/Login_functions.php';
initSession();

// Проверка авторизации
if (!isLoggedIn()) {
    header('Location: /');
    exit;
}

$userId = getSessionUserId();
$courseId = $_GET['course_id'] ?? null;
$confirm = $_POST['confirm'] ?? false;

if (!$courseId) {
    displayError('course_subscription_error_no_course_id');
}

// Получение курса
$stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ? AND tenant_id = ?");
$stmt->execute([$courseId, Tenant::getId()]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    displayError('course_subscription_error_course_not_found');
}

// Получение пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND tenant_id = ?");
$stmt->execute([$userId, Tenant::getId()]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    displayError('course_subscription_error_user_not_found');
}

// Если подтверждение не получено, показываем страницу подтверждения
if (!$confirm) {
    // Проверка, есть ли уже подписка
    $stmt = $pdo->prepare("SELECT * FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
    $stmt->execute([$userId, $courseId, Tenant::getId()]);
    if ($stmt->rowCount() > 0) {
        displayError('course_subscription_error_already_subscribed');
    }

    // Если курс бесплатный - сразу оформляем подписку
    if ($course['price'] == 0) {
        $pdo->beginTransaction();
        try {
            // Free (price = 0) course: no payment to collect, so this self-service
            // subscribe grants active access directly — no transaction is created,
            // since no real payment happened. Paid courses are not sold through this
            // page (see the price check above); those go through the admin
            // enrollment + "Выдать доступ" flow (php/payment_service.php).
            $stmt = $pdo->prepare("
                INSERT INTO course_subs (user_id, course_id, price, purchase_date, access_expires_at, status, tenant_id)
                VALUES (?, ?, 0, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', ?)
            ");
            $stmt->execute([
                $userId,
                $courseId,
                (int)$course['subscription_days'],
                Tenant::getId()
            ]);
            $pdo->commit();
            
            // Перенаправляем в профиль
            header('Location: /profile');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            displayError('course_subscription_error_processing_failed');
        }
    }

    // Для платных курсов покупка с баланса отключена в MVP.
    // Перенаправляем на список курсов.
    header('Location: /classes/courses/index.php?class=' . urlencode($user['class'] ?? ''));
    exit;
}

// Запрещаем списание с баланса при прямом POST
header('Location: /classes/courses/index.php?class=' . urlencode($user['class'] ?? ''));
exit;

function showConfirmationPage($course, $user) {
    $price = (int)$course['price'];
    $balance = (float)$user['balance'];
    $remainingBalance = $balance - $price;
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="course_subscription_confirmation_title">Obunani tasdiqlash</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="course_css/confirm.css">
</head>

<body>
    <?php require_once '../../php/navbar.php' ?>

    <main class="main-content">
        <div class="confirmation-container">
            <div class="confirmation-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <h1 class="confirmation-title" data-i18n="course_subscription_confirmation_title">Obunani tasdiqlash</h1>
            
            <div class="confirmation-details">
                <div class="detail-row">
                    <span class="detail-label" data-i18n="course_subscription_course_name">Kurs nomi:</span>
                    <span class="detail-value course-title"><?= htmlspecialchars($course['title']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label" data-i18n="course_subscription_price">Narxi:</span>
                    <span class="detail-value price-value"><?= number_format($price, 0, ',', ' ') ?> so'm</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label" data-i18n="course_subscription_current_balance">Joriy balans:</span>
                    <span class="detail-value balance-value"><?= number_format($balance, 0, ',', ' ') ?> so'm</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label" data-i18n="course_subscription_remaining_balance">Qolgan balans:</span>
                    <span class="detail-value remaining-balance-value"><?= number_format($remainingBalance, 0, ',', ' ') ?> so'm</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label" data-i18n="course_subscription_duration">Obuna muddati:</span>
                    <span class="detail-value"><?= (int)$course['subscription_days'] ?> kun</span>
                </div>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="confirm" value="yes">
                <div class="buttons-container">
                    <button type="submit" class="confirm-button" data-i18n="course_subscription_confirm_button">Tasdiqlash</button>
                    <a href="/classes/courses/index.php?class=<?= $user['class'] ?>" class="cancel-button" data-i18n="course_subscription_cancel_button">Bekor qilish</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Обработчик переключения темы
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;

        // Проверка сохраненной темы
        const savedTheme = localStorage.getItem('theme') || 'dark';
        htmlElement.setAttribute('data-theme', savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    </script>
</body>

</html>
<?php
}

function showSuccessPage($course) {
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="course_subscription_success_title">Подписка оформлена</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="course_css/success.css">
</head>

<body>
    <!-- Навигация -->
    <?php require_once '../../php/navbar.php' ?>

    <main class="main-content">
        <div class="message-container">
            <div class="message-icon success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="message-title success-title" data-i18n="course_subscription_success_title">Obuna rasmiylashtirildi!</h1>
            <p class="message-text">
                <span data-i18n="course_subscription_success_text">Siz kursni muvaffaqiyatli xarid qildingiz:</span> <span class="course-title"><?= htmlspecialchars($course['title']) ?></span>
            </p>
            <a href="/profile" class="action-button" data-i18n="course_subscription_go_profile">Profilga o‘tish</a>
        </div>
    </main>
</body>

</html>
<?php
}

function displayError($messageKey) {
    // Map message keys to actual messages
    $messages = [
        'course_subscription_error_no_course_id' => 'ID курса не указан.',
        'course_subscription_error_course_not_found' => 'Курс не найден.',
        'course_subscription_error_user_not_found' => 'Пользователь не найден.',
        'course_subscription_error_already_subscribed' => 'Вы уже подписаны на этот курс.',
        'course_subscription_error_insufficient_balance' => 'Недостаточно средств на балансе.',
        'course_subscription_error_processing_failed' => 'Произошла ошибка при оформлении подписки.',
        'course_subscription_error_title' => 'Ошибка при оформлении'
    ];
    
    $message = $messages[$messageKey] ?? $messageKey;
?>
    <!DOCTYPE html>
    <html lang="ru" data-theme="dark">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title data-i18n="course_subscription_error_title">Xatolik!</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Baloo+2:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="stylesheet" href="course_css/error.css">
    </head>

    <body>
        <?php require_once '../../php/navbar.php' ?>

        <main class="main-content">
            <div class="message-container">
                <div class="message-icon error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h1 class="message-title error-title" data-i18n="course_subscription_error_title">Rasmiylashtirishda xatolik</h1>
                <p class="message-text">
                    <span class="error-message" data-i18n="<?= htmlspecialchars($messageKey) ?>"><?= htmlspecialchars($message) ?></span>
                </p>
                <a href="/profile" class="action-button" data-i18n="course_subscription_go_profile">Profilga o‘tish</a>
            </div>
        </main>
    </body>

    </html>
<?php
    exit;
}
?>