<?php
declare(strict_types=1);

/**
 * e-Lesson — automated payment-reminder SMS cron (P0-4).
 *
 * Scans active subscriptions (course_subs.status = 'active') for two triggers:
 *   A) reminder_3days — next_payment_date is exactly 3 days away
 *   B) overdue_1day   — next_payment_date was exactly 1 day ago
 * and sends an Uzbek-language SMS via SmsService for each match, which itself
 * de-duplicates against sms_logs so re-running this script harmlessly no-ops
 * on subscriptions already reminded within the last 25 days.
 *
 * Usage:
 *   php php/cron/send_payment_reminders.php [--tenant=ID] [--dry-run]
 *
 *   --tenant=ID   Only scan this tenant (default: all tenants).
 *   --dry-run     Print what would be sent without calling SmsService::send()
 *                 or writing to sms_logs at all.
 */

require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../../php/sms_service.php';

// --- CLI args --------------------------------------------------------------
$options = getopt('', ['tenant::', 'dry-run']);
$tenantFilter = isset($options['tenant']) ? (int)$options['tenant'] : null;
$dryRun = array_key_exists('dry-run', $options);

const ANSI_GREEN = "\033[32m";
const ANSI_YELLOW = "\033[33m";
const ANSI_RED = "\033[31m";
const ANSI_CYAN = "\033[36m";
const ANSI_BOLD = "\033[1m";
const ANSI_RESET = "\033[0m";

function fmtMoney($v): string
{
    return number_format((float)$v, 0, '.', ' ');
}

echo ANSI_BOLD . "=== e-Lesson: SMS Payment Reminders (P0-4) ===" . ANSI_RESET . "\n";
echo "Запуск: " . date('Y-m-d H:i:s') . "\n";
echo "Режим: " . ($dryRun ? ANSI_YELLOW . "DRY-RUN (никаких SMS и записей в sms_logs)" . ANSI_RESET : ANSI_GREEN . "LIVE" . ANSI_RESET) . "\n";
echo "Тенант: " . ($tenantFilter !== null ? "#{$tenantFilter}" : "все") . "\n";

$triggers = [
    'reminder_3days' => [
        'label' => 'Триггер A — за 3 дня до оплаты',
        'condition' => "cs.next_payment_date IS NOT NULL AND DATEDIFF(cs.next_payment_date, CURDATE()) = 3",
        'template' => function (array $row) {
            return "Hurmatli {$row['first_name']}! {$row['course_title']} kursi uchun to'lov sanasiga 3 kun qoldi ("
                . date('d.m.Y', strtotime($row['next_payment_date'])) . "). To'lov miqdori: " . fmtMoney($row['course_price'])
                . " so'm. O'z vaqtida to'lov qilishingizni so'raymiz.";
        },
    ],
    'overdue_1day' => [
        'label' => 'Триггер B — 1 день просрочки',
        'condition' => "cs.next_payment_date IS NOT NULL AND DATEDIFF(CURDATE(), cs.next_payment_date) = 1",
        'template' => function (array $row) {
            return "Hurmatli {$row['first_name']}! {$row['course_title']} kursi uchun to'lov muddati kecha tugadi. "
                . "To'lov miqdori: " . fmtMoney($row['course_price']) . " so'm. Kursga kirish to'xtatilmasligi uchun to'lovni amalga oshirishingizni so'raymiz.";
        },
    ],
];

$totals = ['sent' => 0, 'mock' => 0, 'skipped' => 0, 'failed' => 0];

foreach ($triggers as $triggerType => $trigger) {
    echo "\n" . ANSI_CYAN . ANSI_BOLD . "--- {$trigger['label']} ---" . ANSI_RESET . "\n";

    $where = "WHERE cs.status = 'active' AND {$trigger['condition']}";
    $params = [];
    if ($tenantFilter !== null) {
        $where .= " AND cs.tenant_id = ?";
        $params[] = $tenantFilter;
    }

    $stmt = $pdo->prepare("
        SELECT cs.subscription_id, cs.tenant_id, cs.user_id, cs.next_payment_date,
               u.first_name, u.last_name, u.phone,
               c.title AS course_title, c.price AS course_price
        FROM course_subs cs
        JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
        JOIN courses c ON c.course_id = cs.course_id AND c.tenant_id = cs.tenant_id
        {$where}
        ORDER BY cs.tenant_id ASC, u.last_name ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Найдено подписок: " . count($rows) . "\n";

    foreach ($rows as $row) {
        $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
        $phone = (string)$row['phone'];
        $message = $trigger['template']($row);
        $label = "{$fullName} (тенант #{$row['tenant_id']}, тел: " . ($phone !== '' ? $phone : '—') . ") — курс «{$row['course_title']}»";

        if ($phone === '') {
            echo "  " . ANSI_RED . "[FAIL]  " . ANSI_RESET . "{$label} — нет номера телефона\n";
            $totals['failed']++;
            continue;
        }

        if ($dryRun) {
            echo "  " . ANSI_YELLOW . "[DRY-RUN]" . ANSI_RESET . " {$label} — было бы отправлено\n";
            continue;
        }

        $result = SmsService::send(
            $pdo,
            (int)$row['tenant_id'],
            (int)$row['user_id'],
            (int)$row['subscription_id'],
            $phone,
            $message,
            $triggerType
        );

        if (!$result['success'] && ($result['reason'] ?? '') === 'already_sent') {
            echo "  " . ANSI_YELLOW . "[SKIP]  " . ANSI_RESET . "{$label} — уже отправлено за последние 25 дней\n";
            $totals['skipped']++;
        } elseif ($result['success'] && !empty($result['mock'])) {
            echo "  " . ANSI_GREEN . "[MOCK]  " . ANSI_RESET . "{$label} — SMS зафиксирован (mock_sent)\n";
            $totals['mock']++;
        } elseif ($result['success']) {
            echo "  " . ANSI_GREEN . "[OK]    " . ANSI_RESET . "{$label} — SMS отправлен\n";
            $totals['sent']++;
        } else {
            echo "  " . ANSI_RED . "[FAIL]  " . ANSI_RESET . "{$label} — " . ($result['error'] ?? $result['reason'] ?? 'unknown error') . "\n";
            $totals['failed']++;
        }
    }
}

echo "\n" . ANSI_BOLD . "=== Итог ===" . ANSI_RESET . "\n";
if ($dryRun) {
    echo "Dry-run завершён — записи в sms_logs не создавались.\n";
} else {
    printf(
        "Отправлено: %d (в т.ч. mock: %d) | Пропущено (дедуп): %d | Ошибок: %d\n",
        $totals['sent'] + $totals['mock'],
        $totals['mock'],
        $totals['skipped'],
        $totals['failed']
    );
}
