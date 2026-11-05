<?php
/**
 * Seed script: create the "Dilnoza" teacher account for Demo Academy
 * (Tenant 3) and assign her as teacher_id on two study groups, so the new
 * teacher-role scoping (P0-3) has a real account to demo against.
 *
 * SAFETY: hard-guards on tenant_id = 3 AND name = 'Demo Academy'. Refuses to
 * run against any other tenant. Wrapped in a transaction; rolls back on any
 * unexpected condition. Idempotent: re-running updates the existing account
 * instead of creating a duplicate.
 *
 * Usage: php database/seeds/seed_demo_academy_teacher.php
 */

require_once __DIR__ . '/../../php/config.php';

const TENANT_ID = 3;
const TEACHER_USERNAME = 'dilnoza_teacher';
const TEACHER_PASSWORD = 'teacher123';
const TEACHER_GROUP_IDS = [3, 5]; // IELTS Intensive — Evening (Band 6.5), DTM Matematika — Juft kunlar

// --- Safety guard -----------------------------------------------------
$chk = $pdo->prepare("SELECT name FROM tenants WHERE tenant_id = ?");
$chk->execute([TENANT_ID]);
$tenantName = $chk->fetchColumn();
if ($tenantName !== 'Demo Academy') {
    fwrite(STDERR, "ABORT: tenant_id=" . TENANT_ID . " is not 'Demo Academy' (got: " . var_export($tenantName, true) . ")\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $passwordHash = password_hash(TEACHER_PASSWORD, PASSWORD_DEFAULT);

    $existing = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND tenant_id = ?");
    $existing->execute([TEACHER_USERNAME, TENANT_ID]);
    $teacherId = $existing->fetchColumn();

    if ($teacherId) {
        $upd = $pdo->prepare("UPDATE admins SET role = 'teacher', password_hash = ? WHERE id = ? AND tenant_id = ?");
        $upd->execute([$passwordHash, $teacherId, TENANT_ID]);
        $teacherId = (int)$teacherId;
    } else {
        $ins = $pdo->prepare("INSERT INTO admins (tenant_id, username, role, password_hash) VALUES (?, ?, 'teacher', ?)");
        $ins->execute([TENANT_ID, TEACHER_USERNAME, $passwordHash]);
        $teacherId = (int)$pdo->lastInsertId();
    }

    // Assign her to the two designated groups (must belong to this tenant).
    $assignedTitles = [];
    $assign = $pdo->prepare("UPDATE study_groups SET teacher_id = ? WHERE group_id = ? AND tenant_id = ?");
    $titleStmt = $pdo->prepare("SELECT title FROM study_groups WHERE group_id = ? AND tenant_id = ?");
    foreach (TEACHER_GROUP_IDS as $groupId) {
        $assign->execute([$teacherId, $groupId, TENANT_ID]);
        $titleStmt->execute([$groupId, TENANT_ID]);
        $title = $titleStmt->fetchColumn();
        if ($title !== false) {
            $assignedTitles[] = "#{$groupId} {$title}";
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

// --- Summary --------------------------------------------------------------
echo "Учитель создан/обновлён:\n";
printf("  id=%d, username=%s, role=teacher, tenant_id=%d\n", $teacherId, TEACHER_USERNAME, TENANT_ID);
echo "Закреплён за группами:\n";
foreach ($assignedTitles as $t) {
    echo "  - {$t}\n";
}

$check = $pdo->prepare("SELECT group_id, title, teacher_id FROM study_groups WHERE tenant_id = ? ORDER BY group_id");
$check->execute([TENANT_ID]);
echo "\nИтоговое распределение групп Demo Academy:\n";
printf("%-10s %-42s %s\n", 'group_id', 'title', 'teacher_id');
foreach ($check->fetchAll(PDO::FETCH_ASSOC) as $row) {
    printf("%-10d %-42s %d\n", $row['group_id'], $row['title'], $row['teacher_id']);
}
