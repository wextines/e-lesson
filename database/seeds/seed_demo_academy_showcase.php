<?php
declare(strict_types=1);

/**
 * One-off calibration script for the Tenant 3 (Demo Academy) showcase tenant.
 *
 * Makes the admin dashboards (director's dashboard revenue widget, the
 * debtors module, "recent activity" tables) look like a live, healthy
 * center instead of static seed data frozen on whatever day the seed ran.
 *
 * STRICTLY scoped to tenant_id = 3. Refuses to run against anything else.
 * Run from the web container (needs DB_HOST=db etc from its environment):
 *   docker compose exec web php database/seeds/seed_demo_academy_showcase.php
 */

const TENANT_ID = 3;

function out(string $msg): void
{
    fwrite(STDOUT, $msg . "\n");
}

function fail(string $msg): void
{
    fwrite(STDERR, "FATAL: {$msg}\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: 'db';
$username = getenv('DB_USER') ?: 'iq_user';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: 'iq_creative';

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// ── 1. Strict isolation guard ──────────────────────────────────────────
$stmt = $pdo->prepare("SELECT name FROM tenants WHERE tenant_id = ?");
$stmt->execute([TENANT_ID]);
$tenantName = $stmt->fetchColumn();
if ($tenantName !== 'Demo Academy') {
    fail(
        "tenant_id=" . TENANT_ID . " has name '" . ($tenantName !== false ? $tenantName : 'NOT FOUND') . "', " .
        "expected 'Demo Academy'. Refusing to run — this script must never touch another tenant."
    );
}
out("Guard OK: tenant_id=" . TENANT_ID . " is '{$tenantName}'.");

// Baseline row counts for tenants 1 and 2, to prove afterwards they were untouched.
function otherTenantCounts(PDO $pdo): array
{
    $counts = [];
    foreach ([1, 2] as $tid) {
        foreach (['course_subs', 'payments', 'test_results'] as $table) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = ?");
            $s->execute([$tid]);
            $counts["{$table}_{$tid}"] = (int)$s->fetchColumn();
        }
    }
    return $counts;
}
$beforeOther = otherTenantCounts($pdo);

$pdo->beginTransaction();

try {
    // ── 2. Mandatory fixtures ────────────────────────────────────────
    $fixtures = [
        ['user_id' => 2108, 'course_id' => 105, 'label' => 'Gulnoza Xolmatova'],
        ['user_id' => 2236, 'course_id' => 117, 'label' => 'Otabek Abdubakiyev'],
    ];
    $fixtureSubIds = [];
    $updFixture = $pdo->prepare(
        "UPDATE course_subs SET status = 'active',
            next_payment_date = DATE_ADD(CURDATE(), INTERVAL 20 DAY),
            access_expires_at = DATE_ADD(CURDATE(), INTERVAL 25 DAY)
         WHERE subscription_id = ? AND tenant_id = ?"
    );
    foreach ($fixtures as $f) {
        $sel = $pdo->prepare("SELECT subscription_id FROM course_subs WHERE tenant_id = ? AND user_id = ? AND course_id = ?");
        $sel->execute([TENANT_ID, $f['user_id'], $f['course_id']]);
        $subId = $sel->fetchColumn();
        if (!$subId) {
            throw new RuntimeException("Required fixture missing: {$f['label']} (user {$f['user_id']}) / course {$f['course_id']}.");
        }
        $updFixture->execute([$subId, TENANT_ID]);
        $fixtureSubIds[] = (int)$subId;
        out("Fixture: {$f['label']} / course {$f['course_id']} (sub #{$subId}) -> active, +20/+25d.");
    }

    // ── 3b. Exactly 4 debtors ────────────────────────────────────────
    $debtorUserIds = [2151, 2152, 2153, 2155];
    $ph = implode(',', array_fill(0, count($debtorUserIds), '?'));
    $sel = $pdo->prepare("SELECT subscription_id FROM course_subs WHERE tenant_id = ? AND user_id IN ($ph)");
    $sel->execute(array_merge([TENANT_ID], $debtorUserIds));
    $debtorSubIds = array_map(fn($r) => (int)$r['subscription_id'], $sel->fetchAll());
    if (count($debtorSubIds) !== 4) {
        throw new RuntimeException("Expected exactly 4 debtor subscriptions for users [" . implode(',', $debtorUserIds) . "], found " . count($debtorSubIds) . ".");
    }
    $updDebtor = $pdo->prepare(
        "UPDATE course_subs SET status = 'active',
            next_payment_date = DATE_SUB(CURDATE(), INTERVAL 4 DAY),
            access_expires_at = DATE_ADD(CURDATE(), INTERVAL 10 DAY)
         WHERE subscription_id = ? AND tenant_id = ?"
    );
    foreach ($debtorSubIds as $subId) {
        $updDebtor->execute([$subId, TENANT_ID]);
    }
    out("Debtors: " . count($debtorSubIds) . " subscriptions set 4 days overdue (users " . implode(',', $debtorUserIds) . ") — access stays active.");

    // ── 3c. Pending applications (users 2148, 2149, 2150 — every subscription they hold) ──
    $pendingUserIds = [2148, 2149, 2150];
    $ph2 = implode(',', array_fill(0, count($pendingUserIds), '?'));
    $sel = $pdo->prepare("SELECT subscription_id FROM course_subs WHERE tenant_id = ? AND user_id IN ($ph2)");
    $sel->execute(array_merge([TENANT_ID], $pendingUserIds));
    $pendingSubIds = array_map(fn($r) => (int)$r['subscription_id'], $sel->fetchAll());
    if (empty($pendingSubIds)) {
        throw new RuntimeException("Expected pending-application subscriptions for users [" . implode(',', $pendingUserIds) . "], found none.");
    }
    $updPending = $pdo->prepare(
        "UPDATE course_subs SET status = 'pending',
            next_payment_date = NULL,
            access_expires_at = NULL,
            purchase_date = DATE_SUB(NOW(), INTERVAL 2 DAY)
         WHERE subscription_id = ? AND tenant_id = ?"
    );
    foreach ($pendingSubIds as $subId) {
        $updPending->execute([$subId, TENANT_ID]);
    }
    out("Pending: " . count($pendingSubIds) . " subscriptions -> pending, no payment (users " . implode(',', $pendingUserIds) . ").");

    // ── 3a/3г. Remaining subscriptions: 18 "core" + everything else ──
    $excludedSubIds = array_merge($fixtureSubIds, $debtorSubIds, $pendingSubIds);
    $phEx = implode(',', array_fill(0, count($excludedSubIds), '?'));
    $sel = $pdo->prepare("SELECT subscription_id FROM course_subs WHERE tenant_id = ? AND subscription_id NOT IN ($phEx) ORDER BY subscription_id ASC");
    $sel->execute(array_merge([TENANT_ID], $excludedSubIds));
    $remainingSubIds = array_map(fn($r) => (int)$r['subscription_id'], $sel->fetchAll());

    $coreSubIds = array_slice($remainingSubIds, 0, 18);
    $restSubIds = array_slice($remainingSubIds, 18);

    if (count($coreSubIds) < 18) {
        throw new RuntimeException("Expected at least 18 remaining subscriptions for the core-students batch, found " . count($coreSubIds) . ".");
    }

    $updCore = $pdo->prepare(
        "UPDATE course_subs SET status = 'active',
            next_payment_date = DATE_ADD(CURDATE(), INTERVAL 15 DAY),
            access_expires_at = DATE_ADD(CURDATE(), INTERVAL 20 DAY)
         WHERE subscription_id = ? AND tenant_id = ?"
    );
    foreach ($coreSubIds as $subId) {
        $updCore->execute([$subId, TENANT_ID]);
    }
    out("Core students: " . count($coreSubIds) . " subscriptions -> active, +15/+20d.");

    $updRest = $pdo->prepare(
        "UPDATE course_subs SET status = 'active',
            next_payment_date = DATE_ADD(CURDATE(), INTERVAL 25 DAY)
         WHERE subscription_id = ? AND tenant_id = ?"
    );
    foreach ($restSubIds as $subId) {
        $updRest->execute([$subId, TENANT_ID]);
    }
    out("All other subscriptions: " . count($restSubIds) . " -> active, next_payment_date +25d.");

    // ── 4a. Freshen the 5 most recent payments ───────────────────────
    $sel = $pdo->prepare("SELECT payment_id FROM payments WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY payment_date DESC LIMIT 5");
    $sel->execute([TENANT_ID]);
    $freshIds = array_map(fn($r) => (int)$r['payment_id'], $sel->fetchAll());
    if (count($freshIds) < 5) {
        throw new RuntimeException("Expected at least 5 payments to freshen, found " . count($freshIds) . ".");
    }
    $freshOffsetsDays = [0, 1, 1, 3, 3]; // today, yesterday, yesterday, -3d, -3d
    $updFresh = $pdo->prepare("UPDATE payments SET payment_date = DATE_SUB(NOW(), INTERVAL ? DAY) WHERE payment_id = ? AND tenant_id = ?");
    foreach ($freshIds as $i => $pid) {
        $updFresh->execute([$freshOffsetsDays[$i], $pid, TENANT_ID]);
    }
    out("Freshened " . count($freshIds) . " most-recent payments: today / -1d / -1d / -3d / -3d.");

    // ── 4b. Remaining August-dated accepted payments -> spread over the last 10-25 days ──
    $notInFresh = implode(',', array_fill(0, count($freshIds), '?'));
    $sel = $pdo->prepare(
        "SELECT payment_id, amount FROM payments
         WHERE tenant_id = ? AND status = 'accepted' AND deleted_at IS NULL
           AND MONTH(payment_date) = 8
           AND payment_id NOT IN ($notInFresh)"
    );
    $sel->execute(array_merge([TENANT_ID], $freshIds));
    $augustRows = $sel->fetchAll();

    $updAug = $pdo->prepare("UPDATE payments SET payment_date = ? WHERE payment_id = ? AND tenant_id = ?");
    mt_srand(20260910); // deterministic spread if the script is ever re-run
    $augSum = 0;
    foreach ($augustRows as $row) {
        $dayOffset = mt_rand(10, 25);
        $newDate = (new DateTime())
            ->modify("-{$dayOffset} days")
            ->setTime(mt_rand(8, 20), mt_rand(0, 59), mt_rand(0, 59))
            ->format('Y-m-d H:i:s');
        $updAug->execute([$newDate, (int)$row['payment_id'], TENANT_ID]);
        $augSum += (int)$row['amount'];
    }
    out("Redistributed " . count($augustRows) . " August-accepted payments across the last 10-25 days (sum " . number_format($augSum, 0, '', ' ') . " so'm).");

    // ── 5. Freshen the 5 most recent test results ────────────────────
    $sel = $pdo->prepare("SELECT result_id FROM test_results WHERE tenant_id = ? ORDER BY completed_at DESC LIMIT 5");
    $sel->execute([TENANT_ID]);
    $recentResultIds = array_map(fn($r) => (int)$r['result_id'], $sel->fetchAll());
    if (count($recentResultIds) < 5) {
        throw new RuntimeException("Expected at least 5 test_results rows to freshen, found " . count($recentResultIds) . ".");
    }
    $scoreCycle = ['4/5', '5/5', '4/4', '4/5', '5/5'];
    $dayOffsetCycle = [0, 0, 1, 1, 0]; // today, today, yesterday, yesterday, today
    $updResult = $pdo->prepare("UPDATE test_results SET score = ?, completed_at = DATE_SUB(NOW(), INTERVAL ? DAY) WHERE result_id = ? AND tenant_id = ?");
    foreach ($recentResultIds as $i => $rid) {
        $updResult->execute([$scoreCycle[$i], $dayOffsetCycle[$i], $rid, TENANT_ID]);
    }
    out("Freshened " . count($recentResultIds) . " most-recent test results (today/yesterday, scores 4/5-5/5-4/4).");

    $pdo->commit();
    out("Transaction committed.");
} catch (Throwable $e) {
    $pdo->rollBack();
    fail("Rolled back — " . $e->getMessage());
}

// ── Post-run verification ────────────────────────────────────────────
$afterOther = otherTenantCounts($pdo);
$otherTenantsUntouched = ($beforeOther === $afterOther);
out("");
out("=== Verification ===");
out("Tenants 1 & 2 untouched: " . ($otherTenantsUntouched ? 'YES' : 'NO — MISMATCH, INVESTIGATE'));
if (!$otherTenantsUntouched) {
    foreach ($beforeOther as $k => $v) {
        if ($afterOther[$k] !== $v) {
            out("  {$k}: before={$v} after={$afterOther[$k]}");
        }
    }
}

$r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = 'accepted' AND deleted_at IS NULL AND payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$r->execute([TENANT_ID]);
$last30 = (int)$r->fetchColumn();

$r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = 'accepted' AND deleted_at IS NULL AND payment_date >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND payment_date < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$r->execute([TENANT_ID]);
$prev30 = (int)$r->fetchColumn();

out("Revenue last 30 days (accepted): " . number_format($last30, 0, '', ' ') . " so'm");
out("Revenue previous 30-60 days (accepted): " . number_format($prev30, 0, '', ' ') . " so'm");
out("Dynamics: " . ($last30 > $prev30 ? 'POSITIVE' : 'NOT positive — investigate'));

$r = $pdo->prepare("SELECT status, COUNT(*) AS n FROM course_subs WHERE tenant_id = ? GROUP BY status");
$r->execute([TENANT_ID]);
out("course_subs status breakdown: " . json_encode($r->fetchAll(PDO::FETCH_KEY_PAIR)));

out("");
out("Done.");
