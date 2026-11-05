<?php
declare(strict_types=1);

/**
 * Seed script: Configure realistic staff and shift distribution for Demo Academy (Tenant 3)
 * Safety: Guarded on tenant_id = 3 AND name = 'Demo Academy'. Transactional.
 */

require_once __DIR__ . '/../../php/config.php';

const TENANT_ID = 3;

$chk = $pdo->prepare("SELECT name FROM tenants WHERE tenant_id = ?");
$chk->execute([TENANT_ID]);
$tenantName = $chk->fetchColumn();
if ($tenantName !== 'Demo Academy') {
    fwrite(STDERR, "ABORT: tenant_id=" . TENANT_ID . " is not 'Demo Academy'\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $teacherPasswordHash = password_hash('teacher123', PASSWORD_DEFAULT);

    // 1. Создание/обновление учетных записей персонала
    $staff = [
        'demoadmin'     => ['role' => 'center_admin', 'hash' => $adminPasswordHash],
        'nazima_admin'  => ['role' => 'center_admin', 'hash' => $adminPasswordHash],
        'feruza_admin'  => ['role' => 'center_admin', 'hash' => $adminPasswordHash],
        'dilnoza_admin' => ['role' => 'center_admin', 'hash' => $adminPasswordHash],
        'aziza_teacher' => ['role' => 'teacher',      'hash' => $teacherPasswordHash],
        'madina_teacher'=> ['role' => 'teacher',      'hash' => $teacherPasswordHash],
    ];

    $staffIds = [];
    foreach ($staff as $username => $data) {
        $find = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND tenant_id = ?");
        $find->execute([$username, TENANT_ID]);
        $existingId = $find->fetchColumn();

        if ($existingId) {
            $upd = $pdo->prepare("UPDATE admins SET role = ?, password_hash = ? WHERE id = ? AND tenant_id = ?");
            $upd->execute([$data['role'], $data['hash'], $existingId, TENANT_ID]);
            $staffIds[$username] = (int)$existingId;
        } else {
            $ins = $pdo->prepare("INSERT INTO admins (tenant_id, username, role, password_hash) VALUES (?, ?, ?, ?)");
            $ins->execute([TENANT_ID, $username, $data['role'], $data['hash']]);
            $staffIds[$username] = (int)$pdo->lastInsertId();
        }
    }

    // 2. Закрепление групп за учителями (2 и 3 -> Азиза, 4 и 5 -> Мадина)
    $assign = $pdo->prepare("UPDATE study_groups SET teacher_id = ? WHERE group_id = ? AND tenant_id = ?");
    $assign->execute([$staffIds['aziza_teacher'], 2, TENANT_ID]);
    $assign->execute([$staffIds['aziza_teacher'], 3, TENANT_ID]);
    $assign->execute([$staffIds['madina_teacher'], 4, TENANT_ID]);
    $assign->execute([$staffIds['madina_teacher'], 5, TENANT_ID]);

    // 3. Обновление автора отметок в журнале
    $pdo->prepare("UPDATE attendance SET marked_by = 'aziza_teacher' WHERE group_id IN (2, 3) AND tenant_id = ?")
        ->execute([TENANT_ID]);
    $pdo->prepare("UPDATE attendance SET marked_by = 'madina_teacher' WHERE group_id IN (4, 5) AND tenant_id = ?")
        ->execute([TENANT_ID]);

    // 4. Равномерное распределение 78 транзакций между 3 сменными админами
    $txStmt = $pdo->prepare("SELECT transaction_id FROM transactions WHERE tenant_id = ? ORDER BY transaction_id ASC");
    $txStmt->execute([TENANT_ID]);
    $txIds = $txStmt->fetchAll(PDO::FETCH_COLUMN);

    $updTx = $pdo->prepare("UPDATE transactions SET created_by = ? WHERE transaction_id = ? AND tenant_id = ?");
    $shiftAdmins = ['nazima_admin', 'feruza_admin', 'dilnoza_admin'];
    foreach ($txIds as $idx => $txId) {
        $assignedAdmin = $shiftAdmins[$idx % count($shiftAdmins)];
        $updTx->execute([$assignedAdmin, $txId, TENANT_ID]);
    }

    $pdo->commit();
    echo "SUCCESS: Demo Academy staff configured and transactions distributed.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
