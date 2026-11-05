<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/config.php';

const TENANT_ID = 3;

$chk = $pdo->prepare("SELECT name FROM tenants WHERE tenant_id = ?");
$chk->execute([TENANT_ID]);
if ($chk->fetchColumn() !== 'Demo Academy') {
    die("ABORT: Tenant 3 is not Demo Academy\n");
}

$pdo->beginTransaction();
try {
    // Удаляем только что созданные тестовые транзакции >= 587
    $pdo->prepare("DELETE FROM transactions WHERE tenant_id = ? AND transaction_id >= 587")->execute([TENANT_ID]);

    // Восстанавливаем 6 активных должников с их исходными просроченными датами
    $restore = [
        4491 => ['status' => 'active', 'date' => '2026-09-06'], // Javoxir Ergashev
        4492 => ['status' => 'active', 'date' => '2026-09-05'], // Shahnoza Sobirova
        4499 => ['status' => 'active', 'date' => '2026-09-06'], // Zilola Davronova
        4500 => ['status' => 'active', 'date' => '2026-09-05'], // Sanjar Murodov
        4501 => ['status' => 'active', 'date' => '2026-09-06'], // Mohira Saidova
        4503 => ['status' => 'active', 'date' => '2026-09-06'], // Sardor Rahimov
    ];
    $updActive = $pdo->prepare("UPDATE course_subs SET status = ?, next_payment_date = ? WHERE subscription_id = ? AND tenant_id = ?");
    foreach ($restore as $subId => $row) {
        $updActive->execute([$row['status'], $row['date'], $subId, TENANT_ID]);
    }

    // Восстанавливаем 2 ожидающих первой оплаты (pending)
    $updPending = $pdo->prepare("UPDATE course_subs SET status = 'pending', next_payment_date = NULL WHERE subscription_id = ? AND tenant_id = ?");
    $updPending->execute([4522, TENANT_ID]); // Asadbek Nurmatov
    $updPending->execute([4523, TENANT_ID]); // Shodiya Boboyeva

    $pdo->commit();
    echo "SUCCESS: Demo Academy debtors restored to initial benchmark state.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    die("ERROR: " . $e->getMessage() . "\n");
}
