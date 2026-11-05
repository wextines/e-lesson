<?php
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../authorization.php';
require_admin_role(['center_admin', 'super_admin']);
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$payment_id) {
    header("Location: /admin/payments/");
    exit;
}

// Handle POST actions for status change and soft delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Soft delete / Archive: Hide payment
    if ($action === 'hide_payment' || $action === 'hide') {
        $reason = trim($_POST['deletion_reason'] ?? '');
        $revokeAccess = !empty($_POST['revoke_access']) ? 1 : 0;

        $adminName = 'admin';
        if (isset($_SESSION['user_id'])) {
            $uStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
            $uStmt->execute([$_SESSION['user_id']]);
            if ($u = $uStmt->fetch(PDO::FETCH_ASSOC)) {
                $adminName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'admin';
            }
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$payment_id, $tid]);
            $currPayment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currPayment) {
                throw new Exception("Платёж не найден в текущем учебном центре.");
            }

            $didRevoke = 0;
            if ($revokeAccess && !empty($currPayment['course_id']) && !empty($currPayment['user_id'])) {
                // Revoke course access strictly for this course_id and user_id in this tenant
                $delSub = $pdo->prepare("DELETE FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
                $delSub->execute([$currPayment['user_id'], $currPayment['course_id'], $tid]);
                $didRevoke = 1;
            }

            $upd = $pdo->prepare("UPDATE payments SET deleted_at = NOW(), deleted_by = ?, deletion_reason = ?, access_revoked = ? WHERE payment_id = ? AND tenant_id = ?");
            $upd->execute([$adminName, $reason, $didRevoke, $payment_id, $tid]);

            $pdo->commit();
            $_SESSION['payment_view_msg'] = $didRevoke 
                ? "Платеж архивирован, доступ к курсу отозван." 
                : "Платеж архивирован (скрыт из статистики). Доступ к курсу сохранён.";
        } catch(Exception $e) {
            $pdo->rollBack();
            error_log("hide_payment error: " . $e->getMessage());
            $_SESSION['payment_view_error'] = "Ошибка архивации платежа: " . $e->getMessage();
        }

        header("Location: /admin/payments/view.php?id=" . $payment_id);
        exit;
    }

    // Soft delete / Archive: Restore payment
    if ($action === 'restore_payment' || $action === 'restore') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$payment_id, $tid]);
            $currPayment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currPayment) {
                throw new Exception("Платёж не найден в текущем учебном центре.");
            }

            // Restore payment metadata: deleted_at, deleted_by, deletion_reason, access_revoked to NULL / 0
            // STRICT REQUIREMENT: DO NOT automatically grant or renew course_subs access upon restore
            $upd = $pdo->prepare("UPDATE payments SET deleted_at = NULL, deleted_by = NULL, deletion_reason = NULL, access_revoked = 0 WHERE payment_id = ? AND tenant_id = ?");
            $upd->execute([$payment_id, $tid]);

            $pdo->commit();
            $_SESSION['payment_view_msg'] = "Платеж восстановлен и возвращён в общий список и статистику. (Доступ к курсу автоматически не выдавался).";
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("restore_payment error: " . $e->getMessage());
            $_SESSION['payment_view_error'] = "Ошибка восстановления: " . $e->getMessage();
        }

        header("Location: /admin/payments/view.php?id=" . $payment_id);
        exit;
    }

    if ($action === 'update_status' || $action === 'confirm' || $action === 'reject' || $action === 'mark_paid') {
        $newStatus = trim($_POST['status'] ?? '');
        if ($action === 'confirm' || $action === 'mark_paid') {
            $newStatus = 'accepted';
        } elseif ($action === 'reject') {
            $newStatus = 'rejected';
        }

        $comment = trim($_POST['comment'] ?? '');

        if (!in_array($newStatus, ['pending', 'accepted', 'rejected'])) {
            $_SESSION['payment_view_error'] = "Недопустимый статус платежа.";
            header("Location: /admin/payments/view.php?id=" . $payment_id);
            exit;
        }

        // Fetch current payment record (Tenant isolated)
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND tenant_id = ?");
        $stmt->execute([$payment_id, $tid]);
        $currPayment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currPayment) {
            $_SESSION['payment_view_error'] = "Платёж не найден в текущем учебном центре.";
            header("Location: /admin/payments/");
            exit;
        }

        $oldStatus = $currPayment['status'];

        // If comment was not submitted in form, preserve existing comment
        if (!isset($_POST['comment'])) {
            $comment = $currPayment['comment'] ?? '';
        }

        // 1. REJECTED logic: Comment is MANDATORY
        if ($newStatus === 'rejected') {
            if (empty($comment)) {
                $_SESSION['payment_view_error'] = "Для отклонения платежа или возврата необходимо обязательно указать комментарий (причину).";
                header("Location: /admin/payments/view.php?id=" . $payment_id);
                exit;
            }

            $upd = $pdo->prepare("UPDATE payments SET status = 'rejected', comment = ? WHERE payment_id = ? AND tenant_id = ?");
            $upd->execute([$comment, $payment_id, $tid]);
            $_SESSION['payment_view_msg'] = "Статус платежа изменён на «Отклонено / возврат». Комментарий сохранён.";
            header("Location: /admin/payments/view.php?id=" . $payment_id);
            exit;
        }

        // 2. PENDING logic
        if ($newStatus === 'pending') {
            $upd = $pdo->prepare("UPDATE payments SET status = 'pending', comment = ? WHERE payment_id = ? AND tenant_id = ?");
            $upd->execute([$comment, $payment_id, $tid]);
            $_SESSION['payment_view_msg'] = "Статус платежа переведён в «Ожидает подтверждения».";
            header("Location: /admin/payments/view.php?id=" . $payment_id);
            exit;
        }

        // 3. ACCEPTED logic
        if ($newStatus === 'accepted') {
            // If already accepted, only update comment if changed
            if ($oldStatus === 'accepted') {
                $upd = $pdo->prepare("UPDATE payments SET comment = ? WHERE payment_id = ? AND tenant_id = ?");
                $upd->execute([$comment, $payment_id, $tid]);
                $_SESSION['payment_view_msg'] = "Данные платежа сохранены.";
                header("Location: /admin/payments/view.php?id=" . $payment_id);
                exit;
            }

            // Transition from pending/rejected/unpaid to accepted. Course-linked payments
            // are reconciled through the same PaymentService state machine as "Выдать
            // доступ"/"Продлить курс" (P0-4) — this stops being a raw access_expires_at
            // UPDATE and always produces a real transactions row + status transition.
            require_once __DIR__ . '/../../php/payment_service.php';

            // Atomically claim this payment FIRST (duplicate-submission guard, same as
            // the original code): only one concurrent "accept" can win this UPDATE. The
            // state-machine mutation below runs only if we actually won the claim, and is
            // reverted if it fails, so the payments row never says "accepted" while no
            // real access was granted.
            $upd = $pdo->prepare("UPDATE payments SET status = 'accepted', comment = ? WHERE payment_id = ? AND tenant_id = ? AND status != 'accepted'");
            $upd->execute([$comment, $payment_id, $tid]);
            if ($upd->rowCount() === 0) {
                $_SESSION['payment_view_error'] = "Платёж уже был подтверждён.";
                header("Location: /admin/payments/view.php?id=" . $payment_id);
                exit;
            }

            if (!empty($currPayment['course_id'])) {
                // Verify student and course belong to this tenant before creating anything.
                $uStmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND tenant_id = ?");
                $uStmt->execute([$currPayment['user_id'], $tid]);
                if (!$uStmt->fetch()) {
                    $_SESSION['payment_view_error'] = "Студент не найден в текущем учебном центре.";
                    header("Location: /admin/payments/view.php?id=" . $payment_id);
                    exit;
                }
                $cStmt = $pdo->prepare("SELECT course_id FROM courses WHERE course_id = ? AND tenant_id = ?");
                $cStmt->execute([$currPayment['course_id'], $tid]);
                if (!$cStmt->fetch()) {
                    $_SESSION['payment_view_error'] = "Курс не найден в текущем учебном центре.";
                    header("Location: /admin/payments/view.php?id=" . $payment_id);
                    exit;
                }

                $subStmt = $pdo->prepare("SELECT subscription_id, status FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
                $subStmt->execute([$currPayment['user_id'], $currPayment['course_id'], $tid]);
                $sub = $subStmt->fetch(PDO::FETCH_ASSOC);

                $adminUsername = $_SESSION['admin_username'] ?? 'admin';
                $method = PaymentService::mapLegacyPaymentMethod($currPayment['payment_method'] ?? null);
                $paymentDateOnly = date('Y-m-d', strtotime($currPayment['payment_date']));

                if ($sub && $sub['status'] === 'active') {
                    // Renewal: amount/method/date are already known from the payment row —
                    // next_payment_date is never entered here, it advances from the
                    // subscription's own current planned date (php/payment_service.php).
                    $result = PaymentService::renew($pdo, $tid, (int)$sub['subscription_id'], [
                        'amount' => $currPayment['amount'],
                        'payment_method' => $method,
                        'payment_date' => $paymentDateOnly,
                        'notes' => $comment,
                        'source_payment_id' => $payment_id,
                    ], $adminUsername);
                } else {
                    // First payment on this subscription: a next_payment_date must be
                    // explicitly chosen by the admin — it is never calculated from
                    // payment_date.
                    $nextPaymentDate = trim($_POST['next_payment_date'] ?? '');
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextPaymentDate)) {
                        $_SESSION['payment_view_error'] = "Укажите дату следующего платежа, чтобы одобрить первый платёж по этой подписке.";
                        header("Location: /admin/payments/view.php?id=" . $payment_id);
                        exit;
                    }

                    $subscriptionId = $sub['subscription_id'] ?? null;
                    if (!$subscriptionId) {
                        // Mirrors admin/courses/view.php's enroll_student: create the
                        // pending, no-access enrollment first.
                        $ins = $pdo->prepare("INSERT INTO course_subs (user_id, course_id, price, purchase_date, access_expires_at, next_payment_date, status, tenant_id) VALUES (?, ?, ?, NOW(), NULL, NULL, 'pending', ?)");
                        $ins->execute([$currPayment['user_id'], $currPayment['course_id'], $currPayment['amount'], $tid]);
                        $subscriptionId = (int)$pdo->lastInsertId();
                    }

                    $result = PaymentService::grantFirstAccess($pdo, $tid, (int)$subscriptionId, [
                        'amount' => $currPayment['amount'],
                        'payment_method' => $method,
                        'payment_date' => $paymentDateOnly,
                        'next_payment_date' => $nextPaymentDate,
                        'notes' => $comment,
                        'source_payment_id' => $payment_id,
                    ], $adminUsername);
                }

                if (!$result['success']) {
                    // Revert the claim above: the payment must not say "accepted" while
                    // no real access was actually granted.
                    $revert = $pdo->prepare("UPDATE payments SET status = ? WHERE payment_id = ? AND tenant_id = ?");
                    $revert->execute([$oldStatus, $payment_id, $tid]);
                    $_SESSION['payment_view_error'] = "Ошибка выдачи доступа: " . implode(' ', $result['errors']);
                    header("Location: /admin/payments/view.php?id=" . $payment_id);
                    exit;
                }
            }

            $_SESSION['payment_view_msg'] = "Платёж успешно одобрен, доступ к курсу открыт.";
            header("Location: /admin/payments/view.php?id=" . $payment_id);
            exit;
        }
    }
}

// Fetch payment details (tenant isolated)
$stmt = $pdo->prepare("
    SELECT p.*, 
           c.title as course_title,
           u.first_name, u.last_name, u.phone, u.class as student_class
    FROM payments p
    LEFT JOIN courses c ON p.course_id = c.course_id AND c.tenant_id = p.tenant_id
    LEFT JOIN users u ON p.user_id = u.user_id AND u.tenant_id = p.tenant_id
    WHERE p.payment_id = ? AND p.tenant_id = ?
");
$stmt->execute([$payment_id, $tid]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    echo "Платеж не найден.";
    exit;
}

// Whether approving this payment is a first payment (subscription pending/missing,
// needs an explicit next_payment_date) or a renewal (subscription already active, no
// extra input required) — see php/payment_service.php.
$paymentSubscription = null;
if (!empty($payment['course_id'])) {
    $psStmt = $pdo->prepare("SELECT subscription_id, status FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ?");
    $psStmt->execute([$payment['user_id'], $payment['course_id'], $tid]);
    $paymentSubscription = $psStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$needsFirstPaymentDate = !empty($payment['course_id']) && (!$paymentSubscription || $paymentSubscription['status'] !== 'active');

$pageTitle = __('nav_payments') . ' - ' . __('view_btn');
include __DIR__ . '/../includes/header.php';

// Format helpers
$sc = match($payment['status'] ?? '') {
    'accepted' => 'badge-green',
    'rejected' => 'badge-red',
    'pending' => 'badge-yellow',
    'unpaid' => 'badge-yellow',
    default => 'badge-gray'
};
$statusText = match($payment['status'] ?? '') {
    'accepted' => __('approved'),
    'rejected' => __('rejected'),
    'pending' => __('pending'),
    'unpaid' => __('unpaid'),
    default => $payment['status'] ?? '—'
};

$methodKey = $payment['payment_method'] ?? 'cash';
$methodText = match($methodKey) {
    'cash' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg> ' . __('cash'),
    'terminal' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> ' . __('terminal'),
    'online' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1 4-10z"/></svg> ' . __('online'),
    default => '—'
};

$isDeleted = !empty($payment['deleted_at']);

$viewMsg = $_SESSION['payment_view_msg'] ?? '';
$viewError = $_SESSION['payment_view_error'] ?? '';
unset($_SESSION['payment_view_msg'], $_SESSION['payment_view_error']);
?>
<div class="mx-auto flex max-w-6xl flex-col gap-4">
    <?php if ($viewMsg): ?>
        <div style="background:#ecfdf5;border:1px solid #10b981;color:#065f46;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:500;">
            <?= htmlspecialchars($viewMsg) ?>
        </div>
    <?php endif; ?>
    <?php if ($viewError): ?>
        <div style="background:#fef2f2;border:1px solid #ef4444;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:500;">
            <?= htmlspecialchars($viewError) ?>
        </div>
    <?php endif; ?>

    <?php if ($isDeleted): ?>
        <div style="background:#fffbeb; border:1px solid #f59e0b; color:#92400e; padding:12px 16px; border-radius:10px; font-size:13px; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
            <div style="display:flex; align-items:center; gap:8px; font-weight:700; font-size:14px; margin-bottom:4px; color:#b45309;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                Платеж архивирован (скрыт)
            </div>
            <div style="font-size:12.5px; line-height:1.5;">
                <div>Скрыл: <strong><?= htmlspecialchars($payment['deleted_by'] ?? 'admin') ?></strong> &nbsp;|&nbsp; Дата: <strong><?= date('d.m.Y H:i', strtotime($payment['deleted_at'])) ?></strong></div>
                <div style="margin-top:2px;">
                    Статус доступа к курсу: 
                    <?php if (!empty($payment['access_revoked'])): ?>
                        <strong style="color:#dc2626;">Доступ к курсу был отозван при архивации</strong>
                    <?php else: ?>
                        <strong style="color:#059669;">Доступ к курсу был сохранён</strong>
                    <?php endif; ?>
                </div>
                <?php if (!empty($payment['deletion_reason'])): ?>
                    <div style="margin-top:4px; background:rgba(245,158,11,0.1); padding:6px 10px; border-radius:6px; border-left:3px solid #f59e0b;">
                        Причина скрытия: <strong><?= htmlspecialchars($payment['deletion_reason']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Header Bar -->
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap: 10px; flex-wrap:wrap;">
            <a href="/admin/payments/" class="btn btn-outline btn-icon" style="border:1px solid #e2e8f0; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.03); padding: 6px 8px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <h2 style="margin:0; font-size: 20px; font-weight:700; color:var(--gray-900);">Детали платежа #<?= $payment['payment_id'] ?></h2>
            <span class="badge <?= $sc ?>" style="font-size:12px; padding:3px 9px;"><?= htmlspecialchars($statusText) ?></span>
            <?php if ($isDeleted): ?>
                <span class="badge badge-gray" style="background:#f1f5f9; color:#64748b; font-weight:600; font-size:12px; padding:3px 9px;">Скрыт</span>
            <?php endif; ?>

            <!-- Quick Action Buttons next to status -->
            <?php if (!$isDeleted): ?>
                <div style="display:inline-flex; align-items:center; gap:6px; margin-left:4px;">
                    <?php if ($payment['status'] === 'pending'): ?>
                        <?php if ($needsFirstPaymentDate): ?>
                            <span style="font-size:11.5px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; padding:4px 9px; border-radius:7px; font-weight:500;">
                                Это первый платёж по подписке — укажите дату следующего платежа в блоке «Управление статусом» →
                            </span>
                        <?php else: ?>
                            <form method="post" style="margin:0;" onsubmit="return confirm('Одобрить платёж и выдать доступ к курсу?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-sm" style="background:#10b981; color:#fff; border:none; padding:4px 10px; font-size:12px; font-weight:600; border-radius:7px; display:inline-flex; align-items:center; gap:4px; cursor:pointer; box-shadow:0 1px 2px rgba(16,185,129,0.25); transition:all 0.15s ease;">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    Одобрить
                                </button>
                            </form>
                        <?php endif; ?>
                        <button type="button" onclick="openRejectModal();" class="btn btn-sm" style="background:#fff; color:#dc2626; border:1px solid #fca5a5; padding:4px 10px; font-size:12px; font-weight:600; border-radius:7px; display:inline-flex; align-items:center; gap:4px; cursor:pointer; transition:all 0.15s ease;">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Отклонить
                        </button>
                    <?php elseif ($payment['status'] === 'rejected'): ?>
                        <?php if ($needsFirstPaymentDate): ?>
                            <span style="font-size:11.5px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; padding:4px 9px; border-radius:7px; font-weight:500;">
                                Это первый платёж по подписке — укажите дату следующего платежа в блоке «Управление статусом» →
                            </span>
                        <?php else: ?>
                            <form method="post" style="margin:0;" onsubmit="return confirm('Одобрить платёж и открыть доступ?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-sm" style="background:#10b981; color:#fff; border:none; padding:4px 10px; font-size:12px; font-weight:600; border-radius:7px; display:inline-flex; align-items:center; gap:4px; cursor:pointer; box-shadow:0 1px 2px rgba(16,185,129,0.25);">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    Одобрить
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($payment['status'] === 'accepted'): ?>
                        <button type="button" onclick="openRejectModal();" class="btn btn-sm" style="background:#fff; color:#64748b; border:1px solid #cbd5e1; padding:4px 9px; font-size:11.5px; font-weight:500; border-radius:7px; display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Отклонить / возврат
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="display:flex; align-items:center; gap:10px;">
            <?php if ($isDeleted): ?>
                <form method="post" onsubmit="return confirm('Восстановить этот платеж в общий список и статистику?');" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="restore_payment">
                    <button type="submit" class="btn btn-outline" style="color:#059669; border-color:#10b981; background:#fff; font-size:13px; font-weight:600; padding:6px 12px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg>
                        Восстановить платеж
                    </button>
                </form>
            <?php else: ?>
                <button type="button" onclick="document.getElementById('hidePaymentModal').style.display='flex';" class="btn btn-outline" style="color:#dc2626; border-color:#fca5a5; background:#fff; font-size:13px; font-weight:600; padding:6px 12px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    Скрыть платеж
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Grid Content -->
    <div style="display:grid; grid-template-columns: 2fr 1.15fr; gap: 16px; align-items:start;">
        <!-- Left Column: Primary Details & Comment & Issued By & Receipt -->
        <div class="card" style="background:#fff; border-radius:12px; border:1px solid #e5e7eb; padding: 18px 20px; box-shadow:0 1px 3px rgba(0,0,0,0.03); margin-bottom:0;">
            <h3 style="margin-top:0; margin-bottom: 14px; font-size:15px; font-weight:600; color:var(--gray-900); border-bottom:1px solid #f3f4f6; padding-bottom:8px;">Основная информация</h3>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                <div>
                    <div style="font-size:11.5px; color:var(--gray-500); margin-bottom:2px;"><?= htmlspecialchars(__('amount')) ?></div>
                    <div style="font-size:20px; font-weight:700; color:var(--gray-900);"><?= number_format($payment['amount'], 0, '.', ' ') ?> сум</div>
                </div>
                <div>
                    <div style="font-size:11.5px; color:var(--gray-500); margin-bottom:2px;"><?= htmlspecialchars(__('payment_method')) ?></div>
                    <div style="font-size:14px; font-weight:500; color:var(--gray-800); margin-top:2px; display:flex; align-items:center; gap:6px;"><?= $methodText ?></div>
                </div>
                <div>
                    <div style="font-size:11.5px; color:var(--gray-500); margin-bottom:2px;"><?= htmlspecialchars(__('date')) ?></div>
                    <div style="font-size:13.5px; font-weight:500; color:var(--gray-800);"><?= date('d.m.Y H:i', strtotime($payment['payment_date'])) ?></div>
                </div>
                <div>
                    <div style="font-size:11.5px; color:var(--gray-500); margin-bottom:2px;"><?= htmlspecialchars(__('course')) ?></div>
                    <div style="font-size:13.5px; font-weight:500; color:var(--gray-900);"><?= !empty($payment['course_title']) ? htmlspecialchars($payment['course_title']) : '<span style="color:var(--gray-400);">—</span>' ?></div>
                </div>
            </div>

            <h3 style="margin-top:0; margin-bottom: 10px; font-size:14px; font-weight:600; color:var(--gray-900); border-bottom:1px solid #f3f4f6; padding-bottom:6px;">
                <?= $payment['status'] === 'rejected' ? 'Причина отклонения / возврата' : htmlspecialchars(__('comment')) ?>
            </h3>
            <div style="background: <?= $payment['status'] === 'rejected' ? '#fef2f2' : '#f9fafb' ?>; padding:10px 14px; border-radius:8px; border:1px solid <?= $payment['status'] === 'rejected' ? '#fecaca' : '#f3f4f6' ?>; font-size:13px; color: <?= $payment['status'] === 'rejected' ? '#991b1b' : 'var(--gray-700)' ?>; min-height: 48px;">
                <?= !empty($payment['comment']) ? nl2br(htmlspecialchars($payment['comment'])) : '<span style="color:var(--gray-400); font-style:italic;">Комментарий отсутствует</span>' ?>
            </div>

            <h3 style="margin-top:16px; margin-bottom: 8px; font-size:14px; font-weight:600; color:var(--gray-900); border-bottom:1px solid #f3f4f6; padding-bottom:6px;">
                <?= htmlspecialchars(__('issued_by')) ?>
            </h3>
            <div style="background:#f9fafb; padding:10px 14px; border-radius:8px; border:1px solid #e5e7eb; display:flex; align-items:center; gap:8px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--gray-500);"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span style="font-size:13px; font-weight:600; color:var(--gray-800);"><?= htmlspecialchars($payment['issued_by'] ?? 'admin') ?></span>
            </div>
            
            <?php if (!empty($payment['receipt_path'])): ?>
                <h3 style="margin-top:18px; margin-bottom: 10px; font-size:14px; font-weight:600; color:var(--gray-900); border-bottom:1px solid #f3f4f6; padding-bottom:6px;">Чек / Скриншот оплаты</h3>
                <?php 
                $receiptUrl = '/file.php?type=receipt&id=' . (int)$payment['payment_id'];
                $ext = strtolower(pathinfo($payment['receipt_path'], PATHINFO_EXTENSION));
                ?>
                <div style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; display:inline-block; padding: 6px; background:#fff;">
                    <a href="<?= htmlspecialchars($receiptUrl) ?>" target="_blank" style="text-decoration:none; display:block;">
                        <?php if ($ext === 'pdf'): ?>
                            <div style="padding: 18px 24px; text-align: center; color: var(--gray-700); background: #f9fafb; border-radius: 6px; display:flex; flex-direction:column; align-items:center; gap:6px;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                <span style="font-size: 12.5px; font-weight: 600; color: var(--gray-900);">Квитанция в PDF</span>
                                <span style="font-size: 11px; color: #2563eb;">Открыть документ ↗</span>
                            </div>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($receiptUrl) ?>" alt="Receipt" style="max-width: 100%; max-height: 240px; display: block; border-radius: 4px; object-fit:contain;">
                            <div style="margin-top:4px; text-align:right;">
                                <span style="font-size:11px; color:#2563eb;">Открыть оригинал ↗</span>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Status Management & Student Details -->
        <div style="display:flex; flex-direction:column; gap: 16px;">
            <!-- Status Management Block -->
            <div class="card" style="background:#fff; border-radius:12px; border:1px solid #e5e7eb; padding: 18px 20px; box-shadow:0 1px 3px rgba(0,0,0,0.03); margin-bottom:0;">
                <h3 style="margin-top:0; margin-bottom: 12px; font-size:14px; font-weight:600; color:var(--gray-900); border-bottom:1px solid #f3f4f6; padding-bottom:8px; display:flex; align-items:center; gap:6px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Управление статусом
                </h3>

                <form method="post" id="statusChangeForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_status">
                    
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:11.5px; font-weight:600; color:var(--gray-700); margin-bottom:4px;">
                            Статус платежа
                        </label>
                        <select name="status" id="paymentStatusSelect" style="width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:7px; font-size:13px; font-weight:500; background:#fff; color:var(--gray-800);" onchange="updateCommentRequirement()">
                            <option value="pending" <?= $payment['status'] === 'pending' ? 'selected' : '' ?>>⏳ Ожидает подтверждения</option>
                            <option value="accepted" <?= $payment['status'] === 'accepted' ? 'selected' : '' ?>>✅ Одобрено (выдача доступа)</option>
                            <option value="rejected" <?= $payment['status'] === 'rejected' ? 'selected' : '' ?>>❌ Отклонено / возврат</option>
                        </select>
                    </div>

                    <?php if ($needsFirstPaymentDate): ?>
                        <div id="nextPaymentDateGroup" style="margin-bottom:14px; display:<?= $payment['status'] === 'accepted' ? 'block' : 'none' ?>;">
                            <label style="display:block; font-size:11.5px; font-weight:600; color:var(--gray-700); margin-bottom:4px;">
                                Дата следующего платежа <span style="color:#dc2626;">* (обязательно для первого платежа)</span>
                            </label>
                            <input type="date" name="next_payment_date" id="nextPaymentDateInput" style="width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:7px; font-size:13px;">
                            <div style="font-size:11px; color:var(--gray-500); margin-top:3px;">Это первый платёж по этой подписке — дата не рассчитывается автоматически, укажите её сами.</div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:11.5px; font-weight:600; color:var(--gray-700); margin-bottom:4px;">
                            Комментарий <span id="commentRequiredStar" style="color:#dc2626; display:<?= $payment['status'] === 'rejected' ? 'inline' : 'none' ?>;">* (обязательно при отклонении)</span>
                        </label>
                        <textarea name="comment" id="paymentCommentInput" rows="2" placeholder="Причина отклонения, возврата или примечание..." style="width:100%; padding:7px 10px; border:1px solid #d1d5db; border-radius:7px; font-size:12.5px; resize:vertical; font-family:inherit;"><?= htmlspecialchars($payment['comment'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:8px 12px; font-size:13px; font-weight:600; border-radius:7px;">
                        Сохранить статус
                    </button>
                </form>
            </div>

            <!-- Student Profile Card -->
            <div class="card" style="background:#fff; border-radius:12px; border:1px solid #e5e7eb; padding: 18px 20px; box-shadow:0 1px 3px rgba(0,0,0,0.03); margin-bottom:0;">
                <h3 style="margin-top:0; margin-bottom: 12px; font-size:14px; font-weight:600; color:var(--gray-900); border-bottom:1px solid #f3f4f6; padding-bottom:8px;">Данные ученика</h3>
                
                <div style="display:flex; align-items:center; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; flex-shrink:0;">
                        <?= mb_substr($payment['first_name'] ?? '?', 0, 1) . mb_substr($payment['last_name'] ?? '?', 0, 1) ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 14px; color: var(--gray-900);"><?= htmlspecialchars(($payment['first_name'] ?? '?') . ' ' . ($payment['last_name'] ?? '')) ?></div>
                        <div style="font-size: 11.5px; color: var(--gray-500);">ID: <?= $payment['user_id'] ?></div>
                    </div>
                </div>

                <div style="margin-bottom: 8px;">
                    <div style="font-size:11px; color:var(--gray-500); margin-bottom:1px;">Телефон</div>
                    <div style="font-size:12.5px; font-weight:500; color:var(--gray-800);"><?= htmlspecialchars($payment['phone'] ?: '—') ?></div>
                </div>
                <div style="margin-bottom: 14px;">
                    <div style="font-size:11px; color:var(--gray-500); margin-bottom:1px;">Класс</div>
                    <div style="font-size:12.5px; font-weight:500; color:var(--gray-800);"><?= htmlspecialchars($payment['student_class'] ? $payment['student_class'].' класс' : '—') ?></div>
                </div>

                <a href="/admin/students/view.php?user_id=<?= $payment['user_id'] ?>" class="btn btn-outline" style="width:100%; justify-content:center; font-size:12.5px; padding:7px 12px; border-radius:7px;">Профиль ученика</a>
            </div>
        </div>
    </div>
</div>
<!-- Modal: Reject Payment -->
<div id="rejectPaymentModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:14px; width:100%; max-width:440px; padding:20px 22px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                <svg width="18" height="18" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                Отклонить платёж #<?= $payment['payment_id'] ?>
            </h3>
            <button type="button" onclick="closeRejectModal();" style="background:none; border:none; color:#94a3b8; cursor:pointer; padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <p style="font-size:12.5px; color:#64748b; margin-top:0; margin-bottom:14px; line-height:1.4;">
            Укажите причину отклонения или возврата платежа. Этот комментарий сохранится в истории платежа.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:4px;">Причина отклонения *</label>
                <textarea name="comment" id="quickRejectComment" rows="3" required placeholder="Например: неверный чек, сумма не поступила, отказ клиента..." style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px; font-size:12.5px; font-family:inherit; resize:vertical;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" onclick="closeRejectModal();" class="btn btn-outline" style="border:1px solid #cbd5e1; font-size:12.5px; padding:6px 12px; border-radius:7px;">Отмена</button>
                <button type="submit" class="btn btn-primary" style="background:#dc2626; border-color:#dc2626; font-size:12.5px; padding:6px 14px; border-radius:7px;">Отклонить платёж</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Hide Payment -->
<div id="hidePaymentModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:14px; width:100%; max-width:460px; padding:20px 22px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                <svg width="18" height="18" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                Скрыть платеж #<?= $payment['payment_id'] ?>
            </h3>
            <button type="button" onclick="document.getElementById('hidePaymentModal').style.display='none';" style="background:none; border:none; color:#94a3b8; cursor:pointer; padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <p style="font-size:12.5px; color:#64748b; margin-top:0; margin-bottom:14px; line-height:1.4;">
            Платеж будет архивирован и исключён из финансовой статистики (KPI). Запись сохранится в базе данных, и вы сможете восстановить её в любой момент.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hide_payment">
            
            <?php if (!empty($payment['course_id'])): ?>
                <div style="margin-bottom:14px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:10px 12px;">
                    <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer; font-size:12.5px; color:#9a3412; font-weight:600; margin:0;">
                        <input type="checkbox" name="revoke_access" value="1" style="margin-top:2px; width:15px; height:15px; accent-color:#ea580c; cursor:pointer;">
                        <div>
                            <span>Убрать доступ к курсу</span>
                            <div style="font-weight:400; font-size:11.5px; color:#c2410c; margin-top:2px;">
                                Отозвать доступ студента к курсу «<?= htmlspecialchars($payment['course_title'] ?? '') ?>». Другие курсы студента затронуты не будут.
                            </div>
                        </div>
                    </label>
                </div>
            <?php endif; ?>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:4px;">Причина архивации (опционально)</label>
                <textarea name="deletion_reason" rows="2" placeholder="Например: дубликат записи, тестовый платеж, ошибка оператора..." style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px; font-size:12.5px; font-family:inherit; resize:vertical;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" onclick="document.getElementById('hidePaymentModal').style.display='none';" class="btn btn-outline" style="border:1px solid #cbd5e1; font-size:12.5px; padding:6px 12px; border-radius:7px;">Отмена</button>
                <button type="submit" class="btn btn-primary" style="background:#dc2626; border-color:#dc2626; font-size:12.5px; padding:6px 14px; border-radius:7px;">Подтвердить архивацию</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal() {
    var m = document.getElementById('rejectPaymentModal');
    if (m) {
        m.style.display = 'flex';
        var inp = document.getElementById('quickRejectComment');
        if (inp) setTimeout(function(){ inp.focus(); }, 100);
    }
}
function closeRejectModal() {
    var m = document.getElementById('rejectPaymentModal');
    if (m) m.style.display = 'none';
}
function updateCommentRequirement() {
    var select = document.getElementById('paymentStatusSelect');
    var star = document.getElementById('commentRequiredStar');
    var input = document.getElementById('paymentCommentInput');
    if (select && star && input) {
        if (select.value === 'rejected') {
            star.style.display = 'inline';
            input.setAttribute('required', 'required');
        } else {
            star.style.display = 'none';
            input.removeAttribute('required');
        }
    }
    var npdGroup = document.getElementById('nextPaymentDateGroup');
    var npdInput = document.getElementById('nextPaymentDateInput');
    if (select && npdGroup && npdInput) {
        if (select.value === 'accepted') {
            npdGroup.style.display = 'block';
            npdInput.setAttribute('required', 'required');
        } else {
            npdGroup.style.display = 'none';
            npdInput.removeAttribute('required');
        }
    }
}
document.addEventListener('DOMContentLoaded', updateCommentRequirement);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
