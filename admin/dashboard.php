<?php
require_once __DIR__ . '/authorization.php';

// Teachers have no financial visibility — the dashboard's revenue/cash KPIs
// are off-limits, so send them straight to their own groups instead.
if (($_SESSION['admin_role'] ?? '') === 'teacher') {
    header('Location: /admin/groups/index.php');
    exit;
}

require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/includes/lang.php';

$currencyLabel = __('adm_currency');

$pageTitle = __('nav_dashboard');
$tid = Tenant::getId();

// ── KPI queries (Tenant isolated) ──
$kpis = [];

// Total students
$r = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?");
$r->execute([$tid]);
$kpis['students'] = (int)$r->fetchColumn();

// New students last 7 days
$r = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$r->execute([$tid]);
$kpis['new_students_7d'] = (int)$r->fetchColumn();

// Total courses
$r = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE tenant_id = ?");
$r->execute([$tid]);
$kpis['courses'] = (int)$r->fetchColumn();

// Revenue 30 days (actual cash ledger)
$signedAmount = "CASE WHEN type = 'refund' THEN -amount ELSE amount END";
$r = $pdo->prepare("
    SELECT COALESCE(SUM({$signedAmount}), 0)
    FROM transactions
    WHERE tenant_id = ?
      AND voided_at IS NULL
      AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      AND payment_date <= CURDATE()
");
$r->execute([$tid]);
$kpis['revenue_30d'] = (int)$r->fetchColumn();

// Revenue previous 30 days (from 60 to 30 days ago)
$r = $pdo->prepare("
    SELECT COALESCE(SUM({$signedAmount}), 0)
    FROM transactions
    WHERE tenant_id = ?
      AND voided_at IS NULL
      AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
      AND payment_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$r->execute([$tid]);
$revPrev30d = (int)$r->fetchColumn();
if ($revPrev30d > 0) {
    $revenueDelta = round((($kpis['revenue_30d'] - $revPrev30d) / $revPrev30d) * 100);
} else {
    $revenueDelta = $kpis['revenue_30d'] > 0 ? 100 : 0;
}
$revenueUp = $revenueDelta >= 0;

// Pending payments
$r = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND status = 'pending' AND deleted_at IS NULL");
$r->execute([$tid]);
$kpis['pending_payments'] = (int)$r->fetchColumn();

// Active subscriptions
$r = $pdo->prepare("SELECT COUNT(*) FROM course_subs WHERE tenant_id = ? AND status = 'active'");
$r->execute([$tid]);
$kpis['active_subs'] = (int)$r->fetchColumn();

// ── Recent Cashbox Transactions (5 rows) ──
$r = $pdo->prepare("
    SELECT t.transaction_id, t.payment_date, t.amount, t.type, t.payment_method,
           u.first_name, u.last_name, u.class,
           c.title AS course_title
    FROM transactions t
    LEFT JOIN users u ON u.user_id = t.user_id AND u.tenant_id = t.tenant_id
    LEFT JOIN course_subs cs ON cs.subscription_id = t.course_sub_id AND cs.tenant_id = t.tenant_id
    LEFT JOIN courses c ON c.course_id = cs.course_id AND c.tenant_id = t.tenant_id
    WHERE t.tenant_id = ? AND t.voided_at IS NULL
      AND t.payment_date <= CURDATE()
    ORDER BY t.payment_date DESC, t.transaction_id DESC
    LIMIT 5
");
$r->execute([$tid]);
$recentTransactions = $r->fetchAll(PDO::FETCH_ASSOC);

// ── Debtors: Overdue & Unpaid (5 rows) ──
$r = $pdo->prepare("
    SELECT cs.subscription_id, cs.user_id, cs.status, cs.next_payment_date, cs.purchase_date,
           u.first_name, u.last_name, u.phone, u.class,
           c.title AS course_title, c.price AS course_price,
           CASE
               WHEN cs.status = 'active' AND cs.next_payment_date < CURDATE()
                   THEN DATEDIFF(CURDATE(), cs.next_payment_date)
               ELSE DATEDIFF(CURDATE(), DATE(cs.purchase_date))
           END AS days_diff,
           CASE
               WHEN cs.status = 'active' AND cs.next_payment_date < CURDATE() THEN 'overdue'
               ELSE 'unpaid'
           END AS debtor_type
    FROM course_subs cs
    JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
    JOIN courses c ON c.course_id = cs.course_id AND c.tenant_id = cs.tenant_id
    WHERE cs.tenant_id = ?
      AND c.price > 0
      AND (
          (cs.status = 'active' AND cs.next_payment_date IS NOT NULL AND cs.next_payment_date < CURDATE())
          OR (cs.status = 'pending')
      )
    ORDER BY
        CASE WHEN cs.status = 'active' THEN 0 ELSE 1 END ASC,
        days_diff DESC
    LIMIT 5
");
$r->execute([$tid]);
$recentDebtors = $r->fetchAll(PDO::FETCH_ASSOC);

$methodBadges = [
    'cash'     => ['label' => __('adm_method_cash'), 'class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/60'],
    'card'     => ['label' => __('adm_method_card'), 'class' => 'bg-blue-50 text-blue-700 border border-blue-200/60'],
    'transfer' => ['label' => __('adm_method_transfer'), 'class' => 'bg-purple-50 text-purple-700 border border-purple-200/60'],
];

include __DIR__ . '/includes/header.php';
?>

<div class="mx-auto flex max-w-7xl flex-col gap-6">

    <!-- Header + quick actions -->
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars(__('nav_dashboard')) ?></h1>
        <div class="flex gap-2">
            <a href="/admin/students/" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-900 no-underline shadow-sm transition-colors hover:bg-slate-50">
                <i data-lucide="user-plus" class="size-4"></i>
                <?= htmlspecialchars(__('btn_student')) ?>
            </a>
            <a href="/admin/finance/index.php" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white no-underline shadow-sm transition-opacity hover:opacity-90">
                <i data-lucide="plus" class="size-4"></i>
                <?= htmlspecialchars(__('btn_payment')) ?>
            </a>
        </div>
    </header>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <!-- Students -->
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('total_students')) ?></p>
            <p class="mt-3 text-4xl font-bold text-slate-900"><?= $kpis['students'] ?></p>
            <div class="mt-3 flex items-center justify-between text-sm font-medium">
                <span class="text-emerald-500">+<?= $kpis['new_students_7d'] ?> <?= htmlspecialchars(__('new_students_7d_text')) ?></span>
                <span class="text-xs font-normal text-slate-400"><?= $kpis['active_subs'] ?> <?= htmlspecialchars(__('adm_in_groups')) ?></span>
            </div>
        </div>

        <!-- Courses -->
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('total_courses')) ?></p>
            <p class="mt-3 text-4xl font-bold text-slate-900"><?= $kpis['courses'] ?></p>
            <a href="/admin/courses/" class="mt-3 inline-flex w-fit items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 no-underline transition-colors hover:bg-slate-200">
                <?= htmlspecialchars(__('transfer_to_courses')) ?>
                <i data-lucide="arrow-right" class="size-4"></i>
            </a>
        </div>

        <!-- Revenue -->
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('revenue_30d')) ?></p>
            <p class="mt-3 text-3xl font-bold text-emerald-500"><?= number_format($kpis['revenue_30d'], 0, '.', ' ') ?> <?= $currencyLabel ?></p>
            <p class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium <?= $revenueUp ? 'text-emerald-500' : 'text-red-500' ?>">
                <i data-lucide="<?= $revenueUp ? 'trending-up' : 'trending-down' ?>" class="size-4"></i>
                <?= ($revenueUp ? '+' : '') . $revenueDelta ?>% <?= htmlspecialchars(__('vs_prev_30d')) ?>
            </p>
        </div>

        <!-- Pending Payments -->
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('pending_payments')) ?></p>
            <p class="mt-3 text-4xl font-bold text-amber-500"><?= $kpis['pending_payments'] ?></p>
            <a href="/admin/payments/?status=pending" class="mt-3 inline-flex w-fit items-center gap-1.5 rounded-lg bg-amber-100/70 px-3 py-1.5 text-sm font-medium text-amber-800 no-underline transition-colors hover:bg-amber-200">
                <?= htmlspecialchars(__('view_all')) ?>
                <i data-lucide="arrow-right" class="size-4"></i>
            </a>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <!-- Касса -->
        <section class="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <h2 class="mb-4 text-xl font-bold text-slate-900"><?= htmlspecialchars(__('adm_cashbox')) ?></h2>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-2 py-3 font-semibold"><?= htmlspecialchars(__('adm_col_student')) ?></th>
                            <th class="px-2 py-3 font-semibold"><?= htmlspecialchars(__('adm_col_course')) ?></th>
                            <th class="px-2 py-3 font-semibold text-center"><?= htmlspecialchars(__('adm_col_method')) ?></th>
                            <th class="px-2 py-3 font-semibold text-right"><?= htmlspecialchars(__('adm_col_amount')) ?></th>
                            <th class="px-2 py-3 text-right font-semibold"><?= htmlspecialchars(__('adm_col_date')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentTransactions): ?>
                            <tr>
                                <td colspan="5" class="px-2 py-6 text-center text-sm text-slate-500">
                                    <?= htmlspecialchars(__('no_data')) ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($recentTransactions as $t):
                                $isRefund = $t['type'] === 'refund';
                                $mb = $methodBadges[$t['payment_method']] ?? ['label' => $t['payment_method'], 'class' => 'bg-slate-100 text-slate-700 border border-slate-200/60'];
                                $studentName = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
                            ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/60 transition-colors">
                                    <td class="px-2 py-4 font-medium text-slate-900">
                                        <?= $studentName !== '' ? htmlspecialchars($studentName) : '<span class="text-slate-300">' . htmlspecialchars(__('adm_deleted')) . '</span>' ?>
                                    </td>
                                    <td class="px-2 py-4 text-slate-500"><?= htmlspecialchars($t['course_title'] ?: '—') ?></td>
                                    <td class="px-2 py-4 text-center">
                                        <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-semibold <?= $mb['class'] ?>">
                                            <?= htmlspecialchars($mb['label']) ?>
                                        </span>
                                    </td>
                                    <td class="px-2 py-4 text-right font-semibold whitespace-nowrap <?= $isRefund ? 'text-red-600' : 'text-slate-900' ?>">
                                        <?= $isRefund ? '−' : '' ?><?= number_format($t['amount'], 0, '.', ' ') ?> <?= $currencyLabel ?>
                                    </td>
                                    <td class="px-2 py-4 text-right text-slate-500 whitespace-nowrap">
                                        <?= date('d.m.Y', strtotime($t['payment_date'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="/admin/finance/index.php" class="mt-4 inline-flex items-center gap-1.5 self-start text-sm font-medium text-blue-600 no-underline hover:underline">
                <?= htmlspecialchars(__('adm_open_cashbox')) ?>
                <i data-lucide="arrow-right" class="size-4"></i>
            </a>
        </section>

        <!-- Должники -->
        <section class="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <h2 class="mb-4 text-xl font-bold text-slate-900"><?= htmlspecialchars(__('adm_debtors')) ?></h2>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-2 py-3 font-semibold"><?= htmlspecialchars(__('adm_col_student')) ?></th>
                            <th class="px-2 py-3 font-semibold"><?= htmlspecialchars(__('adm_col_course')) ?></th>
                            <th class="px-2 py-3 font-semibold text-center"><?= htmlspecialchars(__('adm_col_type')) ?></th>
                            <th class="px-2 py-3 text-right font-semibold"><?= htmlspecialchars(__('adm_col_days')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentDebtors): ?>
                            <tr>
                                <td colspan="4" class="px-2 py-6 text-center text-sm text-slate-500">
                                    <?= htmlspecialchars(__('no_data')) ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($recentDebtors as $d):
                                $isOverdue = $d['debtor_type'] === 'overdue';
                                $studentName = trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''));
                            ?>
                                <tr onclick="window.location.href='/admin/students/view.php?user_id=<?= (int)$d['user_id'] ?>'" class="cursor-pointer border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/60 transition-colors">
                                    <td class="px-2 py-4 font-medium text-slate-900"><?= htmlspecialchars($studentName !== '' ? $studentName : '—') ?></td>
                                    <td class="px-2 py-4 text-slate-500"><?= htmlspecialchars($d['course_title']) ?></td>
                                    <td class="px-2 py-4 text-center">
                                        <?php if ($isOverdue): ?>
                                            <span class="inline-flex rounded-md bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700"><?= htmlspecialchars(__('adm_status_overdue')) ?></span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-md bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800"><?= htmlspecialchars(__('adm_status_unpaid')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2 py-4 text-right text-slate-500 whitespace-nowrap"><?= (int)$d['days_diff'] ?> <?= htmlspecialchars(__('adm_days_short')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="/admin/finance/debtors.php" class="mt-4 inline-flex items-center gap-1.5 self-end text-sm font-medium text-blue-600 no-underline hover:underline">
                <?= htmlspecialchars(__('view_all')) ?>
                <i data-lucide="arrow-right" class="size-4"></i>
            </a>
        </section>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
