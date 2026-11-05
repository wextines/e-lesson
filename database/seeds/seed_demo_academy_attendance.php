<?php
/**
 * Seed script: backfill realistic attendance history for Demo Academy
 * (Tenant 3) for the past September 2026 lesson dates, so the attendance
 * grid on admin/groups/view.php looks like a live learning center.
 *
 * SAFETY: hard-guards on tenant_id = 3 AND name = 'Demo Academy'. Refuses to
 * run against any other tenant. Wrapped in a transaction; rolls back on any
 * unexpected condition.
 *
 * Usage: php database/seeds/seed_demo_academy_attendance.php
 */

require_once __DIR__ . '/../../php/config.php';

const TENANT_ID = 3;

// Lesson dates, derived strictly from each group's days_of_week pattern.
const DATES_MON_WED_FRI = ['2026-09-02', '2026-09-04', '2026-09-07', '2026-09-09', '2026-09-11'];
const DATES_TUE_THU_SAT = ['2026-09-01', '2026-09-03', '2026-09-05', '2026-09-08', '2026-09-10', '2026-09-12'];

// These students must show up as 100% present — the demo narrative is
// "studies for free and attends every lesson, a parent call is overdue."
const ALWAYS_PRESENT_USER_IDS = [2530, 2531, 2541]; // Javoxir Ergashev, Shahnoza Sobirova, Sardor Rahimov

const ABSENT_REASONS = ["Betob / Болен", "Sababli"];
const LATE_REASON = "Yo'lda tirbandlik";

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
    $groupsStmt = $pdo->prepare("
        SELECT sg.group_id, sg.title, sg.days_of_week, a.username AS teacher_username
        FROM study_groups sg
        LEFT JOIN admins a ON a.id = sg.teacher_id AND a.tenant_id = sg.tenant_id
        WHERE sg.tenant_id = ?
        ORDER BY sg.group_id
    ");
    $groupsStmt->execute([TENANT_ID]);
    $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

    $membersStmt = $pdo->prepare("
        SELECT u.user_id
        FROM course_subs cs
        JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
        WHERE cs.group_id = ? AND cs.tenant_id = ? AND cs.status = 'active'
        ORDER BY u.user_id
    ");

    $ins = $pdo->prepare("
        INSERT INTO attendance (tenant_id, group_id, user_id, lesson_date, status, reason, marked_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), reason = VALUES(reason), marked_by = VALUES(marked_by), marked_at = CURRENT_TIMESTAMP
    ");

    $totalRecords = 0;
    $summary = [];

    foreach ($groups as $group) {
        $groupId = (int)$group['group_id'];
        $teacherUsername = $group['teacher_username'] ?: 'admin';
        $days = $group['days_of_week'];

        if ($days === 'mon,wed,fri') {
            $lessonDates = DATES_MON_WED_FRI;
        } elseif ($days === 'tue,thu,sat') {
            $lessonDates = DATES_TUE_THU_SAT;
        } else {
            fwrite(STDERR, "SKIP: group {$groupId} has unexpected days_of_week '{$days}'\n");
            continue;
        }

        $membersStmt->execute([$groupId, TENANT_ID]);
        $memberIds = array_map('intval', $membersStmt->fetchAll(PDO::FETCH_COLUMN));

        $groupRecords = 0;
        $presentCount = 0;
        $absentCount = 0;
        $lateCount = 0;

        foreach ($memberIds as $userId) {
            $isAlwaysPresent = in_array($userId, ALWAYS_PRESENT_USER_IDS, true);

            foreach ($lessonDates as $lessonDate) {
                if ($isAlwaysPresent) {
                    $status = 'present';
                    $reason = null;
                } else {
                    $roll = random_int(1, 100);
                    if ($roll <= 88) {
                        $status = 'present';
                        $reason = null;
                    } elseif ($roll <= 94) {
                        $status = 'absent';
                        $reason = ABSENT_REASONS[array_rand(ABSENT_REASONS)];
                    } else {
                        $status = 'late';
                        $reason = LATE_REASON;
                    }
                }

                $ins->execute([TENANT_ID, $groupId, $userId, $lessonDate, $status, $reason, $teacherUsername]);
                $totalRecords++;
                $groupRecords++;
                if ($status === 'present') { $presentCount++; }
                elseif ($status === 'absent') { $absentCount++; }
                else { $lateCount++; }
            }
        }

        $summary[] = [
            'title' => $group['title'],
            'students' => count($memberIds),
            'lessons' => count($lessonDates),
            'records' => $groupRecords,
            'present' => $presentCount,
            'absent' => $absentCount,
            'late' => $lateCount,
        ];
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

// --- Print summary --------------------------------------------------------
printf("%-42s %-9s %-9s %-9s %-9s %-9s %s\n", 'Группа', 'Студ.', 'Уроков', 'Записей', 'Present', 'Absent', 'Late');
printf("%s\n", str_repeat('-', 100));
foreach ($summary as $row) {
    printf(
        "%-42s %-9d %-9d %-9d %-9d %-9d %d\n",
        $row['title'],
        $row['students'],
        $row['lessons'],
        $row['records'],
        $row['present'],
        $row['absent'],
        $row['late']
    );
}

printf("\nВсего создано/обновлено записей посещаемости: %d\n", $totalRecords);
