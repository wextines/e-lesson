<?php
require_once __DIR__ . '/../authorization.php';
require_admin_role(['center_admin', 'super_admin']);
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php';

$pageTitle = 'Касса';
$tid = Tenant::getId();

// ── Period ────────────────────────────────────────────────────────────────────
// Everything on this page is scoped by transactions.payment_date (the business date
// the money was received), never by created_at (when it was typed in).
$period = $_GET['period'] ?? 'month';
$today = date('Y-m-d');

if ($period === 'today') {
    $from = $to = $today;
} elseif ($period === 'week') {
    // Week starts Monday, the local convention.
    $from = date('Y-m-d', strtotime('monday this week'));
    $to   = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period === 'custom') {
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to = date('Y-m-t'); }
    if ($from > $to) { [$from, $to] = [$to, $from]; }
} else {
    $period = 'month';
    $from = date('Y-m-01');
    $to   = date('Y-m-t');
}

// Refunds are stored as positive amounts distinguished by `type`, so every sum has to
// apply the sign explicitly — otherwise a refund would inflate the cashbox instead of
// reducing it. Only 'payment' rows exist today; this keeps the arithmetic correct as
// soon as the reversal UI lands.
const SIGNED_AMOUNT = "CASE WHEN t.type = 'refund' THEN -t.amount ELSE t.amount END";

// ── KPI ───────────────────────────────────────────────────────────────────────
$sql = "SELECT
            COALESCE(SUM(" . SIGNED_AMOUNT . "), 0) AS total,
            COALESCE(SUM(CASE WHEN t.payment_method = 'cash'     THEN " . SIGNED_AMOUNT . " END), 0) AS cash,
            COALESCE(SUM(CASE WHEN t.payment_method = 'card'     THEN " . SIGNED_AMOUNT . " END), 0) AS card,
            COALESCE(SUM(CASE WHEN t.payment_method = 'transfer' THEN " . SIGNED_AMOUNT . " END), 0) AS transfer,
            COUNT(*) AS cnt
        FROM transactions t
        WHERE t.tenant_id = ? AND t.voided_at IS NULL
          AND t.payment_date BETWEEN ? AND ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$tid, $from, $to]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC);

// "Ожидается до конца периода" is deliberately forward-looking only: course_subs
// .next_payment_date is a moving pointer that advances on every renewal, so what was due
// in a past period cannot be reconstructed after the fact. Asking only "what is still
// due between today and the end of this period" is a question the data can answer
// honestly. A period that has fully elapsed therefore expects nothing.
$expectFrom = max($from, $today);
$expected = 0.0;
if ($expectFrom <= $to) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.price), 0)
        FROM course_subs cs
        JOIN courses c ON c.course_id = cs.course_id AND c.tenant_id = cs.tenant_id
        WHERE cs.tenant_id = ?
          AND cs.status = 'active'
          AND cs.next_payment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tid, $expectFrom, $to]);
    $expected = (float)$stmt->fetchColumn();
}

// ── Per-admin reconciliation ──────────────────────────────────────────────────
// Cash is what the director physically counts; card+transfer is what the terminal and
// bank statement should show. Splitting the two per admin is what makes the cashbox
// checkable against a specific person on duty.
$stmt = $pdo->prepare("
    SELECT t.created_by,
           COUNT(*) AS cnt,
           COALESCE(SUM(CASE WHEN t.payment_method = 'cash' THEN " . SIGNED_AMOUNT . " END), 0) AS cash,
           COALESCE(SUM(CASE WHEN t.payment_method IN ('card','transfer') THEN " . SIGNED_AMOUNT . " END), 0) AS cashless,
           COALESCE(SUM(" . SIGNED_AMOUNT . "), 0) AS total
    FROM transactions t
    WHERE t.tenant_id = ? AND t.voided_at IS NULL
      AND t.payment_date BETWEEN ? AND ?
    GROUP BY t.created_by
    ORDER BY total DESC
");
$stmt->execute([$tid, $from, $to]);
$byAdmin = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Journal ───────────────────────────────────────────────────────────────────
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM transactions t
    WHERE t.tenant_id = ? AND t.voided_at IS NULL AND t.payment_date BETWEEN ? AND ?
");
$stmt->execute([$tid, $from, $to]);
$totalRows = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Every join is a LEFT JOIN by design: user_id and course_sub_id are nullable since
// migration 005, precisely so that deleting a student or a subscription can never erase
// the money record. Those rows must still appear in the cashbook, just without a name.
$stmt = $pdo->prepare("
    SELECT t.transaction_id, t.payment_date, t.created_at, t.amount, t.type,
           t.payment_method, t.notes, t.created_by, t.source_payment_id,
           u.first_name, u.last_name, u.class,
           c.title AS course_title,
           p.receipt_path
    FROM transactions t
    LEFT JOIN users u       ON u.user_id = t.user_id AND u.tenant_id = t.tenant_id
    LEFT JOIN course_subs cs ON cs.subscription_id = t.course_sub_id AND cs.tenant_id = t.tenant_id
    LEFT JOIN courses c     ON c.course_id = cs.course_id AND c.tenant_id = t.tenant_id
    LEFT JOIN payments p    ON p.payment_id = t.source_payment_id AND p.tenant_id = t.tenant_id
    WHERE t.tenant_id = ? AND t.voided_at IS NULL
      AND t.payment_date BETWEEN ? AND ?
    ORDER BY t.payment_date DESC, t.transaction_id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute([$tid, $from, $to]);
$journal = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fmtMoney = fn($v) => number_format((float)$v, 0, '.', ' ');
$methodLabels = ['cash' => 'Наличные', 'card' => 'Карта', 'transfer' => 'Перевод'];
$methodColors = ['cash' => ['#dcfce7', '#166534'], 'card' => ['#dbeafe', '#1e40af'], 'transfer' => ['#f3e8ff', '#6b21a8']];

$periodQs = fn(array $extra = []) => http_build_query(array_merge(
    ['period' => $period] + ($period === 'custom' ? ['from' => $from, 'to' => $to] : []),
    $extra
));

if (($_GET['action'] ?? '') === 'export_csv') {
    // The on-page journal is paginated at 50 rows for readability; a reconciliation
    // report must cover every transaction in the period, so this re-queries without LIMIT.
    $stmtFull = $pdo->prepare("
        SELECT t.transaction_id, t.payment_date, t.amount, t.type,
               t.payment_method, t.notes, t.created_by,
               u.first_name, u.last_name,
               c.title AS course_title
        FROM transactions t
        LEFT JOIN users u        ON u.user_id = t.user_id AND u.tenant_id = t.tenant_id
        LEFT JOIN course_subs cs ON cs.subscription_id = t.course_sub_id AND cs.tenant_id = t.tenant_id
        LEFT JOIN courses c      ON c.course_id = cs.course_id AND c.tenant_id = t.tenant_id
        WHERE t.tenant_id = ? AND t.voided_at IS NULL
          AND t.payment_date BETWEEN ? AND ?
        ORDER BY t.payment_date DESC, t.transaction_id DESC
    ");
    $stmtFull->execute([$tid, $from, $to]);
    $fullJournal = $stmtFull->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'cashbox_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Акт сверки кассы'], ';');
    fputcsv($out, ['Период', date('d.m.Y', strtotime($from)) . ' — ' . date('d.m.Y', strtotime($to))], ';');
    fwrite($out, "\n");

    fputcsv($out, ['СВОДКА ЗА ПЕРИОД'], ';');
    fputcsv($out, ['Показатель', 'Сумма (сум)'], ';');
    fputcsv($out, ['Всего', (int)$kpi['total']], ';');
    fputcsv($out, ['Наличные', (int)$kpi['cash']], ';');
    fputcsv($out, ['Карты', (int)$kpi['card']], ';');
    fputcsv($out, ['Переводы', (int)$kpi['transfer']], ';');
    fputcsv($out, ['Ожидается', (int)$expected], ';');
    fwrite($out, "\n");

    fputcsv($out, ['СВЕРКА ПО АДМИНИСТРАТОРАМ'], ';');
    fputcsv($out, ['Администратор', 'Наличные', 'Безналичные', 'Итого'], ';');
    foreach ($byAdmin as $a) {
        fputcsv($out, [$a['created_by'], (int)$a['cash'], (int)$a['cashless'], (int)$a['total']], ';');
    }
    fwrite($out, "\n");

    fputcsv($out, ['РЕЕСТР ТРАНЗАКЦИЙ'], ';');
    fputcsv($out, ['Дата', 'Студент', 'Курс', 'Метод', 'Сумма', 'Администратор', 'Примечание'], ';');
    foreach ($fullJournal as $t) {
        $name = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
        $signedAmount = $t['type'] === 'refund' ? -(int)$t['amount'] : (int)$t['amount'];
        fputcsv($out, [
            date('d.m.Y', strtotime($t['payment_date'])),
            $name !== '' ? $name : '— удалён —',
            $t['course_title'] ?? '',
            $methodLabels[$t['payment_method']] ?? $t['payment_method'],
            $signedAmount,
            $t['created_by'],
            $t['notes'] ?? '',
        ], ';');
    }

    fclose($out);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Касса</h1>

<div style="margin-bottom:16px;">
    <a href="index.php?action=export_csv&period=<?= htmlspecialchars($period) ?>&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 transition-colors" style="text-decoration:none;">
        📥 Скачать акт сверки (.csv)
    </a>
</div>

<!-- Period switcher -->
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:20px;">
    <?php foreach (['today' => 'Сегодня', 'week' => 'Эта неделя', 'month' => 'Этот месяц'] as $key => $label):
        $on = $period === $key; ?>
        <a href="?period=<?= $key ?>" style="text-decoration:none;padding:8px 16px;border-radius:10px;font-size:14px;font-weight:500;border:1px solid <?= $on ? '#2563eb' : '#e5e7eb' ?>;background:<?= $on ? '#2563eb' : '#fff' ?>;color:<?= $on ? '#fff' : '#4b5563' ?>;">
            <?= $label ?>
        </a>
    <?php endforeach; ?>

    <form method="get" style="display:flex;gap:8px;align-items:center;margin:0;">
        <input type="hidden" name="period" value="custom">
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" style="border:1px solid <?= $period === 'custom' ? '#2563eb' : '#e5e7eb' ?>;border-radius:10px;padding:7px 10px;font-size:14px;">
        <span style="color:#9ca3af;font-size:13px;">—</span>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" style="border:1px solid <?= $period === 'custom' ? '#2563eb' : '#e5e7eb' ?>;border-radius:10px;padding:7px 10px;font-size:14px;">
        <button type="submit" style="border:1px solid #e5e7eb;background:#fff;color:#4b5563;border-radius:10px;padding:8px 16px;font-size:14px;font-weight:500;cursor:pointer;">
            Показать
        </button>
    </form>
</div>

<div style="font-size:13px;color:#6b7280;margin-bottom:16px;">
    Период: <strong style="color:#374151;"><?= date('d.m.Y', strtotime($from)) ?> — <?= date('d.m.Y', strtotime($to)) ?></strong>
    · операций: <strong style="color:#374151;"><?= (int)$kpi['cnt'] ?></strong>
</div>

<!-- KPI -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
    <div style="flex:1.4;min-width:230px;background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#fff;border-radius:14px;padding:22px;">
        <div style="font-size:13px;opacity:.9;margin-bottom:6px;">Всего поступило</div>
        <div style="font-size:30px;font-weight:700;line-height:1.1;white-space:nowrap;"><?= $fmtMoney($kpi['total']) ?> сум</div>
        <div style="font-size:12px;opacity:.85;margin-top:4px;">фактический приход за период</div>
    </div>
    <?php foreach ([['cash', 'Наличные', '#f0fdf4', '#166534'], ['card', 'Карта / Терминал', '#eff6ff', '#1e40af'], ['transfer', 'Перевод', '#faf5ff', '#6b21a8']] as [$k, $label, $bg, $fg]): ?>
        <div style="flex:1;min-width:165px;background:<?= $bg ?>;border:1px solid #e5e7eb;border-radius:14px;padding:22px;">
            <div style="font-size:13px;color:#6b7280;margin-bottom:6px;"><?= $label ?></div>
            <div style="font-size:21px;font-weight:700;color:<?= $fg ?>;line-height:1.1;white-space:nowrap;"><?= $fmtMoney($kpi[$k]) ?> сум</div>
        </div>
    <?php endforeach; ?>
    <div style="flex:1;min-width:190px;background:#fff;border:1px dashed #d1d5db;border-radius:14px;padding:22px;">
        <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">Ожидается до конца периода</div>
        <div style="font-size:21px;font-weight:700;color:#111827;line-height:1.1;white-space:nowrap;"><?= $fmtMoney($expected) ?> сум</div>
        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">по активным подпискам</div>
    </div>
</div>

<!-- Per-admin reconciliation -->
<h2 style="font-size:16px;font-weight:600;color:#111827;margin:28px 0 12px;">
    Кто принял оплату
    <span style="font-weight:400;color:#9ca3af;font-size:13px;">— сверка кассы по администраторам</span>
</h2>
<?php if (empty($byAdmin)): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:24px;text-align:center;color:#6b7280;font-size:14px;">
        За выбранный период операций не было.
    </div>
<?php else: ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;">
                <thead>
                    <tr style="border-bottom:1px solid #e5e7eb;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;">
                        <th style="padding:11px 16px;font-weight:600;">Администратор</th>
                        <th style="padding:11px 16px;font-weight:600;text-align:center;">Операций</th>
                        <th style="padding:11px 16px;font-weight:600;text-align:right;">Наличными</th>
                        <th style="padding:11px 16px;font-weight:600;text-align:right;">Безналом</th>
                        <th style="padding:11px 16px;font-weight:600;text-align:right;">Всего</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($byAdmin as $a): ?>
                    <tr style="border-bottom:1px solid #f3f4f6;font-size:14px;">
                        <td style="padding:12px 16px;font-weight:600;color:#111827;"><?= htmlspecialchars((string)$a['created_by']) ?></td>
                        <td style="padding:12px 16px;text-align:center;color:#6b7280;"><?= (int)$a['cnt'] ?></td>
                        <td style="padding:12px 16px;text-align:right;color:#166534;font-weight:500;white-space:nowrap;"><?= $fmtMoney($a['cash']) ?></td>
                        <td style="padding:12px 16px;text-align:right;color:#1e40af;font-weight:500;white-space:nowrap;"><?= $fmtMoney($a['cashless']) ?></td>
                        <td style="padding:12px 16px;text-align:right;font-weight:700;color:#111827;white-space:nowrap;"><?= $fmtMoney($a['total']) ?> сум</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Journal -->
<h2 style="font-size:16px;font-weight:600;color:#111827;margin:28px 0 12px;">
    Журнал кассовых операций
    <?php if ($totalRows > 0): ?>
        <span style="font-weight:400;color:#9ca3af;font-size:13px;">— <?= $totalRows ?> за период</span>
    <?php endif; ?>
</h2>
<?php if (empty($journal)): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:32px;text-align:center;color:#6b7280;font-size:14px;">
        За выбранный период операций не было.
    </div>
<?php else: ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;">
                <thead>
                    <tr style="border-bottom:1px solid #e5e7eb;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;">
                        <th style="padding:11px 14px;font-weight:600;">#</th>
                        <th style="padding:11px 14px;font-weight:600;">Дата</th>
                        <th style="padding:11px 14px;font-weight:600;">Студент</th>
                        <th style="padding:11px 14px;font-weight:600;">Курс</th>
                        <th style="padding:11px 14px;font-weight:600;">Способ</th>
                        <th style="padding:11px 14px;font-weight:600;text-align:right;">Сумма</th>
                        <th style="padding:11px 14px;font-weight:600;">Администратор</th>
                        <th style="padding:11px 14px;font-weight:600;text-align:center;">Чек</th>
                        <th style="padding:11px 14px;font-weight:600;">Комментарий</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($journal as $t):
                    $name = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
                    $m = $t['payment_method'];
                    [$mBg, $mFg] = $methodColors[$m] ?? ['#f3f4f6', '#4b5563'];
                    $isRefund = $t['type'] === 'refund';
                ?>
                    <tr style="border-bottom:1px solid #f3f4f6;font-size:14px;">
                        <td style="padding:12px 14px;font-family:ui-monospace,monospace;color:#9ca3af;">#<?= (int)$t['transaction_id'] ?></td>
                        <td style="padding:12px 14px;color:#6b7280;white-space:nowrap;">
                            <?= date('d.m.Y', strtotime($t['payment_date'])) ?>
                            <div style="font-size:11px;color:#d1d5db;"><?= date('H:i', strtotime($t['created_at'])) ?></div>
                        </td>
                        <td style="padding:12px 14px;color:#111827;font-weight:500;">
                            <?php if ($name !== ''): ?>
                                <?= htmlspecialchars($name) ?>
                                <?php if (!empty($t['class'])): ?>
                                    <span style="color:#9ca3af;font-weight:400;font-size:12px;"><?= htmlspecialchars((string)$t['class']) ?> кл.</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#d1d5db;">— удалён —</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px 14px;color:#374151;">
                            <?= !empty($t['course_title']) ? htmlspecialchars((string)$t['course_title']) : '<span style="color:#d1d5db;">—</span>' ?>
                        </td>
                        <td style="padding:12px 14px;white-space:nowrap;">
                            <span style="display:inline-block;background:<?= $mBg ?>;color:<?= $mFg ?>;border-radius:6px;padding:3px 9px;font-size:12px;font-weight:600;">
                                <?= htmlspecialchars($methodLabels[$m] ?? $m) ?>
                            </span>
                        </td>
                        <td style="padding:12px 14px;text-align:right;font-weight:700;white-space:nowrap;color:<?= $isRefund ? '#dc2626' : '#111827' ?>;">
                            <?= $isRefund ? '−' : '' ?><?= $fmtMoney($t['amount']) ?>
                        </td>
                        <td style="padding:12px 14px;color:#6b7280;"><?= htmlspecialchars((string)$t['created_by']) ?></td>
                        <td style="padding:12px 14px;text-align:center;">
                            <?php if (!empty($t['source_payment_id']) && !empty($t['receipt_path'])): ?>
                                <a href="/file.php?type=receipt&id=<?= (int)$t['source_payment_id'] ?>" target="_blank"
                                   style="color:#2563eb;text-decoration:none;font-size:12px;font-weight:500;">Открыть</a>
                            <?php else: ?>
                                <span style="color:#e5e7eb;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px 14px;color:#9ca3af;font-size:13px;"><?= htmlspecialchars((string)($t['notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:16px;">
            <?php if ($page > 1): ?>
                <a href="?<?= htmlspecialchars($periodQs(['page' => $page - 1])) ?>" style="text-decoration:none;border:1px solid #e5e7eb;background:#fff;color:#4b5563;border-radius:8px;padding:6px 14px;font-size:13px;">← Назад</a>
            <?php endif; ?>
            <span style="font-size:13px;color:#6b7280;">Страница <?= $page ?> из <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= htmlspecialchars($periodQs(['page' => $page + 1])) ?>" style="text-decoration:none;border:1px solid #e5e7eb;background:#fff;color:#4b5563;border-radius:8px;padding:6px 14px;font-size:13px;">Вперёд →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
