<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../login/Login_functions.php';

if (!isLoggedIn()) {
    header("Location: /login/");
    exit();
}

$user_id = getSessionUserId();
$tenant_id = Tenant::getId();

// 1. Fetch user data
$stmtUser = $pdo->prepare("SELECT user_id, phone, class, balance, first_name, last_name, points, slug, email FROM users WHERE user_id = ? AND tenant_id = ?");
$stmtUser->execute([$user_id, $tenant_id]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$test_type = isset($_GET['type']) && $_GET['type'] === 'weekly' ? 'weekly' : 'daily';

if (!$test_id) {
    header("Location: /profile/tests.php");
    exit();
}

// 2. Fetch test info
if ($test_type === 'daily') {
    $stmtTest = $pdo->prepare("SELECT dt.daily_test_id as id, dt.title, dt.description, dt.min_score, dt.max_score, dt.course_id, c.title as course_title 
        FROM daily_tests dt 
        JOIN courses c ON dt.course_id = c.course_id AND c.tenant_id = dt.tenant_id
        WHERE dt.daily_test_id = ? AND dt.tenant_id = ?");
} else {
    $stmtTest = $pdo->prepare("SELECT wt.weekly_test_id as id, wt.title, wt.description, wt.min_score, wt.max_score, wt.course_id, c.title as course_title 
        FROM weekly_tests wt 
        JOIN courses c ON wt.course_id = c.course_id AND c.tenant_id = wt.tenant_id
        WHERE wt.weekly_test_id = ? AND wt.tenant_id = ?");
}
$stmtTest->execute([$test_id, $tenant_id]);
$test = $stmtTest->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header("Location: /profile/tests.php");
    exit();
}

// 2b. Verify the student has active paid access to this test's course. Tests inherit
// access from the course subscription (README section 11) — this page previously had
// no access check at all, meaning a guessed test_id was reachable directly regardless
// of enrollment. Consistent with every other gate, access is controlled by
// status = 'active', not access_expires_at. (P0-3 added this, then reverted it after
// discovering e2e/student_functional_fixes.spec.ts's "Fresh test attempt" test relied
// on this exact gap with a student who had no real enrollment; P0-4 fixed that test's
// own setup instead of reopening the hole — see that spec file.)
require_once __DIR__ . '/../php/access_service.php';
if (!AccessService::hasActiveAccess($pdo, $tenant_id, $user_id, (int)$test['course_id'])) {
    header("Location: /profile/tests.php");
    exit();
}

// 3. Fetch questions
if ($test_type === 'daily') {
    $stmtQ = $pdo->prepare("SELECT daily_question_id as id, sort_order, question_text, question_img_url, correct_answer 
        FROM daily_questions 
        WHERE daily_test_id = ? 
        ORDER BY sort_order ASC, daily_question_id ASC");
} else {
    $stmtQ = $pdo->prepare("SELECT weekly_question_id as id, sort_order, question_text, question_img_url, correct_answer 
        FROM weekly_questions 
        WHERE weekly_test_id = ? 
        ORDER BY sort_order ASC, weekly_question_id ASC");
}
$stmtQ->execute([$test_id]);
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

$totalQuestions = count($questions);

// 4. Check if already solved
$stmtCheck = $pdo->prepare("SELECT result_id, score, get_points, completed_at 
    FROM test_results 
    WHERE slug = ? AND test_id = ? AND test_type = ? AND tenant_id = ? 
    LIMIT 1");
$stmtCheck->execute([$user['slug'], $test_id, $test_type, $tenant_id]);
$existingResult = $stmtCheck->fetch(PDO::FETCH_ASSOC) ?: null;

$isCompleted = ($existingResult !== null);

// 5. Handle submission (POST)
$submittedResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_test') {
    validateCsrfToken();
    if (!$existingResult) {
        $submittedAnswers = $_POST['answers'] ?? [];
        $correctCount = 0;

        foreach ($questions as $q) {
            $userAns = strtoupper(trim($submittedAnswers[$q['id']] ?? ''));
            $correctAns = strtoupper(trim($q['correct_answer'] ?? ''));
            if ($userAns !== '' && $userAns === $correctAns) {
                $correctCount++;
            }
        }

        $minScore = (int)($test['min_score'] ?? 0);
        $maxScore = (int)($test['max_score'] ?? 100);
        if ($maxScore <= 0) $maxScore = 100;

        $pointsEarned = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * ($maxScore - $minScore) + $minScore) : 0;
        $scoreStr = $correctCount . '/' . $totalQuestions;

        // DB Transaction
        try {
            $pdo->beginTransaction();

            $stmtIns = $pdo->prepare("INSERT INTO test_results 
                (slug, test_type, test_id, score, get_points, completed_at, tenant_id) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)");
            $stmtIns->execute([$user['slug'], $test_type, $test_id, $scoreStr, $pointsEarned, $tenant_id]);

            $stmtUpd = $pdo->prepare("UPDATE users SET points = COALESCE(points, 0) + ? WHERE slug = ? AND tenant_id = ?");
            $stmtUpd->execute([$pointsEarned, $user['slug'], $tenant_id]);

            $pdo->commit();

            // Refresh existing result
            $existingResult = [
                'score' => $scoreStr,
                'get_points' => $pointsEarned,
                'completed_at' => date('Y-m-d H:i:s')
            ];
            $isCompleted = true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = 'Ошибка при сохранении результатов теста: ' . $e->getMessage();
        }
    }
}

// Prepare score metrics for review screen
$scorePercent = 0;
$correctCount = 0;
$passed = false;
if ($existingResult && !empty($existingResult['score'])) {
    if (strpos($existingResult['score'], '/') !== false) {
        [$sc, $st] = explode('/', $existingResult['score']);
        $correctCount = (int)$sc;
        $scorePercent = (int)$st > 0 ? round(((int)$sc / (int)$st) * 100) : 0;
    } else {
        $scorePercent = (int)$existingResult['score'];
        $correctCount = round(($scorePercent / 100) * $totalQuestions);
    }
    $passed = ($scorePercent >= 70);
}

$pageTitle = $test['title'] . ' — Тестирование';
$activeTab = 'tests';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Column -->
<div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
    
    <!-- Header bar -->
    <div class="sticky top-0 z-20 bg-[#FAFAF8]/95 backdrop-blur-sm border-b border-[#E4E1DA] px-4 sm:px-6 h-12 flex items-center justify-between gap-4 shrink-0">
        <a href="/profile/tests.php" class="flex items-center gap-1.5 text-xs text-[#7A8899] hover:text-[#0D1B2A] transition-colors">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5 M12 19l-7-7 7-7"></path>
            </svg>
            <span class="hidden sm:inline">К списку тестов</span>
        </a>

        <span class="text-xs font-semibold text-[#0D1B2A] truncate max-w-sm">
            <?= htmlspecialchars($test['title']) ?>
        </span>

        <?php if (!$isCompleted): ?>
            <!-- Active Timer -->
            <div class="flex items-center gap-1.5 text-xs font-mono font-medium text-[#1B5EE8] bg-[#EEF3FD] px-2.5 py-1 rounded-md">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span id="test-timer">30:00</span>
            </div>
        <?php else: ?>
            <div></div>
        <?php endif; ?>
    </div>

    <!-- Main Body: Review Mode vs Active Test Taking Mode -->
    <div class="flex-1 overflow-y-auto px-4 sm:px-8 py-6">
        
        <?php if ($isCompleted): ?>
            <!-- Results / Review Screen matching Figma TestTakingPage (Submitted state) -->
            <div class="max-w-xl mx-auto py-6 text-center">
                <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-3 <?= $passed ? 'bg-[#D1FAE5]' : 'bg-[#FEE2E2]' ?>">
                    <?php if ($passed): ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[#059669]">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    <?php else: ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[#DC2626]">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    <?php endif; ?>
                </div>

                <h2 class="text-xl sm:text-2xl font-[var(--font-display)] text-[#0D1B2A] mb-0.5">
                    <?= $passed ? 'Тест сдан' : 'Тест не сдан' ?>
                </h2>
                <p class="text-xs sm:text-sm text-[#7A8899] mb-5">
                    <?= $passed ? 'Поздравляем! Вы успешно справились с заданиями.' : 'Проходной балл 70%. Повторите пройденный материал уроков.' ?>
                </p>

                <!-- Metrics Grid -->
                <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 mb-5 text-left shadow-sm">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="text-center">
                            <p class="text-2xl sm:text-3xl font-semibold font-[var(--font-mono)] <?= $passed ? 'text-[#059669]' : 'text-[#DC2626]' ?>">
                                <?= $scorePercent ?>%
                            </p>
                            <p class="text-xs text-[#7A8899] mt-0.5">Результат</p>
                        </div>
                        <div class="text-center border-x border-[#F0EDE8]">
                            <p class="text-2xl sm:text-3xl font-semibold font-[var(--font-mono)] text-[#0D1B2A]">
                                <?= $correctCount ?> / <?= $totalQuestions ?>
                            </p>
                            <p class="text-xs text-[#7A8899] mt-0.5">Правильных</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl sm:text-3xl font-semibold font-[var(--font-mono)] text-[#1B5EE8]">
                                <?= (int)($existingResult['get_points'] ?? 0) ?>
                            </p>
                            <p class="text-xs text-[#7A8899] mt-0.5">Баллов</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-3">
                    <a href="/profile/tests.php" class="px-4 py-2 bg-[#F0EDE8] text-[#0D1B2A] text-xs font-medium rounded-lg hover:bg-[#E4E1DA] transition-colors">
                        К списку тестов
                    </a>
                    <a href="/profile/course.php?course_id=<?= $test['course_id'] ?>" class="px-4 py-2 bg-[#1B5EE8] text-white text-xs font-medium rounded-lg hover:bg-[#1650CC] transition-colors">
                        Вернуться к курсу
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Live Test Taking Screen matching Figma TestTakingPage -->
            <?php if (empty($questions)): ?>
                <div class="max-w-md mx-auto py-10 text-center text-xs text-[#7A8899]">
                    В этом тесте пока нет вопросов.
                </div>
            <?php else: ?>
                <form id="test-form" method="POST" action="">
                    <input type="hidden" name="action" value="submit_test">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    
                    <div class="max-w-4xl mx-auto flex flex-col lg:flex-row gap-6 items-start">
                        
                        <!-- Left: Questions Container -->
                        <div class="flex-1 w-full min-w-0">
                            <?php foreach ($questions as $qIndex => $q): 
                                $qId = $q['id'];
                            ?>
                                <div id="question-card-<?= $qIndex ?>" class="question-card <?= $qIndex !== 0 ? 'hidden' : '' ?> space-y-4">
                                    <div class="flex items-center justify-between text-xs text-[#7A8899]">
                                        <span class="font-mono font-medium text-[#1B5EE8]">Вопрос <?= $qIndex + 1 ?> из <?= $totalQuestions ?></span>
                                        <span>Выберите один правильный ответ</span>
                                    </div>

                                    <!-- Question Card -->
                                    <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 shadow-sm">
                                        <?php if (!empty($q['question_text'])): ?>
                                            <h3 class="text-sm sm:text-base font-medium text-[#0D1B2A] leading-relaxed mb-3">
                                                <?= htmlspecialchars($q['question_text']) ?>
                                            </h3>
                                        <?php endif; ?>

                                        <?php if (!empty($q['question_img_url'])): ?>
                                            <div class="mb-4 rounded-lg border border-[#E4E1DA] bg-white p-2.5 flex justify-center overflow-hidden">
                                                <img src="/file.php?type=question&id=<?= $qId ?>&test_type=<?= $test_type ?>" 
                                                     alt="Задание" 
                                                     class="max-h-72 object-contain"
                                                     onerror="this.onerror=null; this.src='<?= htmlspecialchars($q['question_img_url']) ?>'">
                                            </div>
                                        <?php endif; ?>

                                        <!-- Options A, B, C, D, E -->
                                        <div class="space-y-2">
                                            <?php foreach (['A', 'B', 'C', 'D', 'E'] as $optLetter): ?>
                                                <label class="option-label flex items-center gap-3 p-2.5 sm:p-3 border border-[#E4E1DA] rounded-lg bg-white cursor-pointer hover:border-[#1B5EE8] transition-colors"
                                                       data-q="<?= $qIndex ?>" data-opt="<?= $optLetter ?>">
                                                    <input type="radio" 
                                                           name="answers[<?= $qId ?>]" 
                                                           value="<?= $optLetter ?>" 
                                                           class="sr-only"
                                                           onchange="onOptionSelect(<?= $qIndex ?>, '<?= $optLetter ?>')">
                                                    <span class="letter-circle w-6 h-6 rounded-full border border-[#CCC9C0] flex items-center justify-center text-xs font-mono font-medium text-[#7A8899] shrink-0">
                                                        <?= $optLetter ?>
                                                    </span>
                                                    <span class="text-xs sm:text-sm font-medium text-[#0D1B2A]">
                                                        Вариант <?= $optLetter ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Navigation lives entirely in the right-hand panel
                                         (#sidebar-next-btn + the number grid); a second
                                         stepper here only duplicated it. -->
                                    <div class="pb-1"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Right: Question Number Grid Navigation -->
                        <div class="w-full lg:w-60 shrink-0 border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 shadow-sm">
                            <h4 class="text-xs font-semibold text-[#0D1B2A] uppercase tracking-wider mb-2.5">Навигация по вопросам</h4>

                            <div class="mb-3">
                                <div class="flex items-center justify-between text-xs text-[#7A8899] mb-1.5">
                                    <span>Отвечено:</span>
                                    <span id="answered-stat" class="font-mono font-medium text-[#0D1B2A]">0 / <?= $totalQuestions ?></span>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-[#E4E1DA] overflow-hidden">
                                    <div id="answered-bar" class="h-full w-0 rounded-full bg-[#1B5EE8] transition-all duration-300"></div>
                                </div>
                            </div>

                            <div class="grid grid-cols-5 gap-1.5 mb-4">
                                <?php for ($i = 0; $i < $totalQuestions; $i++): ?>
                                    <button type="button"
                                            id="nav-btn-<?= $i ?>"
                                            onclick="goToQuestion(<?= $i ?>)"
                                            class="nav-btn h-8 rounded-lg border text-xs font-mono font-medium transition-colors flex items-center justify-center <?= $i === 0 ? 'border-[#1B5EE8] bg-[#EEF3FD] text-[#1B5EE8]' : 'border-[#E4E1DA] bg-white text-[#4A607A]' ?>">
                                        <?= $i + 1 ?>
                                    </button>
                                <?php endfor; ?>
                            </div>

                            <!-- Primary action advances through the test; it only becomes "finish" on the
                                 last question. Submitting early is deliberately demoted to the outline
                                 button below, because students were reading the old dark "Сдать тест"
                                 button as "next" and ending the test after one answer. -->
                            <button type="button"
                                    id="sidebar-next-btn"
                                    onclick="sidebarNextAction()"
                                    class="w-full py-2 bg-[#1B5EE8] text-white text-xs font-medium rounded-lg hover:bg-[#1650CC] flex items-center justify-center gap-1.5 shadow-xs">
                                Следующий вопрос →
                            </button>

                            <button type="button" onclick="confirmSubmit()" class="w-full mt-2 py-1.5 border border-[#E4E1DA] bg-white text-[#7A8899] text-xs font-medium rounded-lg hover:bg-[#F0EDE8] hover:text-[#0D1B2A] transition-colors">Сдать тест</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<!-- Modal confirmation before finishing test -->
<div id="confirm-modal" class="fixed inset-0 bg-black/30 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl border border-[#E4E1DA] text-center">
        <div id="confirm-icon" class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full"></div>
        <h3 id="confirm-title" class="text-base font-semibold text-[#0D1B2A] mb-2">Завершить тест?</h3>
        <p id="confirm-text" class="text-xs text-[#7A8899] mb-5 leading-relaxed"></p>
        <div class="flex items-center justify-center gap-3">
            <button type="button" id="confirm-btn-left" class="px-4 py-2 text-xs font-medium rounded-lg transition-colors"></button>
            <button type="button" id="confirm-btn-right" class="px-5 py-2 text-xs font-medium rounded-lg transition-colors"></button>
        </div>
    </div>
</div>

<script>
let currentQuestionIndex = 0;
const totalQ = <?= $totalQuestions ?>;
const answeredMap = {};
// Tracks which questions the student has actually opened, so a question they looked at
// and left blank can be flagged amber — distinct from one they simply haven't reached.
const visitedMap = {};

function goToQuestion(idx) {
    if (idx < 0 || idx >= totalQ) return;

    visitedMap[idx] = true;

    document.querySelectorAll('.question-card').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById('question-card-' + idx);
    if (target) target.classList.remove('hidden');

    // Update nav button styles
    for (let i = 0; i < totalQ; i++) {
        const btn = document.getElementById('nav-btn-' + i);
        if (!btn) continue;
        if (i === idx) {
            btn.className = 'nav-btn h-9 rounded-lg border-2 border-[#1B5EE8] text-xs font-mono font-semibold transition-colors flex items-center justify-center ' + (answeredMap[i] ? 'bg-[#D1FAE5] text-[#059669]' : 'bg-[#EEF3FD] text-[#1B5EE8]');
        } else if (answeredMap[i]) {
            btn.className = 'nav-btn h-9 rounded-lg border border-[#A7F3D0] bg-[#D1FAE5] text-[#059669] text-xs font-mono font-medium transition-colors flex items-center justify-center';
        } else if (visitedMap[i]) {
            btn.className = 'nav-btn h-9 rounded-lg border border-[#FCD34D] bg-[#FEF3C7]/60 text-[#B45309] text-xs font-mono font-medium transition-colors flex items-center justify-center';
        } else {
            btn.className = 'nav-btn h-9 rounded-lg border border-[#E4E1DA] bg-white text-[#4A607A] text-xs font-mono font-medium transition-colors flex items-center justify-center';
        }
    }
    currentQuestionIndex = idx;
    updateSidebarNextBtn();
    updateAnsweredStat();
}

// On the last question there is nowhere further to go, so the primary action turns into
// finishing the test — which is the only place a green "finish" button ever appears.
function updateSidebarNextBtn() {
    const btn = document.getElementById('sidebar-next-btn');
    if (!btn) return;
    const isLast = currentQuestionIndex === totalQ - 1;
    btn.textContent = isLast ? 'Завершить тест ✓' : 'Следующий вопрос →';
    btn.className = 'w-full py-2 text-white text-xs font-medium rounded-lg flex items-center justify-center gap-1.5 shadow-xs '
        + (isLast ? 'bg-[#059669] hover:bg-[#047857]' : 'bg-[#1B5EE8] hover:bg-[#1650CC]');
}

function sidebarNextAction() {
    if (currentQuestionIndex === totalQ - 1) {
        confirmSubmit();
    } else {
        goToQuestion(currentQuestionIndex + 1);
    }
}

function updateAnsweredStat() {
    const n = Object.keys(answeredMap).length;
    const stat = document.getElementById('answered-stat');
    const bar = document.getElementById('answered-bar');
    if (stat) stat.textContent = n + ' / ' + totalQ;
    if (bar) bar.style.width = (totalQ > 0 ? (n / totalQ) * 100 : 0) + '%';
}

// Live tests often have only 1-2 questions, so the singular cases are the common ones,
// not edge cases — the counted noun has to agree or the warning reads broken.
function pluralQuestions(n) {
    const n10 = n % 10, n100 = n % 100;
    if (n10 === 1 && n100 !== 11) return { word: 'вопрос', stay: 'останется', get: 'получит' };
    if (n10 >= 2 && n10 <= 4 && (n100 < 10 || n100 >= 20)) return { word: 'вопроса', stay: 'останутся', get: 'получат' };
    return { word: 'вопросов', stay: 'останутся', get: 'получат' };
}

// Genitive form used after "из N": «из 1 вопроса», «из 4 вопросов».
function questionsAfterOutOf(n) {
    return (n % 10 === 1 && n % 100 !== 11) ? 'вопроса' : 'вопросов';
}

function onOptionSelect(qIdx, letter) {
    answeredMap[qIdx] = letter;
    
    // Highlight selected label
    const labels = document.querySelectorAll(`label[data-q="${qIdx}"]`);
    labels.forEach(lbl => {
        const isSel = lbl.getAttribute('data-opt') === letter;
        const circle = lbl.querySelector('.letter-circle');
        if (isSel) {
            lbl.className = 'option-label flex items-center gap-3 p-3.5 border-2 border-[#1B5EE8] bg-[#EEF3FD] rounded-xl cursor-pointer transition-colors';
            circle.className = 'letter-circle w-6 h-6 rounded-full bg-[#1B5EE8] text-white flex items-center justify-center text-xs font-mono font-semibold shrink-0';
        } else {
            lbl.className = 'option-label flex items-center gap-3 p-3.5 border border-[#E4E1DA] rounded-xl bg-white cursor-pointer hover:border-[#1B5EE8] transition-colors';
            circle.className = 'letter-circle w-6 h-6 rounded-full border border-[#CCC9C0] flex items-center justify-center text-xs font-mono font-medium text-[#7A8899] shrink-0';
        }
    });

    goToQuestion(qIdx);
}

// The dialog is built fresh on every open: when answers are missing it leads with the
// safe choice ("go back") and demotes the destructive one, so a student who hit this by
// reflex cannot lose the test with one more reflexive click.
function confirmSubmit() {
    const answeredCount = Object.keys(answeredMap).length;
    const unansweredCount = totalQ - answeredCount;

    const icon  = document.getElementById('confirm-icon');
    const title = document.getElementById('confirm-title');
    const text  = document.getElementById('confirm-text');
    const left  = document.getElementById('confirm-btn-left');
    const right = document.getElementById('confirm-btn-right');

    if (unansweredCount > 0) {
        const p = pluralQuestions(unansweredCount);
        icon.className = 'mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#FEF3C7] text-2xl';
        icon.textContent = '⚠️';
        title.textContent = 'Внимание: тест не завершён!';
        title.className = 'text-base font-semibold text-[#B45309] mb-2';
        text.innerHTML = 'Вы ответили только на <strong>' + answeredCount + '</strong> из <strong>' + totalQ
            + '</strong> ' + questionsAfterOutOf(totalQ) + '. ' + unansweredCount + ' ' + p.word + ' ' + p.stay
            + ' без ответа и ' + p.get + ' 0 баллов.<br>Вы уверены, что хотите завершить тест досрочно?';

        left.textContent = 'Всё равно завершить';
        left.className = 'px-4 py-2 text-xs font-medium text-[#DC2626] bg-white border border-[#E4E1DA] rounded-lg hover:bg-[#FEE2E2] transition-colors';
        left.onclick = () => document.getElementById('test-form').submit();

        // Take the student straight to the first gap rather than dumping them back on
        // whatever question they happened to be on.
        let firstUnanswered = -1;
        for (let i = 0; i < totalQ; i++) {
            if (!answeredMap[i]) { firstUnanswered = i; break; }
        }

        right.textContent = 'Перейти к пропущенному →';
        right.className = 'px-5 py-2 text-xs font-medium text-white bg-[#1B5EE8] rounded-lg hover:bg-[#1650CC] transition-colors';
        right.onclick = () => {
            closeConfirmModal();
            if (firstUnanswered >= 0) goToQuestion(firstUnanswered);
        };
    } else {
        icon.className = 'mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#D1FAE5] text-2xl';
        icon.textContent = '✓';
        title.textContent = 'Завершить тест?';
        title.className = 'text-base font-semibold text-[#0D1B2A] mb-2';
        text.innerHTML = totalQ === 1
            ? 'Вы ответили на единственный вопрос. Отправить ответ на проверку?'
            : 'Вы ответили на все <strong>' + totalQ + '</strong> ' + pluralQuestions(totalQ).word + '. Отправить ответы на проверку?';

        left.textContent = 'Проверить ответы';
        left.className = 'px-4 py-2 text-xs font-medium text-[#4A607A] bg-[#FAFAF8] border border-[#E4E1DA] rounded-lg hover:bg-[#F0EDE8] transition-colors';
        left.onclick = closeConfirmModal;

        right.textContent = 'Да, отправить';
        right.className = 'px-5 py-2 text-xs font-medium text-white bg-[#059669] rounded-lg hover:bg-[#047857] transition-colors';
        right.onclick = () => document.getElementById('test-form').submit();
    }

    document.getElementById('confirm-modal').classList.remove('hidden');
    right.focus();
}
function closeConfirmModal() {
    document.getElementById('confirm-modal').classList.add('hidden');
}

// Sync the sidebar with the initial question (matters when a test has a single question:
// the primary action must already read "finish", not "next"). Question 0 is rendered
// visible server-side without goToQuestion() ever running, so mark it seen here or it
// would never turn amber when skipped.
visitedMap[0] = true;
updateSidebarNextBtn();
updateAnsweredStat();

// 30 min timer countdown
let remainingSeconds = 1800;
const timerEl = document.getElementById('test-timer');
let timerWarned = false;
if (timerEl) {
    const timerInterval = setInterval(() => {
        remainingSeconds--;
        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            document.getElementById('test-form').submit();
        } else {
            const m = Math.floor(remainingSeconds / 60).toString().padStart(2, '0');
            const s = (remainingSeconds % 60).toString().padStart(2, '0');
            timerEl.textContent = m + ':' + s;

            // Applied once, not every tick — reassigning the class each second would
            // keep restarting the pulse animation.
            if (!timerWarned && remainingSeconds <= 300 && timerEl.parentElement) {
                timerWarned = true;
                timerEl.parentElement.className = 'flex items-center gap-1.5 text-xs font-mono font-medium text-red-600 bg-red-50 border border-red-200 px-2.5 py-1 rounded-md animate-pulse';
            }
        }
    }, 1000);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
