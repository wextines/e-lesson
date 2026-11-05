<?php
/**
 * Seed script: create 4 study groups for Demo Academy (Tenant 3) and
 * distribute existing course_subs across them.
 *
 * SAFETY: hard-guards on tenant_id = 3 AND name = 'Demo Academy'. Refuses to
 * run against any other tenant. Wrapped in a transaction; rolls back on any
 * unexpected condition.
 *
 * Usage: php database/seeds/seed_demo_academy_groups.php
 */

require_once __DIR__ . '/../../php/config.php';

const TENANT_ID = 3;
const COURSE_IELTS = 101;
const COURSE_MATH = 105;
const TEACHER_MORNING_TOQ = 4; // demoadmin
const TEACHER_EVENING_JUFT = 6; // admin2

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
    // --- 1. Create the 4 groups ----------------------------------------
    $insGroup = $pdo->prepare("
        INSERT INTO study_groups (tenant_id, course_id, teacher_id, title, days_of_week, lesson_time, room, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $groupDefs = [
        'ielts_morning' => [COURSE_IELTS, TEACHER_MORNING_TOQ, 'IELTS Intensive — Morning (Band 7.0)', 'mon,wed,fri', '09:00 - 10:30', '1-xona'],
        'ielts_evening' => [COURSE_IELTS, TEACHER_EVENING_JUFT, 'IELTS Intensive — Evening (Band 6.5)', 'mon,wed,fri', '18:30 - 20:00', '2-xona'],
        'math_toq'      => [COURSE_MATH, TEACHER_MORNING_TOQ, 'DTM Matematika — Toq kunlar', 'mon,wed,fri', '15:00 - 16:30', '4-xona'],
        'math_juft'     => [COURSE_MATH, TEACHER_EVENING_JUFT, 'DTM Matematika — Juft kunlar', 'tue,thu,sat', '15:00 - 16:30', '4-xona'],
    ];

    $groupIds = [];
    foreach ($groupDefs as $key => [$courseId, $teacherId, $title, $days, $time, $room]) {
        $insGroup->execute([TENANT_ID, $courseId, $teacherId, $title, $days, $time, $room]);
        $groupIds[$key] = (int)$pdo->lastInsertId();
    }

    // --- 2. Fetch active course_subs for both courses, tenant-scoped -----
    // Pending subscriptions (new, unconfirmed students) are excluded on
    // purpose: they must stay group_id = NULL for the one-click assignment demo.
    $fetchSubs = $pdo->prepare("
        SELECT subscription_id, user_id
        FROM course_subs
        WHERE tenant_id = ? AND course_id = ? AND status = 'active'
        ORDER BY user_id ASC
    ");

    $fetchSubs->execute([TENANT_ID, COURSE_IELTS]);
    $ieltsSubs = $fetchSubs->fetchAll(PDO::FETCH_ASSOC);

    $fetchSubs->execute([TENANT_ID, COURSE_MATH]);
    $mathSubs = $fetchSubs->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Round-robin distribute evenly between the two groups per course -
    $assignGroup = $pdo->prepare("
        UPDATE course_subs SET group_id = ?
        WHERE subscription_id = ? AND tenant_id = ? AND status = 'active'
    ");

    $counts = [
        'ielts_morning' => 0,
        'ielts_evening' => 0,
        'math_toq' => 0,
        'math_juft' => 0,
    ];

    foreach ($ieltsSubs as $i => $sub) {
        $groupKey = ($i % 2 === 0) ? 'ielts_morning' : 'ielts_evening';
        $assignGroup->execute([$groupIds[$groupKey], $sub['subscription_id'], TENANT_ID]);
        $counts[$groupKey]++;
    }

    foreach ($mathSubs as $i => $sub) {
        $groupKey = ($i % 2 === 0) ? 'math_toq' : 'math_juft';
        $assignGroup->execute([$groupIds[$groupKey], $sub['subscription_id'], TENANT_ID]);
        $counts[$groupKey]++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

// --- 4. Print summary table --------------------------------------------
$rows = [
    ['IELTS Intensive — Morning (Band 7.0)', 'demoadmin', 'Пн/Ср/Пт 09:00-10:30, 1-xona', $counts['ielts_morning']],
    ['IELTS Intensive — Evening (Band 6.5)', 'admin2', 'Пн/Ср/Пт 18:30-20:00, 2-xona', $counts['ielts_evening']],
    ['DTM Matematika — Toq kunlar', 'demoadmin', 'Пн/Ср/Пт 15:00-16:30, 4-xona', $counts['math_toq']],
    ['DTM Matematika — Juft kunlar', 'admin2', 'Вт/Чт/Сб 15:00-16:30, 4-xona', $counts['math_juft']],
];

printf("%-42s %-12s %-32s %s\n", 'Группа', 'Препод.', 'Расписание', 'Студентов');
printf("%s\n", str_repeat('-', 100));
foreach ($rows as [$title, $teacher, $sched, $count]) {
    printf("%-42s %-12s %-32s %d\n", $title, $teacher, $sched, $count);
}

$totalAssigned = array_sum($counts);
$pendingCount = $pdo->prepare("SELECT COUNT(*) FROM course_subs WHERE tenant_id = ? AND status = 'pending' AND group_id IS NULL");
$pendingCount->execute([TENANT_ID]);
printf("\nВсего распределено: %d студентов (подписок)\n", $totalAssigned);
printf("Без группы (pending, ожидают подтверждения): %d\n", (int)$pendingCount->fetchColumn());
