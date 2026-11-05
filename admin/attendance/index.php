<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();
$pageTitle = 'Посещаемость';
$isTeacher = ($_SESSION['admin_role'] ?? '') === 'teacher';
$adminId = (int)($_SESSION['admin_id'] ?? 0);

function h($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDaysOfWeek(?string $raw): string
{
    $labels = ['mon' => 'Пн', 'tue' => 'Вт', 'wed' => 'Ср', 'thu' => 'Чт', 'fri' => 'Пт', 'sat' => 'Сб', 'sun' => 'Вс'];
    $days = array_filter(array_map('trim', explode(',', (string)$raw)));
    if (empty($days)) {
        return '—';
    }
    $out = [];
    foreach ($days as $d) {
        $out[] = $labels[strtolower($d)] ?? h($d);
    }
    return implode('/', $out);
}

function ruWeekdayShort(string $ymd): string
{
    $labels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    $n = (int)date('N', strtotime($ymd)); // 1 (Mon) .. 7 (Sun)
    return $labels[$n - 1];
}

function initials(string $first, string $last): string
{
    $a = mb_substr(trim($first), 0, 1);
    $b = mb_substr(trim($last), 0, 1);
    return mb_strtoupper($a . $b);
}

const RU_MONTHS = [
    1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
    7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
];

$message = '';
$messageOk = true;

// --- Save attendance (POST) ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_attendance') {
    verify_csrf();

    $postGroupId = (int)($_POST['group_id'] ?? 0);
    $postMonth = (string)($_POST['month'] ?? date('Y-m'));
    $postDate = (string)($_POST['lesson_date'] ?? '');
    $statuses = $_POST['status'] ?? [];
    $reasons = $_POST['reason'] ?? [];

    $dateOk = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate);

    // Group must belong to this tenant — and, for a teacher, must be one of
    // their own groups (never someone else's roster).
    if ($isTeacher) {
        $chk = $pdo->prepare("SELECT group_id FROM study_groups WHERE group_id = ? AND tenant_id = ? AND teacher_id = ?");
        $chk->execute([$postGroupId, $tid, $adminId]);
    } else {
        $chk = $pdo->prepare("SELECT group_id FROM study_groups WHERE group_id = ? AND tenant_id = ?");
        $chk->execute([$postGroupId, $tid]);
    }
    $groupOk = (bool)$chk->fetchColumn();

    if ($postGroupId > 0 && $dateOk && $groupOk && is_array($statuses)) {
        // Only students who are actually active members of this group may be marked —
        // ignores any user_id a crafted request might add that isn't really on the roster.
        $rosterStmt = $pdo->prepare("
            SELECT u.user_id
            FROM course_subs cs
            JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
            WHERE cs.group_id = ? AND cs.tenant_id = ? AND cs.status = 'active'
        ");
        $rosterStmt->execute([$postGroupId, $tid]);
        $rosterIds = array_map('intval', $rosterStmt->fetchAll(PDO::FETCH_COLUMN));
        $rosterSet = array_flip($rosterIds);

        $markedBy = (string)($_SESSION['admin_username'] ?? 'admin');
        $ins = $pdo->prepare("
            INSERT INTO attendance (tenant_id, group_id, user_id, lesson_date, status, reason, marked_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), reason = VALUES(reason), marked_by = VALUES(marked_by), marked_at = CURRENT_TIMESTAMP
        ");

        $saved = 0;
        foreach ($statuses as $userId => $status) {
            $userId = (int)$userId;
            if (!isset($rosterSet[$userId])) {
                continue;
            }
            if (!in_array($status, ['present', 'absent', 'late'], true)) {
                continue;
            }
            $reason = trim((string)($reasons[$userId] ?? ''));
            $ins->execute([$tid, $postGroupId, $userId, $postDate, $status, $reason !== '' ? $reason : null, $markedBy]);
            $saved++;
        }

        // Back to the updated grid — the marking panel closes on success.
        $redirectMonth = preg_match('/^\d{4}-\d{2}$/', $postMonth) ? $postMonth : substr($postDate, 0, 7);
        header('Location: index.php?' . http_build_query(['group_id' => $postGroupId, 'month' => $redirectMonth]));
        exit;
    }

    $message = 'Не удалось сохранить посещаемость: некорректные данные.';
    $messageOk = false;
}

// --- Groups available for this tenant (tabs + auto-select) --------------
// A teacher only ever sees their own groups here.
$groupsWhere = "WHERE sg.tenant_id = ? AND sg.is_active = 1";
$groupsParams = [$tid];
if ($isTeacher) {
    $groupsWhere .= " AND sg.teacher_id = ?";
    $groupsParams[] = $adminId;
}
$groupsStmt = $pdo->prepare("
    SELECT sg.group_id, sg.title, sg.days_of_week, sg.lesson_time, sg.room, sg.course_id,
           c.title AS course_title, a.username AS teacher_username
    FROM study_groups sg
    JOIN courses c ON c.course_id = sg.course_id AND c.tenant_id = sg.tenant_id
    LEFT JOIN admins a ON a.id = sg.teacher_id AND a.tenant_id = sg.tenant_id
    {$groupsWhere}
    ORDER BY c.title ASC, sg.title ASC
");
$groupsStmt->execute($groupsParams);
$allGroups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);
$groupsById = [];
foreach ($allGroups as $g) {
    $groupsById[(int)$g['group_id']] = $g;
}

// Zero-state elimination: no group_id (or an invalid/foreign one) falls back
// to the first active group of this tenant, so the screen is never empty.
$groupId = (int)($_GET['group_id'] ?? 0);
if (!isset($groupsById[$groupId]) && !empty($allGroups)) {
    $groupId = (int)$allGroups[0]['group_id'];
}
$group = $groupsById[$groupId] ?? null;

// --- Month navigation -----------------------------------------------------
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthDt = DateTime::createFromFormat('Y-m-d', $month . '-01') ?: new DateTime(date('Y-m-01'));
$monthStart = $monthDt->format('Y-m-01');
$monthEnd = $monthDt->format('Y-m-t');
$monthLabel = RU_MONTHS[(int)$monthDt->format('n')] . ' ' . $monthDt->format('Y');
$prevMonth = (clone $monthDt)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthDt)->modify('+1 month')->format('Y-m');

// --- Marking panel state ---------------------------------------------------
$showMarkPanel = ($_GET['action'] ?? '') === 'mark';
$markDate = (string)($_GET['mark_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $markDate)) {
    $markDate = date('Y-m-d');
}

$members = [];
$lessonDates = [];
$attendanceMap = [];
$markDateAttendance = [];
$lessonsThisMonth = 0;
$avgAttendancePct = null;

if ($group) {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.phone
        FROM course_subs cs
        JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
        WHERE cs.group_id = ? AND cs.tenant_id = ? AND cs.status = 'active'
        ORDER BY u.last_name ASC, u.first_name ASC
    ");
    $stmt->execute([$groupId, $tid]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grid data: every attendance record for this group within the selected month.
    $stmt = $pdo->prepare("
        SELECT user_id, lesson_date, status, reason
        FROM attendance
        WHERE tenant_id = ? AND group_id = ? AND lesson_date BETWEEN ? AND ?
        ORDER BY lesson_date ASC
    ");
    $stmt->execute([$tid, $groupId, $monthStart, $monthEnd]);
    $monthRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalMarks = 0;
    $attendedMarks = 0;
    foreach ($monthRows as $row) {
        $d = $row['lesson_date'];
        $lessonDates[$d] = true;
        $attendanceMap[(int)$row['user_id']][$d] = ['status' => $row['status'], 'reason' => $row['reason']];
        $totalMarks++;
        if ($row['status'] === 'present' || $row['status'] === 'late') {
            $attendedMarks++;
        }
    }
    $lessonDates = array_keys($lessonDates);
    sort($lessonDates);
    $lessonsThisMonth = count($lessonDates);
    $avgAttendancePct = $totalMarks > 0 ? round($attendedMarks / $totalMarks * 100) : null;

    // Prefill data for the marking panel's own date (may fall outside $month).
    $stmt = $pdo->prepare("
        SELECT user_id, status, reason
        FROM attendance
        WHERE tenant_id = ? AND group_id = ? AND lesson_date = ?
    ");
    $stmt->execute([$tid, $groupId, $markDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $markDateAttendance[(int)$row['user_id']] = $row;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<style>
.att-segment {
    display: inline-flex;
    width: 100%;
    align-items: stretch;
    gap: 2px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 2px;
}
.att-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    flex: 1;
    padding: 0 8px;
    border-radius: 6px;
    border: none;
    background: transparent;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}
.att-btn.active-present { background: #ffffff; color: #059669; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }
.att-btn.active-absent { background: #ffffff; color: #dc2626; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }
.att-btn.active-late { background: #ffffff; color: #d97706; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }
</style>

<div class="mx-auto flex max-w-5xl flex-col gap-5">

    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">Посещаемость</h1>

    <?php if ($message): ?>
        <div class="rounded-xl border <?= $messageOk ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm font-medium shadow-sm">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($allGroups)): ?>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-12 text-center text-sm text-slate-500 shadow-sm">
            В этом центре пока нет активных групп. Сначала <a href="/admin/groups/edit.php" class="text-blue-600 no-underline hover:underline">создайте группу</a>.
        </div>
    <?php else: ?>

        <!-- Group tabs -->
        <div class="flex gap-2 overflow-x-auto pb-1">
            <?php foreach ($allGroups as $g): ?>
                <?php $isActiveTab = (int)$g['group_id'] === $groupId; ?>
                <a href="?<?= h(http_build_query(['group_id' => $g['group_id'], 'month' => $month])) ?>"
                   title="<?= h($g['course_title']) ?>"
                   class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs no-underline transition-colors <?= $isActiveTab ? 'bg-slate-900 text-white font-medium shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
                    <?= h($g['title']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($group): ?>
            <?php
            $studentsCount = count($members);
            $pctBadgeClass = $avgAttendancePct === null ? 'text-slate-400' : ($avgAttendancePct >= 90 ? 'text-emerald-600' : ($avgAttendancePct >= 75 ? 'text-amber-600' : 'text-red-600'));
            ?>

            <!-- Group header -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-lg font-bold text-slate-900"><?= h($group['title']) ?></p>
                        <p class="mt-0.5 text-sm text-slate-500"><?= h($group['course_title']) ?></p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="user" class="size-3.5"></i>
                                <?= $group['teacher_username'] ? h($group['teacher_username']) : '<span class="text-amber-600 font-medium">Не назначен</span>' ?>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="calendar" class="size-3.5"></i>
                                <?= h(formatDaysOfWeek($group['days_of_week'])) ?><?= $group['lesson_time'] ? ' · ' . h($group['lesson_time']) : '' ?>
                            </span>
                            <?php if (!empty($group['room'])): ?>
                                <span class="inline-flex items-center gap-1">
                                    <i data-lucide="map-pin" class="size-3.5"></i>
                                    <?= h($group['room']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="?<?= h(http_build_query(['group_id' => $groupId, 'month' => $month, 'action' => 'mark', 'mark_date' => $showMarkPanel ? $markDate : date('Y-m-d')])) ?>"
                       class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white no-underline hover:bg-blue-700">
                        <i data-lucide="calendar-plus" class="size-4"></i>
                        Отметить урок
                    </a>
                </div>

                <!-- KPI plaques -->
                <div class="mt-4 grid grid-cols-3 gap-3 border-t border-slate-100 pt-4">
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 text-center">
                        <p class="text-lg font-bold text-slate-900"><?= (int)$lessonsThisMonth ?></p>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Уроков в месяце</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 text-center">
                        <p class="text-lg font-bold <?= $pctBadgeClass ?>"><?= $avgAttendancePct !== null ? $avgAttendancePct . '%' : '—' ?></p>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Средняя явка</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 text-center">
                        <p class="text-lg font-bold text-slate-900"><?= $studentsCount ?></p>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Учеников</p>
                    </div>
                </div>

                <!-- Month switcher -->
                <div class="mt-4 flex items-center justify-center gap-3 border-t border-slate-100 pt-4">
                    <a href="?<?= h(http_build_query(['group_id' => $groupId, 'month' => $prevMonth])) ?>" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 no-underline hover:bg-slate-50">
                        <i data-lucide="chevron-left" class="size-4"></i>
                    </a>
                    <span class="min-w-[160px] text-center text-sm font-semibold text-slate-900"><?= h($monthLabel) ?></span>
                    <a href="?<?= h(http_build_query(['group_id' => $groupId, 'month' => $nextMonth])) ?>" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 no-underline hover:bg-slate-50">
                        <i data-lucide="chevron-right" class="size-4"></i>
                    </a>
                </div>
            </div>

            <?php if ($showMarkPanel): ?>
                <!-- Marking panel -->
                <div class="rounded-2xl border border-blue-200 bg-blue-50/40 p-5 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-sm font-bold text-slate-900">Отметить урок</span>
                            <form method="get" class="flex items-center gap-2">
                                <input type="hidden" name="group_id" value="<?= $groupId ?>">
                                <input type="hidden" name="month" value="<?= h($month) ?>">
                                <input type="hidden" name="action" value="mark">
                                <input type="date" name="mark_date" value="<?= h($markDate) ?>" onchange="this.form.submit()"
                                       class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                            </form>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="markAllPresent()" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 cursor-pointer">
                                <i data-lucide="check-check" class="size-3.5"></i>
                                Отметить всех «Был»
                            </button>
                            <a href="?<?= h(http_build_query(['group_id' => $groupId, 'month' => $month])) ?>" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 no-underline shadow-sm hover:bg-slate-50">
                                <i data-lucide="x" class="size-3.5"></i>
                                Свернуть
                            </a>
                        </div>
                    </div>

                    <?php if (empty($members)): ?>
                        <div class="rounded-xl border border-slate-200/80 bg-white p-8 text-center text-sm text-slate-500">
                            В группе пока нет активных студентов.
                        </div>
                    <?php else: ?>
                        <form method="post" class="flex flex-col gap-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_attendance">
                            <input type="hidden" name="group_id" value="<?= $groupId ?>">
                            <input type="hidden" name="month" value="<?= h($month) ?>">
                            <input type="hidden" name="lesson_date" value="<?= h($markDate) ?>">

                            <div class="rounded-xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                                <?php foreach ($members as $m): ?>
                                    <?php
                                    $uid = (int)$m['user_id'];
                                    $existing = $markDateAttendance[$uid] ?? null;
                                    $existingStatus = $existing['status'] ?? '';
                                    $existingReason = $existing['reason'] ?? '';
                                    $reasonHidden = !in_array($existingStatus, ['absent', 'late'], true);
                                    ?>
                                    <div class="student-row border-b border-slate-100 last:border-0" data-user="<?= $uid ?>">
                                        <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                            <div class="min-w-0 sm:w-48 shrink-0">
                                                <p class="text-sm font-medium text-slate-900 truncate"><?= h(trim($m['first_name'] . ' ' . $m['last_name'])) ?></p>
                                                <p class="text-xs text-slate-500"><?= h($m['phone']) ?: '—' ?></p>
                                            </div>
                                            <div class="att-segment sm:flex-1">
                                                <button type="button" class="att-btn <?= $existingStatus === 'present' ? 'active-present' : '' ?>" data-status="present" onclick="setAttendance(this)">Был</button>
                                                <button type="button" class="att-btn <?= $existingStatus === 'absent' ? 'active-absent' : '' ?>" data-status="absent" onclick="setAttendance(this)">Не был</button>
                                                <button type="button" class="att-btn <?= $existingStatus === 'late' ? 'active-late' : '' ?>" data-status="late" onclick="setAttendance(this)">Опоздал</button>
                                            </div>
                                        </div>
                                        <div class="reason-wrap px-3 pb-2.5 <?= $reasonHidden ? 'hidden' : '' ?>">
                                            <input type="text" name="reason[<?= $uid ?>]" value="<?= h($existingReason) ?>" placeholder="Причина (необязательно)"
                                                   class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                                        </div>
                                        <input type="hidden" name="status[<?= $uid ?>]" value="<?= h($existingStatus) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="flex items-center justify-end">
                                <button type="submit" style="border: none;" class="inline-flex items-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                                    Сохранить
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Attendance grid -->
            <div>
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Журнал за <?= h($monthLabel) ?></h2>
                <?php if (empty($members)): ?>
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                        В группе пока нет активных студентов.
                    </div>
                <?php elseif (empty($lessonDates)): ?>
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                        За <?= h($monthLabel) ?> ещё нет отметок посещаемости.
                        <a href="?<?= h(http_build_query(['group_id' => $groupId, 'month' => $month, 'action' => 'mark', 'mark_date' => date('Y-m-d')])) ?>" class="text-blue-600 no-underline hover:underline">Отметить урок</a>
                    </div>
                <?php else: ?>
                    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left">
                                <thead>
                                    <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                        <th class="px-3 py-3.5 font-semibold text-center">#</th>
                                        <th class="sticky left-0 bg-white px-3 py-3.5 font-semibold whitespace-nowrap">Студент</th>
                                        <th class="hidden sm:table-cell px-3 py-3.5 font-semibold whitespace-nowrap">Телефон</th>
                                        <?php foreach ($lessonDates as $d): ?>
                                            <th class="px-2 py-3.5 text-center font-semibold whitespace-nowrap">
                                                <?= h(ruWeekdayShort($d)) ?> <?= h(date('d', strtotime($d))) ?>
                                            </th>
                                        <?php endforeach; ?>
                                        <th class="px-3 py-3.5 text-center font-semibold whitespace-nowrap">Явка %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $i => $m): ?>
                                        <?php
                                        $uid = (int)$m['user_id'];
                                        $marks = $attendanceMap[$uid] ?? [];
                                        $total = count($marks);
                                        $attended = 0;
                                        foreach ($marks as $mm) {
                                            if ($mm['status'] === 'present' || $mm['status'] === 'late') {
                                                $attended++;
                                            }
                                        }
                                        $pct = $total > 0 ? round($attended / $total * 100) : null;
                                        $pctClass = $pct === null ? 'text-slate-300' : ($pct >= 90 ? 'text-emerald-600' : ($pct >= 75 ? 'text-amber-600' : 'text-red-600'));
                                        ?>
                                        <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/60 transition-colors">
                                            <td class="px-3 py-3 text-center text-xs text-slate-400"><?= $i + 1 ?></td>
                                            <td class="sticky left-0 bg-white px-3 py-3 font-medium text-slate-900 whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-600"><?= h(initials($m['first_name'], $m['last_name'])) ?></span>
                                                    <a href="/admin/students/view.php?user_id=<?= $uid ?>" class="text-slate-900 no-underline hover:text-blue-600">
                                                        <?= h(trim($m['first_name'] . ' ' . $m['last_name'])) ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="hidden sm:table-cell px-3 py-3 text-slate-500 whitespace-nowrap"><?= h($m['phone']) ?: '—' ?></td>
                                            <?php foreach ($lessonDates as $d): ?>
                                                <td class="px-2 py-3 text-center">
                                                    <?php if (isset($marks[$d])):
                                                        $st = $marks[$d]['status'];
                                                        $reasonTxt = $marks[$d]['reason'];
                                                        $labels = ['present' => 'Был', 'absent' => 'Не был', 'late' => 'Опоздал'];
                                                        $badgeClasses = [
                                                            'present' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                                            'absent' => 'bg-rose-50 text-rose-700 border border-rose-200',
                                                            'late' => 'bg-amber-50 text-amber-700 border border-amber-200',
                                                        ];
                                                        $title = $labels[$st] . ($reasonTxt ? ': ' . $reasonTxt : '');
                                                        ?>
                                                        <span class="inline-flex size-[26px] items-center justify-center rounded-full text-[11px] font-bold <?= $badgeClasses[$st] ?>" title="<?= h($title) ?>">
                                                            <?php if ($st === 'present'): ?>
                                                                <i data-lucide="check" class="size-3"></i>
                                                            <?php elseif ($st === 'absent'): ?>
                                                                Н
                                                            <?php else: ?>
                                                                О
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-slate-300">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="px-3 py-3 text-center font-semibold whitespace-nowrap <?= $pctClass ?>">
                                                <?= $pct !== null ? $pct . '%' : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
function setAttendance(btn) {
    var row = btn.closest('.student-row');
    var status = btn.dataset.status;
    row.querySelector('input[type="hidden"][name^="status"]').value = status;
    row.querySelectorAll('.att-btn').forEach(function (b) {
        b.classList.remove('active-present', 'active-absent', 'active-late');
    });
    btn.classList.add('active-' + status);
    var reasonWrap = row.querySelector('.reason-wrap');
    if (status === 'absent' || status === 'late') {
        reasonWrap.classList.remove('hidden');
    } else {
        reasonWrap.classList.add('hidden');
    }
}
function markAllPresent() {
    document.querySelectorAll('.student-row').forEach(function (row) {
        var btn = row.querySelector('.att-btn[data-status="present"]');
        if (btn) setAttendance(btn);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
