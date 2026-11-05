<?php
require_once __DIR__ . '/../authorization.php';
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../../php/tenant_file.php';
require_once __DIR__ . '/../../php/question_service.php';

$tid = Tenant::getId();
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$type = $_GET['type'] ?? '';
$tab = $_GET['tab'] ?? 'overview';

if (!$test_id || !in_array($type, ['daily', 'weekly'])) { header('Location: /admin/courses/'); exit; }

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

if ($type === 'daily') {
    $qt='daily_questions'; $tc='daily_test_id'; $qc='daily_question_id'; $tt='daily_tests'; $uiType='material';
} else {
    $qt='weekly_questions'; $tc='weekly_test_id'; $qc='weekly_question_id'; $tt='weekly_tests'; $uiType='course';
}

$test=$pdo->prepare("SELECT * FROM {$tt} WHERE {$tc}=? AND tenant_id=?");
$test->execute([$test_id,$tid]); $test=$test->fetch(PDO::FETCH_ASSOC);
if (!$test) { header('Location: /admin/courses/'); exit; }

$course_id=(int)$test['course_id'];
$course=$pdo->prepare("SELECT title FROM courses WHERE course_id=? AND tenant_id=?");
$course->execute([$course_id,$tid]); $course=$course->fetch(PDO::FETCH_ASSOC);

$msg=$_SESSION['message']??''; unset($_SESSION['message']);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf(); $act=$_POST['action']??'';

    if ($act==='edit_test') {
        $t=trim($_POST['title']??''); $d=trim($_POST['description']??'');
        $sd=str_replace('T',' ',$_POST['start_date']??'').':00'; $ed=str_replace('T',' ',$_POST['end_date']??'').':00';
        $ms=(int)($_POST['min_score']??0); $xs=(int)($_POST['max_score']??100);
        if (!$t||!$d) { $msg='Title and description required'; }
        else {
            try {
                if ($type==='daily') {
                    $mid=!empty($_POST['material_id'])?(int)$_POST['material_id']:null;
                    $u=$pdo->prepare("UPDATE daily_tests SET material_id=?,title=?,description=?,start_date=?,end_date=?,min_score=?,max_score=? WHERE daily_test_id=? AND tenant_id=?");
                    $u->execute([$mid,$t,$d,$sd,$ed,$ms,$xs,$test_id,$tid]);
                } else {
                    $u=$pdo->prepare("UPDATE weekly_tests SET title=?,description=?,start_date=?,end_date=?,min_score=?,max_score=? WHERE weekly_test_id=? AND tenant_id=?");
                    $u->execute([$t,$d,$sd,$ed,$ms,$xs,$test_id,$tid]);
                }
                $msg='Test updated';
            } catch(PDOException $e) { error_log("edit_test: ".$e->getMessage()); $msg='Error updating test'; }
        }
    }

    if ($act==='add_questions') {
        $nums=$_POST['question_number']??[]; 
        $texts=$_POST['question_text']??[];
        $ans=$_POST['correct_answer']??[]; 
        $files=$_FILES['question_img_url']??[];
        $ok=0; $errs=[];
        $seenNumbers = [];
        
        for ($i=0,$n=count($nums);$i<$n;$i++) {
            $qn=(int)($nums[$i] ?? 0);
            $qText=trim($texts[$i]??'');
            $a=strtoupper(trim($ans[$i]??''));
            
            if ($qn > 0 && in_array($qn, $seenNumbers, true)) {
                $errs[] = "Вопрос №{$qn} дублируется в форме. Укажите уникальные номера.";
                continue;
            }
            if ($qn > 0) {
                $seenNumbers[] = $qn;
            }
            
            $fileData = null;
            if (isset($files['name'][$i]) && !empty($files['name'][$i])) {
                $fileData = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$i] ?? 0
                ];
            }
            
            $res = QuestionService::addQuestion($pdo, $tid, $type, $test_id, $course_id, $qText, $fileData, $a, $qn > 0 ? $qn : null);
            if ($res['success']) {
                $ok++;
            } else {
                $errs[] = $res['error'] ?? "Ошибка при добавлении вопроса #" . ($i + 1);
            }
        }
        
        $msg = $ok ? "Успешно добавлено вопросов: {$ok}" : '';
        if ($errs) {
            $msg .= ($msg ? '. ' : '') . implode('; ', $errs);
        }
    }
    
    if ($act==='edit_question') {
        $qid=(int)($_POST['question_id']??0);
        $qText=trim($_POST['question_text']??'');
        $a=strtoupper(trim($_POST['correct_answer']??''));
        $removeImg=!empty($_POST['remove_image']);
        $fileData = (!empty($_FILES['question_img_url']['name'])) ? $_FILES['question_img_url'] : null;

        $res = QuestionService::updateQuestion($pdo, $tid, $type, $test_id, $course_id, $qid, $qText, $fileData, $removeImg, $a);
        if ($res['success']) {
            $msg = "Вопрос успешно обновлён";
        } else {
            $msg = $res['error'] ?? "Ошибка при обновлении вопроса";
        }
    }

    if ($act==='delete_question') {
        $qid=(int)($_POST['question_id']??0);
        if ($qid) {
            $res = QuestionService::deleteQuestion($pdo, $tid, $type, $test_id, $qid);
            if ($res['success']) {
                $msg = 'Вопрос удалён';
            } else {
                $msg = $res['error'] ?? 'Ошибка удаления вопроса';
            }
        }
    }

    $targetTab = in_array($act, ['add_questions', 'edit_question', 'delete_question']) ? 'questions' : $tab;
    $_SESSION['message']=$msg;
    header("Location: view.php?test_id={$test_id}&type={$type}&tab={$targetTab}"); exit;
}

$questions = QuestionService::getQuestions($pdo, $type, $test_id);

$materials=[];
if ($type==='daily') {
    $mt=$pdo->prepare("SELECT material_id,title FROM materials WHERE course_id=? AND tenant_id=?");
    $mt->execute([$course_id,$tid]); $materials=$mt->fetchAll(PDO::FETCH_ASSOC);
}

$stats=$pdo->prepare("SELECT COUNT(DISTINCT slug) AS students,COUNT(result_id) AS attempts,ROUND(AVG(CAST(score AS DECIMAL(5,2))),1) AS avg_score FROM test_results WHERE test_id=? AND test_type=? AND tenant_id=?");
$stats->execute([$test_id,$type,$tid]); $ts=$stats->fetch(PDO::FETCH_ASSOC);

$results=$pdo->prepare("SELECT tr.slug,u.first_name,u.last_name,tr.score,tr.completed_at FROM test_results tr LEFT JOIN users u ON tr.slug=u.slug AND u.tenant_id=tr.tenant_id WHERE tr.test_id=? AND tr.test_type=? AND tr.tenant_id=? ORDER BY tr.completed_at DESC");
$results->execute([$test_id,$type,$tid]); $results=$results->fetchAll(PDO::FETCH_ASSOC);

$pageTitle=h($test['title']);
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="/admin/courses/view.php?course_id=<?=$course_id?>&tab=<?=$type==='daily'?'content':'tests'?>" class="btn btn-outline btn-sm">&larr; <?=h($course['title'])?></a>
    <h1 style="margin:0;font-size:24px;flex:1"><?=h($test['title'])?></h1>
</div>

<?php if ($msg): 
    $isErr = (stripos($msg, 'ошибка') !== false || stripos($msg, 'error') !== false || stripos($msg, 'необходимо') !== false || stripos($msg, 'не найден') !== false);
    $isWarn = (stripos($msg, 'уже существует') !== false || stripos($msg, 'дублир') !== false || stripos($msg, 'внимание') !== false || stripos($msg, 'warning') !== false);
    
    if ($isErr) {
        $bg = '#fef2f2'; $border = '#fca5a5'; $color = '#991b1b';
    } elseif ($isWarn) {
        $bg = '#fffbeb'; $border = '#fde047'; $color = '#92400e';
    } else {
        $bg = '#ecfdf5'; $border = '#10b981'; $color = '#065f46';
    }
?>
    <div class="msg" style="padding:10px 14px; border-radius:8px; margin-bottom:16px; font-weight:500; font-size:13.5px; background:<?= $bg ?>; border:1px solid <?= $border ?>; color:<?= $color ?>;">
        <?=h($msg)?>
    </div>
<?php endif; ?>

<div class="tabs" style="gap:16px;">
    <a href="?test_id=<?=$test_id?>&type=<?=$type?>&tab=overview" class="tab-btn <?=$tab==='overview'?'active':''?>" style="text-decoration:none; display:flex; align-items:center; gap:8px;">
        <?= htmlspecialchars(__('overview')) ?>
    </a>
    <a href="?test_id=<?=$test_id?>&type=<?=$type?>&tab=questions" class="tab-btn <?=$tab==='questions'?'active':''?>" style="text-decoration:none; display:flex; align-items:center; gap:8px;">
        <?= htmlspecialchars(__('questions_tab')) ?> <span class="badge <?= $tab==='questions' ? 'badge-blue' : 'badge-gray' ?>"><?=count($questions)?></span>
    </a>
    <a href="?test_id=<?=$test_id?>&type=<?=$type?>&tab=results" class="tab-btn <?=$tab==='results'?'active':''?>" style="text-decoration:none; display:flex; align-items:center; gap:8px;">
        <?= htmlspecialchars(__('results_tab')) ?> <span class="badge <?= $tab==='results' ? 'badge-blue' : 'badge-gray' ?>"><?=count($results)?></span>
    </a>
</div>

<?php if ($tab==='overview'): ?>
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px;">
            <h2 style="margin:0;font-size:18px"><?= htmlspecialchars(__('test_info')) ?></h2>
            <button class="btn btn-outline btn-sm" onclick="document.getElementById('editModal').style.display='flex'"><?= htmlspecialchars(__('edit_test')) ?></button>
        </div>
        <table class="info-table">
            <tr><td style="width:150px;color:var(--gray-500)"><?= htmlspecialchars(__('description')) ?></td><td><?= nl2br(h($test['description'])) ?></td></tr>
            <tr><td style="color:var(--gray-500)"><?= htmlspecialchars(__('period')) ?></td><td><?= date('d.m.Y H:i', strtotime($test['start_date'])) ?> — <?= date('d.m.Y H:i', strtotime($test['end_date'])) ?></td></tr>
            <?php if ($type==='daily'&&!empty($test['material_id'])): ?>
                <tr><td style="color:var(--gray-500)"><?= htmlspecialchars(__('materials_col')) ?></td><td><?= h($test['material_title']??$test['material_id']) ?></td></tr>
            <?php endif; ?>
            <tr><td style="color:var(--gray-500)"><?= htmlspecialchars(__('score_range')) ?></td><td><?=$test['min_score']??0?> — <?=$test['max_score']??100?></td></tr>
            <tr><td style="color:var(--gray-500)"><?= htmlspecialchars(__("test_type")) ?></td><td><span class="badge <?= $type==='daily' ? 'badge-green' : 'badge-blue' ?>"><?= htmlspecialchars(__($type==='daily' ? 'material_test' : 'course_test')) ?></span></td></tr>
        </table>
    </div>

<?php elseif ($tab==='questions'): 
    $nextQNum = count($questions) + 1;
?>
    <!-- Add Questions Card -->
    <div class="card" style="margin-bottom:20px; border-radius:12px; border:1px solid #e2e8f0; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
            <div>
                <h2 style="margin:0; font-size:17px; font-weight:700; color:var(--gray-900);"><?= htmlspecialchars(__("add_questions")) ?></h2>
                <p style="margin:4px 0 0; font-size:12.5px; color:var(--gray-500);">
                    Вы можете <strong>написать вопрос текстом</strong>, <strong>загрузить изображение</strong> или объединить оба варианта.
                </p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_questions">
            <div id="questions-container">
                <div class="question-row-block" style="border:1px solid #e2e8f0; padding:16px; margin-bottom:14px; border-radius:10px; background:#f8fafc;">
                    <div style="display:grid; grid-template-columns: 90px 1.8fr 1.3fr 130px; gap:12px; align-items:start;">
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:11.5px; font-weight:600; color:var(--gray-600); margin-bottom:4px; display:block;"><?= htmlspecialchars(__("question_number")) ?></label>
                            <input type="number" name="question_number[]" min="1" value="<?= $nextQNum ?>" required style="width:100%; padding:7px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#fff;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:11.5px; font-weight:600; color:var(--gray-600); margin-bottom:4px; display:block;">Текст вопроса</label>
                            <textarea name="question_text[]" rows="2" placeholder="Напишите текст вопроса..." style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; font-family:inherit; resize:vertical; background:#fff;"></textarea>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:11.5px; font-weight:600; color:var(--gray-600); margin-bottom:4px; display:block;">Картинка (опционально)</label>
                            <input type="file" name="question_img_url[]" accept="image/*" style="font-size:11.5px; width:100%;">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:11.5px; font-weight:600; color:var(--gray-600); margin-bottom:4px; display:block;"><?= htmlspecialchars(__("answer")) ?> *</label>
                            <select name="correct_answer[]" required style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; background:#fff; color:#2563eb;">
                                <option value="" disabled selected><?= htmlspecialchars(__("select")) ?></option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="E">E</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:10px; margin-top:8px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="addQ()" style="display:inline-flex; align-items:center; gap:4px; font-weight:600;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
                    <?= htmlspecialchars(__("add_question")) ?>
                </button>
                <button type="submit" class="btn btn-success" style="background:#10b981; border:none; padding:7px 16px; font-weight:600; border-radius:7px;">
                    <?= htmlspecialchars(__("save_questions")) ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Questions List Table -->
    <?php if (empty($questions)): ?>
        <div class="card"><p style="text-align:center;color:#6b7280;padding:20px"><?= htmlspecialchars(__("no_questions")) ?></p></div>
    <?php else: ?>
        <div class="card" style="border-radius:12px; border:1px solid #e2e8f0; padding:16px;">
            <div class="table-responsive">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:1px solid #e2e8f0; color:var(--gray-500); font-size:12px; text-transform:uppercase;">
                            <th style="padding:10px; width:60px;">№</th>
                            <th style="padding:10px;">Вопрос</th>
                            <th style="padding:10px; width:90px; text-align:center;"><?= htmlspecialchars(__("answer")) ?></th>
                            <th style="padding:10px; width:170px; text-align:right;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $idx = 1;
                    foreach ($questions as $q): 
                        $hasText = !empty(trim($q['question_text'] ?? ''));
                        $hasImg = !empty($q['question_img_url']);
                        $qid = (int)$q[$qc];
                    ?>
                        <tr style="border-bottom:1px solid #f1f5f9; font-size:13.5px;">
                            <td style="padding:12px 10px; font-weight:700; color:var(--gray-800); font-size:14px;">
                                <?=$idx?>
                            </td>
                            <td style="padding:12px 10px;">
                                <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                                    <?php if ($hasImg): ?>
                                        <div style="flex-shrink:0; border:1px solid #e2e8f0; border-radius:6px; padding:2px; background:#fff;">
                                            <img src="/file.php?type=question&id=<?=$qid?>&v=<?=time()?>" alt="Q #<?=$idx?>" style="width:72px; height:46px; object-fit:cover; border-radius:4px; cursor:pointer; display:block;" onclick="openImg(this.src)">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="flex:1; min-width:200px;">
                                        <?php if ($hasText): ?>
                                            <div style="font-weight:500; color:var(--gray-900); line-height:1.4;">
                                                <?= htmlspecialchars($q['question_text']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$hasText && $hasImg): ?>
                                            <div style="font-size:12px; color:var(--gray-500); font-style:italic;">
                                                [Вопрос в виде картинки]
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:12px 10px; text-align:center;">
                                <span class="badge badge-blue" style="font-size:13px; font-weight:700; padding:4px 10px; border-radius:6px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">
                                    <?=h($q['correct_answer'])?>
                                </span>
                            </td>
                            <td style="padding:12px 10px; text-align:right;">
                                <div style="display:inline-flex; align-items:center; gap:6px;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openEditQModal(<?= htmlspecialchars(json_encode($q), ENT_QUOTES, 'UTF-8') ?>, <?=$idx?>)" style="padding:4px 9px; font-size:12px; border-radius:6px;">
                                        <?= htmlspecialchars(__("edit")) ?>
                                    </button>
                                    <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('<?= htmlspecialchars(__("delete_question_confirm")) ?>')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?=$qid?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 9px; font-size:12px; border-radius:6px;">
                                            <?= htmlspecialchars(__("delete_btn")) ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php 
                    $idx++;
                    endforeach; 
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <script>
    var qCounter = <?= $nextQNum + 1 ?>;
    function addQ() {
        var c = document.getElementById('questions-container');
        var g = document.createElement('div');
        g.className = 'question-row-block';
        g.style.cssText = 'border:1px solid #e2e8f0; padding:16px; margin-bottom:14px; border-radius:10px; background:#f8fafc; position:relative;';
        g.innerHTML = `
            <button type="button" onclick="this.parentElement.remove()" style="position:absolute; top:8px; right:8px; background:none; border:none; color:#94a3b8; font-size:16px; cursor:pointer;" title="Удалить строку">&times;</button>
            <div style="display:grid; grid-template-columns: 90px 1.8fr 1.3fr 130px; gap:12px; align-items:start;">
                <div class="form-group" style="margin:0;">
                    <label style="font-size:11.5px; font-weight:600; color:var(--gray-600); margin-bottom:4px; display:block;"><?= htmlspecialchars(__("question_number")) ?></label>
                    <input type="number" name="question_number[]" min="1" value="${qCounter}" required style="width:100%; padding:7px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background:#fff;">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size:11.5px; font-weight:600; color:var(--gray-600); margin-bottom:4px; display:block;">Текст вопроса</label>
                    <textarea name="question_text[]" rows="2" placeholder="Напишите текст вопроса..." style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; font-family:inherit; resize:vertical; background:#fff;"></textarea>
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size:11.5px; font-weight:600; color:var(--gray-600); margin-bottom:4px; display:block;">Картинка (опционально)</label>
                    <input type="file" name="question_img_url[]" accept="image/*" style="font-size:11.5px; width:100%;">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size:11.5px; font-weight:600; color:var(--gray-600); margin-bottom:4px; display:block;"><?= htmlspecialchars(__("answer")) ?> *</label>
                    <select name="correct_answer[]" required style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; background:#fff; color:#2563eb;">
                        <option value="" disabled selected><?= htmlspecialchars(__("select")) ?></option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                    </select>
                </div>
            </div>`;
        c.appendChild(g);
        qCounter++;
    }

    function openImg(s) {
        var m = document.createElement('div');
        m.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); backdrop-filter:blur(3px); display:flex; justify-content:center; align-items:center; z-index:9999; padding:20px;';
        m.innerHTML = '<div style="position:relative; max-width:90%; max-height:90%;"><img src="' + s + '" style="max-width:100%; max-height:85vh; border-radius:10px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); display:block;"><button onclick="this.closest(\'div\').parentElement.remove()" style="position:absolute; top:-12px; right:-12px; background:#0f172a; color:#fff; border:2px solid #fff; border-radius:50%; width:32px; height:32px; font-size:16px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">&times;</button></div>';
        m.onclick = function(e) { if (e.target === m) m.remove(); };
        document.body.appendChild(m);
    }

    function openEditQModal(q, num) {
        var qc = '<?= $qc ?>';
        var qid = q[qc];
        document.getElementById('editQId').value = qid;
        document.getElementById('editQModalTitle').textContent = 'Редактировать вопрос #' + num;
        document.getElementById('editQText').value = q.question_text || '';
        document.getElementById('editQAnswer').value = q.correct_answer || 'A';
        
        var remCb = document.getElementById('editQRemoveImage');
        if (remCb) remCb.checked = false;
        var fileInp = document.getElementById('editQFileInput');
        if (fileInp) fileInp.value = '';

        var imgBox = document.getElementById('editQImgPreview');
        var fileLabel = document.getElementById('editQFileLabel');
        if (q.question_img_url) {
            imgBox.style.display = 'block';
            document.getElementById('editQImgTag').src = '/file.php?type=question&id=' + qid + '&v=' + Date.now();
            if (fileLabel) fileLabel.textContent = 'Заменить изображение на новое (опционально):';
        } else {
            imgBox.style.display = 'none';
            if (fileLabel) fileLabel.textContent = 'Прикрепить изображение к вопросу (опционально):';
        }
        document.getElementById('editQuestionModal').style.display = 'flex';
    }
    function closeEditQModal() {
        document.getElementById('editQuestionModal').style.display = 'none';
    }
    </script>

<?php elseif ($tab==='results'): ?>
    <?php if (empty($results)): ?>
        <div class="card"><p style="text-align:center;color:#6b7280;padding:20px"><?= htmlspecialchars(__("no_results")) ?></p></div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table><thead><tr><th><?= htmlspecialchars(__("name")) ?></th><th><?= htmlspecialchars(__("scores")) ?></th><th><?= htmlspecialchars(__("date_col")) ?></th></tr></thead><tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><strong><?=h(($r['first_name']??'').' '.($r['last_name']??''))?></strong></td>
                        <td><span class="badge badge-blue"><?=h($r['score'])?></span></td>
                        <td><?=h($r['completed_at'])?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Edit Question In-place Modal -->
<div id="editQuestionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:14px; max-width:540px; width:100%; padding:22px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); max-height:90vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 id="editQModalTitle" style="margin:0; font-size:17px; font-weight:700; color:#0f172a;">Редактировать вопрос</h3>
            <button type="button" onclick="closeEditQModal()" style="background:none; border:none; color:#94a3b8; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form method="post" action="?test_id=<?=$test_id?>&type=<?=$type?>&tab=questions" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_question">
            <input type="hidden" name="question_id" id="editQId" value="">

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:5px;">Текст вопроса</label>
                <textarea name="question_text" id="editQText" rows="3" placeholder="Введите текст вопроса..." style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-family:inherit; resize:vertical;"></textarea>
            </div>

            <div id="editQImgPreview" style="display:none; margin-bottom:14px; background:#f8fafc; border:1px solid #e2e8f0; padding:10px; border-radius:8px;">
                <div style="font-size:11.5px; font-weight:600; color:#64748b; margin-bottom:6px;">Текущее изображение:</div>
                <img id="editQImgTag" src="" alt="Preview" style="max-width:100%; max-height:160px; border-radius:6px; display:block; margin-bottom:8px;">
                <label style="font-size:12px; color:#dc2626; display:flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox" name="remove_image" id="editQRemoveImage" value="1">
                    Удалить текущее изображение
                </label>
            </div>

            <div style="margin-bottom:14px;">
                <label id="editQFileLabel" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:5px;">Загрузить новое изображение (заменить):</label>
                <input type="file" name="question_img_url" id="editQFileInput" accept="image/*" style="font-size:12px; width:100%;">
            </div>

            <div style="margin-bottom:18px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:5px;">Правильный ответ *</label>
                <select name="correct_answer" id="editQAnswer" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px; font-weight:600; color:#2563eb; background:#fff;">
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                    <option value="E">E</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-outline" onclick="closeEditQModal()" style="border:1px solid #cbd5e1; font-size:13px; padding:7px 14px; border-radius:7px;">Отмена</button>
                <button type="submit" class="btn btn-primary" style="background:#2563eb; font-size:13px; padding:7px 16px; border-radius:7px; font-weight:600;">Сохранить изменения</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Test Modal -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h2 style="margin:0"><?= htmlspecialchars(__("edit_test")) ?></h2>
            <button onclick="document.getElementById('editModal').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer">&times;</button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_test">
            <?php if ($type==='daily'): ?>
                <div class="form-group"><label><?= htmlspecialchars(__("materials_col")) ?></label><select name="material_id"><option value="">-- <?= htmlspecialchars(__("select")) ?> --</option><?php foreach($materials as $m):?><option value="<?=$m['material_id']?>" <?=($test['material_id']??'')==$m['material_id']?'selected':''?>><?=h($m['title'])?></option><?php endforeach;?></select></div>
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group"><label><?= htmlspecialchars(__("title_col")) ?> *</label><input type="text" name="title" value="<?=h($test['title'])?>" required></div>
            </div>
            <div class="form-group"><label><?= htmlspecialchars(__("description")) ?> *</label><textarea name="description" rows="3" required><?=h($test['description'])?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label><?= htmlspecialchars(__("start")) ?> *</label><input type="datetime-local" name="start_date" value="<?=h(str_replace(' ','T',substr($test['start_date'],0,16)))?>" required></div>
                <div class="form-group"><label><?= htmlspecialchars(__("end")) ?> *</label><input type="datetime-local" name="end_date" value="<?=h(str_replace(' ','T',substr($test['end_date'],0,16)))?>" required></div>
                <div class="form-group"><label><?= htmlspecialchars(__("min_score")) ?></label><input type="number" name="min_score" min="0" value="<?=$test['min_score']??0?>"></div>
                <div class="form-group"><label><?= htmlspecialchars(__("max_score")) ?></label><input type="number" name="max_score" min="0" value="<?=$test['max_score']??100?>"></div>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars(__("save_btn")) ?></button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').style.display='none'"><?= htmlspecialchars(__("cancel")) ?></button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
