<?php
require_once __DIR__ . '/../authorization.php';
require_admin_role(['center_admin', 'super_admin']);
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php'; // For translation functions

$pageTitle = __('payments_title');
$tid = Tenant::getId();


// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_payment') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        $payment_method = in_array($_POST['payment_method'] ?? '', ['cash', 'terminal', 'online']) ? $_POST['payment_method'] : 'cash';
        $comment = trim($_POST['comment'] ?? '');

        if ($user_id <= 0) {
            $_SESSION['payment_error'] = "Необходимо выбрать студента.";
            header("Location: /admin/payments/");
            exit;
        }

        if ($course_id <= 0) {
            $_SESSION['payment_error'] = "Необходимо выбрать курс для платежа.";
            header("Location: /admin/payments/");
            exit;
        }

        // Tenant isolation: verify student and course belong to current tenant
        $uStmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND tenant_id = ?");
        $uStmt->execute([$user_id, $tid]);
        $studentData = $uStmt->fetch(PDO::FETCH_ASSOC);

        $cStmt = $pdo->prepare("SELECT course_id, price, subscription_days FROM courses WHERE course_id = ? AND tenant_id = ?");
        $cStmt->execute([$course_id, $tid]);
        $courseData = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$studentData) {
            $_SESSION['payment_error'] = "Студент не найден в текущем учебном центре.";
            header("Location: /admin/payments/");
            exit;
        }

        if (!$courseData) {
            $_SESSION['payment_error'] = "Курс не найден в текущем учебном центре.";
            header("Location: /admin/payments/");
            exit;
        }

        // Strictly validate subscription_days from DB (no silent 30-day fallback)
        $days = (int)($courseData['subscription_days'] ?? 0);
        if ($days <= 0) {
            $_SESSION['payment_error'] = "У курса не указан срок доступа (subscription_days = 0/NULL). Укажите срок подписки в настройках курса.";
            header("Location: /admin/payments/");
            exit;
        }

        // Amount is strictly verified and taken from DB course price
        $amount = (float)$courseData['price'];

        $adminName = 'admin';
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id=?");
            $stmt->execute([$_SESSION['user_id']]);
            if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $adminName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                if (empty($adminName)) $adminName = 'admin';
            }
        }

        if ($amount == 0) {
            // FREE / TRIAL COURSE: no financial record, grant access directly (status
            // 'active', no transaction — matches classes/subscriptions/course_subs.php's
            // free-course self-subscribe: a $0 course has nothing to record financially).
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT subscription_id FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $course_id, $tid]);
                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("UPDATE course_subs SET status = 'active', access_expires_at = DATE_ADD(GREATEST(COALESCE(access_expires_at, NOW()), NOW()), INTERVAL ? DAY), purchase_date = NOW() WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
                    $stmt->execute([$days, $user_id, $course_id, $tid]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO course_subs (user_id, course_id, price, purchase_date, access_expires_at, status, tenant_id) VALUES (?, ?, 0, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', ?)");
                    $stmt->execute([$user_id, $course_id, $days, $tid]);
                }
                $pdo->commit();
                $_SESSION['payment_msg'] = __('data_updated');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("add_payment (free) error: " . $e->getMessage());
                $_SESSION['payment_error'] = "Ошибка записи: " . $e->getMessage();
            }
        } elseif ($payment_method === 'online') {
            // Online payment: pending status, access NOT granted until later confirmation
            // (admin/payments/view.php's reconciled accept flow).
            try {
                $stmt = $pdo->prepare("INSERT INTO payments (tenant_id, user_id, course_id, amount, payment_method, status, issued_by, comment, payment_date) VALUES (?, ?, ?, ?, 'online', 'pending', ?, ?, NOW())");
                $stmt->execute([$tid, $user_id, $course_id, $amount, $adminName, $comment]);
                $_SESSION['payment_msg'] = __('data_updated');
            } catch (Exception $e) {
                error_log("add_payment (online) error: " . $e->getMessage());
                $_SESSION['payment_error'] = "Ошибка записи: " . $e->getMessage();
            }
        } else {
            // Cash / Terminal: admin is recording a payment that grants access immediately.
            // Reconciled (P0-4) through the same PaymentService state machine as "Выдать
            // доступ"/"Продлить курс" instead of a raw access_expires_at UPDATE.
            require_once __DIR__ . '/../../php/payment_service.php';

            $subStmt = $pdo->prepare("SELECT subscription_id, status FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
            $subStmt->execute([$user_id, $course_id, $tid]);
            $sub = $subStmt->fetch(PDO::FETCH_ASSOC);
            $method = PaymentService::mapLegacyPaymentMethod($payment_method);
            $today = date('Y-m-d');

            if ($sub && $sub['status'] === 'active') {
                $result = PaymentService::renew($pdo, $tid, (int)$sub['subscription_id'], [
                    'amount' => $amount, 'payment_method' => $method, 'payment_date' => $today, 'notes' => $comment,
                ], $adminName);
            } else {
                $nextPaymentDate = trim($_POST['next_payment_date'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextPaymentDate)) {
                    $_SESSION['payment_error'] = "Укажите дату следующего платежа для первого платежа по подписке.";
                    header("Location: /admin/payments/");
                    exit;
                }
                $subscriptionId = $sub['subscription_id'] ?? null;
                if (!$subscriptionId) {
                    $ins = $pdo->prepare("INSERT INTO course_subs (user_id, course_id, price, purchase_date, access_expires_at, next_payment_date, status, tenant_id) VALUES (?, ?, ?, NOW(), NULL, NULL, 'pending', ?)");
                    $ins->execute([$user_id, $course_id, $amount, $tid]);
                    $subscriptionId = (int)$pdo->lastInsertId();
                }
                $result = PaymentService::grantFirstAccess($pdo, $tid, (int)$subscriptionId, [
                    'amount' => $amount, 'payment_method' => $method, 'payment_date' => $today,
                    'next_payment_date' => $nextPaymentDate, 'notes' => $comment,
                ], $adminName);
            }

            if (!$result['success']) {
                $_SESSION['payment_error'] = "Ошибка выдачи доступа: " . implode(' ', $result['errors']);
                header("Location: /admin/payments/");
                exit;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO payments (tenant_id, user_id, course_id, amount, payment_method, status, issued_by, comment, payment_date) VALUES (?, ?, ?, ?, ?, 'accepted', ?, ?, NOW())");
                $stmt->execute([$tid, $user_id, $course_id, $amount, $payment_method, $adminName, $comment]);
                $_SESSION['payment_msg'] = __('data_updated');
            } catch (Exception $e) {
                error_log("add_payment (cash/terminal) legacy record error: " . $e->getMessage());
                // Access was already correctly granted above; only the legacy payments
                // record failed to insert — non-fatal, just note it.
                $_SESSION['payment_msg'] = __('data_updated') . " (примечание: запись в истории платежей не создана)";
            }
        }
        header("Location: /admin/payments/");
        exit;
    }

    if ($action === 'confirm' || $action === 'mark_paid') {
        // Not currently wired to any button in this page's UI (the list routes to
        // admin/payments/view.php for review instead) — reconciled anyway (P0-4) so a
        // direct POST to this action can never silently grant/extend access without a
        // real transaction. First-payment cases (no extra input available here) are
        // deferred to admin/payments/view.php, which has the next_payment_date field.
        require_once __DIR__ . '/../../php/payment_service.php';
        $payment_id = (int)$_POST['payment_id'];

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND tenant_id = ? AND status != 'accepted'");
        $stmt->execute([$payment_id, $tid]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment) {
            header("Location: /admin/payments/");
            exit;
        }

        if (empty($payment['course_id'])) {
            $upd = $pdo->prepare("UPDATE payments SET status = 'accepted' WHERE payment_id = ? AND tenant_id = ? AND status != 'accepted'");
            $upd->execute([$payment_id, $tid]);
            $_SESSION['payment_msg'] = "Платёж #{$payment_id} подтверждён.";
            header("Location: /admin/payments/");
            exit;
        }

        $subStmt = $pdo->prepare("SELECT subscription_id, status FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
        $subStmt->execute([$payment['user_id'], $payment['course_id'], $tid]);
        $sub = $subStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub || $sub['status'] !== 'active') {
            // First payment: needs an explicit next_payment_date this action has no form
            // field for — send the admin to the page that does.
            $_SESSION['payment_error'] = "Это первый платёж по подписке — укажите дату следующего платежа на странице платежа.";
            header("Location: /admin/payments/view.php?id=" . $payment_id);
            exit;
        }

        $upd = $pdo->prepare("UPDATE payments SET status = 'accepted' WHERE payment_id = ? AND tenant_id = ? AND status != 'accepted'");
        $upd->execute([$payment_id, $tid]);
        if ($upd->rowCount() === 0) {
            $_SESSION['payment_error'] = "Платёж уже был подтверждён.";
            header("Location: /admin/payments/");
            exit;
        }

        $method = PaymentService::mapLegacyPaymentMethod($payment['payment_method'] ?? null);
        $paymentDateOnly = date('Y-m-d', strtotime($payment['payment_date']));
        $result = PaymentService::renew($pdo, $tid, (int)$sub['subscription_id'], [
            'amount' => $payment['amount'], 'payment_method' => $method, 'payment_date' => $paymentDateOnly,
            'source_payment_id' => $payment_id,
        ], $_SESSION['admin_username'] ?? 'admin');

        if (!$result['success']) {
            $revert = $pdo->prepare("UPDATE payments SET status = ? WHERE payment_id = ? AND tenant_id = ?");
            $revert->execute([$payment['status'], $payment_id, $tid]);
            $_SESSION['payment_error'] = "Ошибка подтверждения: " . implode(' ', $result['errors']);
            header("Location: /admin/payments/");
            exit;
        }

        $_SESSION['payment_msg'] = "Платёж #{$payment_id} подтверждён, доступ к курсу продлён.";
        header("Location: /admin/payments/");
        exit;
    }

    if ($action === 'reject') {
        $payment_id = (int)$_POST['payment_id'];
        $stmt = $pdo->prepare("UPDATE payments SET status='rejected' WHERE payment_id=? AND tenant_id=?");
        $stmt->execute([$payment_id, $tid]);
        $_SESSION['payment_msg'] = "Платёж #{$payment_id} отклонён.";
        header("Location: /admin/payments/");
        exit;
    }
}


// Filters
$filterPeriod = $_GET['period'] ?? 'all';
$filterStatus = $_GET['status'] ?? '';
$filterCourse = $_GET['course_id'] ?? '';
$filterClass = $_GET['classLevel'] ?? '';
$filterStudent = trim($_GET['student'] ?? '');
$filterVisibility = $_GET['visibility'] ?? 'active';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = "WHERE p.tenant_id = :tid";
$params = [':tid' => $tid];

// Soft delete visibility filter
if ($filterVisibility === 'hidden') {
    $where .= " AND p.deleted_at IS NOT NULL";
} elseif ($filterVisibility === 'all') {
    // show both active and hidden
} else {
    $filterVisibility = 'active';
    $where .= " AND p.deleted_at IS NULL";
}

if ($filterPeriod === 'this_month') {
    $where .= " AND p.payment_date >= DATE_FORMAT(NOW(), '%Y-%m-01')";
}

if ($filterStatus !== '') {
    $where .= " AND p.status = :status";
    $params[':status'] = $filterStatus;
}

if ($filterCourse !== '') {
    $where .= " AND p.course_id = :course_id";
    $params[':course_id'] = $filterCourse;
}

if ($filterClass !== '') {
    $where .= " AND u.class = :classLevel";
    $params[':classLevel'] = $filterClass;
}

if ($filterStudent !== '') {
    $where .= " AND (p.user_id = :uid OR u.first_name LIKE :like1 OR u.last_name LIKE :like2 OR u.phone LIKE :like3)";
    $like = "%{$filterStudent}%";
    $params[':uid'] = $filterStudent;
    $params[':like1'] = $like;
    $params[':like2'] = $like;
    $params[':like3'] = $like;
}

// Count
$r = $pdo->prepare("SELECT COUNT(*) FROM payments p LEFT JOIN users u ON p.user_id = u.user_id AND u.tenant_id = p.tenant_id {$where}");
$r->execute($params);
$total = (int)$r->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Fetch
$r = $pdo->prepare("
    SELECT p.*, u.first_name, u.last_name, u.phone, u.class as student_class, c.title as course_title
    FROM payments p
    LEFT JOIN users u ON p.user_id = u.user_id AND u.tenant_id = p.tenant_id
    LEFT JOIN courses c ON p.course_id = c.course_id AND c.tenant_id = p.tenant_id
    {$where}
    ORDER BY p.payment_date DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$r->execute($params);
$payments = $r->fetchAll(PDO::FETCH_ASSOC);

// Fetch all courses for filter & modal
$cstmt = $pdo->prepare("SELECT course_id, title, price, subscription_days FROM courses WHERE tenant_id = ? ORDER BY title ASC");
$cstmt->execute([$tid]);
$allCourses = $cstmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all students for modal
$ustmt = $pdo->prepare("SELECT user_id, first_name, last_name, phone FROM users WHERE tenant_id = ? ORDER BY first_name ASC");
$ustmt->execute([$tid]);
$allStudents = $ustmt->fetchAll(PDO::FETCH_ASSOC);


// Stats (strictly excluding hidden/soft-deleted payments)
$statsRaw = $pdo->prepare("
    SELECT status, COUNT(*) as cnt, COALESCE(SUM(amount),0) as sum_amt
    FROM payments
    WHERE tenant_id = ? AND deleted_at IS NULL
    GROUP BY status
");
$statsRaw->execute([$tid]);
$stats = [
    'pending' => ['count' => 0, 'sum' => 0],
    'accepted' => ['count' => 0, 'sum' => 0],
    'rejected' => ['count' => 0, 'sum' => 0],
    'unpaid' => ['count' => 0, 'sum' => 0],
];
$totalPaymentsCount = 0;
$totalPaymentsSum = 0;
foreach ($statsRaw->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $st = strtolower(trim((string)$row['status']));
    $cnt = (int)$row['cnt'];
    $sum = (float)$row['sum_amt'];
    $totalPaymentsCount += $cnt;
    $totalPaymentsSum += $sum;

    if ($st === 'accepted' || $st === 'approved') {
        $stats['accepted']['count'] += $cnt;
        $stats['accepted']['sum'] += $sum;
    } elseif ($st === 'rejected' || $st === 'refunded') {
        $stats['rejected']['count'] += $cnt;
        $stats['rejected']['sum'] += $sum;
    } elseif ($st === 'pending') {
        $stats['pending']['count'] += $cnt;
        $stats['pending']['sum'] += $sum;
    } elseif ($st === 'unpaid') {
        $stats['unpaid']['count'] += $cnt;
        $stats['unpaid']['sum'] += $sum;
    } else {
        $stats['pending']['count'] += $cnt;
        $stats['pending']['sum'] += $sum;
    }
}

$monthStat = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0) 
    FROM payments 
    WHERE tenant_id = ? AND status IN ('accepted', 'approved') AND deleted_at IS NULL AND payment_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
");
$monthStat->execute([$tid]);
$monthIncome = $monthStat->fetchColumn();

$current_lang = get_admin_lang();

$paymentMsg = $_SESSION['payment_msg'] ?? '';
$paymentError = $_SESSION['payment_error'] ?? '';
unset($_SESSION['payment_msg'], $_SESSION['payment_error']);

// Format helper for payment counts
$fmtPaymentsCount = function($cnt) use ($current_lang) {
    if ($current_lang === 'ru') {
        $n10 = $cnt % 10;
        $n100 = $cnt % 100;
        if ($n10 === 1 && $n100 !== 11) {
            return "$cnt платеж";
        } elseif ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
            return "$cnt платежа";
        } else {
            return "$cnt платежей";
        }
    } elseif ($current_lang === 'uz') {
        return "$cnt ta to'lov";
    } else {
        return "$cnt " . ($cnt === 1 ? 'payment' : 'payments');
    }
};

// Include header
include __DIR__ . '/../includes/header.php';
?>

<style>
.payments-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}
.payments-title h1 {
    margin: 0 0 4px;
    font-size: 24px;
    font-weight: 600;
}
.payments-title p {
    margin: 0;
    color: var(--gray-500);
    font-size: 14px;
}
.kpi-cards-row {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.kpi-card-new {
    flex: 1;
    min-width: 200px;
    background: #fff;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    display: flex;
    align-items: flex-start;
    gap: 16px;
}
.kpi-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.kpi-icon-green { background: #e0f2fe; color: #0284c7; }
.kpi-icon-yellow { background: #fef3c7; color: #d97706; }
.kpi-icon-blue { background: #e0e7ff; color: #4f46e5; }
.kpi-icon-red { background: #fee2e2; color: #dc2626; }
.kpi-icon-gray { background: #f3f4f6; color: #4b5563; }

.kpi-card-content { flex: 1; }
.kpi-card-label {
    font-size: 13px;
    color: var(--gray-500);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.kpi-card-value {
    font-size: 20px;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 4px;
}
.kpi-card-subtext {
    font-size: 12px;
    color: var(--gray-400);
}

.filter-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.filter-bar select, .filter-bar input {
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
}
.filter-bar .search-wrapper {
    flex: 1;
    min-width: 200px;
    position: relative;
}
.filter-bar .search-wrapper input {
    width: 100%;
    padding-left: 36px;
}
.filter-bar .search-wrapper::before {
    content: '🔍';
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
    font-size: 14px;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.student-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--gray-600);
    font-size: 12px;
    overflow: hidden;
}
.student-avatar img { width: 100%; height: 100%; object-fit: cover; }
.student-details { display: flex; flex-direction: column; }
.student-name { font-weight: 600; color: var(--gray-900); }
.student-class { font-size: 11px; color: var(--blue-600); background: var(--blue-100); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top:2px; width: fit-content; }

.pm-icon {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.table-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>

<div class="payments-header">
    <div class="payments-title">
        <h1><?= htmlspecialchars(__('payments_title')) ?></h1>
        <p><?= htmlspecialchars(__('payments_desc')) ?></p>
    </div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('addPaymentModal').style.display='flex'">+ <?= htmlspecialchars(__('new_payment')) ?></button>
</div>

<?php if ($paymentMsg): ?>
    <div style="background:#ecfdf5;border:1px solid #10b981;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;font-weight:500;">
        <?= htmlspecialchars($paymentMsg) ?>
    </div>
<?php endif; ?>
<?php if ($paymentError): ?>
    <div style="background:#fef2f2;border:1px solid #ef4444;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;font-weight:500;">
        <?= htmlspecialchars($paymentError) ?>
    </div>
<?php endif; ?>

<div class="kpi-layout" style="display:flex; gap:20px; margin-bottom:24px; flex-wrap:wrap;">
    <!-- Главная карточка: Доход -->
    <div class="kpi-main-card" style="flex: 1; min-width: 250px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: var(--radius-lg); padding: 24px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);">
        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 8px;"><?= htmlspecialchars(__('total_income_stat')) ?></div>
        <div style="font-size: 32px; font-weight: 700;"><?= number_format($stats['accepted']['sum'], 0, '.', ' ') ?> сум</div>
        <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">из всех одобренных платежей</div>
    </div>

    <!-- Сетка мини-статистики -->
    <div class="kpi-stats-grid" style="flex: 2; min-width: 300px; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 16px;">
        <div class="stat-mini" style="background: #fff; padding: 16px; border-radius: var(--radius-lg); border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: flex-start; min-height: 105px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; height: 32px; margin-bottom: 10px;">
                <span style="font-size: 13px; color: var(--gray-500); font-weight: 500; line-height: 1.25;"><?= htmlspecialchars(__('total_payments_stat')) ?></span>
                <svg style="flex-shrink: 0; margin-top: 1px;" width="15" height="15" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            </div>
            <div>
                <div style="font-size: 16px; font-weight: 700; color: var(--gray-900); white-space: nowrap; line-height: 1.2;"><?= number_format($totalPaymentsSum, 0, '.', ' ') ?> сум</div>
                <div style="font-size: 11px; color: var(--gray-400); margin-top: 4px; line-height: 1.2;"><?= $fmtPaymentsCount($totalPaymentsCount) ?></div>
            </div>
        </div>
        <div class="stat-mini" style="background: #fff; padding: 16px; border-radius: var(--radius-lg); border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: flex-start; min-height: 105px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; height: 32px; margin-bottom: 10px;">
                <span style="font-size: 13px; color: var(--gray-500); font-weight: 500; line-height: 1.25;"><?= htmlspecialchars(__('pending')) ?></span>
                <svg style="flex-shrink: 0; margin-top: 1px;" width="15" height="15" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <div style="font-size: 16px; font-weight: 700; color: #d97706; white-space: nowrap; line-height: 1.2;"><?= number_format($stats['pending']['sum'], 0, '.', ' ') ?> сум</div>
                <div style="font-size: 11px; color: var(--gray-400); margin-top: 4px; line-height: 1.2;"><?= $fmtPaymentsCount($stats['pending']['count']) ?></div>
            </div>
        </div>
        <div class="stat-mini" style="background: #fff; padding: 16px; border-radius: var(--radius-lg); border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: flex-start; min-height: 105px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; height: 32px; margin-bottom: 10px;">
                <span style="font-size: 13px; color: var(--gray-500); font-weight: 500; line-height: 1.25;"><?= htmlspecialchars(__('approved')) ?></span>
                <svg style="flex-shrink: 0; margin-top: 1px;" width="15" height="15" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <div style="font-size: 16px; font-weight: 700; color: #059669; white-space: nowrap; line-height: 1.2;"><?= number_format($stats['accepted']['sum'], 0, '.', ' ') ?> сум</div>
                <div style="font-size: 11px; color: var(--gray-400); margin-top: 4px; line-height: 1.2;"><?= $fmtPaymentsCount($stats['accepted']['count']) ?></div>
            </div>
        </div>
        <div class="stat-mini" style="background: #fff; padding: 16px; border-radius: var(--radius-lg); border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: flex-start; min-height: 105px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; height: 32px; margin-bottom: 10px;">
                <span style="font-size: 13px; color: var(--gray-500); font-weight: 500; line-height: 1.25;"><?= htmlspecialchars(__('rejected')) ?></span>
                <svg style="flex-shrink: 0; margin-top: 1px;" width="15" height="15" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <div style="font-size: 16px; font-weight: 700; color: #dc2626; white-space: nowrap; line-height: 1.2;"><?= number_format($stats['rejected']['sum'], 0, '.', ' ') ?> сум</div>
                <div style="font-size: 11px; color: var(--gray-400); margin-top: 4px; line-height: 1.2;"><?= $fmtPaymentsCount($stats['rejected']['count']) ?></div>
            </div>
        </div>
    </div>
</div>

<style>
.show-filters { display: flex !important; }
.btn-icon { padding: 8px !important; display: inline-flex; align-items: center; justify-content: center; }
</style>

<form method="get" style="margin-bottom: 24px;">
    <div style="display: flex; gap: 10px; align-items: center; width: 100%;">
        <!-- Поле поиска -->
        <div style="flex: 1; position: relative; display: flex; align-items: center;">
            <svg style="position: absolute; left: 14px; color: #94a3b8; pointer-events: none;" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" name="student" placeholder="<?= htmlspecialchars(__('search_student')) ?>" value="<?= htmlspecialchars($filterStudent) ?>" style="width: 100%; height: 40px; padding: 8px 14px 8px 38px; border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; font-size: 14px; color: #0f172a; outline: none; box-shadow: none;">
        </div>
        
        <!-- Фильтр периода -->
        <div style="position: relative; display: inline-flex; align-items: center; flex-shrink: 0;">
            <select name="period" onchange="this.form.submit()" style="height: 40px; padding: 8px 32px 8px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; font-size: 14px; font-weight: 500; color: #0f172a; appearance: none; -webkit-appearance: none; outline: none; box-shadow: none; cursor: pointer; white-space: nowrap;">
                <option value="all"><?= $current_lang === 'en' ? 'All time' : ($current_lang === 'uz' ? 'Barcha vaqt' : 'Все время') ?></option>
                <option value="this_month" <?= $filterPeriod === 'this_month' ? 'selected' : '' ?>><?= htmlspecialchars(__('this_month')) ?></option>
            </select>
            <svg style="position: absolute; right: 12px; pointer-events: none; color: #64748b;" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        
        <!-- Кнопка «Фильтр» -->
        <button type="button" onclick="document.getElementById('advancedFilters').classList.toggle('show-filters')" style="height: 40px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; font-size: 14px; font-weight: 500; color: #0f172a; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; outline: none; box-shadow: none; white-space: nowrap; flex-shrink: 0;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
            </svg>
            <?= htmlspecialchars(__('filter_btn')) ?>
        </button>
        
        <button type="submit" style="display:none;" id="applyFiltersBtn">Применить</button>
        
        <!-- Кнопка «Экспорт» -->
        <button type="button" style="height: 40px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; font-size: 14px; font-weight: 500; color: #0f172a; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; outline: none; box-shadow: none; white-space: nowrap; flex-shrink: 0;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            <?= htmlspecialchars(__('export')) ?>
        </button>
    </div>

    <!-- Дополнительные фильтры -->
    <div id="advancedFilters" class="" style="display: none; gap: 12px; margin-top: 12px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; flex-wrap: wrap; align-items: center;">
        <select name="visibility" style="padding:10px; border:1px solid #d1d5db; border-radius:8px; background:#fff;">
            <option value="active" <?= $filterVisibility === 'active' ? 'selected' : '' ?>>Все активные</option>
            <option value="hidden" <?= $filterVisibility === 'hidden' ? 'selected' : '' ?>>Скрытые</option>
            <option value="all" <?= $filterVisibility === 'all' ? 'selected' : '' ?>>Все</option>
        </select>

        <select name="status" style="padding:10px; border:1px solid #d1d5db; border-radius:8px; background:#fff;">
            <option value=""><?= htmlspecialchars(__('all_statuses')) ?></option>
            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>><?= htmlspecialchars(__('pending')) ?></option>
            <option value="accepted" <?= $filterStatus === 'accepted' ? 'selected' : '' ?>><?= htmlspecialchars(__('approved')) ?></option>
            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>><?= htmlspecialchars(__('rejected')) ?></option>
            <option value="unpaid" <?= $filterStatus === 'unpaid' ? 'selected' : '' ?>><?= htmlspecialchars(__('unpaid')) ?></option>
        </select>
        
        <select name="course_id" style="padding:10px; border:1px solid #d1d5db; border-radius:8px; background:#fff;">
            <option value=""><?= htmlspecialchars(__('all_courses')) ?></option>
            <?php foreach ($allCourses as $c): ?>
                <option value="<?= $c['course_id'] ?>" <?= $filterCourse == $c['course_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <select name="classLevel" style="padding:10px; border:1px solid #d1d5db; border-radius:8px; background:#fff;">
            <option value=""><?= htmlspecialchars(__('all_classes')) ?></option>
            <?php for($i=1; $i<=11; $i++): ?>
                <option value="<?=$i?>" <?= $filterClass == $i ? 'selected' : '' ?>><?=$i?> класс</option>
            <?php endfor; ?>
        </select>
        
        <button type="submit" class="btn btn-primary">Применить фильтры</button>
        <?php if ($filterStatus || $filterCourse || $filterClass || $filterStudent || $filterPeriod !== 'all' || $filterVisibility !== 'active'): ?>
            <a href="/admin/payments/" class="btn btn-outline">Сбросить</a>
        <?php endif; ?>
    </div>
</form>

<style>
tr.row-pending {
    background-color: #fffdf0 !important;
    border-left: 3px solid #f59e0b !important;
}
tr.row-pending td {
    background-color: #fffdf0 !important;
    transition: background-color 0.15s ease;
}
tr.row-pending:hover td,
tr.row-pending:hover {
    background-color: #fef3c7 !important;
}
tr.row-default {
    transition: background-color 0.15s ease;
    border-left: 3px solid transparent;
}
tr.row-default:hover td {
    background-color: var(--gray-50);
}
</style>

<div class="card" style="padding:0">
    <div class="table-responsive">
        <table style="margin:0;width:100%;border-collapse:collapse">
            <thead>
                <tr style="border-bottom:1px solid #f3f4f6">
                    <th style="padding:16px;text-align:left;color:var(--gray-500);font-weight:500;font-size:13px;width:250px;"><?= htmlspecialchars(__('student')) ?></th>
                    <th style="padding:16px;text-align:left;color:var(--gray-500);font-weight:500;font-size:13px"><?= htmlspecialchars(__('course')) ?></th>
                    <th style="padding:16px;text-align:left;color:var(--gray-500);font-weight:500;font-size:13px">Сумма и Оплата</th>
                    <th style="padding:16px;text-align:left;color:var(--gray-500);font-weight:500;font-size:13px"><?= htmlspecialchars(__('status')) ?></th>
                    <th style="padding:16px;text-align:left;color:var(--gray-500);font-weight:500;font-size:13px">Дата и Оформил</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($payments)): ?>
                <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-500);">Нет платежей</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $p):
                    $sc = match($p['status'] ?? '') {
                        'accepted' => 'badge-green',
                        'rejected' => 'badge-red',
                        'pending' => 'badge-yellow',
                        'unpaid' => 'badge-yellow',
                        default => 'badge-gray'
                    };
                    $statusText = match($p['status'] ?? '') {
                        'accepted' => __('approved'),
                        'rejected' => __('rejected'),
                        'pending' => __('pending'),
                        'unpaid' => __('unpaid'),
                        default => $p['status']
                    };
                    
                    $methodText = match($p['payment_method'] ?? '') {
                        'cash' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg> ' . __('cash'),
                        'terminal' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> ' . __('terminal'),
                        'online' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> ' . __('online'),
                        default => '—'
                    };
                    
                    $initials = mb_substr($p['first_name'] ?? '?', 0, 1) . mb_substr($p['last_name'] ?? '?', 0, 1);
                    $courseTitle = $p['course_title'] ? htmlspecialchars($p['course_title']) : '<span style="color:var(--gray-400);font-size:13px;">—</span>';
                    $isPending = in_array($p['status'] ?? '', ['pending', 'unpaid']);
                    $isItemDeleted = !empty($p['deleted_at']);
                ?>
                    <tr onclick="window.location.href='/admin/payments/view.php?id=<?= $p['payment_id'] ?>'" class="<?= $isPending ? 'row-pending' : 'row-default' ?>" style="border-bottom:1px solid #f3f4f6; cursor: pointer; <?= $isItemDeleted ? 'opacity: 0.75;' : '' ?>">
                        <td style="padding:16px">
                            <div class="student-info">
                                <div class="student-avatar"><?= htmlspecialchars($initials) ?></div>
                                <div class="student-details">
                                    <span class="student-name"><?= htmlspecialchars(($p['first_name'] ?? '?') . ' ' . ($p['last_name'] ?? '')) ?></span>
                                    <?php if ($p['student_class']): ?>
                                        <span class="student-class"><?= htmlspecialchars($p['student_class']) ?> класс</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="padding:16px;font-size:14px;font-weight:500"><?= $courseTitle ?></td>
                        <td style="padding:16px;">
                            <div style="font-size:15px; font-weight:600; color:var(--gray-900);"><?= number_format($p['amount'], 0, '.', ' ') ?> сум</div>
                            <div style="font-size:12px; color:var(--gray-500); display:flex; align-items:center; gap:4px; margin-top:4px;">
                                <?= $methodText ?>
                            </div>
                        </td>
                        <td style="padding:16px">
                            <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                <span class="badge <?= $sc ?>" style="white-space:nowrap; <?= $isPending ? 'box-shadow: 0 0 0 1px #fde68a;' : '' ?>">
                                    <?= htmlspecialchars($statusText) ?>
                                </span>
                                <?php if ($isItemDeleted): ?>
                                    <span class="badge badge-gray" style="white-space:nowrap; background:#f1f5f9; color:#64748b; font-size:11px;">Скрыт</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding:16px;">
                            <div style="font-size:14px; font-weight:500; color:var(--gray-900);"><?= date('d.m.Y H:i', strtotime($p['payment_date'])) ?></div>
                            <div style="font-size:12px; color:var(--gray-500); margin-top:4px;"><?= htmlspecialchars(__('issued_by')) ?>: <span style="font-weight:500;color:var(--gray-700);"><?= htmlspecialchars($p['issued_by'] ?? '—') ?></span></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
</tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination" style="padding:16px;border-top:1px solid #f3f4f6;margin:0;display:flex;justify-content:center">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>&student=<?= urlencode($filterStudent) ?>&course_id=<?= urlencode($filterCourse) ?>&classLevel=<?= urlencode($filterClass) ?>&period=<?= urlencode($filterPeriod) ?>&visibility=<?= urlencode($filterVisibility) ?>">&laquo;</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 3);
            $end = min($totalPages, $page + 3);
            for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&student=<?= urlencode($filterStudent) ?>&course_id=<?= urlencode($filterCourse) ?>&classLevel=<?= urlencode($filterClass) ?>&period=<?= urlencode($filterPeriod) ?>&visibility=<?= urlencode($filterVisibility) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>&student=<?= urlencode($filterStudent) ?>&course_id=<?= urlencode($filterCourse) ?>&classLevel=<?= urlencode($filterClass) ?>&period=<?= urlencode($filterPeriod) ?>&visibility=<?= urlencode($filterVisibility) ?>">&raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>


<!-- Modal -->
<div id="addPaymentModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="card" style="width:100%;max-width:520px;margin:20px;max-height:90vh;overflow-y:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h3 style="margin:0;font-size:18px;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars(__('enroll_student')) ?></h3>
            <button type="button" onclick="document.getElementById('addPaymentModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gray-400);font-size:22px;line-height:1;padding:0 4px;">&times;</button>
        </div>
        <form method="post" id="enrollModalForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_payment">
            
            <div class="form-group" style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-size:14px;color:var(--gray-600);font-weight:500;"><?= htmlspecialchars(__('student')) ?> *</label>
                <select name="user_id" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                    <option value="" disabled selected><?= htmlspecialchars(__('choose_student')) ?></option>
                    <?php foreach ($allStudents as $stu): ?>
                        <option value="<?= $stu['user_id'] ?>"><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name'] . ' (' . $stu['phone'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-size:14px;color:var(--gray-600);font-weight:500;"><?= htmlspecialchars(__('course')) ?> *</label>
                <select name="course_id" id="modal_course_id" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                    <option value="" disabled selected>-- <?= htmlspecialchars(__('select_course') ?? 'Выберите курс') ?> --</option>
                    <?php foreach ($allCourses as $c): ?>
                        <option value="<?= $c['course_id'] ?>" data-price="<?= (float)$c['price'] ?>" data-days="<?= (int)($c['subscription_days'] ?: 30) ?>">
                            <?= htmlspecialchars($c['title']) ?> (<?= number_format($c['price'], 0, '.', ' ') ?> сум)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row" style="display:flex;gap:16px;margin-bottom:16px;">
                <div class="form-group" style="flex:1;">
                    <label style="display:block;margin-bottom:6px;font-size:14px;color:var(--gray-600);font-weight:500;"><?= htmlspecialchars(__('amount')) ?> (сум) *</label>
                    <input type="number" id="modal_amount" name="amount" min="0" step="any" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                </div>
                <div class="form-group" style="flex:1;">
                    <label style="display:block;margin-bottom:6px;font-size:14px;color:var(--gray-600);font-weight:500;"><?= htmlspecialchars(__('payment_method')) ?> *</label>
                    <select name="payment_method" id="modal_payment_method" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                        <option value="cash"><?= htmlspecialchars(__('cash')) ?></option>
                        <option value="terminal"><?= htmlspecialchars(__('terminal')) ?></option>
                        <option value="online"><?= htmlspecialchars(__('online')) ?></option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-size:14px;color:var(--gray-600);font-weight:500;"><?= htmlspecialchars(__('course_subscription_duration')) ?> (<?= htmlspecialchars(__('days')) ?>) *</label>
                <input type="number" id="modal_days" name="days" min="1" step="1" value="30" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
            </div>

            <div class="form-group" id="modal_next_payment_group" style="margin-bottom:16px; display:none;">
                <label style="display:block;margin-bottom:6px;font-size:14px;color:var(--gray-600);font-weight:500;">Дата следующего платежа *</label>
                <input type="date" id="modal_next_payment_date" name="next_payment_date" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                <div style="font-size:12px;color:var(--gray-500);margin-top:4px;">Для первого платежа наличными/картой — дата не рассчитывается автоматически.</div>
            </div>

            <div class="form-group" style="margin-bottom:24px;">
                <label style="display:block;margin-bottom:6px;font-size:14px;color:var(--gray-600);font-weight:500;"><?= htmlspecialchars(__('comment')) ?></label>
                <textarea name="comment" rows="2" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;" placeholder="Необязательный комментарий"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addPaymentModal').style.display='none'"><?= htmlspecialchars(__('cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars(__('enroll_student')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const courseSelect = document.getElementById('modal_course_id');
    const amountInput = document.getElementById('modal_amount');
    const daysInput = document.getElementById('modal_days');
    const methodSelect = document.getElementById('modal_payment_method');
    const npdGroup = document.getElementById('modal_next_payment_group');
    const npdInput = document.getElementById('modal_next_payment_date');

    if (courseSelect && amountInput && daysInput) {
        courseSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (opt) {
                const price = opt.getAttribute('data-price');
                const days = opt.getAttribute('data-days');
                if (price !== null && price !== undefined) {
                    amountInput.value = price;
                }
                if (days !== null && days !== undefined && parseInt(days) > 0) {
                    daysInput.value = days;
                } else {
                    daysInput.value = 30;
                }
            }
            toggleNextPaymentDate();
        });
    }

    // The next_payment_date field only matters for an immediate cash/terminal payment
    // on a non-free course (the case that reconciles through PaymentService::grantFirstAccess
    // for a first payment) — online payments and free courses don't need it.
    function toggleNextPaymentDate() {
        if (!methodSelect || !npdGroup || !npdInput) return;
        const amount = parseFloat(amountInput && amountInput.value ? amountInput.value : '0');
        const needsIt = amount > 0 && methodSelect.value !== 'online';
        npdGroup.style.display = needsIt ? 'block' : 'none';
        if (needsIt) {
            npdInput.setAttribute('required', 'required');
        } else {
            npdInput.removeAttribute('required');
        }
    }
    if (methodSelect) methodSelect.addEventListener('change', toggleNextPaymentDate);
    if (amountInput) amountInput.addEventListener('input', toggleNextPaymentDate);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
