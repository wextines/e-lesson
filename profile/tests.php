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

// 2. Fetch all daily and weekly tests belonging to enrolled courses
$stmtTests = $pdo->prepare("SELECT 
    'daily' as test_type,
    dt.daily_test_id as test_id,
    dt.title as test_title,
    dt.description as test_desc,
    dt.start_date,
    dt.end_date,
    dt.min_score,
    dt.max_score,
    c.course_id,
    c.title as course_title,
    c.subject as course_subject,
    (SELECT COUNT(*) FROM daily_questions dq WHERE dq.daily_test_id = dt.daily_test_id) as questions_count
FROM daily_tests dt
JOIN courses c ON dt.course_id = c.course_id AND c.tenant_id = dt.tenant_id
JOIN course_subs cs ON cs.course_id = c.course_id AND cs.user_id = ? AND cs.status = 'active' AND cs.tenant_id = dt.tenant_id
WHERE dt.tenant_id = ?
UNION ALL
SELECT 
    'weekly' as test_type,
    wt.weekly_test_id as test_id,
    wt.title as test_title,
    wt.description as test_desc,
    wt.start_date,
    wt.end_date,
    wt.min_score,
    wt.max_score,
    c.course_id,
    c.title as course_title,
    c.subject as course_subject,
    (SELECT COUNT(*) FROM weekly_questions wq WHERE wq.weekly_test_id = wt.weekly_test_id) as questions_count
FROM weekly_tests wt
JOIN courses c ON wt.course_id = c.course_id AND c.tenant_id = wt.tenant_id
JOIN course_subs cs ON cs.course_id = c.course_id AND cs.user_id = ? AND cs.status = 'active' AND cs.tenant_id = wt.tenant_id
WHERE wt.tenant_id = ?
ORDER BY test_id DESC");
$stmtTests->execute([$user_id, $tenant_id, $user_id, $tenant_id]);
$allTests = $stmtTests->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch user test results
$userResults = [];
if (!empty($user['slug'])) {
    $stmtTR = $pdo->prepare("SELECT result_id, test_id, test_type, score, get_points, completed_at 
        FROM test_results 
        WHERE slug = ? AND tenant_id = ?");
    $stmtTR->execute([$user['slug'], $tenant_id]);
    $trRows = $stmtTR->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trRows as $r) {
        $userResults[$r['test_type'] . '_' . $r['test_id']] = $r;
    }
}

// Calculate metrics
$completedCount = 0;
$passedCount = 0;
$scoreSum = 0;

$processed = [];
foreach ($allTests as $t) {
    $key = $t['test_type'] . '_' . $t['test_id'];
    $result = $userResults[$key] ?? null;

    $isCompleted = ($result !== null);
    $percent = 0;
    $passed = false;

    if ($isCompleted && !empty($result['score'])) {
        if (strpos($result['score'], '/') !== false) {
            [$sc, $st] = explode('/', $result['score']);
            $percent = (int)$st > 0 ? round(((int)$sc / (int)$st) * 100) : 0;
        } else {
            $percent = (int)$result['score'];
        }
        $passed = ($percent >= 70);
        $completedCount++;
        if ($passed) $passedCount++;
        $scoreSum += $percent;
    }

    $t['is_completed'] = $isCompleted;
    $t['result'] = $result;
    $t['percent'] = $percent;
    $t['passed'] = $passed;
    $t['status'] = $isCompleted ? ($passed ? 'passed' : 'failed') : 'available';

    $processed[] = $t;
}

$avgScore = $completedCount > 0 ? round($scoreSum / $completedCount) : 0;

// Filter handling
$currentFilter = $_GET['filter'] ?? 'all';
$counts = [
    'all' => count($processed),
    'available' => count(array_filter($processed, fn($t) => !$t['is_completed'])),
    'completed' => $completedCount
];

$filtered = array_filter($processed, function($t) use ($currentFilter) {
    if ($currentFilter === 'available') return !$t['is_completed'];
    if ($currentFilter === 'completed') return $t['is_completed'];
    return true;
});

$pageTitle = 'Тесты';
$activeTab = 'tests';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Column -->
<div class="flex-1 flex flex-col min-w-0 h-full">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto px-4 sm:px-6 pt-4 sm:pt-6 pb-16 lg:pb-6">
        <div class="space-y-6 max-w-5xl mx-auto">
            
            <!-- Page Header & Key Metrics -->
            <div>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#0D1B2A] leading-tight mb-1">
                        Тесты
                    </h2>
                    <p class="text-sm text-[#7A8899]">
                        Проверьте свои знания и отслеживайте прогресс по каждому курсу.
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mt-4">
                    <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 shadow-sm">
                        <p class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">Всего тестов</p>
                        <p class="mt-2 text-2xl sm:text-3xl font-bold text-[#0D1B2A] font-[var(--font-mono)] tabular-nums leading-none"><?= count($processed) ?></p>
                    </div>
                    <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 shadow-sm">
                        <p class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">Сдано успешно</p>
                        <p class="mt-2 text-2xl sm:text-3xl font-bold text-[#059669] font-[var(--font-mono)] tabular-nums leading-none"><?= $passedCount ?></p>
                    </div>
                    <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 shadow-sm col-span-2 sm:col-span-1">
                        <p class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">Средний балл</p>
                        <p class="mt-2 text-2xl sm:text-3xl font-bold text-[#0D1B2A] font-[var(--font-mono)] tabular-nums leading-none"><?= $completedCount > 0 ? $avgScore . '%' : '—' ?></p>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="flex items-center gap-1 border-b border-[#E4E1DA]">
                <?php
                $tabs = [
                    ['id' => 'all', 'label' => 'Все'],
                    ['id' => 'available', 'label' => 'Доступные'],
                    ['id' => 'completed', 'label' => 'Завершённые'],
                ];
                foreach ($tabs as $tab): 
                    $isSelected = ($currentFilter === $tab['id']);
                ?>
                    <a href="?filter=<?= htmlspecialchars($tab['id']) ?>"
                       class="relative flex items-center gap-2 px-3.5 py-2.5 text-sm font-medium transition-colors <?= $isSelected ? 'text-[#0D1B2A]' : 'text-[#7A8899] hover:text-[#4A607A]' ?>">
                        <span><?= htmlspecialchars($tab['label']) ?></span>
                        <?php if ($counts[$tab['id']] > 0): ?>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded font-[var(--font-mono)] leading-none <?= $isSelected ? 'bg-[#EEF3FD] text-[#1B5EE8]' : 'bg-[#F0EDE8] text-[#7A8899]' ?>">
                                <?= $counts[$tab['id']] ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($isSelected): ?>
                            <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#1B5EE8] rounded-t-full"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Tests List Panel -->
            <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] px-4 sm:px-5 py-0.5 shadow-sm">
                <?php if (empty($filtered)): ?>
                    <div class="py-12 text-center text-sm text-[#7A8899]">
                        Тестов в этой категории не найдено.
                    </div>
                <?php else: ?>
                    <?php 
                    $fList = array_values($filtered);
                    foreach ($fList as $idx => $t): 
                        $isLast = ($idx === count($fList) - 1);
                        $solveUrl = '/profile/test.php?test_id=' . $t['test_id'] . '&type=' . $t['test_type'];
                    ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3 sm:py-3.5 <?= !$isLast ? 'border-b border-[#F0EDE8]' : '' ?>">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider">
                                        <?= htmlspecialchars($t['course_title'] ?: 'Курс') ?>
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded font-mono bg-[#F0EDE8] text-[#7A8899]">
                                        <?= $t['test_type'] === 'daily' ? 'Тест урока' : 'Экзамен' ?>
                                    </span>
                                </div>
                                <h3 class="text-sm sm:text-base font-semibold text-[#0D1B2A] leading-snug">
                                    <?= htmlspecialchars($t['test_title']) ?>
                                </h3>
                                <div class="flex items-center gap-2 text-xs sm:text-sm text-[#7A8899] mt-1 font-mono">
                                    <span><?= $t['questions_count'] ?> вопросов</span>
                                    <span>·</span>
                                    <span>Проходной: 70%</span>
                                </div>
                            </div>

                            <!-- Right Status and Action Button -->
                            <div class="flex items-center gap-3 shrink-0 self-end sm:self-center">
                                <?php if ($t['is_completed']): ?>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold font-[var(--font-mono)] <?= $t['passed'] ? 'text-[#059669]' : 'text-[#DC2626]' ?>">
                                            <?= $t['percent'] ?>%
                                        </span>
                                        <span class="text-xs font-medium px-2.5 py-1 rounded-md <?= $t['passed'] ? 'bg-[#D1FAE5] text-[#059669]' : 'bg-[#FEE2E2] text-[#DC2626]' ?>">
                                            <?= $t['passed'] ? 'Сдан' : 'Не сдан' ?>
                                        </span>
                                    </div>
                                    <a href="<?= htmlspecialchars($solveUrl) ?>&view=results" class="px-3.5 py-2 bg-[#F0EDE8] text-[#0D1B2A] text-xs sm:text-sm font-medium rounded-xl hover:bg-[#E4E1DA] transition-colors">
                                        Результаты
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs font-medium px-2.5 py-1 rounded-md bg-[#EEF3FD] text-[#1B5EE8]">
                                        Доступен
                                    </span>
                                    <a href="<?= htmlspecialchars($solveUrl) ?>" class="px-4 py-2 bg-[#1B5EE8] text-white text-xs sm:text-sm font-medium rounded-xl hover:bg-[#1650CC] active:bg-[#1244B8] transition-colors">
                                        Начать тест
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="h-2"></div>
        </div>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>
