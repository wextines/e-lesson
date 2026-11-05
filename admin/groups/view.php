<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();
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

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT sg.*, c.title AS course_title, a.username AS teacher_username
    FROM study_groups sg
    JOIN courses c ON c.course_id = sg.course_id AND c.tenant_id = sg.tenant_id
    LEFT JOIN admins a ON a.id = sg.teacher_id AND a.tenant_id = sg.tenant_id
    WHERE sg.group_id = ? AND sg.tenant_id = ?
");
$stmt->execute([$groupId, $tid]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$group) {
    header('Location: index.php');
    exit;
}

// A teacher may only ever look at their own groups — never someone else's.
if ($isTeacher && (int)$group['teacher_id'] !== $adminId) {
    http_response_code(403);
    die('Доступ запрещён: эта группа закреплена за другим преподавателем.');
}

$pageTitle = $group['title'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Teachers don't change group composition — assigning/unassigning students
    // is a center_admin/super_admin action only.
    require_admin_role(['center_admin', 'super_admin']);
    $action = $_POST['action'] ?? '';

    if ($action === 'assign_student') {
        $subscriptionId = (int)($_POST['subscription_id'] ?? 0);
        // Only a subscription for THIS group's own course, still unassigned, can be pulled in —
        // prevents attaching a student enrolled in a different course by a crafted request.
        $upd = $pdo->prepare("
            UPDATE course_subs cs
            JOIN study_groups sg ON sg.group_id = ? AND sg.tenant_id = cs.tenant_id AND sg.course_id = cs.course_id
            SET cs.group_id = sg.group_id
            WHERE cs.subscription_id = ? AND cs.tenant_id = ? AND cs.group_id IS NULL
        ");
        $upd->execute([$groupId, $subscriptionId, $tid]);
        $message = $upd->rowCount() > 0 ? 'Студент добавлен в группу.' : 'Не удалось добавить студента.';
    } elseif ($action === 'unassign_student') {
        $subscriptionId = (int)($_POST['subscription_id'] ?? 0);
        $upd = $pdo->prepare("UPDATE course_subs SET group_id = NULL WHERE subscription_id = ? AND group_id = ? AND tenant_id = ?");
        $upd->execute([$subscriptionId, $groupId, $tid]);
        $message = $upd->rowCount() > 0 ? 'Студент откреплён от группы.' : 'Не удалось открепить студента.';
    }
}

$stmt = $pdo->prepare("
    SELECT cs.subscription_id, cs.status, cs.next_payment_date,
           u.user_id, u.first_name, u.last_name, u.phone
    FROM course_subs cs
    JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
    WHERE cs.group_id = ? AND cs.tenant_id = ?
    ORDER BY u.last_name ASC, u.first_name ASC
");
$stmt->execute([$groupId, $tid]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$availableStudents = [];
if (!$isTeacher) {
    $stmt = $pdo->prepare("
        SELECT cs.subscription_id, u.first_name, u.last_name
        FROM course_subs cs
        JOIN users u ON u.user_id = cs.user_id AND u.tenant_id = cs.tenant_id
        WHERE cs.course_id = ? AND cs.tenant_id = ? AND cs.group_id IS NULL
        ORDER BY u.last_name ASC, u.first_name ASC
    ");
    $stmt->execute([$group['course_id'], $tid]);
    $availableStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Attendance grid for the current month ------------------------------
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$attStmt = $pdo->prepare("
    SELECT user_id, lesson_date, status
    FROM attendance
    WHERE tenant_id = ? AND group_id = ? AND lesson_date BETWEEN ? AND ?
    ORDER BY lesson_date ASC
");
$attStmt->execute([$tid, $groupId, $monthStart, $monthEnd]);
$attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);

$lessonDates = [];
$attendanceMap = [];
foreach ($attRows as $row) {
    $d = $row['lesson_date'];
    $lessonDates[$d] = true;
    $attendanceMap[(int)$row['user_id']][$d] = $row['status'];
}
$lessonDates = array_keys($lessonDates);
sort($lessonDates);

$attBadge = ['present' => '🟢', 'absent' => '🔴', 'late' => '🟡'];

include __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto flex max-w-4xl flex-col gap-6">

    <div>
        <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 no-underline transition-colors hover:text-slate-900">
            <i data-lucide="arrow-left" class="size-4"></i>
            К списку групп
        </a>
    </div>

    <?php if ($message): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <!-- Group header -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight m-0"><?= h($group['title']) ?></h1>
                <p class="mt-1 text-sm text-slate-500"><?= h($group['course_title']) ?></p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="/admin/attendance/index.php?group_id=<?= (int)$group['group_id'] ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white no-underline shadow-sm hover:opacity-90">
                    <i data-lucide="calendar-check" class="size-3.5"></i>
                    Отметить урок
                </a>
                <?php if (!$isTeacher): ?>
                    <a href="edit.php?id=<?= (int)$group['group_id'] ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 no-underline shadow-sm hover:bg-slate-50">
                        <i data-lucide="pencil" class="size-3.5"></i>
                        Редактировать
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Преподаватель</p>
                <p class="mt-1 text-sm font-medium text-slate-900"><?= $group['teacher_username'] ? h($group['teacher_username']) : '<span class="text-amber-600">Не назначен</span>' ?></p>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Дни</p>
                <p class="mt-1 text-sm font-medium text-slate-900"><?= h(formatDaysOfWeek($group['days_of_week'])) ?></p>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Время</p>
                <p class="mt-1 text-sm font-medium text-slate-900"><?= $group['lesson_time'] ? h($group['lesson_time']) : '—' ?></p>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Аудитория</p>
                <p class="mt-1 text-sm font-medium text-slate-900"><?= $group['room'] ? h($group['room']) : '—' ?></p>
            </div>
        </div>
    </div>

    <!-- Add student -->
    <?php if (!empty($availableStudents)): ?>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
            <h2 class="mb-3 text-sm font-bold text-slate-900">Добавить студента в группу</h2>
            <form method="post" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign_student">
                <select name="subscription_id" required class="w-full sm:max-w-xs rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                    <option value="">— Выберите студента —</option>
                    <?php foreach ($availableStudents as $s): ?>
                        <option value="<?= (int)$s['subscription_id'] ?>"><?= h(trim($s['first_name'] . ' ' . $s['last_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="border: none;" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer shrink-0">
                    Добавить
                </button>
            </form>
            <p class="mt-2 text-xs text-slate-400">Показаны только студенты курса «<?= h($group['course_title']) ?>», ещё не состоящие в группе.</p>
        </div>
    <?php endif; ?>

    <!-- Members table -->
    <div>
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Состав группы (<?= count($members) ?>)</h2>
        <?php if (empty($members)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                В группе пока нет студентов.
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="px-5 py-3.5 font-semibold">Студент</th>
                                <th class="px-5 py-3.5 font-semibold">Телефон</th>
                                <th class="px-5 py-3.5 font-semibold text-center">Статус</th>
                                <th class="px-5 py-3.5 font-semibold">След. платёж</th>
                                <?php if (!$isTeacher): ?>
                                    <th class="px-5 py-3.5 text-right font-semibold"></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/60 transition-colors">
                                    <td class="px-5 py-4 font-medium text-slate-900">
                                        <a href="/admin/students/view.php?user_id=<?= (int)$m['user_id'] ?>" class="text-slate-900 no-underline hover:text-blue-600">
                                            <?= h(trim($m['first_name'] . ' ' . $m['last_name'])) ?>
                                        </a>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500 whitespace-nowrap"><?= h($m['phone']) ?: '—' ?></td>
                                    <td class="px-5 py-4 text-center">
                                        <?php if ($m['status'] === 'active'): ?>
                                            <span class="inline-flex rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Активна</span>
                                        <?php elseif ($m['status'] === 'pending'): ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Ожидает оплаты</span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600"><?= h($m['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500 whitespace-nowrap">
                                        <?= !empty($m['next_payment_date']) ? date('d.m.Y', strtotime($m['next_payment_date'])) : '—' ?>
                                    </td>
                                    <?php if (!$isTeacher): ?>
                                    <td class="px-5 py-4 text-right whitespace-nowrap">
                                        <form method="post" class="m-0 inline" onsubmit="return confirm('Открепить студента от группы?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unassign_student">
                                            <input type="hidden" name="subscription_id" value="<?= (int)$m['subscription_id'] ?>">
                                            <button type="submit" style="border: 1px solid #e2e8f0;" class="inline-flex items-center rounded-lg bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-50 cursor-pointer">
                                                Открепить
                                            </button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Attendance grid (current month) -->
    <div>
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Посещаемость за <?= h(date('m.Y')) ?></h2>
        <?php if (empty($members)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                В группе нет студентов для отображения посещаемости.
            </div>
        <?php elseif (empty($lessonDates)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                В этом месяце ещё нет отметок посещаемости.
                <a href="/admin/attendance/index.php?group_id=<?= (int)$group['group_id'] ?>" class="text-blue-600 no-underline hover:underline">Отметить урок</a>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="sticky left-0 bg-white px-5 py-3.5 font-semibold">Студент</th>
                                <?php foreach ($lessonDates as $d): ?>
                                    <th class="px-2 py-3.5 text-center font-semibold whitespace-nowrap"><?= h(date('d.m', strtotime($d))) ?></th>
                                <?php endforeach; ?>
                                <th class="px-3 py-3.5 text-center font-semibold whitespace-nowrap">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m): ?>
                                <?php
                                $uid = (int)$m['user_id'];
                                $marks = $attendanceMap[$uid] ?? [];
                                $total = count($marks);
                                $attended = 0;
                                foreach ($marks as $st) {
                                    if ($st === 'present' || $st === 'late') {
                                        $attended++;
                                    }
                                }
                                $pct = $total > 0 ? round($attended / $total * 100) : null;
                                ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/60 transition-colors">
                                    <td class="sticky left-0 bg-white px-5 py-3 font-medium text-slate-900 whitespace-nowrap">
                                        <?= h(trim($m['first_name'] . ' ' . $m['last_name'])) ?>
                                    </td>
                                    <?php foreach ($lessonDates as $d): ?>
                                        <td class="px-2 py-3 text-center">
                                            <?= isset($marks[$d]) ? ($attBadge[$marks[$d]] ?? '—') : '<span class="text-slate-300">—</span>' ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-3 py-3 text-center font-semibold whitespace-nowrap <?= $pct !== null && $pct < 70 ? 'text-red-600' : 'text-slate-700' ?>">
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

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
