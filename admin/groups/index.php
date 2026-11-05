<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();
$pageTitle = 'Группы';
$isTeacher = ($_SESSION['admin_role'] ?? '') === 'teacher';
$adminId = (int)($_SESSION['admin_id'] ?? 0);

function h($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// Human-readable day labels for the compact schedule badge, e.g. "Пн/Ср/Пт".
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

// Archive / restore a group in place (POST, tenant-scoped). Teachers may not
// archive groups — this is a center_admin/super_admin-only action.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
    require_admin_role(['center_admin', 'super_admin']);
    verify_csrf();
    $groupId = (int)($_POST['group_id'] ?? 0);
    $newActive = ($_POST['new_active'] ?? '1') === '1' ? 1 : 0;
    $upd = $pdo->prepare("UPDATE study_groups SET is_active = ? WHERE group_id = ? AND tenant_id = ?");
    $upd->execute([$newActive, $groupId, $tid]);
    header('Location: index.php?' . http_build_query($_GET));
    exit;
}

$filterCourse = (int)($_GET['course'] ?? 0);
$showArchived = ($_GET['status'] ?? '') === 'archived';
$search = trim((string)($_GET['q'] ?? ''));

// --- Top KPI panel (tenant-wide, unaffected by the filters below —
// but scoped down to just this teacher's own groups when applicable) -----
$kpiTeacherClause = $isTeacher ? " AND sg.teacher_id = ?" : "";
$kpiTeacherParam = $isTeacher ? [$adminId] : [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM study_groups sg WHERE sg.tenant_id = ? AND sg.is_active = 1{$kpiTeacherClause}");
$stmt->execute(array_merge([$tid], $kpiTeacherParam));
$kpiTotalGroups = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM course_subs cs
    JOIN study_groups sg ON sg.group_id = cs.group_id AND sg.tenant_id = cs.tenant_id
    WHERE cs.tenant_id = ? AND cs.status = 'active' AND sg.is_active = 1{$kpiTeacherClause}
");
$stmt->execute(array_merge([$tid], $kpiTeacherParam));
$kpiTotalStudents = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT sg.title
    FROM study_groups sg
    WHERE sg.tenant_id = ? AND sg.is_active = 1
      AND FIND_IN_SET(LEFT(LOWER(DAYNAME(CURDATE())), 3), sg.days_of_week){$kpiTeacherClause}
    ORDER BY sg.title ASC
");
$stmt->execute(array_merge([$tid], $kpiTeacherParam));
$kpiTodayGroupTitles = $stmt->fetchAll(PDO::FETCH_COLUMN);
$kpiTodayCount = count($kpiTodayGroupTitles);

// --- Filtered group list --------------------------------------------------
$where = "WHERE sg.tenant_id = ? AND sg.is_active = ?";
$params = [$tid, $showArchived ? 0 : 1];

// A teacher only ever sees the groups assigned to them.
if ($isTeacher) {
    $where .= " AND sg.teacher_id = ?";
    $params[] = $adminId;
}

if ($filterCourse > 0) {
    $where .= " AND sg.course_id = ?";
    $params[] = $filterCourse;
}
if ($search !== '') {
    $where .= " AND (sg.title LIKE ? OR a.username LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare("
    SELECT sg.*, c.title AS course_title,
           a.username AS teacher_username,
           (SELECT COUNT(*) FROM course_subs cs WHERE cs.group_id = sg.group_id AND cs.tenant_id = sg.tenant_id AND cs.status = 'active') AS active_students,
           FIND_IN_SET(LEFT(LOWER(DAYNAME(CURDATE())), 3), sg.days_of_week) AS is_lesson_today,
           (
               SELECT ROUND(SUM(CASE WHEN at.status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(*) * 100)
               FROM attendance at
               WHERE at.group_id = sg.group_id AND at.tenant_id = sg.tenant_id
                 AND at.lesson_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                 AND at.lesson_date <= LAST_DAY(CURDATE())
           ) AS attendance_pct
    FROM study_groups sg
    JOIN courses c ON c.course_id = sg.course_id AND c.tenant_id = sg.tenant_id
    LEFT JOIN admins a ON a.id = sg.teacher_id AND a.tenant_id = sg.tenant_id
    {$where}
    ORDER BY c.title ASC, sg.title ASC
");
$stmt->execute($params);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$coursesStmt = $pdo->prepare("SELECT course_id, title FROM courses WHERE tenant_id = ? ORDER BY title ASC");
$coursesStmt->execute([$tid]);
$allCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<style>
.metadata-row { display: flex; align-items: center; gap: 6px; color: #64748b; }
.metadata-row i { color: #94a3b8; flex-shrink: 0; }
</style>

<div class="mx-auto flex max-w-6xl flex-col gap-6">

    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Группы</h1>
            <span class="inline-flex min-w-7 items-center justify-center rounded-full bg-blue-50 px-2.5 py-0.5 text-sm font-semibold text-blue-700">
                <?= count($groups) ?>
            </span>
        </div>
        <?php if (!$isTeacher): ?>
            <a href="edit.php" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white no-underline shadow-sm transition-opacity hover:opacity-90">
                <i data-lucide="plus" class="size-4"></i>
                Создать группу
            </a>
        <?php endif; ?>
    </header>

    <!-- KPI panel -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
            <p class="text-2xl font-bold text-slate-900"><?= $kpiTotalGroups ?></p>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Всего групп</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
            <p class="text-2xl font-bold text-slate-900"><?= $kpiTotalStudents ?></p>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Учеников в группах</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
            <p class="text-2xl font-bold <?= $kpiTodayCount > 0 ? 'text-emerald-600' : 'text-slate-900' ?>"><?= $kpiTodayCount ?></p>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Занятия сегодня</p>
            <?php if ($kpiTodayCount > 0): ?>
                <p class="mt-1 truncate text-xs text-slate-500" title="<?= h(implode(', ', $kpiTodayGroupTitles)) ?>">
                    <?= h(implode(', ', $kpiTodayGroupTitles)) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="q" value="<?= h($search) ?>" placeholder="Поиск по названию группы или преподавателю..."
                   class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
        </div>
        <div class="relative sm:w-64 shrink-0">
            <select name="course" onchange="this.form.submit()" class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-9 text-sm font-medium text-slate-700 focus:border-blue-500 focus:outline-none cursor-pointer">
                <option value="0">Все курсы</option>
                <?php foreach ($allCourses as $c): ?>
                    <option value="<?= (int)$c['course_id'] ?>" <?= $filterCourse === (int)$c['course_id'] ? 'selected' : '' ?>><?= h($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm shrink-0">
            <a href="?<?= h(http_build_query(['q' => $search, 'course' => $filterCourse, 'status' => ''])) ?>" class="rounded-lg px-3 py-1.5 no-underline transition-colors <?= !$showArchived ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-900' ?>">Активные</a>
            <a href="?<?= h(http_build_query(['q' => $search, 'course' => $filterCourse, 'status' => 'archived'])) ?>" class="rounded-lg px-3 py-1.5 no-underline transition-colors <?= $showArchived ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-900' ?>">Архивные</a>
        </div>
        <button type="submit" class="hidden">Найти</button>
    </form>

    <?php if (empty($groups)): ?>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-12 text-center text-sm text-slate-500 shadow-sm">
            <?= $showArchived ? 'В архиве групп нет.' : 'Групп не найдено.' ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <?php foreach ($groups as $g): ?>
                <?php
                $gId = (int)$g['group_id'];
                $isLessonToday = (int)($g['is_lesson_today'] ?? 0) > 0;
                $pct = $g['attendance_pct'] !== null ? (int)$g['attendance_pct'] : null;
                $pctClass = $pct === null ? 'text-slate-400' : ($pct >= 90 ? 'text-emerald-600' : ($pct >= 75 ? 'text-amber-600' : 'text-red-600'));
                ?>
                <div class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">

                    <!-- Top badge row -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= h($g['course_title']) ?></span>
                        <?php if ($g['is_active']): ?>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">● Активна</span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-400">○ В архиве</span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <a href="view.php?id=<?= $gId ?>" class="text-lg font-bold text-slate-900 no-underline hover:text-blue-600"><?= h($g['title']) ?></a>
                    </div>

                    <?php if ($isLessonToday && $g['is_active']): ?>
                        <div class="inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                            <i data-lucide="zap" class="size-3.5"></i>
                            Урок сегодня<?= $g['lesson_time'] ? ' в ' . h($g['lesson_time']) : '' ?>
                        </div>
                    <?php endif; ?>

                    <!-- Metadata grid -->
                    <div class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                        <div class="metadata-row">
                            <i data-lucide="user" class="size-4"></i>
                            <?= $g['teacher_username'] ? h($g['teacher_username']) : '<span class="text-amber-600 font-medium">Не назначен</span>' ?>
                        </div>
                        <div class="metadata-row">
                            <i data-lucide="calendar" class="size-4"></i>
                            <?= h(formatDaysOfWeek($g['days_of_week'])) ?><?= $g['lesson_time'] ? ' · ' . h($g['lesson_time']) : '' ?>
                        </div>
                        <div class="metadata-row">
                            <i data-lucide="map-pin" class="size-4"></i>
                            <?= $g['room'] ? h($g['room']) : '—' ?>
                        </div>
                        <div class="metadata-row">
                            <i data-lucide="users" class="size-4"></i>
                            <?= (int)$g['active_students'] ?> уч.
                        </div>
                        <div class="metadata-row">
                            <i data-lucide="trending-up" class="size-4"></i>
                            <span class="font-semibold <?= $pctClass ?>"><?= $pct !== null ? $pct . '%' : '—' ?></span>
                            <span class="text-xs text-slate-400">явка</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
                        <a href="/admin/attendance/index.php?group_id=<?= $gId ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white no-underline hover:bg-blue-700">
                            <i data-lucide="calendar-check" class="size-3.5"></i>
                            Журнал
                        </a>
                        <a href="view.php?id=<?= $gId ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 no-underline hover:bg-slate-50">
                            <i data-lucide="users" class="size-3.5"></i>
                            Состав
                        </a>
                        <?php if (!$isTeacher): ?>
                            <div class="ml-auto flex items-center gap-1 shrink-0">
                                <a href="edit.php?id=<?= $gId ?>" title="Редактировать" class="inline-flex size-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                                    <i data-lucide="pencil" class="size-3.5"></i>
                                </a>
                                <form method="post" class="m-0" onsubmit="return confirm('<?= $g['is_active'] ? 'Перенести группу в архив?' : 'Восстановить группу?' ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="group_id" value="<?= $gId ?>">
                                    <input type="hidden" name="new_active" value="<?= $g['is_active'] ? '0' : '1' ?>">
                                    <button type="submit" title="<?= $g['is_active'] ? 'В архив' : 'Восстановить' ?>" class="inline-flex size-8 items-center justify-center rounded-lg border-0 bg-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer">
                                        <i data-lucide="<?= $g['is_active'] ? 'archive' : 'archive-restore' ?>" class="size-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
