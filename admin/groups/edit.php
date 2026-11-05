<?php
require_once __DIR__ . '/../authorization.php';
require_admin_role(['center_admin', 'super_admin']);
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php';

$tid = Tenant::getId();

function h($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

$groupId = (int)($_GET['id'] ?? 0);
$isEdit = $groupId > 0;
$errors = [];

// Prefill values (from DB on GET, from the resubmitted form on a failed POST).
$title = '';
$courseId = 0;
$teacherId = 0;
$daysOfWeek = 'mon,wed,fri';
$lessonTime = '18:00 - 19:30';
$room = '';
$isActive = true;

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM study_groups WHERE group_id = ? AND tenant_id = ?");
    $stmt->execute([$groupId, $tid]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) {
        header('Location: index.php');
        exit;
    }
    $title = $group['title'];
    $courseId = (int)$group['course_id'];
    $teacherId = (int)($group['teacher_id'] ?? 0);
    $daysOfWeek = $group['days_of_week'];
    $lessonTime = (string)$group['lesson_time'];
    $room = (string)$group['room'];
    $isActive = (bool)$group['is_active'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $courseId = (int)($_POST['course_id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $daysOfWeek = trim($_POST['days_of_week'] ?? '');
    $lessonTime = trim($_POST['lesson_time'] ?? '');
    $room = trim($_POST['room'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($title === '') {
        $errors[] = 'Введите название группы.';
    }
    if ($daysOfWeek === '') {
        $errors[] = 'Укажите дни занятий.';
    }

    // Course must belong to this tenant.
    if ($courseId <= 0) {
        $errors[] = 'Выберите курс.';
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = ? AND tenant_id = ?");
        $chk->execute([$courseId, $tid]);
        if ((int)$chk->fetchColumn() === 0) {
            $errors[] = 'Выбранный курс не найден.';
        }
    }

    // Teacher, if given, must be an admin of this tenant with role teacher/center_admin.
    if ($teacherId > 0) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE id = ? AND tenant_id = ? AND role IN ('teacher', 'center_admin')");
        $chk->execute([$teacherId, $tid]);
        if ((int)$chk->fetchColumn() === 0) {
            $errors[] = 'Выбранный преподаватель не найден.';
        }
    } else {
        $teacherId = null;
    }

    if (empty($errors)) {
        if ($isEdit) {
            $upd = $pdo->prepare("
                UPDATE study_groups SET
                    title = ?, course_id = ?, teacher_id = ?, days_of_week = ?,
                    lesson_time = ?, room = ?, is_active = ?
                WHERE group_id = ? AND tenant_id = ?
            ");
            $upd->execute([$title, $courseId, $teacherId, $daysOfWeek, $lessonTime ?: null, $room ?: null, $isActive, $groupId, $tid]);
        } else {
            $ins = $pdo->prepare("
                INSERT INTO study_groups (tenant_id, course_id, teacher_id, title, days_of_week, lesson_time, room, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$tid, $courseId, $teacherId, $title, $daysOfWeek, $lessonTime ?: null, $room ?: null, $isActive]);
        }
        header('Location: index.php');
        exit;
    }
}

$pageTitle = $isEdit ? 'Редактировать группу' : 'Создать группу';

$coursesStmt = $pdo->prepare("SELECT course_id, title FROM courses WHERE tenant_id = ? ORDER BY title ASC");
$coursesStmt->execute([$tid]);
$allCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$teachersStmt = $pdo->prepare("SELECT id, username, role FROM admins WHERE tenant_id = ? AND role IN ('teacher', 'center_admin') ORDER BY username ASC");
$teachersStmt->execute([$tid]);
$allTeachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

$dayPresets = [
    'mon,wed,fri' => "Пн / Ср / Пт (Toq kunlar)",
    'tue,thu,sat' => "Вт / Чт / Сб (Juft kunlar)",
    'mon,tue,wed,thu,fri,sat,sun' => 'Каждый день',
];

include __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto flex max-w-2xl flex-col gap-6">

    <div>
        <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 no-underline transition-colors hover:text-slate-900">
            <i data-lucide="arrow-left" class="size-4"></i>
            К списку групп
        </a>
    </div>

    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight"><?= h($pageTitle) ?></h1>

    <?php if ($errors): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
            <ul class="m-0 list-disc pl-5">
                <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
        <form method="post" class="flex flex-col gap-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Название группы *</label>
                <input type="text" name="title" value="<?= h($title) ?>" required placeholder="Например: IELTS Intensive — Утренняя"
                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Курс *</label>
                    <select name="course_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                        <option value="">— Выберите курс —</option>
                        <?php foreach ($allCourses as $c): ?>
                            <option value="<?= (int)$c['course_id'] ?>" <?= $courseId === (int)$c['course_id'] ? 'selected' : '' ?>><?= h($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Преподаватель</label>
                    <select name="teacher_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                        <option value="">Не назначен</option>
                        <?php foreach ($allTeachers as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= $teacherId === (int)$t['id'] ? 'selected' : '' ?>><?= h($t['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Дни недели *</label>
                <select id="days_preset" onchange="applyDaysPreset(this.value)" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none mb-2">
                    <?php $matchedPreset = false; ?>
                    <?php foreach ($dayPresets as $val => $label): ?>
                        <option value="<?= h($val) ?>" <?= $daysOfWeek === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php if ($daysOfWeek === $val) { $matchedPreset = true; } ?>
                    <?php endforeach; ?>
                    <option value="" <?= !$matchedPreset ? 'selected' : '' ?>>Индивидуально</option>
                </select>
                <input type="text" id="days_of_week_input" name="days_of_week" value="<?= h($daysOfWeek) ?>" required
                       placeholder="mon,wed,fri"
                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                <p class="mt-1 text-xs text-slate-400">Значения через запятую: mon, tue, wed, thu, fri, sat, sun.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Время урока</label>
                    <input type="text" name="lesson_time" value="<?= h($lessonTime) ?>" placeholder="09:00 - 10:30"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Кабинет / Аудитория</label>
                    <input type="text" name="room" value="<?= h($room) ?>" placeholder="1-xona"
                           class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> class="size-4 rounded border-slate-300">
                Группа активна
            </label>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <a href="index.php" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 no-underline shadow-sm hover:bg-slate-50">
                    Отмена
                </a>
                <button type="submit" style="border: none;" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer">
                    Сохранить
                </button>
            </div>
        </form>
    </div>

</div>

<script>
function applyDaysPreset(value) {
    if (value === '') return; // "Индивидуально" — leave the text field for manual editing
    document.getElementById('days_of_week_input').value = value;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
