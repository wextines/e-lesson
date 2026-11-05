<?php
require_once __DIR__ . '/../../php/config.php';

$tenantId = 3;

$check = $pdo->prepare("SELECT name FROM tenants WHERE tenant_id = ?");
$check->execute([$tenantId]);
if ($check->fetchColumn() !== 'Demo Academy') {
    die("ABORT: Tenant 3 is not Demo Academy\n");
}

$pdo->beginTransaction();
try {
    // 1. Очистка транзакций
    $pdo->prepare("DELETE FROM transactions WHERE tenant_id = ?")->execute([$tenantId]);

    // 2. Очистка старых платежей (кроме тестовой фикстуры 3094)
    $pdo->prepare("DELETE FROM payments WHERE tenant_id = ? AND payment_id != 3094")->execute([$tenantId]);

    // 3. Очистка подписок
    $pdo->prepare("DELETE FROM course_subs WHERE tenant_id = ?")->execute([$tenantId]);

    // 4. Очистка лишних результатов тестов (кроме Гюльнозы 2108)
    $pdo->prepare("DELETE FROM test_results WHERE tenant_id = ? AND slug != 'gulnoza-xolmatova-2108'")->execute([$tenantId]);

    // 5. Очистка пользователей (кроме Гюльнозы 2108)
    $pdo->prepare("DELETE FROM users WHERE tenant_id = ? AND user_id != 2108")->execute([$tenantId]);

    // 6. Очистка материалов и 15 лишних курсов (оставляем только 101 и 105)
    $pdo->prepare("DELETE FROM materials WHERE tenant_id = ? AND course_id NOT IN (101, 105)")->execute([$tenantId]);
    $pdo->prepare("DELETE FROM courses WHERE tenant_id = ? AND course_id NOT IN (101, 105)")->execute([$tenantId]);

    // Курс 105: ставим цену 450 000 сум
    $pdo->prepare("UPDATE courses SET price = 450000, subscription_days = 30 WHERE tenant_id = ? AND course_id = 105")->execute([$tenantId]);
    // Курс 101: ставим цену 400 000 сум
    $pdo->prepare("UPDATE courses SET price = 400000, subscription_days = 30 WHERE tenant_id = ? AND course_id = 101")->execute([$tenantId]);

    // Гюльнозе 2108 восстанавливаем чистую активную подписку на курс 105 до конца месяца
    $pdo->prepare("INSERT INTO course_subs (tenant_id, user_id, course_id, price, status, next_payment_date, access_expires_at)
                   VALUES (?, 2108, 105, 450000, 'active', '2026-09-30', '2026-09-30')")->execute([$tenantId]);

    $pdo->commit();
    echo "CLEANUP SUCCESS: Tenant 3 is clean.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("CLEANUP FAILED: " . $e->getMessage() . "\n");
}
