<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../../php/tenant_file.php';
require_once __DIR__ . '/../includes/lang.php';

$pageTitle = __('courses');
$tid = Tenant::getId();

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return;
    $items = scandir($dirPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) { deleteDir($itemPath); } else { unlink($itemPath); }
    }
    rmdir($dirPath);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_course') {
        $subject = trim($_POST['subject'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $days = (int)($_POST['days'] ?? 30);
        $classLevel = trim($_POST['classLevel'] ?? '');

        if ($subject && $title && $classLevel && $days > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO courses (tenant_id, subject, title, description, price, subscription_days, classLevel) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tid, $subject, $title, $desc, $price, $days, $classLevel]);
                $message = __('data_updated');
            } catch (PDOException $e) {
                error_log("add_course error: " . $e->getMessage());
                $message = __('error_occurred');
            }
        } else {
            $message = 'Заполните все обязательные поля. Срок подписки должен быть больше 0 дней.';
        }
    } elseif ($action === 'delete_course') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        if ($courseId > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ? AND tenant_id = ?");
                $stmt->execute([$courseId, $tid]);
                $resourceDir = TenantFile::getFullPath($tid, "courses/{$courseId}");
                if (is_dir($resourceDir)) { deleteDir($resourceDir); }
                $message = __('data_updated');
            } catch (PDOException $e) {
                error_log("delete_course error: " . $e->getMessage());
                $message = __('error_occurred');
            }
        }
    }

    $_SESSION['message'] = $message;
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Filters
$filterClass = $_GET['class'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = "WHERE c.tenant_id = ?";
$params = [$tid];

if ($filterClass !== '' && in_array($filterClass, ['1-2', '3-4', '5-6', '7-8', '9-11', '1-4'])) {
    $where .= " AND c.classLevel = ?";
    $params[] = $filterClass;
}

if ($search !== '') {
    $where .= " AND (c.subject LIKE ? OR c.title LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

// Fetch courses with counts (Tenant isolated)
$r = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.course_id AND m.tenant_id = c.tenant_id) AS material_count,
           (SELECT COUNT(*) FROM course_subs cs WHERE cs.course_id = c.course_id AND cs.tenant_id = c.tenant_id AND cs.status = 'active') AS student_count
    FROM courses c
    {$where}
    ORDER BY c.created_at DESC
");
$r->execute($params);
$courses = $r->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto flex max-w-6xl flex-col gap-6">

    <!-- Page Header -->
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars(__('courses')) ?></h1>
            <span class="inline-flex min-w-7 items-center justify-center rounded-full bg-blue-50 px-2.5 py-0.5 text-sm font-semibold text-blue-700">
                <?= count($courses) ?>
            </span>
        </div>
        <button type="button" onclick="var f=document.getElementById('courseForm');f.style.display=f.style.display==='none'?'block':'none';" style="border: none;" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-90 cursor-pointer appearance-none outline-none">
            <i data-lucide="plus" class="size-4"></i>
            <?= htmlspecialchars(__('add_course')) ?>
        </button>
    </header>

    <!-- Flash message -->
    <?php if ($message): ?>
        <div class="rounded-xl border <?= $message === __('data_updated') ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm font-medium shadow-sm">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Add Course Form (Modal / Expandable Card) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]" id="courseForm" style="display:none;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars(__('add_course_title')) ?></h2>
            <button type="button" onclick="document.getElementById('courseForm').style.display='none'" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border: 1px solid #e2e8f0;">
                <?= htmlspecialchars(__('cancel')) ?>
            </button>
        </div>
        <form method="post" class="flex flex-col gap-4 max-w-2xl">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_course">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('subject_col')) ?> *</label>
                    <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('title_col')) ?> *</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('description')) ?></label>
                <textarea name="description" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('price')) ?> (so'm) *</label>
                    <input type="number" name="price" step="1000" min="0" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('subscription_days')) ?> *</label>
                    <input type="number" name="days" min="1" value="<?= htmlspecialchars($_POST['days'] ?? 30) ?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none" style="border: 1px solid #e2e8f0;">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"><?= htmlspecialchars(__('class')) ?> *</label>
                    <select name="classLevel" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none cursor-pointer" style="border: 1px solid #e2e8f0;">
                        <option value=""><?= htmlspecialchars(__('select')) ?></option>
                        <?php foreach (['1-2','3-4','5-6','7-8','9-11','1-4'] as $level): ?>
                            <option value="<?= $level ?>" <?= (isset($_POST['classLevel']) && $_POST['classLevel'] === $level) ? 'selected' : '' ?>><?= $level ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-2">
                <button type="submit" style="border: none;" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                    <?= htmlspecialchars(__('save_course')) ?>
                </button>
                <button type="button" onclick="document.getElementById('courseForm').style.display='none'" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border: 1px solid #e2e8f0;">
                    <?= htmlspecialchars(__('cancel')) ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Main Card -->
    <div class="rounded-xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">

        <!-- Controls: Search on left, Class filter on right -->
        <div class="border-b border-slate-200/80 p-4">
            <form method="get" id="filterForm" class="flex flex-col gap-3 sm:flex-row sm:items-center m-0 w-full">
                <!-- Search on the left -->
                <div class="relative flex-1 min-w-0">
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                    <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars(__('search_courses')) ?>" class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" style="border: 1px solid #e2e8f0;">
                </div>

                <!-- Class Filter on the right -->
                <div class="relative sm:w-44 shrink-0">
                    <select name="class" onchange="this.form.submit()" class="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-9 text-sm font-medium text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 cursor-pointer" style="border: 1px solid #e2e8f0;">
                        <option value=""><?= htmlspecialchars(__('all_classes')) ?></option>
                        <?php foreach (['1-2', '3-4', '5-6', '7-8', '9-11', '1-4'] as $level): ?>
                            <option value="<?= $level ?>" <?= $filterClass === $level ? 'selected' : '' ?>><?= $level ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        <th class="px-4 py-3.5 pl-6"><?= htmlspecialchars(__('subject_col')) ?></th>
                        <th class="px-4 py-3.5"><?= htmlspecialchars(__('title_col')) ?></th>
                        <th class="px-4 py-3.5"><?= htmlspecialchars(__('class')) ?></th>
                        <th class="px-4 py-3.5 text-right"><?= htmlspecialchars(__('price')) ?></th>
                        <th class="px-4 py-3.5 text-right"><?= htmlspecialchars(__('students_col')) ?></th>
                        <th class="px-4 py-3.5 pr-6 text-right"><?= htmlspecialchars(__('materials_col')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">
                                <?= $search || $filterClass ? htmlspecialchars(__('no_courses_search')) : htmlspecialchars(__('no_courses')) ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courses as $c): ?>
                            <tr onclick="window.location.href='view.php?course_id=<?= $c['course_id'] ?>'" class="group border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/70 transition-colors cursor-pointer">
                                <td class="px-4 py-4 pl-6 text-slate-500"><?= htmlspecialchars($c['subject']) ?></td>
                                <td class="px-4 py-4">
                                    <a href="view.php?course_id=<?= $c['course_id'] ?>" class="font-semibold text-slate-900 no-underline transition-colors group-hover:text-blue-600 hover:text-blue-600">
                                        <?= htmlspecialchars($c['title']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                        <?= htmlspecialchars($c['classLevel']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right tabular-nums whitespace-nowrap">
                                    <?php if ($c['price'] > 0): ?>
                                        <span class="font-semibold text-slate-900"><?= number_format($c['price'], 0, '.', ' ') ?></span> <span class="text-xs font-normal text-slate-400">so'm</span>
                                    <?php else: ?>
                                        <span class="text-slate-400"><?= htmlspecialchars(__('free')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-right font-semibold tabular-nums text-slate-900">
                                    <?= $c['student_count'] ?>
                                </td>
                                <td class="px-4 py-4 pr-6 text-right tabular-nums text-slate-400">
                                    <?= $c['material_count'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
