<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../../php/tenant_file.php';

$tid = Tenant::getId();
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$tab = $_GET['tab'] ?? 'overview';
if ($tab === 'materials') {
    $tab = 'content';
}

if (!$course_id) { header('Location: /admin/courses/'); exit; }

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function deleteDir($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) { if ($f==='.'||$f==='..') continue; $p=$d.'/'.$f; is_dir($p)?deleteDir($p):unlink($p); }
    rmdir($d);
}
function dt2sql($dt) { return $dt ? str_replace('T',' ',$dt).':00' : null; }

$course = $pdo->prepare("SELECT * FROM courses WHERE course_id=? AND tenant_id=?");
$course->execute([$course_id, $tid]);
$course = $course->fetch(PDO::FETCH_ASSOC);
if (!$course) { header('Location: /admin/courses/'); exit; }

$msg = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'edit_course') {
        $s=trim($_POST['subject']??''); $t=trim($_POST['title']??''); $d=trim($_POST['description']??'');
        $p=(float)($_POST['price']??0); $dy=(int)($_POST['days']??0); $cl=$_POST['classLevel']??'';
        if (!$s || !$t || $dy <= 0) { $msg = 'Заполните обязательные поля. Срок подписки должен быть больше 0 дней.'; }
        else {
            $u=$pdo->prepare("UPDATE courses SET subject=?,title=?,description=?,price=?,subscription_days=?,classLevel=? WHERE course_id=? AND tenant_id=?");
            $u->execute([$s,$t,$d,$p,$dy,$cl,$course_id,$tid]);
            $msg=__('data_updated');
            $course['subject']=$s; $course['title']=$t; $course['description']=$d;
            $course['price']=$p; $course['subscription_days']=$dy; $course['classLevel']=$cl;
        }
    }
    if ($act === 'delete_course') {
        $pdo->prepare("DELETE FROM courses WHERE course_id=? AND tenant_id=?")->execute([$course_id,$tid]);
        $rd=TenantFile::getFullPath($tid,"courses/{$course_id}"); if(is_dir($rd)) deleteDir($rd);
        $_SESSION['message']='Course deleted'; header('Location: /admin/courses/'); exit;
    }
    if ($act === 'enroll_student') {
        // Enrollment is NOT a payment: it only creates a pending subscription with no
        // access and no transaction. Access is granted later via "Выдать доступ" on the
        // student's profile (php/payment_service.php). Enrollment itself must never
        // grant or extend access.
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            $uStmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id=? AND tenant_id=?");
            $uStmt->execute([$uid, $tid]);
            if ($uStmt->fetch()) {
                $stmt = $pdo->prepare("SELECT subscription_id FROM course_subs WHERE user_id=? AND course_id=? AND tenant_id=?");
                $stmt->execute([$uid, $course_id, $tid]);
                if ($stmt->rowCount() > 0) {
                    $msg = 'Студент уже записан на этот курс. Управляйте доступом на странице студента.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO course_subs (user_id, course_id, price, purchase_date, access_expires_at, next_payment_date, status, tenant_id) VALUES (?, ?, ?, NOW(), NULL, NULL, 'pending', ?)");
                    $stmt->execute([$uid, $course_id, $course['price'], $tid]);
                    $msg = __('data_updated');
                }
            }
        }
    }
    if ($act === 'add_material') {
        $lid=(int)($_POST['lesson_id']??0); $t=trim($_POST['title']??''); $d=trim($_POST['description']??''); $dur=(int)($_POST['duration']??0);
        $youtubeUrl=trim($_POST['youtube_url']??'');
        $ytId=$youtubeUrl!==''?VideoHelper::extractYouTubeId($youtubeUrl):null;
        if (!$t||!$lid) { $msg='Lesson number and title are required'; }
        elseif ($youtubeUrl!==''&&!$ytId) { $msg='Укажите корректную ссылку на YouTube видео.'; }
        elseif ($youtubeUrl===''&&(empty($_FILES['file_path']['tmp_name'])||$_FILES['file_path']['error']!==UPLOAD_ERR_OK)) { $msg='Video file is required'; }
        else {
            $ck=$pdo->prepare("SELECT COUNT(*) FROM materials WHERE lesson_id=? AND course_id=? AND tenant_id=?");
            $ck->execute([$lid,$course_id,$tid]);
            if ($ck->fetchColumn()>0) { $msg='Lesson number already exists in this course'; }
            elseif ($ytId) {
                $td=!empty($_FILES['thumbnail_path']['tmp_name'])?TenantFile::handleUpload($_FILES['thumbnail_path'],$tid,'courses',$course_id):null;
                $ins=$pdo->prepare("INSERT INTO materials (tenant_id,course_id,lesson_id,title,description,file_path,thumbnail_path,duration) VALUES (?,?,?,?,?,?,?,?)");
                $ins->execute([$tid,$course_id,$lid,$t,$d,$youtubeUrl,$td['relativePath']??VideoHelper::getThumbnailUrl($ytId),$dur]);
                $msg=__('data_updated');
            }
            else {
                $fd=TenantFile::handleUpload($_FILES['file_path'],$tid,'courses',$course_id);
                $td=!empty($_FILES['thumbnail_path']['tmp_name'])?TenantFile::handleUpload($_FILES['thumbnail_path'],$tid,'courses',$course_id):null;
                if ($fd) {
                    $ins=$pdo->prepare("INSERT INTO materials (tenant_id,course_id,lesson_id,title,description,file_path,thumbnail_path,duration) VALUES (?,?,?,?,?,?,?,?)");
                    $ins->execute([$tid,$course_id,$lid,$t,$d,$fd['relativePath'],$td['relativePath']??null,$dur]);
                    $msg=__('data_updated');
                } else { $msg='Upload error'; }
            }
        }
    }
    if ($act === 'delete_material') {
        $mid=(int)($_POST['material_id']??0);
        if ($mid) { $pdo->prepare("DELETE FROM materials WHERE material_id=? AND course_id=? AND tenant_id=?")->execute([$mid,$course_id,$tid]); $msg=__('data_updated'); }
    }
    if ($act === 'add_test') {
        $t=trim($_POST['title']??''); $d=trim($_POST['description']??'');
        $tt=$_POST['test_type']??''; $mid=($_POST['test_type']==='material'&& !empty($_POST['material_id']))?(int)$_POST['material_id']:null;
        $sd=dt2sql($_POST['start_date']??''); $ed=dt2sql($_POST['end_date']??'');
        $ms=(int)($_POST['min_score']??0); $xs=(int)($_POST['max_score']??100);

        if (!$t||!$d) { $msg='Title and description required'; }
        elseif (!in_array($tt,['material','course'])) { $msg='Select test type'; }
        elseif (!$sd||!$ed) { $msg='Start and end dates required'; }
        elseif ($tt==='material'&& !$mid) { $msg='Select a material for material test'; }
        else {
            if ($tt==='material') {
                $ck=$pdo->prepare("SELECT COUNT(*) FROM daily_tests WHERE material_id=? AND course_id=? AND tenant_id=?");
                $ck->execute([$mid,$course_id,$tid]);
                if ($ck->fetchColumn()>0) { $msg='This material already has a test. Only one test per material is allowed.'; }
            }
            if (!$msg) {
                try {
                    $pdo->beginTransaction();
                    if ($tt==='material') {
                        $ins=$pdo->prepare("INSERT INTO daily_tests (tenant_id,course_id,material_id,title,description,start_date,end_date,min_score,max_score) VALUES (?,?,?,?,?,?,?,?,?)");
                        $ins->execute([$tid,$course_id,$mid,$t,$d,$sd,$ed,$ms,$xs]);
                    } else {
                        $ins=$pdo->prepare("INSERT INTO weekly_tests (tenant_id,course_id,title,description,start_date,end_date,min_score,max_score) VALUES (?,?,?,?,?,?,?,?)");
                        $ins->execute([$tid,$course_id,$t,$d,$sd,$ed,$ms,$xs]);
                    }
                    $pdo->commit(); $msg=__('data_updated');
                } catch(PDOException $e) { $pdo->rollBack(); error_log("add_test: ".$e->getMessage()); $msg='Error creating test'; }
            }
        }
    }
    if ($act === 'delete_test') {
        $xid=(int)($_POST['test_id']??0); $xt=$_POST['test_type']??'';
        if ($xid&&in_array($xt,['material','course'])) {
            try {
                $dbType=$xt==='material'?'daily':'weekly';
                $pdo->prepare("DELETE FROM test_results WHERE test_id=? AND test_type=? AND tenant_id=?")->execute([$xid,$dbType,$tid]);
                if ($xt==='material') { $pdo->prepare("DELETE FROM daily_tests WHERE daily_test_id=? AND course_id=? AND tenant_id=?")->execute([$xid,$course_id,$tid]); }
                else { $pdo->prepare("DELETE FROM weekly_tests WHERE weekly_test_id=? AND course_id=? AND tenant_id=?")->execute([$xid,$course_id,$tid]); }
                $msg=__('data_updated');
            } catch(PDOException $e) { error_log("del_test: ".$e->getMessage()); $msg='Error deleting test'; }
        }
    }

    $_SESSION['message']=$msg;
    header('Location: view.php?course_id='.$course_id.'&tab='.$tab);
    exit;
}

// Fetch data (Tenant isolated)
$mats=$pdo->prepare("SELECT * FROM materials WHERE course_id=? AND tenant_id=? ORDER BY lesson_id ASC");
$mats->execute([$course_id,$tid]); $materials=$mats->fetchAll(PDO::FETCH_ASSOC);

$dtStmt=$pdo->prepare("SELECT d.daily_test_id AS test_id,d.title,d.description,d.start_date,d.end_date,'material' AS test_type,d.material_id,m.title AS material_title FROM daily_tests d LEFT JOIN materials m ON d.material_id=m.material_id AND m.tenant_id=d.tenant_id WHERE d.course_id=? AND d.tenant_id=?");
$dtStmt->execute([$course_id,$tid]); $dailyTests=$dtStmt->fetchAll(PDO::FETCH_ASSOC);

$wtStmt=$pdo->prepare("SELECT weekly_test_id AS test_id,title,description,start_date,end_date,'course' AS test_type FROM weekly_tests WHERE course_id=? AND tenant_id=?");
$wtStmt->execute([$course_id,$tid]); $weeklyTests=$wtStmt->fetchAll(PDO::FETCH_ASSOC);

$allTests=array_merge($dailyTests,$weeklyTests);

$stats=$pdo->prepare("SELECT (SELECT COUNT(*) FROM course_subs WHERE course_id=? AND tenant_id=?) AS total_subs,(SELECT COUNT(*) FROM course_subs WHERE course_id=? AND tenant_id=? AND status='active') AS active_subs,(SELECT COALESCE(SUM(price),0) FROM course_subs WHERE course_id=? AND tenant_id=?) AS total_revenue");
$stats->execute([$course_id,$tid,$course_id,$tid,$course_id,$tid]); $cs=$stats->fetch(PDO::FETCH_ASSOC);

$enrolled=$pdo->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, cs.access_expires_at, cs.status, cs.next_payment_date,
    (SELECT COALESCE(SUM(get_points), 0) FROM test_results tr 
     WHERE tr.slug = u.slug AND tr.tenant_id = u.tenant_id 
     AND ((tr.test_type = 'daily' AND tr.test_id IN (SELECT daily_test_id FROM daily_tests WHERE course_id = cs.course_id AND tenant_id = cs.tenant_id))
          OR (tr.test_type = 'weekly' AND tr.test_id IN (SELECT weekly_test_id FROM weekly_tests WHERE course_id = cs.course_id AND tenant_id = cs.tenant_id)))) AS course_score
    FROM course_subs cs JOIN users u ON cs.user_id=u.user_id 
    WHERE cs.course_id=? AND cs.tenant_id=? AND u.tenant_id=? ORDER BY cs.purchase_date DESC
");
$enrolled->execute([$course_id,$tid,$tid]); $students=$enrolled->fetchAll(PDO::FETCH_ASSOC);

// Average score
$avgStmt = $pdo->prepare("
    SELECT AVG(tr.get_points * 100.0 / NULLIF(COALESCE(dt.max_score, wt.max_score, 100), 0)) AS avg_score
    FROM test_results tr
    LEFT JOIN daily_tests dt ON tr.test_type = 'daily' AND tr.test_id = dt.daily_test_id AND dt.tenant_id = tr.tenant_id
    LEFT JOIN weekly_tests wt ON tr.test_type = 'weekly' AND tr.test_id = wt.weekly_test_id AND wt.tenant_id = tr.tenant_id
    WHERE (dt.course_id = ? OR wt.course_id = ?) AND tr.tenant_id = ?
");
$avgStmt->execute([$course_id, $course_id, $tid]);
$avgScoreVal = $avgStmt->fetchColumn();
$averageScore = $avgScoreVal !== null && $avgScoreVal !== false ? round((float)$avgScoreVal) : 8;

// Recent payments for this course (up to 3, direct payments.course_id relation)
$coursePaymentsStmt = $pdo->prepare("
    SELECT p.*, u.first_name, u.last_name, u.class AS student_class
    FROM payments p
    JOIN users u ON p.user_id = u.user_id AND u.tenant_id = p.tenant_id
    WHERE p.tenant_id = ? AND p.course_id = ? AND p.deleted_at IS NULL
    ORDER BY p.payment_date DESC
    LIMIT 3
");
$coursePaymentsStmt->execute([$tid, $course_id]);
$recentPayments = $coursePaymentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Available students for enrollment
$availStudentsStmt = $pdo->prepare("
    SELECT user_id, first_name, last_name, phone, email 
    FROM users 
    WHERE tenant_id = ? 
    ORDER BY first_name ASC
");
$availStudentsStmt->execute([$tid]);
$availableStudents = $availStudentsStmt->fetchAll(PDO::FETCH_ASSOC);

$matTestMap=[];
foreach($dailyTests as $dt) { if($dt['material_id']) $matTestMap[$dt['material_id']]=$dt; }

$pageTitle = h($course['title']);
include __DIR__ . '/../includes/header.php';
?>

<style>
.tab-course-nav {
    border: none !important;
    border-bottom: 2px solid transparent !important;
    background: transparent !important;
    cursor: pointer;
    outline: none !important;
    padding: 0 0 12px 0 !important;
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    white-space: nowrap;
    text-decoration: none !important;
    transition: color 0.15s ease, border-color 0.15s ease;
    margin-bottom: -1px;
}
.tab-course-nav:hover {
    color: #0f172a;
}
.tab-course-nav.active {
    border-bottom-color: #2563eb !important;
    color: #2563eb !important;
    font-weight: 600;
}
</style>

<div class="mx-auto flex max-w-5xl flex-col gap-6">

    <!-- Back to courses link -->
    <div>
        <a href="/admin/courses/" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 no-underline transition-colors hover:text-slate-900">
            <i data-lucide="arrow-left" class="size-4"></i>
            <?= htmlspecialchars(__('back_to_courses')) ?>
        </a>
    </div>

    <!-- Header Section -->
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 m-0">
                <?= h($course['title']) ?>
            </h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                    <?= h($course['classLevel']) ?>
                </span>
                <span><?= h($course['subject']) ?></span>
                <span aria-hidden="true">·</span>
                <span class="font-medium text-slate-900">
                    <?= $course['price'] > 0 ? number_format($course['price'], 0, '.', ' ') . ' so\'m' : htmlspecialchars(__('free')) ?>
                </span>
            </div>
        </div>

        <div>
            <button type="button" onclick="document.getElementById('editModal').style.display='flex';" style="border: 1px solid #e2e8f0; background: #ffffff;" class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-50 transition-colors cursor-pointer outline-none">
                <i data-lucide="pencil" class="size-3.5 text-slate-500"></i>
                <?= htmlspecialchars(__('edit_course')) ?>
            </button>
        </div>
    </header>

    <!-- Flash message -->
    <?php if ($msg): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
            <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Course sections">
            <a href="?course_id=<?=$course_id?>&tab=overview" class="tab-course-nav <?= $tab==='overview' ? 'active' : '' ?>">
                <?= htmlspecialchars(__('overview')) ?>
            </a>
            <a href="?course_id=<?=$course_id?>&tab=content" class="tab-course-nav <?= $tab==='content' ? 'active' : '' ?> inline-flex items-center gap-1.5">
                <?= htmlspecialchars(__('materials_col')) ?>
                <span class="rounded-md <?= $tab==='content' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' ?> px-1.5 py-0.5 text-xs font-semibold">
                    <?= count($materials) ?>
                </span>
            </a>
            <a href="?course_id=<?=$course_id?>&tab=students" class="tab-course-nav <?= $tab==='students' ? 'active' : '' ?> inline-flex items-center gap-1.5">
                <?= htmlspecialchars(__('students_col')) ?>
                <span class="rounded-md <?= $tab==='students' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' ?> px-1.5 py-0.5 text-xs font-semibold">
                    <?= count($students) ?>
                </span>
            </a>
            <a href="?course_id=<?=$course_id?>&tab=tests" class="tab-course-nav <?= $tab==='tests' ? 'active' : '' ?> inline-flex items-center gap-1.5">
                <?= htmlspecialchars(__('tests_tab')) ?>
                <span class="rounded-md <?= $tab==='tests' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' ?> px-1.5 py-0.5 text-xs font-semibold">
                    <?= count($allTests) ?>
                </span>
            </a>
        </nav>
    </div>

    <?php if ($tab === 'overview'): ?>

        <!-- KPI Grid (3x2, No icons, strictly LABEL + VALUE) -->
        <section aria-label="Key statistics" class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <!-- Students -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('students_col')) ?></p>
                <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums"><?= count($students) ?></p>
            </div>
            <!-- Active access -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('active_access')) ?></p>
                <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums"><?= (int)($cs['active_subs'] ?? 0) ?></p>
            </div>
            <!-- Revenue -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('revenue')) ?></p>
                <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums"><?= number_format($cs['total_revenue'] ?? 0, 0, '.', ' ') ?> so'm</p>
            </div>
            <!-- Materials -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('materials_col')) ?></p>
                <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums"><?= count($materials) ?></p>
            </div>
            <!-- Tests -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('tests_tab')) ?></p>
                <p class="mt-2 text-2xl font-bold text-slate-900 tabular-nums"><?= count($allTests) ?></p>
            </div>
            <!-- Average score -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)]">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('average_score')) ?></p>
                <p class="mt-2 text-2xl font-bold tabular-nums">
                    <span class="text-slate-900"><?= $averageScore ?></span><span class="text-slate-400">/100</span>
                </p>
            </div>
        </section>

        <!-- Course information + Quick Actions -->
        <section class="grid gap-6 lg:grid-cols-3">
            <!-- Left Card: Course Information -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] lg:col-span-2 flex flex-col justify-between">
                <h2 class="font-bold text-slate-900 text-base mb-3"><?= htmlspecialchars(__('course_info')) ?></h2>
                <div class="grid grid-cols-2 gap-x-6 gap-y-3.5 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('grade_label')) ?></p>
                        <p class="mt-1 font-semibold text-slate-900 text-sm"><?= h($course['classLevel']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('subject_col')) ?></p>
                        <p class="mt-1 font-semibold text-slate-900 text-sm"><?= h($course['subject']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('price')) ?></p>
                        <p class="mt-1 font-semibold text-slate-900 text-sm">
                            <?= $course['price'] > 0 ? number_format($course['price'], 0, '.', ' ') . ' so\'m' : htmlspecialchars(__('free')) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('enrolled_label')) ?></p>
                        <p class="mt-1 font-semibold text-slate-900 text-sm"><?= count($students) ?> <?= htmlspecialchars(__('students_word')) ?></p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('content_label')) ?></p>
                        <p class="mt-1 font-semibold text-slate-900 text-sm">
                            <?= count($materials) ?> <?= htmlspecialchars(__('materials_word')) ?> · <?= count($allTests) ?> <?= htmlspecialchars(__('tests_word')) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right Card: Quick Actions -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] flex flex-col justify-between">
                <h2 class="font-bold text-slate-900 text-base mb-3"><?= htmlspecialchars(__('quick_actions')) ?></h2>
                <div class="flex flex-col gap-2.5">
                    <a href="?course_id=<?= $course_id ?>&tab=content" style="border: 1px solid #e2e8f0; background: #ffffff;" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-50 transition-colors no-underline">
                        <i data-lucide="upload" class="size-4 text-slate-400"></i>
                        <?= htmlspecialchars(__('add_material')) ?>
                    </a>
                    <a href="?course_id=<?= $course_id ?>&tab=tests" style="border: 1px solid #e2e8f0; background: #ffffff;" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-50 transition-colors no-underline">
                        <i data-lucide="plus" class="size-4 text-slate-400"></i>
                        <?= htmlspecialchars(__('create_test')) ?>
                    </a>
                    <button type="button" onclick="document.getElementById('enrollModal').style.display='flex';" style="border: 1px solid #e2e8f0; background: #ffffff;" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-50 transition-colors cursor-pointer text-left w-full outline-none">
                        <i data-lucide="user-plus" class="size-4 text-slate-400"></i>
                        <?= htmlspecialchars(__('enroll_student')) ?>
                    </button>
                </div>
            </div>
        </section>

        <!-- Course payments -->
        <section class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                <div>
                    <h2 class="font-bold text-slate-900 text-base m-0"><?= htmlspecialchars(__('recent_payments')) ?></h2>
                    <p class="text-xs text-slate-500 mt-0.5 m-0"><?= htmlspecialchars(__('payments_linked_course')) ?></p>
                </div>
                <a href="/admin/payments/" class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors no-underline">
                    <?= htmlspecialchars(__('view_all_payments')) ?>
                    <i data-lucide="arrow-right" class="size-3.5"></i>
                </a>
            </div>

            <?php if (empty($recentPayments)): ?>
                <div class="flex flex-col items-center justify-center gap-2 px-5 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                        <i data-lucide="receipt" class="size-5"></i>
                    </div>
                    <p class="font-medium text-slate-900 text-sm"><?= htmlspecialchars(__('no_payments_yet')) ?></p>
                    <p class="max-w-xs text-xs text-slate-400"><?= htmlspecialchars(__('payments_appear_here')) ?></p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($recentPayments as $p): ?>
                        <div class="flex items-center gap-4 px-6 py-3.5 hover:bg-slate-50/50 transition-colors">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-600">
                                <?= mb_strtoupper(mb_substr($p['first_name'] ?? '', 0, 1) . mb_substr($p['last_name'] ?? '', 0, 1)) ?: 'ST' ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-slate-900 text-sm m-0"><?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></p>
                                <p class="truncate text-xs text-slate-500 m-0"><?= htmlspecialchars($p['student_class'] ?? '') ?></p>
                            </div>
                            <div class="hidden w-28 text-right sm:block">
                                <p class="font-bold text-slate-900 text-sm tabular-nums m-0"><?= number_format($p['amount'], 0, '.', ' ') ?> so'm</p>
                            </div>
                            <div class="hidden w-24 justify-center sm:flex">
                                <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                    <?= htmlspecialchars($p['payment_method'] ?? 'cash') ?>
                                </span>
                            </div>
                            <div class="hidden w-28 justify-center md:flex">
                                <?php if ($p['status'] === 'accepted' || $p['status'] === 'approved'): ?>
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                        <i data-lucide="check" class="size-3"></i>
                                        <?= htmlspecialchars(__('approved')) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                        <?= htmlspecialchars(__('pending')) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="hidden w-28 text-right text-xs text-slate-500 lg:block tabular-nums">
                                <p class="m-0 font-medium"><?= date('d.m.Y', strtotime($p['payment_date'])) ?></p>
                                <p class="m-0 text-[11px] text-slate-400"><?= date('H:i', strtotime($p['payment_date'])) ?></p>
                            </div>
                            <a href="/admin/payments/view.php?payment_id=<?= $p['payment_id'] ?>" class="flex size-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-900">
                                <i data-lucide="eye" class="size-4"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($tab === 'content'): ?>

        <!-- Materials Section -->
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900 m-0"><?= htmlspecialchars(__("materials_col")) ?> <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700"><?=count($materials)?></span></h2>
            <button class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer" style="border:none;" onclick="document.getElementById('addMaterialForm').style.display=document.getElementById('addMaterialForm').style.display==='none'?'block':'none'">+ <?= htmlspecialchars(__("add_material")) ?></button>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm" id="addMaterialForm" style="display:none">
            <h3 class="text-base font-bold text-slate-900 mb-4"><?= htmlspecialchars(__("add_material")) ?></h3>
            <form method="post" enctype="multipart/form-data" class="flex flex-col gap-4 max-w-2xl">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_material">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("lesson_number")) ?> *</label><input type="number" name="lesson_id" min="1" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("title_col")) ?> *</label><input type="text" name="title" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                </div>
                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("description")) ?></label><textarea name="description" rows="2" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></textarea></div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Источник видео</label>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-1.5 text-sm font-normal text-slate-700">
                            <input type="radio" name="source_type" id="amf_source_file" value="file" checked>
                            Видеофайл / PDF
                        </label>
                        <label class="flex items-center gap-1.5 text-sm font-normal text-slate-700">
                            <input type="radio" name="source_type" id="amf_source_youtube" value="youtube">
                            YouTube ссылка
                        </label>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div id="amf_file_group"><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("video_file")) ?> *</label><input type="file" id="amf_file_path" name="file_path" accept="video/*" required class="w-full text-xs"></div>
                    <div id="amf_youtube_group" style="display:none"><label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Ссылка на YouTube *</label><input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..." class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("thumbnail")) ?></label><input type="file" name="thumbnail_path" accept="image/*" class="w-full text-xs"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("duration_min")) ?></label><input type="number" name="duration" min="0" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                </div>
                <div class="flex items-center gap-3 mt-2">
                    <button type="submit" style="border:none;" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer"><?= htmlspecialchars(__('add_material')) ?></button>
                    <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" onclick="document.getElementById('addMaterialForm').style.display='none'"><?= htmlspecialchars(__('cancel')) ?></button>
                </div>
            </form>
        </div>
        <script>
            (function () {
                var fileRadio = document.getElementById('amf_source_file');
                var ytRadio = document.getElementById('amf_source_youtube');
                var fileGroup = document.getElementById('amf_file_group');
                var ytGroup = document.getElementById('amf_youtube_group');
                var fileInput = document.getElementById('amf_file_path');
                if (!fileRadio || !ytRadio) return;

                function sync() {
                    var isYoutube = ytRadio.checked;
                    ytGroup.style.display = isYoutube ? '' : 'none';
                    fileGroup.style.display = isYoutube ? 'none' : '';
                    fileInput.required = !isYoutube;
                }

                fileRadio.addEventListener('change', sync);
                ytRadio.addEventListener('change', sync);
                sync();
            })();
        </script>

        <?php if (empty($materials)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-12 text-center text-sm text-slate-500 shadow-sm"><?= htmlspecialchars(__("no_materials")) ?></div>
        <?php else: ?>
            <div class="flex flex-col gap-3">
                <?php foreach ($materials as $mat):
                    $hasTest=isset($matTestMap[$mat['material_id']]);
                    $test=$matTestMap[$mat['material_id']]??null;
                ?>
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div class="flex items-start gap-4 flex-1 min-w-0">
                                <!-- Compact Visual Preview (120-128px x 68-72px) -->
                                <?php 
                                $matExt = strtolower(pathinfo($mat['file_path'] ?? '', PATHINFO_EXTENSION));
                                $isVideo = in_array($matExt, ['mp4', 'webm', 'ogg', 'mov']);
                                $isPdf = ($matExt === 'pdf');
                                $ytId = VideoHelper::extractYouTubeId($mat['file_path'] ?? null);
                                $materialUrl = '/file.php?type=material&id=' . (int)$mat['material_id'];
                                $thumbUrl = '/file.php?type=preview&id=' . (int)$mat['material_id'];
                                $hasRealThumb = TenantFile::fileExists((int)$mat['tenant_id'], $mat['thumbnail_path'] ?? null);
                                $hasRealVideo = $isVideo && TenantFile::fileExists((int)$mat['tenant_id'], $mat['file_path'] ?? null);
                                ?>
                                <div onclick="toggleLessonPreview(<?= (int)$mat['material_id'] ?>)" role="button" tabindex="0" title="<?= htmlspecialchars(__('view_material')) ?>" class="w-32 h-[72px] rounded-lg overflow-hidden bg-slate-900 border border-slate-200/80 shrink-0 relative group block cursor-pointer transition-transform duration-150 hover:brightness-105 select-none">
                                    <?php if ($ytId): ?>
                                        <img src="<?= htmlspecialchars(VideoHelper::getThumbnailUrl($ytId)) ?>" alt="" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/25 group-hover:bg-black/35 transition-colors pointer-events-none">
                                            <div class="w-7 h-7 rounded-full bg-black/60 backdrop-blur-xs flex items-center justify-center text-white shadow-sm transition-transform group-hover:scale-110">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
                                            </div>
                                        </div>
                                        <span class="absolute bottom-1 right-1 px-1 py-0.5 rounded bg-black/70 text-[9px] font-semibold text-slate-200 tracking-wider">YOUTUBE</span>
                                    <?php elseif ($hasRealThumb): ?>
                                        <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="" class="w-full h-full object-cover">
                                        <?php if ($isVideo): ?>
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/25 group-hover:bg-black/35 transition-colors pointer-events-none">
                                                <div class="w-7 h-7 rounded-full bg-black/60 backdrop-blur-xs flex items-center justify-center text-white shadow-sm transition-transform group-hover:scale-110">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($hasRealVideo): ?>
                                        <video preload="metadata" muted playsinline class="w-full h-full object-cover bg-slate-950 pointer-events-none">
                                            <source src="<?= htmlspecialchars($materialUrl) ?>#t=0.001" type="video/<?= $matExt === 'mov' ? 'quicktime' : 'mp4' ?>">
                                        </video>
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/30 transition-colors pointer-events-none">
                                            <div class="w-7 h-7 rounded-full bg-black/60 backdrop-blur-xs flex items-center justify-center text-white shadow-sm transition-transform group-hover:scale-110">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
                                            </div>
                                        </div>
                                    <?php elseif ($isVideo): ?>
                                        <div class="w-full h-full bg-gradient-to-br from-slate-800 to-slate-950 flex flex-col items-center justify-center relative p-1.5">
                                            <div class="w-7 h-7 rounded-full bg-white/15 backdrop-blur-xs border border-white/20 flex items-center justify-center text-white shadow-sm transition-transform group-hover:scale-110">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
                                            </div>
                                            <span class="absolute bottom-1 right-1 px-1 py-0.5 rounded bg-black/70 text-[9px] font-semibold text-slate-200 tracking-wider">
                                                <?= !empty($mat['duration']) ? (int)$mat['duration'] . ' мин' : 'ВИДЕО' ?>
                                            </span>
                                        </div>
                                    <?php elseif ($isPdf): ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center bg-red-50 text-red-600">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                            <span style="font-size:10px; font-weight:700; margin-top:2px;">PDF</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-400 bg-slate-800">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <h3 class="text-base font-semibold text-slate-900 m-0"><?=h($mat['lesson_id'])?>. <?=h($mat['title'])?></h3>
                                        <button type="button" onclick="toggleLessonPreview(<?= (int)$mat['material_id'] ?>)" class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-0.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" id="btn-preview-<?= (int)$mat['material_id'] ?>">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg>
                                            <span><?= htmlspecialchars(__('view_material')) ?></span>
                                        </button>
                                    </div>
                                <?php if ($mat['description']): ?><p class="mt-1 text-xs text-slate-500"><?=nl2br(h($mat['description']))?></p><?php endif; ?>
                                <div class="mt-3 flex items-center gap-2 flex-wrap">
                                    <?php if ($hasTest): ?>
                                        <span class="inline-flex rounded-md bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700"><?= htmlspecialchars(__("test_attached")) ?></span>
                                        <a href="/admin/tests/view.php?test_id=<?=$test['test_id']?>&type=daily&tab=overview" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-700 no-underline hover:bg-slate-50" style="border:1px solid #e2e8f0;"><?= htmlspecialchars(__('open_test')) ?></a>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600"><?= htmlspecialchars(__("no_test")) ?></span>
                                        <button class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" onclick="document.getElementById('testForm<?=$mat['material_id']?>').style.display='block'">+ <?= htmlspecialchars(__('add_test')) ?></button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$hasTest): ?>
                                    <div id="testForm<?=$mat['material_id']?>" style="display:none;" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                        <form method="post" class="flex flex-col gap-3">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="add_test">
                                            <input type="hidden" name="test_type" value="material">
                                            <input type="hidden" name="material_id" value="<?=$mat['material_id']?>">
                                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("title_col")) ?> *</label><input type="text" name="title" value="Test: <?=h($mat['title'])?>" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs"></div>
                                                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("description")) ?> *</label><input type="text" name="description" value="Test for <?=h($mat['title'])?>" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs"></div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("start")) ?> *</label><input type="datetime-local" name="start_date" value="<?=date('Y-m-d\TH:i')?>" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"></div>
                                                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("end")) ?> *</label><input type="datetime-local" name="end_date" value="<?=date('Y-m-d\TH:i', strtotime('+30 days'))?>" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"></div>
                                                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("min_score")) ?></label><input type="number" name="min_score" value="0" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"></div>
                                                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("max_score")) ?></label><input type="number" name="max_score" value="100" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"></div>
                                            </div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <button type="submit" style="border:none;" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:opacity-90 cursor-pointer"><?= htmlspecialchars(__("create_test")) ?></button>
                                                <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 cursor-pointer" onclick="this.closest('div[id^=testForm]').style.display='none'"><?= htmlspecialchars(__('cancel')) ?></button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="/admin/materials/edit_materials.php?material_id=<?=$mat['material_id']?>&course_id=<?=$course_id?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 no-underline shadow-sm hover:bg-slate-50" style="border:1px solid #e2e8f0;"><?= htmlspecialchars(__("edit")) ?></a>
                            <form method="post" class="inline m-0" onsubmit="return confirm('<?= htmlspecialchars(__("delete_material_confirm")) ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_material">
                                <input type="hidden" name="material_id" value="<?=$mat['material_id']?>">
                                <button type="submit" style="border:1px solid #fecaca; background:#fff;" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 shadow-sm hover:bg-red-50 cursor-pointer"><?= htmlspecialchars(__("delete_btn")) ?></button>
                            </form>
                        </div>
                    </div>

                        <!-- Inline Expandable Lesson Preview -->
                        <div id="lesson-preview-<?= (int)$mat['material_id'] ?>" class="hidden mt-4 pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        <?= $isPdf ? 'Просмотр документа' : 'Предпросмотр урока' ?>
                                    </span>
                                    <span class="text-xs text-slate-400">· <?= h($mat['title']) ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="<?= htmlspecialchars($materialUrl) ?>" target="_blank" style="border:1px solid #e2e8f0; background:#fff;" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-xs hover:bg-slate-50 no-underline">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path></svg>
                                        <span>В новой вкладке</span>
                                    </a>
                                    <button type="button" onclick="toggleLessonPreview(<?= (int)$mat['material_id'] ?>)" style="border:1px solid #e2e8f0; background:#fff;" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-xs hover:bg-slate-50 hover:text-slate-900 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                                        <span>Закрыть</span>
                                    </button>
                                </div>
                            </div>

                            <?php if ($ytId): ?>
                                <div class="relative rounded-xl overflow-hidden bg-black aspect-video max-w-2xl mx-auto shadow-inner border border-slate-800">
                                    <iframe class="w-full h-full"
                                            src="<?= VideoHelper::getEmbedUrl($ytId) ?>"
                                            title="<?= h($mat['title']) ?>"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                            allowfullscreen>
                                    </iframe>
                                </div>
                            <?php elseif ($isVideo): ?>
                                <?php if ($hasRealVideo): ?>
                                    <div class="relative rounded-xl overflow-hidden bg-black aspect-video max-w-2xl mx-auto shadow-inner border border-slate-800">
                                        <video id="video-player-<?= (int)$mat['material_id'] ?>" controls preload="metadata" playsinline class="w-full h-full object-contain"
                                               src="<?= htmlspecialchars($materialUrl) ?>"
                                               <?= $hasRealThumb ? 'poster="' . htmlspecialchars($thumbUrl) . '"' : '' ?>
                                               onerror="showPreviewError(<?= (int)$mat['material_id'] ?>)">
                                            <source src="<?= htmlspecialchars($materialUrl) ?>" type="video/<?= $matExt === 'mov' ? 'quicktime' : 'mp4' ?>">
                                            Ваш браузер не поддерживает встроенное воспроизведение видео.
                                        </video>
                                        <div id="video-error-<?= (int)$mat['material_id'] ?>" class="hidden absolute inset-0 bg-slate-900/95 flex flex-col items-center justify-center text-center p-6">
                                            <svg class="w-8 h-8 text-amber-400 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                            <p class="text-sm font-semibold text-white mb-1">Ошибка воспроизведения видео</p>
                                            <p class="text-xs text-slate-400 max-w-sm">Не удалось загрузить видеофайл с сервера. Возможно, файл перемещен или поврежден.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-amber-800 flex items-start gap-3 max-w-2xl mx-auto">
                                        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-900 m-0">Файл урока не найден на диске</p>
                                            <p class="text-xs text-amber-700 mt-1 m-0">Физический файл «<?= h(basename($mat['file_path'])) ?>» не найден в хранилище. Загрузите видеофайл через кнопку «Редактировать».</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($isPdf): ?>
                                <?php $hasRealPdf = TenantFile::fileExists((int)$mat['tenant_id'], $mat['file_path'] ?? null); ?>
                                <?php if ($hasRealPdf): ?>
                                    <div class="rounded-xl overflow-hidden border border-slate-200 bg-slate-50 max-w-3xl mx-auto" style="height: 500px;">
                                        <iframe src="<?= htmlspecialchars($materialUrl) ?>" class="w-full h-full border-none" title="PDF Preview"></iframe>
                                    </div>
                                <?php else: ?>
                                    <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-amber-800 flex items-start gap-3 max-w-2xl mx-auto">
                                        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-900 m-0">Документ не найден на диске</p>
                                            <p class="text-xs text-amber-700 mt-1 m-0">Файл документа отсутствует в хранилище. Загрузите файл через редактирование урока.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-slate-700 flex items-center justify-between max-w-2xl mx-auto">
                                    <span class="text-xs">Файл доступен для просмотра</span>
                                    <a href="<?= htmlspecialchars($materialUrl) ?>" target="_blank" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:opacity-90">Открыть ↗</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'students'): ?>

        <?php if (empty($students)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-12 text-center text-sm text-slate-500 shadow-sm"><?= htmlspecialchars(__('no_students_enrolled')) ?></div>
        <?php else: ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_-2px_rgba(15,23,42,0.08),0_1px_4px_-1px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="px-5 py-4 pl-6"><?= htmlspecialchars(__('name')) ?></th>
                                <th class="px-5 py-4"><?= htmlspecialchars(__('phone')) ?></th>
                                <th class="px-5 py-4 text-center"><?= htmlspecialchars(__('scores')) ?></th>
                                <th class="px-5 py-4"><?= htmlspecialchars(__('status')) ?></th>
                                <th class="px-5 py-4"><?= htmlspecialchars(__('until')) ?></th>
                                <th class="px-5 py-4 text-right pr-6"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <?php
                                    $subStatus = $s['status'] ?? 'pending';
                                    $isOverdue = $subStatus === 'active' && !empty($s['next_payment_date']) && strtotime($s['next_payment_date']) < strtotime('today');
                                ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-4 pl-6 font-semibold text-slate-900"><?=h(($s['first_name']??'').' '.($s['last_name']??''))?></td>
                                    <td class="px-5 py-4 text-slate-500"><?=h($s['phone'] ?? $s['email'])?></td>
                                    <td class="px-5 py-4 text-center"><span class="inline-flex rounded-md bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700"><?=h($s['course_score'])?></span></td>
                                    <td class="px-5 py-4">
                                        <?php if ($subStatus === 'active'): ?>
                                            <span class="inline-flex rounded-md bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Активна</span>
                                            <?php if ($isOverdue): ?>
                                                <span class="ml-1 inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Просрочено на <?= (int)floor((strtotime('today') - strtotime($s['next_payment_date'])) / 86400) ?> дн.</span>
                                            <?php endif; ?>
                                        <?php elseif ($subStatus === 'pending'): ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Ожидает оплаты</span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600"><?=h($subStatus)?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500 tabular-nums"><?= !empty($s['access_expires_at']) ? date('d.m.Y', strtotime($s['access_expires_at'])) : '—' ?></td>
                                    <td class="px-5 py-4 text-right pr-6">
                                        <a href="/admin/students/view.php?user_id=<?=$s['user_id']?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 no-underline shadow-sm hover:bg-slate-50" style="border:1px solid #e2e8f0;"><?= htmlspecialchars(__('profile')) ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'tests'): ?>

        <!-- Course Tests Section -->
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900 m-0"><?= htmlspecialchars(__("course_tests")) ?> <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700"><?=count($weeklyTests)?></span></h2>
            <button class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" onclick="document.getElementById('addCourseTestForm').style.display=document.getElementById('addCourseTestForm').style.display==='none'?'block':'none'">+ <?= htmlspecialchars(__('create_test')) ?></button>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm" id="addCourseTestForm" style="display:none;">
            <h3 class="text-base font-bold text-slate-900 mb-4"><?= htmlspecialchars(__('create_test')) ?> (<?= htmlspecialchars(__('course_tests')) ?>)</h3>
            <form method="post" class="flex flex-col gap-4 max-w-2xl">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_test">
                <input type="hidden" name="test_type" value="course">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('title_col')) ?> *</label><input type="text" name="title" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('description')) ?> *</label><input type="text" name="description" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('start')) ?> *</label><input type="datetime-local" name="start_date" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('end')) ?> *</label><input type="datetime-local" name="end_date" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('min_score')) ?></label><input type="number" name="min_score" value="0" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('max_score')) ?></label><input type="number" name="max_score" value="100" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></div>
                </div>
                <div class="flex items-center gap-3 mt-2">
                    <button type="submit" style="border:none;" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer"><?= htmlspecialchars(__('create_test')) ?></button>
                    <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" onclick="document.getElementById('addCourseTestForm').style.display='none'"><?= htmlspecialchars(__('cancel')) ?></button>
                </div>
            </form>
        </div>

        <?php if (!empty($weeklyTests)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="px-5 py-4 pl-6"><?= htmlspecialchars(__("title_col")) ?></th>
                                <th class="px-5 py-4"><?= htmlspecialchars(__("date_col")) ?></th>
                                <th class="px-5 py-4 text-right pr-6"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weeklyTests as $test): ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-4 pl-6 font-semibold text-slate-900"><?=h($test['title'])?></td>
                                    <td class="px-5 py-4 text-slate-500 tabular-nums"><?=date('d.m.Y', strtotime($test['start_date']))?> — <?=date('d.m.Y', strtotime($test['end_date']))?></td>
                                    <td class="px-5 py-4 text-right pr-6 whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="/admin/tests/view.php?test_id=<?=$test['test_id']?>&type=weekly&tab=overview" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 no-underline shadow-sm hover:bg-slate-50" style="border:1px solid #e2e8f0;"><?= htmlspecialchars(__("open_btn")) ?></a>
                                            <form method="post" class="inline m-0" onsubmit="return confirm('<?= htmlspecialchars(__("delete_test_confirm")) ?>')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_test">
                                                <input type="hidden" name="test_id" value="<?=$test['test_id']?>">
                                                <input type="hidden" name="test_type" value="course">
                                                <button type="submit" style="border:1px solid #fecaca; background:#fff;" class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium text-red-600 shadow-sm hover:bg-red-50 cursor-pointer"><?= htmlspecialchars(__("delete_btn")) ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Material Tests Section -->
        <div class="flex items-center justify-between mt-6">
            <h2 class="text-xl font-bold text-slate-900 m-0"><?= htmlspecialchars(__("material_tests")) ?> <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700"><?=count($dailyTests)?></span></h2>
            <button class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" onclick="document.getElementById('addMaterialTestForm').style.display=document.getElementById('addMaterialTestForm').style.display==='none'?'block':'none'">+ <?= htmlspecialchars(__('create_test')) ?></button>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm" id="addMaterialTestForm" style="display:none;">
            <h3 class="text-base font-bold text-slate-900 mb-4"><?= htmlspecialchars(__('create_test')) ?> (<?= htmlspecialchars(__('material_tests')) ?>)</h3>
            <form method="post" class="flex flex-col gap-4 max-w-2xl">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_test">
                <input type="hidden" name="test_type" value="material">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('materials_col')) ?> *</label>
                    <select name="material_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;">
                        <option value="" disabled selected>-- <?= htmlspecialchars(__('select')) ?> --</option>
                        <?php foreach ($materials as $m): ?>
                            <option value="<?=$m['material_id']?>"><?=h($m['title'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('title_col')) ?> *</label><input type="text" name="title" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('description')) ?> *</label><input type="text" name="description" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('start')) ?> *</label><input type="datetime-local" name="start_date" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('end')) ?> *</label><input type="datetime-local" name="end_date" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('min_score')) ?></label><input type="number" name="min_score" value="0" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></div>
                    <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('max_score')) ?></label><input type="number" name="max_score" value="100" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></div>
                </div>
                <div class="flex items-center gap-3 mt-2">
                    <button type="submit" style="border:none;" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer"><?= htmlspecialchars(__('create_test')) ?></button>
                    <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" onclick="document.getElementById('addMaterialTestForm').style.display='none'"><?= htmlspecialchars(__('cancel')) ?></button>
                </div>
            </form>
        </div>

        <?php if (!empty($dailyTests)): ?>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200/80 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="px-5 py-4 pl-6"><?= htmlspecialchars(__("title_col")) ?></th>
                                <th class="px-5 py-4"><?= htmlspecialchars(__("materials_col")) ?></th>
                                <th class="px-5 py-4"><?= htmlspecialchars(__("date_col")) ?></th>
                                <th class="px-5 py-4 text-right pr-6"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dailyTests as $test): ?>
                                <tr class="border-b border-slate-100 text-sm last:border-0 hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-4 pl-6 font-semibold text-slate-900"><?=h($test['title'])?></td>
                                    <td class="px-5 py-4"><span class="inline-flex rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700"><?=h($test['material_title']??'—')?></span></td>
                                    <td class="px-5 py-4 text-slate-500 tabular-nums"><?=date('d.m.Y', strtotime($test['start_date']))?> — <?=date('d.m.Y', strtotime($test['end_date']))?></td>
                                    <td class="px-5 py-4 text-right pr-6 whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="/admin/tests/view.php?test_id=<?=$test['test_id']?>&type=daily&tab=overview" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 no-underline shadow-sm hover:bg-slate-50" style="border:1px solid #e2e8f0;"><?= htmlspecialchars(__("open_btn")) ?></a>
                                            <form method="post" class="inline m-0" onsubmit="return confirm('<?= htmlspecialchars(__("delete_test_confirm")) ?>')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_test">
                                                <input type="hidden" name="test_id" value="<?=$test['test_id']?>">
                                                <input type="hidden" name="test_type" value="material">
                                                <button type="submit" style="border:1px solid #fecaca; background:#fff;" class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium text-red-600 shadow-sm hover:bg-red-50 cursor-pointer"><?= htmlspecialchars(__("delete_btn")) ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<!-- Edit Course Modal -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(2px);">
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xl max-w-xl w-full m-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-slate-900 m-0"><?= htmlspecialchars(__("edit_course")) ?></h2>
            <button onclick="document.getElementById('editModal').style.display='none'" class="text-slate-400 hover:text-slate-900 bg-transparent border-none text-2xl cursor-pointer leading-none">&times;</button>
        </div>
        <form method="post" class="flex flex-col gap-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_course">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("subject_col")) ?> *</label><input type="text" name="subject" value="<?=h($course['subject'])?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("title_col")) ?> *</label><input type="text" name="title" value="<?=h($course['title'])?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
            </div>
            <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("description")) ?></label><textarea name="description" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"><?=h($course['description'])?></textarea></div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("price")) ?> *</label><input type="number" name="price" step="1000" min="0" value="<?=$course['price']?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                <div><label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("subscription_days")) ?> *</label><input type="number" name="days" min="1" value="<?=$course['subscription_days']?>" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;"></div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__("class")) ?> *</label>
                    <select name="classLevel" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm cursor-pointer" style="border:1px solid #e2e8f0;">
                        <?php foreach(['1-2','3-4','5-6','7-8','9-11','1-4'] as $l): ?>
                            <option value="<?=$l?>" <?=$course['classLevel']===$l?'selected':''?>><?=$l?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 mt-2 pt-2 border-t border-slate-100">
                <div class="flex items-center gap-3">
                    <button type="submit" style="border:none;" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer"><?= htmlspecialchars(__("save")) ?></button>
                    <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" onclick="document.getElementById('editModal').style.display='none'"><?= htmlspecialchars(__('cancel')) ?></button>
                </div>
            </div>
        </form>
        <form method="post" class="mt-4 pt-4 border-t border-slate-100 m-0" onsubmit="return confirm('<?= htmlspecialchars(__("delete_course_confirm")) ?>')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_course">
            <button type="submit" style="border:1px solid #fecaca; background:#fff;" class="rounded-xl px-4 py-2 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50 cursor-pointer"><?= htmlspecialchars(__("delete_btn")) ?></button>
        </form>
    </div>
</div>

<!-- Enroll Student Modal -->
<div id="enrollModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.4);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(2px);">
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xl max-w-md w-full m-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-slate-900 m-0"><?= htmlspecialchars(__('enroll_student')) ?></h2>
            <button onclick="document.getElementById('enrollModal').style.display='none'" class="text-slate-400 hover:text-slate-900 bg-transparent border-none text-2xl cursor-pointer leading-none">&times;</button>
        </div>
        <form method="post" class="flex flex-col gap-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="enroll_student">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('nav_students')) ?> *</label>
                <select name="user_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm cursor-pointer" style="border:1px solid #e2e8f0;">
                    <option value="" disabled selected>-- <?= htmlspecialchars(__('select')) ?> --</option>
                    <?php foreach ($availableStudents as $st): ?>
                        <option value="<?=$st['user_id']?>"><?=h($st['first_name'].' '.$st['last_name'])?> (<?=h($st['phone']?:$st['email'])?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1"><?= htmlspecialchars(__('subscription_days')) ?></label>
                <input type="number" name="days" value="<?=$course['subscription_days']?:30?>" min="1" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" style="border:1px solid #e2e8f0;">
            </div>
            <div class="flex items-center gap-3 mt-2">
                <button type="submit" style="border:none;" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 cursor-pointer"><?= htmlspecialchars(__('enroll_student')) ?></button>
                <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 cursor-pointer" style="border:1px solid #e2e8f0;" onclick="document.getElementById('enrollModal').style.display='none'"><?= htmlspecialchars(__('cancel')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleLessonPreview(matId) {
    const el = document.getElementById('lesson-preview-' + matId);
    if (!el) return;
    const isHidden = el.classList.contains('hidden');
    if (isHidden) {
        el.classList.remove('hidden');
        const video = document.getElementById('video-player-' + matId);
        if (video) {
            const errBox = document.getElementById('video-error-' + matId);
            if (errBox) errBox.classList.add('hidden');
        }
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        el.classList.add('hidden');
        const video = document.getElementById('video-player-' + matId);
        if (video && !video.paused) {
            video.pause();
        }
    }
}

function showPreviewError(matId) {
    const errBox = document.getElementById('video-error-' + matId);
    if (errBox) {
        errBox.classList.remove('hidden');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
