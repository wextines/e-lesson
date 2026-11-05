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
$stmtUser = $pdo->prepare("SELECT user_id, phone, class, balance, first_name, last_name, points, slug, email FROM users WHERE user_id = ? AND tenant_id = ?");
$stmtUser->execute([$user_id, $tenant_id]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

// Centers that take payment at the desk opt out of online receipts entirely
// (admin/settings/). Default is off: a center only gets the online flow by asking for it.
$tenant = Tenant::get();
$allowUploads = Tenant::getConfig('allow_receipt_uploads', '0') === '1';

// Handle Receipt Upload (POST)
$feedbackMsg = '';
$feedbackType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_receipt' && !$allowUploads) {
    // Hiding the form is presentation; this is the actual gate. Without it the endpoint
    // would still accept uploads from anyone who kept an old page open or crafted a POST.
    header("Location: /profile/payments.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_receipt') {
    validateCsrfToken();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $amount = (int)($_POST['amount'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    $maxFileSize = 10 * 1024 * 1024; // 10 MB limit
    $allowedMimeMap = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'pdf'  => ['application/pdf'],
        'webp' => ['image/webp'],
    ];

    if ($courseId <= 0 || $amount <= 0) {
        $feedbackMsg = 'Пожалуйста, выберите курс и укажите сумму оплаты.';
        $feedbackType = 'error';
    } elseif (empty($_FILES['receipt']['name']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['receipt']['tmp_name'])) {
        $feedbackMsg = 'Пожалуйста, прикрепите файл квитанции или чека.';
        $feedbackType = 'error';
    } elseif ($_FILES['receipt']['size'] > $maxFileSize || $_FILES['receipt']['size'] <= 0) {
        $feedbackMsg = 'Файл слишком большой. Максимальный размер: 10 МБ.';
        $feedbackType = 'error';
    } else {
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));

        if (!array_key_exists($ext, $allowedMimeMap)) {
            $feedbackMsg = 'Недопустимый формат файла. Разрешены только JPG, PNG, PDF и WebP.';
            $feedbackType = 'error';
        } else {
            // Real MIME type validation via finfo_file()
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = finfo_file($finfo, $_FILES['receipt']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = $allowedMimeMap[$ext];
            if (!$realMime || !in_array($realMime, $allowedMimes, true)) {
                $feedbackMsg = 'Содержимое файла не соответствует расширению или файл повреждён.';
                $feedbackType = 'error';
            } else {
                $uploadDir = __DIR__ . '/../storage/tenants/' . $tenant_id . '/receipts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $filename = 'receipt_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $targetFile = $uploadDir . $filename;

                if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $targetFile)) {
                    $feedbackMsg = 'Не удалось сохранить файл на сервере.';
                    $feedbackType = 'error';
                } else {
                    @chmod($targetFile, 0666);
                    $receiptPath = 'receipts/' . $filename;

                    try {
                        $stmtInsPay = $pdo->prepare("INSERT INTO payments
                            (tenant_id, user_id, course_id, amount, payment_method, payment_date, status, issued_by, receipt_path, comment)
                            VALUES (?, ?, ?, ?, 'online', NOW(), 'pending', 'student', ?, ?)");
                        $stmtInsPay->execute([$tenant_id, $user_id, $courseId, $amount, $receiptPath, $comment]);
                        // The student-facing home for this flow is now the "Подписки и оплаты"
                        // tab in profile/settings.php; this endpoint stays as the POST target
                        // (and keeps rendering on GET) only for backward/E2E compatibility.
                        header('Location: /profile/settings.php?tab=payments&success=1');
                        exit();
                    } catch (Exception $e) {
                        $feedbackMsg = 'Ошибка при сохранении платежа: ' . $e->getMessage();
                        $feedbackType = 'error';
                    }
                }
            }
        }
    }
}

// 2. Fetch active course access
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

// 3. Fetch payment history
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
$payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch all tenant courses for payment dropdown
$stmtAllCourses = $pdo->prepare("SELECT course_id, title, price FROM courses WHERE tenant_id = ? AND is_active = 1 ORDER BY title ASC");
$stmtAllCourses->execute([$tenant_id]);
$allCourses = $stmtAllCourses->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$totalPaid = 0;
$pendingCount = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'accepted') {
        $totalPaid += (int)$p['amount'];
    } elseif ($p['status'] === 'pending') {
        $pendingCount++;
    }
}

$months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
$courseColors = ['#1B5EE8', '#059669', '#7C3AED', '#D97706', '#2563EB', '#0D9488'];

$pageTitle = 'Оплаты и доступ';
$activeTab = 'payments';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Column -->
<div class="flex-1 flex flex-col min-w-0 h-full">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto px-4 sm:px-6 pt-4 sm:pt-6 pb-16 lg:pb-6">
        <div class="space-y-6 max-w-5xl mx-auto">
            
            <!-- Page Header & Metrics -->
            <div>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#0D1B2A] leading-tight mb-1">
                        Оплаты
                    </h2>
                    <p class="text-sm text-[#7A8899]">
                        Активный доступ к курсам и история платежей.
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mt-4">
                    <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 shadow-sm">
                        <p class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">Активных курсов</p>
                        <p class="mt-2 text-2xl sm:text-3xl font-bold text-[#0D1B2A] font-[var(--font-mono)] tabular-nums leading-none"><?= count($subscriptions) ?></p>
                    </div>
                    <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 shadow-sm">
                        <p class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">Всего оплачено</p>
                        <p class="mt-2 text-xl sm:text-2xl font-bold text-[#0D1B2A] font-[var(--font-mono)] tabular-nums leading-none"><?= number_format($totalPaid, 0, '', ' ') ?> сум</p>
                    </div>
                    <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 shadow-sm col-span-2 sm:col-span-1">
                        <p class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">На проверке</p>
                        <p class="mt-2 text-2xl sm:text-3xl font-bold <?= $pendingCount > 0 ? 'text-[#D97706]' : 'text-[#0D1B2A]' ?> font-[var(--font-mono)] tabular-nums leading-none"><?= $pendingCount ?></p>
                    </div>
                </div>
            </div>

            <!-- Feedback Alert -->
            <?php if (!empty($feedbackMsg)): ?>
                <div class="p-4 rounded-xl text-sm font-medium border <?= $feedbackType === 'success' ? 'bg-[#D1FAE5] text-[#059669] border-[#A7F3D0]' : 'bg-[#FEE2E2] text-[#DC2626] border-[#FECACA]' ?>">
                    <?= htmlspecialchars($feedbackMsg) ?>
                </div>
            <?php endif; ?>

            <!-- Pending Alert Banner if waiting for confirmation -->
            <?php if ($pendingCount > 0): ?>
                <div class="flex items-start gap-3 p-4 border border-[#FDE68A] bg-[#FFFDF7] rounded-xl shadow-sm">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[#D97706] shrink-0 mt-0.5">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-[#0D1B2A]">Есть платёж на проверке</p>
                        <p class="text-xs sm:text-sm text-[#7A8899] mt-0.5">
                            Администратор проверяет квитанцию. Доступ к курсу будет активирован сразу после подтверждения.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Section 1: Активный доступ к курсам -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">Активный доступ</h2>
                    <?php if ($allowUploads): ?>
                        <button onclick="openPaymentModal()" class="text-xs sm:text-sm text-[#1B5EE8] font-medium hover:underline flex items-center gap-1">
                            + Отправить квитанцию
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (!$allowUploads): ?>
                    <div class="mb-3 flex items-start gap-3 rounded-xl border border-[#E4E1DA] bg-[#F7F5F1] px-4 py-3">
                        <span class="text-[#1B5EE8] text-base leading-none mt-0.5">&#9432;</span>
                        <p class="text-xs sm:text-sm text-[#4A607A] leading-relaxed m-0">
                            Оплата обучения принимается у администратора учебного центра.
                            <?php if (!empty($tenant['phone'])): ?>
                                Телефон: <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', (string)$tenant['phone'])) ?>" class="text-[#1B5EE8] font-medium hover:underline"><?= htmlspecialchars($tenant['phone']) ?></a>.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] px-4 sm:px-5 py-0.5 shadow-sm">
                    <?php if (empty($subscriptions)): ?>
                        <div class="py-12 text-center text-sm text-[#7A8899]">
                            У вас пока нет активных подписок.
                        </div>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $idx => $s): 
                            $isLast = ($idx === count($subscriptions) - 1);
                            $color = $courseColors[$idx % count($courseColors)];
                            $hasRealAccess = ($s['status'] ?? '') === 'active' && !empty($s['access_expires_at']);
                            $daysLeft = $hasRealAccess ? (int)$s['days_left'] : 0;

                            $expDate = '';
                            if (!empty($s['access_expires_at'])) {
                                $ts = strtotime($s['access_expires_at']);
                                $expDate = date('j', $ts) . ' ' . ($months[(int)date('n', $ts) - 1] ?? '') . ' ' . date('Y', $ts);
                            }
                        ?>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3 sm:py-3.5 <?= !$isLast ? 'border-b border-[#F0EDE8]' : '' ?>">
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="w-1.5 h-8 rounded-full shrink-0" style="background-color: <?= htmlspecialchars($color) ?>;"></div>
                                    <div class="min-w-0">
                                        <p class="text-sm sm:text-base font-semibold text-[#0D1B2A] leading-snug truncate">
                                            <?= htmlspecialchars($s['title']) ?>
                                        </p>
                                        <p class="text-xs text-[#7A8899] mt-0.5">
                                            <?= htmlspecialchars($s['subject'] ?: 'Курс') ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 shrink-0 self-end sm:self-center">
                                    <span class="text-xs sm:text-sm text-[#7A8899] font-mono">
                                        до <?= htmlspecialchars($expDate) ?>
                                    </span>
                                    <span class="text-xs font-medium px-2.5 py-1 rounded-md font-mono <?= !$hasRealAccess ? 'bg-[#F0EDE8] text-[#7A8899]' : ($daysLeft > 14 ? 'bg-[#D1FAE5] text-[#059669]' : ($daysLeft >= 0 ? 'bg-[#FDE68A] text-[#D97706]' : 'bg-[#FEE2E2] text-[#DC2626]')) ?>">
                                        <?= !$hasRealAccess ? 'Ожидает оплаты' : ($daysLeft > 0 ? 'Ещё ' . $daysLeft . ' дн.' : ($daysLeft === 0 ? 'Истекает сегодня' : 'Истёк')) ?>
                                    </span>
                                    <?php if ($allowUploads): ?>
                                        <button onclick="openPaymentModal(<?= $s['course_id'] ?>, '<?= htmlspecialchars(addslashes($s['title'])) ?>', <?= (int)$s['price'] ?>)"
                                                class="text-xs sm:text-sm text-[#1B5EE8] font-medium hover:underline">
                                            Продлить
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section 2: История платежей -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">История платежей</h2>
                </div>

                <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] px-4 sm:px-5 py-0.5 shadow-sm">
                    <?php if (empty($payments)): ?>
                        <div class="py-12 text-center text-sm text-[#7A8899]">
                            История платежей пуста.
                        </div>
                    <?php else: ?>
                        <?php foreach ($payments as $idx => $p): 
                            $isLast = ($idx === count($payments) - 1);
                            $pDate = $p['payment_date'] ? strtotime($p['payment_date']) : time();
                            $dateStr = date('j', $pDate) . ' ' . ($months[(int)date('n', $pDate) - 1] ?? '') . ' ' . date('Y', $pDate);
                            
                            $statusStyle = 'bg-[#F0EDE8] text-[#7A8899]';
                            $statusLabel = 'В обработке';
                            if ($p['status'] === 'accepted') {
                                $statusStyle = 'bg-[#D1FAE5] text-[#059669]';
                                $statusLabel = 'Оплачен';
                            } elseif ($p['status'] === 'rejected') {
                                $statusStyle = 'bg-[#FEE2E2] text-[#DC2626]';
                                $statusLabel = 'Отклонён';
                            }
                        ?>
                            <div class="flex items-center gap-3 py-3 sm:py-3.5 <?= !$isLast ? 'border-b border-[#F0EDE8]' : '' ?>">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm sm:text-base font-semibold text-[#0D1B2A] truncate">
                                        <?= htmlspecialchars($p['course_title'] ?: 'Оплата за курс') ?>
                                    </p>
                                    <p class="text-xs sm:text-sm text-[#7A8899] mt-0.5">
                                        <?= $dateStr ?> · <?= htmlspecialchars($p['payment_method'] === 'cash' ? 'Наличные в кассе' : 'Онлайн / Перевод') ?>
                                    </p>
                                </div>

                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-sm sm:text-base font-semibold font-[var(--font-mono)] text-[#0D1B2A]">
                                        <?= number_format((int)$p['amount'], 0, '', ' ') ?> сум
                                    </span>
                                    <span class="text-xs font-medium px-2.5 py-1 rounded-md <?= $statusStyle ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                    <?php if (!empty($p['receipt_path'])): ?>
                                        <a href="/file.php?type=receipt&id=<?= $p['payment_id'] ?>" target="_blank" class="text-xs sm:text-sm text-[#1B5EE8] hover:underline" title="Посмотреть квитанцию">
                                            Чек ↗
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="h-2"></div>
        </div>
    </main>

<?php if ($allowUploads): ?>
<!-- Modal: Отправить квитанцию / Оплатить курс -->
<div id="payment-modal" class="fixed inset-0 bg-black/30 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E4E1DA]">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-[#0D1B2A]">Продление / Оплата курса</h3>
            <button onclick="closePaymentModal()" class="text-[#7A8899] hover:text-[#0D1B2A] text-lg font-mono">✕</button>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="upload_receipt">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            
            <div>
                <label class="block text-xs font-medium text-[#4A607A] mb-1">Выберите курс</label>
                <select name="course_id" id="modal-course-id" required class="w-full px-3 py-2 text-sm bg-[#F7F5F1] border border-[#E4E1DA] rounded-lg focus:outline-none focus:border-[#1B5EE8]">
                    <option value="">-- Выберите курс --</option>
                    <?php foreach ($allCourses as $ac): ?>
                        <option value="<?= $ac['course_id'] ?>" data-price="<?= (int)$ac['price'] ?>">
                            <?= htmlspecialchars($ac['title']) ?> (<?= number_format((int)$ac['price'], 0, '', ' ') ?> сум)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-[#4A607A] mb-1">Сумма оплаты (сум)</label>
                <input type="number" name="amount" id="modal-amount" required placeholder="Например: 250000" class="w-full px-3 py-2 text-sm bg-[#F7F5F1] border border-[#E4E1DA] rounded-lg focus:outline-none focus:border-[#1B5EE8]">
            </div>

            <div>
                <label class="block text-xs font-medium text-[#4A607A] mb-1">Фото квитанции или чека (JPG, PNG, PDF)</label>
                <input type="file" name="receipt" accept="image/*,.pdf" required class="w-full text-xs text-[#7A8899] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-[#EEF3FD] file:text-[#1B5EE8] hover:file:bg-[#E0ECFD]">
            </div>

            <div>
                <label class="block text-xs font-medium text-[#4A607A] mb-1">Комментарий (необязательно)</label>
                <input type="text" name="comment" placeholder="Номер транзакции или ФИО плательщика" class="w-full px-3 py-2 text-sm bg-[#F7F5F1] border border-[#E4E1DA] rounded-lg focus:outline-none focus:border-[#1B5EE8]">
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closePaymentModal()" class="px-4 py-2 text-xs font-medium text-[#4A607A] bg-[#FAFAF8] border border-[#E4E1DA] rounded-lg hover:bg-[#F0EDE8]">
                    Отмена
                </button>
                <button type="submit" class="px-5 py-2 text-xs font-medium text-white bg-[#1B5EE8] rounded-lg hover:bg-[#1650CC]">
                    Отправить квитанцию
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openPaymentModal(courseId, courseTitle, price) {
    const modal = document.getElementById('payment-modal');
    if (!modal) return;
    if (courseId) {
        const select = document.getElementById('modal-course-id');
        if (select) select.value = courseId;
        const amountInput = document.getElementById('modal-amount');
        if (amountInput && price) amountInput.value = price;
    }
    modal.classList.remove('hidden');
}

function closePaymentModal() {
    const modal = document.getElementById('payment-modal');
    if (modal) modal.classList.add('hidden');
}

document.getElementById('modal-course-id')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const price = selected?.getAttribute('data-price');
    if (price) {
        document.getElementById('modal-amount').value = price;
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
