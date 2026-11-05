<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../../php/student_service.php';
require_once __DIR__ . '/../includes/lang.php';

$pageTitle = __('nav_students');
$tid = Tenant::getId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_student') {
        // tenant_id comes only from Tenant::getId() (the admin's own session-bound
        // tenant) — never from $_POST, so a manipulated form field cannot create a
        // student in another tenant. StudentService::create() has no other side
        // effects: no enrollment, payment, transaction, test result, or progress row
        // is created here.
        $result = StudentService::create($pdo, $tid, $_POST);

        if ($result['success']) {
            $message = __('data_updated');
        } else {
            $message = implode(' ', array_values($result['errors']));
        }
    }

    $_SESSION['message'] = $message ?? '';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$showInactive = isset($_GET['inactive']) && $_GET['inactive'] === '1';

// Build query
$where = "WHERE u.tenant_id = ?";
$params = [$tid];

$filterCourse = (int)($_GET['course'] ?? 0);
$filterClass = trim($_GET['class'] ?? '');

if (!$showInactive) {
    $where .= " AND u.is_active = 1";
}
if ($filterCourse > 0) {
    $where .= " AND EXISTS (SELECT 1 FROM course_subs cs WHERE cs.user_id = u.user_id AND cs.course_id = ? AND cs.status = 'active')";
    $params[] = $filterCourse;
}
if ($filterClass !== '') {
    $where .= " AND u.class = ?";
    $params[] = $filterClass;
}

if ($search !== '') {
    $where .= " AND (u.user_id = ? OR u.phone LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.slug LIKE ?)";
    $like = "%{$search}%";
    $params[] = $search;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Count
$r = $pdo->prepare("SELECT COUNT(*) FROM users u {$where}");
$r->execute($params);
$total = (int)$r->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Fetch students with subscription info (Tenant isolated)
$r = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM course_subs cs WHERE cs.user_id = u.user_id AND cs.tenant_id = u.tenant_id AND cs.status = 'active') AS active_courses,
           (SELECT payment_date FROM payments p WHERE p.user_id = u.user_id AND p.tenant_id = u.tenant_id AND p.status = 'accepted' AND p.deleted_at IS NULL ORDER BY p.payment_date DESC LIMIT 1) AS last_payment
    FROM users u
    {$where}
    ORDER BY u.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$r->execute($params);
$students = $r->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses and classes for filters
$coursesStmt = $pdo->prepare("SELECT course_id, title, classLevel FROM courses WHERE tenant_id = ? ORDER BY title ASC");
$coursesStmt->execute([$tid]);
$allCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$classesStmt = $pdo->prepare("SELECT DISTINCT class FROM users WHERE tenant_id = ? AND class IS NOT NULL AND class != '' ORDER BY class ASC");
$classesStmt->execute([$tid]);
$allClasses = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

function getStudentInitials($firstName, $lastName) {
    $f = mb_substr(trim((string)$firstName), 0, 1, 'UTF-8');
    $l = mb_substr(trim((string)$lastName), 0, 1, 'UTF-8');
    $in = mb_strtoupper($f . $l, 'UTF-8');
    return $in ?: 'ST';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto flex max-w-7xl flex-col gap-6">

    <!-- Page Header -->
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars(__('nav_students')) ?></h1>
            <span class="inline-flex min-w-7 items-center justify-center rounded-full bg-blue-50 px-2.5 py-0.5 text-sm font-semibold text-blue-700">
                <?= $total ?>
            </span>
        </div>
        <div class="flex items-center gap-3">
            <a href="import.php" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 text-sm font-medium transition-colors">
                📥 Импорт из Excel/CSV
            </a>
            <button type="button" onclick="var f=document.getElementById('studentForm');f.style.display=f.style.display==='none'?'block':'none';" style="border: none;" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-90 cursor-pointer appearance-none outline-none">
                <i data-lucide="user-plus" class="size-4"></i>
                <?= htmlspecialchars(__('add_student')) ?>
            </button>
        </div>
    </header>

    <!-- Flash message -->
    <?php if ($message): ?>
        <div class="rounded-xl border <?= $message === __('data_updated') ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm font-medium shadow-sm">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Add Student Form (Modal / Expandable Card) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]" id="studentForm" style="display:none;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars(__('add_student')) ?></h2>
            <button type="button" onclick="document.getElementById('studentForm').style.display='none'" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border: 1px solid #e2e8f0;">
                <?= htmlspecialchars(__('cancel')) ?>
            </button>
        </div>
        <form method="post" class="flex flex-col gap-4 max-w-2xl">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_student">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('first_name')) ?> *</label>
                    <input type="text" name="first_name" required maxlength="255" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('last_name')) ?> *</label>
                    <input type="text" name="last_name" required maxlength="255" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('email')) ?> *</label>
                    <input type="email" name="email" required maxlength="255" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('phone')) ?></label>
                    <input type="text" name="phone" placeholder="90 123 45 67" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('class')) ?> *</label>
                    <select name="class" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                        <option value="">—</option>
                        <?php foreach (StudentService::CLASS_LEVELS as $lvl): ?>
                            <option value="<?= htmlspecialchars($lvl) ?>"><?= htmlspecialchars($lvl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('password')) ?> *</label>
                    <input type="password" name="password" required minlength="6" placeholder="мин. 6 символов" autocomplete="new-password" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="submit" style="border: none;" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-90 cursor-pointer appearance-none outline-none">
                    <?= htmlspecialchars(__('save')) ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Main Card -->
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
        
        <!-- Filter Bar (Matching Photo 1: search on left, selects on right) -->
        <div class="border-b border-slate-200/80 p-4 sm:p-5">
            <form method="get" id="filterForm" class="flex flex-col gap-3 lg:flex-row lg:items-center m-0 w-full">
                <!-- Search on the left -->
                <div class="relative flex-1 lg:max-w-md">
                    <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                    <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars(__('search_placeholder')) ?>" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" style="border: 1px solid #e2e8f0;">
                </div>

                <!-- Selects on the right -->
                <div class="flex flex-col gap-3 sm:flex-row lg:ml-auto">
                    <div class="relative sm:w-40 shrink-0">
                        <select name="class" id="classFilter" onchange="this.form.submit()" class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-9 text-sm font-medium text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 cursor-pointer" style="border: 1px solid #e2e8f0;">
                            <option value=""><?= htmlspecialchars(__('all_classes')) ?></option>
                            <?php foreach($allClasses as $cl): ?>
                                <option value="<?= htmlspecialchars($cl['class']) ?>" <?= $filterClass === $cl['class'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['class']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                    </div>

                    <div class="relative sm:w-56 shrink-0">
                        <select name="course" id="courseFilter" onchange="this.form.submit()" class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-9 text-sm font-medium text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 cursor-pointer" style="border: 1px solid #e2e8f0;">
                            <option value=""><?= htmlspecialchars(__('all_courses')) ?></option>
                            <?php foreach($allCourses as $c): ?>
                                <option value="<?= $c['course_id'] ?>" data-class="<?= htmlspecialchars($c['classLevel'] ?? '') ?>" <?= $filterCourse === $c['course_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                    </div>
                </div>
            </form>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const classFilter = document.getElementById("classFilter");
            const courseFilter = document.getElementById("courseFilter");
            const allCourseOptions = Array.from(courseFilter.options);
            
            function filterCourses() {
                const selectedClass = classFilter.value;
                const currentCourse = courseFilter.value;
                
                courseFilter.innerHTML = "";
                let hasSelected = false;
                
                allCourseOptions.forEach(opt => {
                    const optClass = opt.getAttribute("data-class");
                    if (opt.value === "" || selectedClass === "" || optClass === selectedClass) {
                        const clonedOpt = opt.cloneNode(true);
                        courseFilter.appendChild(clonedOpt);
                        if (opt.value === currentCourse) {
                            hasSelected = true;
                        }
                    }
                });
                
                if (hasSelected) {
                    courseFilter.value = currentCourse;
                } else {
                    courseFilter.value = "";
                }
            }
            
            classFilter.addEventListener("change", filterCourses);
        });
        </script>

        <!-- Students Table -->
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        <th class="px-5 py-4">ID</th>
                        <th class="px-5 py-4"><?= htmlspecialchars(__('name')) ?></th>
                        <th class="px-5 py-4"><?= htmlspecialchars(__('phone')) ?></th>
                        <th class="px-5 py-4"><?= htmlspecialchars(__('class')) ?></th>
                        <th class="px-5 py-4"><?= htmlspecialchars(__('status')) ?></th>
                        <th class="px-5 py-4"><?= htmlspecialchars(__('courses_count')) ?></th>
                        <th class="px-5 py-4"><?= htmlspecialchars(__('registration')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">
                                <?= htmlspecialchars(__('students_not_found')) ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr onclick="window.location.href='view.php?user_id=<?= $s['user_id'] ?>'" class="group border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50 cursor-pointer transition-colors <?= !$s['is_active'] ? 'opacity-60' : '' ?>">
                                <td class="px-5 py-5 font-mono text-slate-400"><?= $s['user_id'] ?></td>
                                <td class="px-5 py-5">
                                    <div class="flex items-center gap-3">
                                        <!-- Avatar with initials -->
                                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-semibold text-blue-600">
                                            <?= getStudentInitials($s['first_name'] ?? '', $s['last_name'] ?? '') ?>
                                        </div>
                                        <!-- Student name -->
                                        <span class="font-medium text-slate-900 group-hover:text-blue-600 transition-colors">
                                            <?= htmlspecialchars(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-5 text-slate-400 tabular-nums whitespace-nowrap"><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                                <td class="px-5 py-5 whitespace-nowrap">
                                    <?php if (!empty($s['class'])): ?>
                                        <span class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                            <?= htmlspecialchars($s['class']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-5 whitespace-nowrap">
                                    <?php if ($s['is_active']): ?>
                                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            <?= htmlspecialchars(__('active')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                            <?= htmlspecialchars(__('inactive')) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-5 whitespace-nowrap">
                                    <?php if ((int)$s['active_courses'] > 0): ?>
                                        <span class="inline-flex min-w-7 items-center justify-center rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">
                                            <?= $s['active_courses'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex min-w-7 items-center justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500">
                                            0
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-5 text-slate-400 tabular-nums whitespace-nowrap">
                                    <?= date('d.m.Y', strtotime($s['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-center gap-1 border-t border-slate-100 p-4">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&class=<?= urlencode($filterClass) ?>&course=<?= urlencode($filterCourse) ?>" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 no-underline transition-colors hover:bg-slate-50">&laquo;</a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-blue-600 text-sm font-semibold text-white"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&class=<?= urlencode($filterClass) ?>&course=<?= urlencode($filterCourse) ?>" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 no-underline transition-colors hover:bg-slate-50"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&class=<?= urlencode($filterClass) ?>&course=<?= urlencode($filterCourse) ?>" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 no-underline transition-colors hover:bg-slate-50">&raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
