<?php
require_once __DIR__ . '/../authorization.php';
require_admin_role(['center_admin', 'super_admin']);
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php';

$pageTitle = __('adm_debtors');
$currencyLabel = __('adm_currency');
$tid = Tenant::getId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_payment'])) {
    verify_csrf();
    require_once __DIR__ . '/../../php/payment_service.php';
    $subId = (int)($_POST['subscription_id'] ?? 0);
    $subStatus = trim((string)($_POST['sub_status'] ?? ''));
    $adminUsername = $_SESSION['admin_username'] ?? 'admin';

    if ($subStatus === 'active') {
        $result = PaymentService::renew($pdo, $tid, $subId, $_POST, $adminUsername);
    } else {
        $result = PaymentService::grantFirstAccess($pdo, $tid, $subId, $_POST, $adminUsername);
    }

    if ($result['success']) {
        $_SESSION['flash_success'] = __('adm_pay_success');
    } else {
        $_SESSION['flash_error'] = implode(' ', $result['errors'] ?? [__('adm_pay_error')]);
    }
    header('Location: /admin/finance/debtors.php');
    exit;
}

// Two distinct kinds of debtor, deliberately kept apart rather than merged into one list:
// they need different conversations. An overdue active student is already studying and
// simply hasn't paid this period (access is intentionally NOT cut — README section 12),
// while a 'pending' student enrolled and never paid at all, and is otherwise invisible
// everywhere in the admin panel.
// Free courses (price = 0) are excluded from both: there is nothing to collect, so they
// would only ever be noise in a list whose whole purpose is "who do I need to chase".

// Group 1: active subscriptions whose planned payment date has passed.
$stmt = $pdo->prepare("
    SELECT cs.subscription_id, cs.user_id, cs.next_payment_date, cs.price,
           u.first_name, u.last_name, u.phone, u.class,
           c.title AS course_title, c.price AS course_price,
           DATEDIFF(CURDATE(), cs.next_payment_date) AS days_overdue,
           (SELECT MAX(t.payment_date) FROM transactions t
             WHERE t.course_sub_id = cs.subscription_id AND t.tenant_id = cs.tenant_id
               AND t.voided_at IS NULL) AS last_payment_date,
           (SELECT sl.sent_at FROM sms_logs sl
             WHERE sl.course_sub_id = cs.subscription_id AND sl.tenant_id = cs.tenant_id
               AND sl.status IN ('sent', 'mock_sent')
             ORDER BY sl.sent_at DESC LIMIT 1) AS last_sms_at,
           (SELECT sl.trigger_type FROM sms_logs sl
             WHERE sl.course_sub_id = cs.subscription_id AND sl.tenant_id = cs.tenant_id
               AND sl.status IN ('sent', 'mock_sent')
             ORDER BY sl.sent_at DESC LIMIT 1) AS last_sms_trigger
    FROM course_subs cs
    JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
    JOIN courses c ON c.course_id = cs.course_id AND c.tenant_id = cs.tenant_id
    WHERE cs.tenant_id = ?
      AND cs.status = 'active'
      AND c.price > 0
      AND cs.next_payment_date IS NOT NULL
      AND cs.next_payment_date < CURDATE()
    ORDER BY days_overdue DESC, u.last_name ASC
");
$stmt->execute([$tid]);
$overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group 2: enrolled but never paid. Ordered by how long they have been waiting, which is
// the closest equivalent to "days overdue" for a subscription that has no payment date yet.
$stmt = $pdo->prepare("
    SELECT cs.subscription_id, cs.user_id, cs.purchase_date, cs.price,
           u.first_name, u.last_name, u.phone, u.class,
           c.title AS course_title, c.price AS course_price,
           DATEDIFF(CURDATE(), DATE(cs.purchase_date)) AS days_waiting,
           (SELECT sl.sent_at FROM sms_logs sl
             WHERE sl.course_sub_id = cs.subscription_id AND sl.tenant_id = cs.tenant_id
               AND sl.status IN ('sent', 'mock_sent')
             ORDER BY sl.sent_at DESC LIMIT 1) AS last_sms_at,
           (SELECT sl.trigger_type FROM sms_logs sl
             WHERE sl.course_sub_id = cs.subscription_id AND sl.tenant_id = cs.tenant_id
               AND sl.status IN ('sent', 'mock_sent')
             ORDER BY sl.sent_at DESC LIMIT 1) AS last_sms_trigger
    FROM course_subs cs
    JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
    JOIN courses c ON c.course_id = cs.course_id AND c.tenant_id = cs.tenant_id
    WHERE cs.tenant_id = ?
      AND cs.status = 'pending'
      AND c.price > 0
    ORDER BY days_waiting DESC, u.last_name ASC
");
$stmt->execute([$tid]);
$neverPaid = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Amount due: the course's current price is the authoritative figure to collect. The
// subscription's own `price` is a snapshot from enrolment time and may be stale.
$sumDue = 0;
foreach ($overdue as $d)   { $sumDue += (int)$d['course_price']; }
foreach ($neverPaid as $d) { $sumDue += (int)$d['course_price']; }

$fmtMoney = fn($v) => number_format((float)$v, 0, '.', ' ');

// Uzbek mobile numbers are stored in assorted shapes; strip everything but digits and a
// leading + so tel: links work regardless of how the number was entered.
$telHref = function (?string $phone): ?string {
    $clean = preg_replace('/[^0-9+]/', '', (string)$phone);
    return strlen(preg_replace('/\D/', '', (string)$clean)) >= 7 ? $clean : null;
};

// Pre-written WhatsApp/copy reminder text (Uzbek) — one template for an overdue active
// subscription (mentions how many days late), one for a never-paid enrolment (no days
// mentioned, since there is nothing overdue yet, only unstarted).
$buildMessage = function (array $d, string $daysKey) use ($fmtMoney): string {
    $firstName = (string)($d['first_name'] ?? '');
    $courseTitle = (string)($d['course_title'] ?? '');
    $price = $fmtMoney($d['course_price'] ?? 0);
    if ($daysKey === 'days_overdue') {
        $days = (int)($d['days_overdue'] ?? 0);
        return "Assalomu alaykum, {$firstName}! {$courseTitle} kursi uchun to'lov muddati ({$days} kun oldin) yetib keldi. "
             . "To'lov miqdori: {$price} so'm. Iltimos, to'lovni amalga oshirishingizni so'raymiz. Savollar bo'lsa, adminga murojaat qiling.";
    }
    return "Assalomu alaykum, {$firstName}! Siz {$courseTitle} kursiga ro'yxatdan o'tgansiz. "
         . "Kursni boshlash uchun to'lov miqdori: {$price} so'm. O'rningizni band qilish uchun to'lovni tasdiqlashingizni so'raymiz.";
};

// Renders the "last automated SMS reminder" status for a debtor row (P0-4).
$smsTriggerLabels = ['reminder_3days' => __('adm_sms_reminder'), 'overdue_1day' => __('adm_sms_overdue'), 'manual' => __('adm_sms_manual')];
$renderSmsStatus = function (array $d) use ($smsTriggerLabels): string {
    if (empty($d['last_sms_at'])) {
        return '<span style="color:#d1d5db;font-size:12px;">' . htmlspecialchars(__('adm_sms_never')) . '</span>';
    }
    $date = htmlspecialchars(date('d.m', strtotime($d['last_sms_at'])), ENT_QUOTES, 'UTF-8');
    $trigger = htmlspecialchars($smsTriggerLabels[$d['last_sms_trigger']] ?? (string)$d['last_sms_trigger'], ENT_QUOTES, 'UTF-8');
    return '<span style="display:inline-flex;align-items:center;gap:4px;background:#f3f4f6;color:#4b5563;border-radius:6px;padding:3px 8px;font-size:11.5px;font-weight:600;white-space:nowrap;">'
         . '<i data-lucide="mail-check" style="width:12px;height:12px;"></i>'
         . "SMS: {$date} ({$trigger})</span>";
};

if (($_GET['action'] ?? '') === 'export_csv') {
    $filename = 'debtors_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [__('adm_col_student'), __('adm_col_phone'), __('adm_col_class'), __('adm_col_course'), __('adm_csv_price'), __('adm_csv_days_overdue'), __('adm_col_type'), __('adm_csv_last_payment')], ';');

    foreach ($overdue as $d) {
        $fullName = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
        fputcsv($out, [
            $fullName !== '' ? $fullName : '—',
            $d['phone'] ?? '',
            $d['class'] ?? '',
            $d['course_title'],
            (int)$d['course_price'],
            (int)$d['days_overdue'],
            __('adm_status_overdue'),
            !empty($d['last_payment_date']) ? date('d.m.Y', strtotime($d['last_payment_date'])) : '',
        ], ';');
    }
    foreach ($neverPaid as $d) {
        $fullName = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
        fputcsv($out, [
            $fullName !== '' ? $fullName : '—',
            $d['phone'] ?? '',
            $d['class'] ?? '',
            $d['course_title'],
            (int)$d['course_price'],
            (int)$d['days_waiting'],
            __('adm_status_unpaid'),
            '',
        ], ';');
    }

    fclose($out);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 class="page-title" style="margin:0;"><?= htmlspecialchars(__('adm_debtors')) ?></h1>
    <a href="debtors.php?action=export_csv" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 transition-colors" style="text-decoration:none;">
        📥 <?= htmlspecialchars(__('adm_export_csv')) ?>
    </a>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:12px;padding:12px 16px;font-size:14px;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
        <i data-lucide="check-circle" style="width:18px;height:18px;color:#059669;"></i>
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:12px;padding:12px 16px;font-size:14px;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
        <i data-lucide="alert-circle" style="width:18px;height:18px;color:#dc2626;"></i>
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
    <div style="flex:1;min-width:190px;background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);color:#fff;border-radius:14px;padding:20px;">
        <div style="font-size:13px;opacity:.9;margin-bottom:6px;"><?= htmlspecialchars(__('adm_kpi_overdue')) ?></div>
        <div style="font-size:28px;font-weight:700;line-height:1.1;"><?= count($overdue) ?></div>
        <div style="font-size:12px;opacity:.85;margin-top:4px;"><?= htmlspecialchars(__('adm_kpi_active_subs')) ?></div>
    </div>
    <div style="flex:1;min-width:190px;background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);color:#fff;border-radius:14px;padding:20px;">
        <div style="font-size:13px;opacity:.9;margin-bottom:6px;"><?= htmlspecialchars(__('adm_kpi_never_paid')) ?></div>
        <div style="font-size:28px;font-weight:700;line-height:1.1;"><?= count($neverPaid) ?></div>
        <div style="font-size:12px;opacity:.85;margin-top:4px;"><?= htmlspecialchars(__('adm_kpi_enrolled_unpaid')) ?></div>
    </div>
    <div style="flex:1;min-width:190px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;">
        <div style="font-size:13px;color:#6b7280;margin-bottom:6px;"><?= htmlspecialchars(__('adm_kpi_to_collect')) ?></div>
        <div style="font-size:28px;font-weight:700;color:#111827;line-height:1.1;white-space:nowrap;"><?= $fmtMoney($sumDue) ?> <?= htmlspecialchars($currencyLabel) ?></div>
        <div style="font-size:12px;color:#9ca3af;margin-top:4px;"><?= htmlspecialchars(__('adm_kpi_current_prices')) ?></div>
    </div>
</div>

<?php
/**
 * Both groups render the same columns; only the "overdue" metric differs in meaning
 * (days past the payment date vs days since enrolment), so the table is emitted once.
 */
$renderTable = function (array $rows, string $daysKey, string $daysLabel, string $emptyText) use ($fmtMoney, $telHref, $buildMessage, $renderSmsStatus, $currencyLabel) {
    if (empty($rows)) {
        echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:32px;text-align:center;color:#6b7280;font-size:14px;">'
           . htmlspecialchars($emptyText) . '</div>';
        return;
    }
    ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;">
                <thead>
                    <tr style="border-bottom:1px solid #e5e7eb;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;">
                        <th style="padding:12px 16px;font-weight:600;"><?= htmlspecialchars(__('adm_col_student')) ?></th>
                        <th style="padding:12px 16px;font-weight:600;"><?= htmlspecialchars(__('adm_col_class')) ?></th>
                        <th style="padding:12px 16px;font-weight:600;"><?= htmlspecialchars(__('adm_col_course')) ?></th>
                        <th style="padding:12px 16px;font-weight:600;"><?= htmlspecialchars(__('adm_col_phone')) ?></th>
                        <th style="padding:12px 16px;font-weight:600;text-align:center;"><?= htmlspecialchars($daysLabel) ?></th>
                        <th style="padding:12px 16px;font-weight:600;text-align:right;"><?= htmlspecialchars(__('adm_col_due')) ?></th>
                        <th style="padding:12px 16px;font-weight:600;">SMS</th>
                        <th style="padding:12px 16px;font-weight:600;text-align:right;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $d):
                    $days = (int)$d[$daysKey];
                    $tel  = $telHref($d['phone'] ?? '');
                    // Escalating urgency: a week late is a reminder, a month late is a problem.
                    $badgeBg = $days >= 30 ? '#fee2e2' : ($days >= 7 ? '#fef3c7' : '#f3f4f6');
                    $badgeFg = $days >= 30 ? '#991b1b' : ($days >= 7 ? '#92400e' : '#4b5563');
                    $fullName = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
                ?>
                    <tr style="border-bottom:1px solid #f3f4f6;font-size:14px;">
                        <td style="padding:14px 16px;font-weight:600;color:#111827;">
                            <?= htmlspecialchars($fullName !== '' ? $fullName : '—') ?>
                        </td>
                        <td style="padding:14px 16px;color:#6b7280;"><?= htmlspecialchars((string)($d['class'] ?? '—')) ?></td>
                        <td style="padding:14px 16px;color:#374151;"><?= htmlspecialchars((string)$d['course_title']) ?></td>
                        <td style="padding:14px 16px;white-space:nowrap;">
                            <?php if ($tel):
                                $waPhone = preg_replace('/\D+/', '', $tel);
                                $msg = $buildMessage($d, $daysKey);
                                $waHref = 'https://wa.me/' . rawurlencode($waPhone) . '?text=' . rawurlencode($msg);
                            ?>
                                <div style="font-size:12.5px;color:#374151;font-weight:500;margin-bottom:5px;">
                                    <?= htmlspecialchars((string)$d['phone'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div style="display:inline-flex;gap:4px;flex-wrap:wrap;align-items:center;">
                                    <a href="tel:<?= htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') ?>" title="Qo'ng'iroq qilish"
                                       style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;min-height:32px;border-radius:8px;background:#eff6ff;color:#2563eb;text-decoration:none;font-size:15px;line-height:1;">📞</a>
                                    <a href="<?= htmlspecialchars($waHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp"
                                       style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;min-height:32px;border-radius:8px;background:#ecfdf5;color:#059669;text-decoration:none;font-size:15px;line-height:1;">💬</a>
                                    <button type="button" class="copy-msg-btn" onclick="copyDebtorMsg(this)"
                                            data-msg="<?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>" title="Xabarni nusxalash"
                                            style="display:inline-flex;align-items:center;justify-content:center;min-width:32px;min-height:32px;padding:0 8px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;color:#4b5563;cursor:pointer;font-size:15px;line-height:1;">📋</button>
                                </div>
                            <?php else: ?>
                                <span style="color:#d1d5db;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 16px;text-align:center;white-space:nowrap;">
                            <span style="display:inline-block;background:<?= $badgeBg ?>;color:<?= $badgeFg ?>;border-radius:6px;padding:3px 10px;font-size:12px;font-weight:700;">
                                <?= $days ?> <?= htmlspecialchars(__('adm_days_short')) ?>
                            </span>
                        </td>
                        <td style="padding:14px 16px;text-align:right;font-weight:600;color:#111827;white-space:nowrap;">
                            <?= $fmtMoney($d['course_price']) ?> <?= htmlspecialchars($currencyLabel) ?>
                        </td>
                        <td style="padding:14px 16px;white-space:nowrap;">
                            <?= $renderSmsStatus($d) ?>
                        </td>
                        <td style="padding:14px 16px;text-align:right;white-space:nowrap;">
                            <button type="button"
                                    onclick="openPayModal(<?= htmlspecialchars(json_encode([
                                        'sub_id' => (int)$d['subscription_id'],
                                        'student_name' => trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')),
                                        'course_title' => (string)($d['course_title'] ?? ''),
                                        'course_price' => (float)($d['course_price'] ?? 0),
                                        'sub_status' => ($daysKey === 'days_overdue' ? 'active' : 'pending'),
                                        'next_payment_default' => date('Y-m-d', strtotime('+30 days')),
                                    ]), ENT_QUOTES, 'UTF-8') ?>)"
                                    style="display:inline-flex;align-items:center;gap:6px;background:#2563eb;color:#fff;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:500;border:none;cursor:pointer;">
                                <i data-lucide="credit-card" style="width:13px;height:13px;"></i>
                                <?= htmlspecialchars(__('adm_accept_payment')) ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
};
?>

<h2 style="font-size:16px;font-weight:600;color:#111827;margin:0 0 12px;">
    <?= htmlspecialchars(__('adm_overdue_title')) ?>
    <span style="font-weight:400;color:#9ca3af;font-size:13px;"><?= htmlspecialchars(__('adm_overdue_hint')) ?></span>
</h2>
<?php $renderTable($overdue, 'days_overdue', __('adm_overdue_col'), __('adm_overdue_empty')); ?>

<h2 style="font-size:16px;font-weight:600;color:#111827;margin:32px 0 12px;">
    <?= htmlspecialchars(__('adm_never_title')) ?>
    <span style="font-weight:400;color:#9ca3af;font-size:13px;"><?= htmlspecialchars(__('adm_never_hint')) ?></span>
</h2>
<?php $renderTable($neverPaid, 'days_waiting', __('adm_never_col'), __('adm_never_empty')); ?>

<script>
function copyDebtorMsg(btn) {
    var msg = btn.getAttribute('data-msg');
    var original = btn.innerHTML;
    var originalBg = btn.style.background;
    var originalColor = btn.style.color;

    function showCopied() {
        btn.innerHTML = '✓ Nusxa olindi';
        btn.style.background = '#d1fae5';
        btn.style.color = '#059669';
        setTimeout(function () {
            btn.innerHTML = original;
            btn.style.background = originalBg;
            btn.style.color = originalColor;
        }, 2000);
    }

    function fallbackCopy() {
        var ta = document.createElement('textarea');
        ta.value = msg;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(msg).then(showCopied, function () {
            fallbackCopy();
            showCopied();
        });
    } else {
        fallbackCopy();
        showCopied();
    }
}
</script>

<!-- In-place Payment Modal -->
<div id="payModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);backdrop-filter:blur(2px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);max-width:440px;width:100%;overflow:hidden;border:1px solid #e2e8f0;" onclick="event.stopPropagation()">
        <div style="padding:18px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a;" id="payModalTitle"><?= htmlspecialchars(__('adm_modal_title')) ?></h3>
                <p style="margin:3px 0 0;font-size:12px;color:#64748b;" id="payModalSubtitle"><?= htmlspecialchars(__('adm_col_course')) ?></p>
            </div>
            <button type="button" onclick="closePayModal()" style="background:none;border:none;color:#94a3b8;cursor:pointer;padding:4px;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="x" style="width:18px;height:18px;"></i>
            </button>
        </div>

        <form method="POST" action="/admin/finance/debtors.php" style="padding:20px 22px;margin:0;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="accept_payment" value="1">
            <input type="hidden" name="subscription_id" id="modalSubId" value="">
            <input type="hidden" name="sub_status" id="modalSubStatus" value="">

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;margin-bottom:6px;"><?= htmlspecialchars(__('adm_modal_amount')) ?></label>
                <input type="number" name="amount" id="modalAmount" min="1" step="1" required style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:9px 12px;font-size:14px;font-weight:600;color:#0f172a;outline:none;">
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;margin-bottom:6px;"><?= htmlspecialchars(__('adm_modal_method')) ?></label>
                <select name="payment_method" required style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:9px 12px;font-size:14px;color:#0f172a;background:#fff;outline:none;">
                    <option value="cash" selected><?= htmlspecialchars(__('adm_method_cash')) ?></option>
                    <option value="card"><?= htmlspecialchars(__('adm_modal_card')) ?></option>
                    <option value="transfer"><?= htmlspecialchars(__('adm_modal_transfer')) ?></option>
                </select>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;margin-bottom:6px;"><?= htmlspecialchars(__('adm_modal_date')) ?></label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:9px 12px;font-size:14px;color:#0f172a;outline:none;">
            </div>

            <div id="modalNextDateGroup" style="display:none;margin-bottom:16px;">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;margin-bottom:6px;"><?= htmlspecialchars(__('adm_modal_next_date')) ?></label>
                <input type="date" name="next_payment_date" id="modalNextDate" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:9px 12px;font-size:14px;color:#0f172a;outline:none;">
            </div>

            <div style="margin-bottom:22px;">
                <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;margin-bottom:6px;"><?= htmlspecialchars(__('adm_modal_note')) ?></label>
                <input type="text" name="notes" placeholder="<?= htmlspecialchars(__('adm_modal_note_ph')) ?>" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:9px 12px;font-size:13px;color:#0f172a;outline:none;">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closePayModal()" style="background:#f1f5f9;color:#475569;border:none;border-radius:8px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;">
                    <?= htmlspecialchars(__('adm_cancel')) ?>
                </button>
                <button type="submit" style="display:inline-flex;align-items:center;gap:6px;background:#2563eb;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;">
                    <i data-lucide="check" style="width:15px;height:15px;"></i>
                    <?= htmlspecialchars(__('adm_modal_confirm')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayModal(data) {
    document.getElementById('modalSubId').value = data.sub_id;
    document.getElementById('modalSubStatus').value = data.sub_status;
    document.getElementById('payModalTitle').textContent = <?= json_encode(__('adm_modal_title') . ' — ') ?> + data.student_name;
    document.getElementById('payModalSubtitle').textContent = data.course_title;
    document.getElementById('modalAmount').value = data.course_price;

    var nextGroup = document.getElementById('modalNextDateGroup');
    var nextInput = document.getElementById('modalNextDate');
    if (data.sub_status === 'pending') {
        nextGroup.style.display = 'block';
        nextInput.required = true;
        nextInput.value = data.next_payment_default;
    } else {
        nextGroup.style.display = 'none';
        nextInput.required = false;
        nextInput.value = '';
    }

    var m = document.getElementById('payModal');
    m.style.display = 'flex';
    if (window.lucide && window.lucide.createIcons) {
        window.lucide.createIcons();
    }
}

function closePayModal() {
    var m = document.getElementById('payModal');
    m.style.display = 'none';
}

document.getElementById('payModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePayModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
