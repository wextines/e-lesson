<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../../php/payment_service.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();
$userId = (int)($_GET['user_id'] ?? 0);

if (!$userId) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['extend_course'])) {
        $courseId = (int)$_POST['course_id'];
        $days = $_POST['days'] ?? '30';
        $exactDate = trim($_POST['exact_date'] ?? '');

        if ($days === 'exact' && $exactDate) {
            $newExpire = $exactDate . ' 23:59:59';
        } else {
            $numDays = max(1, (int)$days);
            // Fetch current expiry
            $stmt = $pdo->prepare("SELECT access_expires_at FROM course_subs WHERE user_id = :uid AND course_id = :cid AND tenant_id = :tid");
            $stmt->execute(['uid' => $userId, 'cid' => $courseId, 'tid' => $tid]);
            $current = $stmt->fetchColumn();

            $baseTime = ($current && strtotime($current) > time()) ? strtotime($current) : time();
            $newExpire = date('Y-m-d H:i:s', strtotime("+{$numDays} days", $baseTime));
        }

        $stmt = $pdo->prepare("UPDATE course_subs SET access_expires_at = :exp WHERE user_id = :uid AND course_id = :cid AND tenant_id = :tid");
        $stmt->execute(['exp' => $newExpire, 'uid' => $userId, 'cid' => $courseId, 'tid' => $tid]);
        $success = __('access_extended');
    }

    if (isset($_POST['update_user'])) {
        $fields = [
            'first_name = :first_name',
            'last_name = :last_name',
            'phone = :phone',
            'email = :email',
            'class = :class',
            'points = :points'
        ];
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'class' => trim($_POST['class'] ?? ''),
            'points' => (int)($_POST['points'] ?? 0),
        ];

        if (!empty($_POST['password'])) {
            $fields[] = 'password = :password';
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = :user_id AND tenant_id = :tenant_id";
        $stmt = $pdo->prepare($sql);
        $data['user_id'] = $userId;
        $data['tenant_id'] = $tid;
        $stmt->execute($data);
        $success = __('data_updated');
    }

    if (isset($_POST['update_purchase_date'])) {
        $courseId = (int)$_POST['course_id'];
        $newDate = trim($_POST['purchase_date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            $stmt = $pdo->prepare("UPDATE course_subs SET purchase_date = CONCAT(:new_date, ' ', TIME(purchase_date)) WHERE user_id = :uid AND course_id = :cid AND tenant_id = :tid");
            $stmt->execute(['new_date' => $newDate, 'uid' => $userId, 'cid' => $courseId, 'tid' => $tid]);
            $success = __('data_updated');
        }
    }

    if (isset($_POST['update_payment'])) {
        // Reconciled (P0-4): course-linked acceptance now goes through the same
        // PaymentService state machine as "Выдать доступ"/"Продлить курс" instead of a
        // raw access_expires_at UPDATE. A first payment (no active subscription yet)
        // needs an explicit next_payment_date this button has no field for — that case
        // is deferred to admin/payments/view.php, which has it.
        require_once __DIR__ . '/../../php/payment_service.php';
        $paymentId = (int)$_POST['payment_id'];
        $status = $_POST['status'] ?? '';
        if ($status === 'accepted') {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND tenant_id = ? AND status != 'accepted'");
            $stmt->execute([$paymentId, $tid]);
            $pRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pRow) {
                $error = "Платёж уже был подтверждён.";
            } elseif (empty($pRow['course_id'])) {
                $pdo->prepare("UPDATE payments SET status = 'accepted' WHERE payment_id = ? AND tenant_id = ? AND status != 'accepted'")
                    ->execute([$paymentId, $tid]);
                $success = "Платеж #$paymentId подтвержден.";
            } else {
                $subStmt = $pdo->prepare("SELECT subscription_id, status FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
                $subStmt->execute([$pRow['user_id'], $pRow['course_id'], $tid]);
                $sub = $subStmt->fetch(PDO::FETCH_ASSOC);

                if (!$sub || $sub['status'] !== 'active') {
                    header('Location: /admin/payments/view.php?id=' . $paymentId);
                    exit;
                }

                $upd = $pdo->prepare("UPDATE payments SET status = 'accepted' WHERE payment_id = ? AND tenant_id = ? AND status != 'accepted'");
                $upd->execute([$paymentId, $tid]);
                if ($upd->rowCount() === 0) {
                    $error = "Платёж уже был подтверждён.";
                } else {
                    $method = PaymentService::mapLegacyPaymentMethod($pRow['payment_method'] ?? null);
                    $paymentDateOnly = date('Y-m-d', strtotime($pRow['payment_date']));
                    $result = PaymentService::renew($pdo, $tid, (int)$sub['subscription_id'], [
                        'amount' => $pRow['amount'], 'payment_method' => $method, 'payment_date' => $paymentDateOnly,
                        'source_payment_id' => $paymentId,
                    ], $_SESSION['admin_username'] ?? 'admin');

                    if ($result['success']) {
                        $success = "Платеж #$paymentId подтвержден, доступ к курсу продлён.";
                    } else {
                        $pdo->prepare("UPDATE payments SET status = ? WHERE payment_id = ? AND tenant_id = ?")
                            ->execute([$pRow['status'], $paymentId, $tid]);
                        $error = "Ошибка подтверждения платежа: " . implode(' ', $result['errors']);
                    }
                }
            }
        } elseif ($status === 'rejected') {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'rejected' WHERE payment_id = :payment_id AND tenant_id = :tenant_id");
            $stmt->execute(['payment_id' => $paymentId, 'tenant_id' => $tid]);
            $success = "Платеж #$paymentId отклонён";
        }
    }

    if (isset($_POST['grant_access'])) {
        // "Выдать доступ" — first payment on a pending subscription (php/payment_service.php).
        // Creates exactly one transaction and activates access; never touches the legacy
        // `payments` table.
        $subscriptionId = (int)($_POST['subscription_id'] ?? 0);
        $adminUsername = $_SESSION['admin_username'] ?? 'admin';
        $result = PaymentService::grantFirstAccess($pdo, $tid, $subscriptionId, $_POST, $adminUsername);
        $success = $result['success'] ? 'Доступ выдан.' : '';
        $error = $result['success'] ? '' : implode(' ', $result['errors']);
    }

    if (isset($_POST['renew_subscription'])) {
        // "Продлить курс" — a subsequent payment on an active subscription
        // (php/payment_service.php). Advances next_payment_date from its current
        // planned value, never from today.
        $subscriptionId = (int)($_POST['subscription_id'] ?? 0);
        $adminUsername = $_SESSION['admin_username'] ?? 'admin';
        $result = PaymentService::renew($pdo, $tid, $subscriptionId, $_POST, $adminUsername);
        $success = $result['success'] ? 'Подписка продлена.' : '';
        $error = $result['success'] ? '' : implode(' ', $result['errors']);
    }

    if (isset($_POST['assign_group'])) {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $groupId = (int)($_POST['group_id'] ?? 0);
        if ($groupId > 0 && $courseId > 0) {
            // The JOIN re-checks tenant_id and course_id server-side, so a crafted group_id
            // for another course (or another tenant) silently matches zero rows instead of
            // cross-linking the subscription to the wrong group.
            $upd = $pdo->prepare("
                UPDATE course_subs cs
                JOIN study_groups sg ON sg.group_id = ? AND sg.tenant_id = cs.tenant_id AND sg.course_id = cs.course_id
                SET cs.group_id = sg.group_id
                WHERE cs.user_id = ? AND cs.course_id = ? AND cs.tenant_id = ?
            ");
            $upd->execute([$groupId, $userId, $courseId, $tid]);
            $success = $upd->rowCount() > 0 ? 'Группа назначена.' : '';
            $error = $upd->rowCount() > 0 ? '' : 'Не удалось назначить группу.';
        }
    }

    if (isset($_POST['toggle_active'])) {
        $newStatus = $_POST['new_status'] === '0' ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = :is_active WHERE user_id = :user_id AND tenant_id = :tenant_id");
        $stmt->execute(['is_active' => $newStatus, 'user_id' => $userId, 'tenant_id' => $tid]);
        $success = $newStatus ? "Ученик активирован" : "Ученик деактивирован";
    }

    $_SESSION['student_msg'] = $success ?: $error;
    header('Location: view.php?user_id=' . $userId);
    exit;
}

$success = $_SESSION['student_msg'] ?? '';
unset($_SESSION['student_msg']);

// Fetch student (Tenant isolated)
$r = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND tenant_id = ?");
$r->execute([$userId, $tid]);
$student = $r->fetch(PDO::FETCH_ASSOC);
if (!$student) { header('Location: index.php'); exit; }

$pageTitle = htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));

// All related data (Tenant isolated)
$r = $pdo->prepare("
    SELECT cs.*, c.title AS course_title, c.subject, c.subscription_days, sg.title AS group_title
    FROM course_subs cs
    JOIN courses c ON cs.course_id = c.course_id AND c.tenant_id = cs.tenant_id
    LEFT JOIN study_groups sg ON sg.group_id = cs.group_id AND sg.tenant_id = cs.tenant_id
    WHERE cs.user_id = ? AND cs.tenant_id = ?
    ORDER BY cs.purchase_date DESC
");
$r->execute([$userId, $tid]);
$courses = $r->fetchAll(PDO::FETCH_ASSOC);

// Active groups per course, for the "assign a group" quick-select on an unassigned subscription.
$r = $pdo->prepare("SELECT group_id, course_id, title FROM study_groups WHERE tenant_id = ? AND is_active = 1 ORDER BY title ASC");
$r->execute([$tid]);
$groupsByCourse = [];
foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $sg) {
    $groupsByCourse[(int)$sg['course_id']][] = $sg;
}

$r = $pdo->prepare("
    SELECT tr.*, 
           CASE WHEN tr.test_type='daily' THEN dt.title WHEN tr.test_type='weekly' THEN wt.title END AS test_title,
           CASE WHEN tr.test_type='daily' THEN dt.max_score WHEN tr.test_type='weekly' THEN wt.max_score END AS max_score
    FROM test_results tr 
    LEFT JOIN daily_tests dt ON tr.test_type='daily' AND tr.test_id = dt.daily_test_id AND dt.tenant_id = tr.tenant_id 
    LEFT JOIN weekly_tests wt ON tr.test_type='weekly' AND tr.test_id = wt.weekly_test_id AND wt.tenant_id = tr.tenant_id 
    WHERE tr.slug = ? AND tr.tenant_id = ? 
    ORDER BY tr.completed_at DESC
");
$r->execute([$student['slug'], $tid]); 
$testResults = $r->fetchAll(PDO::FETCH_ASSOC);

$totalPoints = 0;
$totalMaxScore = 0;
foreach($testResults as $tr) {
    $totalPoints += (int)$tr['get_points'];
    $totalMaxScore += (int)($tr['max_score'] ?? 100);
}

$r = $pdo->prepare("
    SELECT p.*, c.title AS course_title 
    FROM payments p 
    LEFT JOIN courses c ON p.course_id = c.course_id AND c.tenant_id = p.tenant_id 
    WHERE p.user_id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL 
    ORDER BY p.payment_date DESC
");
$r->execute([$userId, $tid]);
$payments = $r->fetchAll(PDO::FETCH_ASSOC);

$r = $pdo->prepare("
    SELECT t.transaction_id, t.amount, t.payment_date, t.payment_method, t.notes, t.created_at, t.created_by,
           c.title AS course_title
    FROM transactions t
    JOIN course_subs cs ON t.course_sub_id = cs.subscription_id AND cs.tenant_id = t.tenant_id
    JOIN courses c ON cs.course_id = c.course_id AND c.tenant_id = t.tenant_id
    WHERE t.user_id = ? AND t.tenant_id = ?
    ORDER BY t.created_at DESC
");
$r->execute([$userId, $tid]);
$transactions = $r->fetchAll(PDO::FETCH_ASSOC);

function getStudentInitials($firstName, $lastName) {
    $f = mb_substr(trim((string)$firstName), 0, 1, 'UTF-8');
    $l = mb_substr(trim((string)$lastName), 0, 1, 'UTF-8');
    $in = mb_strtoupper($f . $l, 'UTF-8');
    return $in ?: 'ST';
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.tab-profile-btn {
    border: none !important;
    border-bottom: 2px solid transparent !important;
    background: transparent !important;
    cursor: pointer;
    outline: none !important;
    padding: 0 0 12px 0 !important;
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    white-space: nowrap;
    transition: color 0.15s ease, border-color 0.15s ease;
    margin-bottom: -1px;
}
.tab-profile-btn:hover {
    color: #0f172a;
}
.tab-profile-btn.active {
    border-bottom-color: #2563eb !important;
    color: #2563eb !important;
    font-weight: 600;
}
.contact-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px 0;
    border-bottom: 1px solid #f1f5f9;
}
@media (min-width: 640px) {
    .contact-row {
        flex-direction: row;
        align-items: center;
    }
}
.contact-row:last-child {
    border-bottom: none;
}
.btn-deactivate {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #fecaca !important;
    background-color: #ffffff !important;
    color: #ef4444 !important;
    border-radius: 12px !important;
    padding: 7px 18px !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    cursor: pointer;
    box-shadow: none !important;
    outline: none !important;
    transition: all 0.15s ease;
}
.btn-deactivate:hover {
    background-color: #fef2f2 !important;
    border-color: #fca5a5 !important;
}
.btn-activate {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0 !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-radius: 12px !important;
    padding: 7px 18px !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    cursor: pointer;
    box-shadow: none !important;
    outline: none !important;
    transition: all 0.15s ease;
}
.btn-activate:hover {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
}
.btn-edit-contact {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #e2e8f0 !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-radius: 12px !important;
    padding: 7px 18px !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    cursor: pointer;
    box-shadow: none !important;
    outline: none !important;
    transition: all 0.15s ease;
}
.btn-edit-contact:hover {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
}
</style>

<div class="mx-auto flex max-w-7xl flex-col gap-6">

    <!-- Back link -->
    <div>
        <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 no-underline transition-colors hover:text-slate-900">
            <i data-lucide="arrow-left" class="size-4"></i>
            <?= htmlspecialchars(__('back_to_list')) ?>
        </a>
    </div>

    <!-- Student Header (Avatar + Name + Status + ID + Action button) -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-blue-50 text-base font-bold text-blue-600">
                <?= getStudentInitials($student['first_name'] ?? '', $student['last_name'] ?? '') ?>
            </div>
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 m-0">
                    <?= htmlspecialchars($student['first_name'] ?? '') ?> <?= htmlspecialchars($student['last_name'] ?? '') ?>
                </h1>
                <div class="flex items-center gap-2.5 text-sm">
                    <?php if ($student['is_active']): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            <?= htmlspecialchars(__('active')) ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                            <span class="size-1.5 rounded-full bg-slate-400"></span>
                            <?= htmlspecialchars(__('inactive')) ?>
                        </span>
                    <?php endif; ?>
                    <span class="font-mono text-slate-400">ID <?= $student['user_id'] ?></span>
                </div>
            </div>
        </div>

        <div>
            <form method="post" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="toggle_active" value="1">
                <input type="hidden" name="new_status" value="<?= $student['is_active'] ? '0' : '1' ?>">
                <?php if ($student['is_active']): ?>
                    <button type="submit" class="btn-deactivate" onclick="return confirm('<?= htmlspecialchars(__('deactivate')) ?>?')">
                        <?= htmlspecialchars(__('deactivate')) ?>
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn-activate" onclick="return confirm('<?= htmlspecialchars(__('activate')) ?>?')">
                        <?= htmlspecialchars(__('activate')) ?>
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($success): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Summary stats (3 KPI cards) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <!-- Overall Score -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('overall_score')) ?></p>
            <p class="mt-2 text-2xl font-bold tabular-nums">
                <span class="text-slate-900"><?= $totalPoints ?></span><span class="text-slate-400">/<?= $totalMaxScore ?: 100 ?></span>
            </p>
        </div>
        <!-- Active Courses -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('active_courses')) ?></p>
            <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums"><?= count(array_filter($courses, fn($c) => ($c['status'] ?? '') === 'active')) ?></p>
        </div>
        <!-- Tests Taken -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('tests_taken')) ?></p>
            <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums"><?= count($testResults) ?></p>
        </div>
    </div>

    <!-- Tabs Bar -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Разделы профиля">
            <button type="button" onclick="switchProfileTab('info')" id="tab-btn-info" class="tab-profile-btn active">
                <?= htmlspecialchars(__('info_tab')) ?>
            </button>
            <button type="button" onclick="switchProfileTab('courses')" id="tab-btn-courses" class="tab-profile-btn">
                <?= htmlspecialchars(__('courses_tab')) ?>
            </button>
            <button type="button" onclick="switchProfileTab('tests')" id="tab-btn-tests" class="tab-profile-btn">
                <?= htmlspecialchars(__('tests_tab')) ?>
            </button>
            <button type="button" onclick="switchProfileTab('payments')" id="tab-btn-payments" class="tab-profile-btn">
                <?= htmlspecialchars(__('payments_tab')) ?>
            </button>
        </nav>
    </div>

    <!-- Tab 1: Information -->
    <div id="tab-content-info" class="tab-profile-content">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]" id="info-view-panel">
            <div class="flex items-center justify-between pb-2">
                <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars(__('contact_data')) ?></h2>
                <button type="button" onclick="toggleEditInfo(true)" class="btn-edit-contact">
                    <i data-lucide="pencil" class="size-3.5"></i>
                    <?= htmlspecialchars(__('edit')) ?>
                </button>
            </div>
            <dl class="mt-2">
                <div class="contact-row">
                    <dt class="text-sm font-semibold text-slate-900 sm:w-48"><?= htmlspecialchars(__('name')) ?></dt>
                    <dd class="text-sm text-slate-500"><?= htmlspecialchars($student['first_name'] ?? '') ?></dd>
                </div>
                <div class="contact-row">
                    <dt class="text-sm font-semibold text-slate-900 sm:w-48"><?= htmlspecialchars(__('surname')) ?></dt>
                    <dd class="text-sm text-slate-500"><?= htmlspecialchars($student['last_name'] ?? '') ?></dd>
                </div>
                <div class="contact-row">
                    <dt class="text-sm font-semibold text-slate-900 sm:w-48"><?= htmlspecialchars(__('phone')) ?></dt>
                    <dd class="text-sm text-slate-500"><?= htmlspecialchars($student['phone'] ?? '') ?></dd>
                </div>
                <div class="contact-row">
                    <dt class="text-sm font-semibold text-slate-900 sm:w-48">Email</dt>
                    <dd class="text-sm text-slate-500"><?= htmlspecialchars($student['email'] ?? '') ?></dd>
                </div>
                <div class="contact-row">
                    <dt class="text-sm font-semibold text-slate-900 sm:w-48"><?= htmlspecialchars(__('class')) ?></dt>
                    <dd class="text-sm text-slate-500"><?= htmlspecialchars($student['class'] ?? '') ?></dd>
                </div>
                <div class="contact-row">
                    <dt class="text-sm font-semibold text-slate-900 sm:w-48"><?= htmlspecialchars(__('registration')) ?></dt>
                    <dd class="text-sm text-slate-500"><?= date('d.m.Y H:i', strtotime($student['created_at'])) ?></dd>
                </div>
            </dl>
            <p class="mt-4 text-right text-xs text-slate-400">slug: <?= htmlspecialchars($student['slug'] ?? '') ?></p>
        </div>

        <!-- Edit mode form -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]" id="info-edit-panel" style="display:none;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars(__('edit_data')) ?></h2>
                <button type="button" onclick="toggleEditInfo(false)" class="btn-edit-contact">
                    <?= htmlspecialchars(__('cancel')) ?>
                </button>
            </div>
            <form method="post" class="flex flex-col gap-4 max-w-2xl">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= $student['user_id'] ?>">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('name')) ?></label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($student['first_name'] ?? '') ?>" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('surname')) ?></label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($student['last_name'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('phone')) ?></label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('class')) ?></label>
                        <input type="text" name="class" value="<?= htmlspecialchars($student['class'] ?? '') ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('password')) ?></label>
                        <input type="text" name="password" placeholder="<?= htmlspecialchars(__('new_password_placeholder')) ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('scores')) ?></label>
                        <input type="number" name="points" value="<?= htmlspecialchars($student['points'] ?? 0) ?>" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <button type="submit" name="update_user" style="border: none;" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                        <?= htmlspecialchars(__('save')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab 2: Courses -->
    <div id="tab-content-courses" class="tab-profile-content" style="display:none;">
        <?php if (empty($courses)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-sm text-slate-500"><?= htmlspecialchars(__('no_subs')) ?></p>
            </div>
        <?php else: ?>
            <div class="flex flex-col gap-3">
                <?php foreach ($courses as $c):
                    $subStatus = $c['status'] ?? 'pending';
                    $isOverdue = $subStatus === 'active' && !empty($c['next_payment_date']) && strtotime($c['next_payment_date']) < strtotime('today');
                    $overdueDays = $isOverdue ? (int)floor((strtotime('today') - strtotime($c['next_payment_date'])) / 86400) : 0;
                ?>
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] flex flex-col gap-4" data-course-id="<?= $c['course_id'] ?>">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <!-- Left: Title & Subject -->
                            <div class="flex flex-col gap-1">
                                <span class="text-base font-semibold text-slate-900"><?= htmlspecialchars($c['course_title']) ?></span>
                                <span class="text-sm text-slate-400"><?= htmlspecialchars($c['subject'] ?: '') ?></span>
                            </div>

                            <!-- Right: Status, Purchased, Access Until -->
                            <div class="flex flex-wrap items-center gap-5 sm:gap-6">
                                <div class="flex flex-col">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Статус</span>
                                    <div class="mt-0.5 flex items-center gap-1.5">
                                        <?php if ($subStatus === 'active'): ?>
                                            <span class="inline-flex rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Активна</span>
                                            <?php if ($isOverdue): ?>
                                                <span class="inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Просрочено на <?= $overdueDays ?> дн.</span>
                                            <?php endif; ?>
                                        <?php elseif ($subStatus === 'pending'): ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Ожидает оплаты</span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600"><?= htmlspecialchars($subStatus) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex flex-col">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('purchased')) ?></span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-sm font-bold text-slate-900 tabular-nums">
                                            <?= !empty($c['purchase_date']) ? date('d.m.Y', strtotime($c['purchase_date'])) : '—' ?>
                                        </span>
                                        <button type="button" onclick="const f = document.getElementById('pdate-form-<?= $c['course_id'] ?>'); f.classList.toggle('hidden'); if(!f.classList.contains('hidden')) f.querySelector('input[type=date]').focus();" class="text-slate-400 hover:text-blue-600 transition-colors p-0.5 cursor-pointer bg-transparent border-0 inline-flex items-center" title="<?= htmlspecialchars(__('edit')) ?>">
                                            <i data-lucide="pencil" class="size-3"></i>
                                        </button>
                                    </div>
                                    <form method="post" id="pdate-form-<?= $c['course_id'] ?>" class="hidden flex items-center gap-1.5 mt-1.5 m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="update_purchase_date" value="1">
                                        <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                                        <input type="date" name="purchase_date" value="<?= !empty($c['purchase_date']) ? date('Y-m-d', strtotime($c['purchase_date'])) : date('Y-m-d') ?>" required class="rounded-lg border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-900 shadow-sm focus:outline-none">
                                        <button type="submit" style="border: none;" class="rounded-lg bg-blue-600 px-2 py-0.5 text-xs text-white shadow-sm hover:opacity-90 cursor-pointer">✓</button>
                                    </form>
                                </div>

                                <div class="flex flex-col">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Оплачено до</span>
                                    <span class="text-sm font-bold text-slate-900 tabular-nums mt-0.5">
                                        <?= !empty($c['access_expires_at']) ? date('d.m.Y', strtotime($c['access_expires_at'])) : '—' ?>
                                    </span>
                                </div>

                                <?php if ($subStatus === 'pending'): ?>
                                    <button type="button" onclick="document.getElementById('grant-form-<?= $c['subscription_id'] ?>').classList.toggle('hidden')" style="border: none;" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-3.5 py-1.5 text-xs font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                                        Выдать доступ
                                    </button>
                                <?php elseif ($subStatus === 'active'): ?>
                                    <button type="button" onclick="document.getElementById('renew-form-<?= $c['subscription_id'] ?>').classList.toggle('hidden')" style="border: 1px solid #e2e8f0; background: #ffffff;" class="inline-flex items-center justify-center rounded-xl px-3.5 py-1.5 text-xs font-medium text-slate-900 shadow-sm hover:bg-slate-50 cursor-pointer">
                                        Продлить курс
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Group assignment -->
                        <div class="border-t border-slate-100 pt-3 flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Группа</span>
                            <?php if (!empty($c['group_id'])): ?>
                                <a href="/admin/groups/view.php?id=<?= (int)$c['group_id'] ?>" class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 no-underline hover:bg-blue-100">
                                    <?= htmlspecialchars($c['group_title'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-md bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Без группы</span>
                                <?php if (!empty($groupsByCourse[$c['course_id']])): ?>
                                    <form method="post" class="m-0 flex flex-wrap items-center gap-1.5">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="course_id" value="<?= (int)$c['course_id'] ?>">
                                        <select name="group_id" required class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:border-blue-500 focus:outline-none">
                                            <option value="">— Назначить группу —</option>
                                            <?php foreach ($groupsByCourse[$c['course_id']] as $sg): ?>
                                                <option value="<?= (int)$sg['group_id'] ?>"><?= htmlspecialchars($sg['title'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_group" style="border: none;" class="inline-flex items-center rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                                            Назначить
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($subStatus === 'pending'): ?>
                            <!-- "Выдать доступ" form: first payment, admin selects the next payment date explicitly. -->
                            <form method="post" id="grant-form-<?= $c['subscription_id'] ?>" class="hidden flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4" onsubmit="setTimeout(() => { const b = this.querySelector('button[type=submit]'); if (b) b.disabled = true; }, 0);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="subscription_id" value="<?= $c['subscription_id'] ?>">
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Сумма</label>
                                    <input type="number" name="amount" min="1" step="1" required class="w-32 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Способ оплаты</label>
                                    <select name="payment_method" required class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                        <option value="cash">Наличные</option>
                                        <option value="card">Карта</option>
                                        <option value="transfer">Перевод</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Дата платежа</label>
                                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Следующий платёж</label>
                                    <input type="date" name="next_payment_date" required class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                                <div class="flex-1 min-w-[10rem]">
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Комментарий</label>
                                    <input type="text" name="notes" class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                                <button type="submit" name="grant_access" style="border: none;" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-1.5 text-xs font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                                    Подтвердить
                                </button>
                            </form>
                        <?php elseif ($subStatus === 'active'): ?>
                            <!-- "Продлить курс" form: next_payment_date defaults to the auto-computed
                                 date (current planned date + subscription_days) but is editable — an
                                 explicit value here overrides the server-side default (php/payment_service.php). -->
                            <form method="post" id="renew-form-<?= $c['subscription_id'] ?>" class="hidden flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4" onsubmit="setTimeout(() => { const b = this.querySelector('button[type=submit]'); if (b) b.disabled = true; }, 0);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="subscription_id" value="<?= $c['subscription_id'] ?>">
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Сумма</label>
                                    <input type="number" name="amount" min="1" step="1" required class="w-32 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Способ оплаты</label>
                                    <select name="payment_method" required class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                        <option value="cash">Наличные</option>
                                        <option value="card">Карта</option>
                                        <option value="transfer">Перевод</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Дата внесения оплаты <span class="normal-case font-normal text-slate-400">(сегодня)</span></label>
                                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Продлить доступ до</label>
                                    <input type="date" name="next_payment_date" value="<?= date('Y-m-d', strtotime(($c['next_payment_date'] ?: date('Y-m-d')) . ' +' . ($c['subscription_days'] ?: 30) . ' days')) ?>" required class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                                <div class="flex-1 min-w-[10rem]">
                                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Комментарий</label>
                                    <input type="text" name="notes" class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                </div>
                                <button type="submit" name="renew_subscription" style="border: none;" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-1.5 text-xs font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                                    Подтвердить
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab 3: Tests -->
    <div id="tab-content-tests" class="tab-profile-content" style="display:none;">
        <?php if (empty($testResults)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-sm text-slate-500"><?= htmlspecialchars(__('no_results_found')) ?></p>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="px-5 py-3.5 font-semibold"><?= htmlspecialchars(__('test')) ?></th>
                                <th class="px-5 py-3.5 font-semibold"><?= htmlspecialchars(__('test_type')) ?></th>
                                <th class="px-5 py-3.5 font-semibold text-center"><?= htmlspecialchars(__('scores')) ?></th>
                                <th class="px-5 py-3.5 text-right font-semibold"><?= htmlspecialchars(__('date_col')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testResults as $t): ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/60 transition-colors">
                                    <td class="px-5 py-4 font-medium text-slate-900"><?= htmlspecialchars($t['test_title'] ?? '—') ?></td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center rounded-md <?= $t['test_type'] === 'daily' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-800' ?> px-2.5 py-0.5 text-xs font-semibold">
                                            <?= $t['test_type'] === 'daily' ? htmlspecialchars(__('daily')) : htmlspecialchars(__('weekly')) ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex min-w-[2.25rem] justify-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                            <?= $t['get_points'] ?> / <?= $t['max_score'] ?? 100 ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right text-slate-500 tabular-nums whitespace-nowrap">
                                        <?= date('d.m.Y H:i', strtotime($t['completed_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab 4: Payments -->
    <div id="tab-content-payments" class="tab-profile-content" style="display:none;">
        <?php if (empty($payments) && empty($transactions)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-sm text-slate-500"><?= htmlspecialchars(__('no_payments')) ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($payments)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="px-5 py-3.5 font-semibold">#</th>
                                <th class="px-5 py-3.5 font-semibold"><?= htmlspecialchars(__('course')) ?></th>
                                <th class="px-5 py-3.5 font-semibold"><?= htmlspecialchars(__('amount')) ?></th>
                                <th class="px-5 py-3.5 font-semibold text-center"><?= htmlspecialchars(__('status')) ?></th>
                                <th class="px-5 py-3.5 text-right font-semibold"><?= htmlspecialchars(__('date_col')) ?></th>
                                <th class="px-5 py-3.5 font-semibold"><?= htmlspecialchars(__('comment')) ?></th>
                                <th class="px-5 py-3.5 text-right font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/60 transition-colors">
                                    <td class="px-5 py-4 font-mono text-slate-500">#<?= $p['payment_id'] ?></td>
                                    <td class="px-5 py-4 font-medium text-slate-800">
                                        <?= !empty($p['course_title']) ? htmlspecialchars($p['course_title']) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-900 tabular-nums whitespace-nowrap">
                                        <?= number_format($p['amount'], 0, '.', ' ') ?> so'm
                                    </td>
                                    <td class="px-5 py-4 text-center whitespace-nowrap">
                                        <?php if ($p['status'] === 'accepted' || $p['status'] === 'approved'): ?>
                                            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                <i data-lucide="check" class="size-3.5"></i>
                                                <?= htmlspecialchars(__('approved')) ?>
                                            </span>
                                        <?php elseif ($p['status'] === 'pending'): ?>
                                            <span class="inline-flex rounded-md bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                                <?= htmlspecialchars(__('pending')) ?>
                                            </span>
                                        <?php elseif ($p['status'] === 'rejected'): ?>
                                            <span class="inline-flex rounded-md bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                                                <?= htmlspecialchars(__('rejected')) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                <?= htmlspecialchars($p['status'] ?? '—') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-right text-slate-500 tabular-nums whitespace-nowrap">
                                        <?= date('d.m.Y H:i', strtotime($p['payment_date'])) ?>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500"><?= htmlspecialchars($p['comment'] ?? '') ?></td>
                                    <td class="px-5 py-4 text-right whitespace-nowrap">
                                        <?php if (($p['status'] ?? '') === 'pending'): ?>
                                            <form method="post" class="inline m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                                                <input type="hidden" name="amount" value="<?= $p['amount'] ?>">
                                                <input type="hidden" name="status" value="accepted">
                                                <button type="submit" name="update_payment" style="border: none;" class="inline-flex items-center rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                                                    <?= htmlspecialchars(__('approve')) ?>
                                                </button>
                                            </form>
                                            <form method="post" class="inline m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                                                <input type="hidden" name="amount" value="<?= $p['amount'] ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" name="update_payment" style="border: none;" class="inline-flex items-center rounded-lg bg-red-600 px-2.5 py-1 text-xs font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                                                    <?= htmlspecialchars(__('reject')) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($transactions)): ?>
            <h3 class="mt-8 mb-3 text-sm font-semibold text-slate-700">Транзакции (Выдача доступа / Продление)</h3>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="px-5 py-3.5 font-semibold">#</th>
                                <th class="px-5 py-3.5 font-semibold">Курс</th>
                                <th class="px-5 py-3.5 font-semibold">Сумма</th>
                                <th class="px-5 py-3.5 font-semibold">Способ оплаты</th>
                                <th class="px-5 py-3.5 text-right font-semibold">Дата оплаты</th>
                                <th class="px-5 py-3.5 font-semibold">Кем принят</th>
                                <th class="px-5 py-3.5 font-semibold">Комментарий</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $transactionMethodLabels = ['cash' => 'Наличные', 'card' => 'Карта', 'transfer' => 'Перевод'];
                            ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/60 transition-colors">
                                    <td class="px-5 py-4 font-mono text-slate-500">#<?= $t['transaction_id'] ?></td>
                                    <td class="px-5 py-4 font-medium text-slate-800">
                                        <?= !empty($t['course_title']) ? htmlspecialchars($t['course_title']) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-900 tabular-nums whitespace-nowrap">
                                        <?= number_format($t['amount'], 0, '.', ' ') ?> so'm
                                    </td>
                                    <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                        <?= htmlspecialchars($transactionMethodLabels[$t['payment_method']] ?? $t['payment_method']) ?>
                                    </td>
                                    <td class="px-5 py-4 text-right text-slate-500 tabular-nums whitespace-nowrap">
                                        <?= date('d.m.Y', strtotime($t['payment_date'])) ?>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500"><?= htmlspecialchars($t['created_by']) ?></td>
                                    <td class="px-5 py-4 text-slate-500"><?= htmlspecialchars($t['notes'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function switchProfileTab(name) {
    document.querySelectorAll('.tab-profile-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-profile-btn').forEach(el => el.classList.remove('active'));
    const targetContent = document.getElementById('tab-content-' + name);
    if (targetContent) targetContent.style.display = 'block';
    const activeBtn = document.getElementById('tab-btn-' + name);
    if (activeBtn) activeBtn.classList.add('active');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
function toggleEditInfo(showEdit) {
    const v = document.getElementById('info-view-panel');
    const e = document.getElementById('info-edit-panel');
    if (v && e) {
        v.style.display = showEdit ? 'none' : 'block';
        e.style.display = showEdit ? 'block' : 'none';
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
