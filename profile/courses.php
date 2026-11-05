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

// 2. Fetch enrolled courses
$stmtSubs = $pdo->prepare("SELECT 
    s.subscription_id,
    c.course_id,
    c.title,
    c.subject,
    c.description,
    c.classLevel,
    s.price,
    s.purchase_date,
    s.access_expires_at,
    s.status,
    DATEDIFF(s.access_expires_at, NOW()) as days_left
FROM course_subs s
JOIN courses c ON s.course_id = c.course_id
WHERE s.user_id = ? AND s.tenant_id = ? AND c.tenant_id = ?
ORDER BY s.access_expires_at DESC");
$stmtSubs->execute([$user_id, $tenant_id, $tenant_id]);
$enrolledCourses = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

$courseColors = ['#1B5EE8', '#059669', '#7C3AED', '#D97706', '#2563EB', '#0D9488'];

$courses = [];
foreach ($enrolledCourses as $idx => $c) {
    $cId = (int)$c['course_id'];
    $color = $courseColors[$idx % count($courseColors)];

    // Materials count
    $stmtMat = $pdo->prepare("SELECT material_id, lesson_id, title, duration FROM materials WHERE course_id = ? AND tenant_id = ? ORDER BY lesson_id ASC");
    $stmtMat->execute([$cId, $tenant_id]);
    $materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
    $totalMat = count($materials);

    // Solved tests
    $solvedInCourse = 0;
    if (!empty($user['slug'])) {
        $stmtSolvedCount = $pdo->prepare("SELECT DISTINCT dt.material_id
            FROM test_results tr
            JOIN daily_tests dt ON tr.test_id = dt.daily_test_id AND tr.test_type = 'daily'
            WHERE tr.slug = ? AND tr.tenant_id = ? AND dt.course_id = ?");
        $stmtSolvedCount->execute([$user['slug'], $tenant_id, $cId]);
        $solvedInCourse = count($stmtSolvedCount->fetchAll());
    }

    $progress = $totalMat > 0 ? min(100, round(($solvedInCourse / $totalMat) * 100)) : 0;
    $hasRealAccess = ($c['status'] ?? '') === 'active' && !empty($c['access_expires_at']);
    $daysLeft = $hasRealAccess ? (int)($c['days_left'] ?? 0) : 0;

    if (($c['status'] ?? '') === 'pending') {
        $status = 'pending';
    } elseif ($progress === 100) {
        $status = 'completed';
    } elseif ($hasRealAccess && $daysLeft < 0) {
        $status = 'expired';
    } else {
        $status = 'active';
    }

    $expiresFormatted = '';
    if (!empty($c['access_expires_at'])) {
        $expiresTs = strtotime($c['access_expires_at']);
        $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        $expiresFormatted = 'до ' . date('j', $expiresTs) . ' ' . ($months[(int)date('n', $expiresTs) - 1] ?? '') . ' ' . date('Y', $expiresTs);
    }

    $c['color'] = $color;
    $c['total_materials'] = $totalMat;
    $c['solved_materials'] = $solvedInCourse;
    $c['progress'] = $progress;
    $c['days_left'] = $daysLeft;
    $c['status'] = $status;
    $c['expires_formatted'] = $expiresFormatted;
    $c['is_current'] = ($idx === 0);
    $c['materials'] = $materials;

    $courses[] = $c;
}

// Filter handling
$currentFilter = $_GET['filter'] ?? 'all';

$counts = [
    'all' => count($courses),
    'active' => count(array_filter($courses, fn($c) => $c['status'] === 'active')),
    'completed' => count(array_filter($courses, fn($c) => $c['status'] === 'completed')),
    'expiring' => count(array_filter($courses, fn($c) => $c['status'] === 'active' && $c['days_left'] >= 0 && $c['days_left'] <= 21))
];

$filteredCourses = array_filter($courses, function($c) use ($currentFilter) {
    if ($currentFilter === 'active') return $c['status'] === 'active';
    if ($currentFilter === 'completed') return $c['status'] === 'completed';
    if ($currentFilter === 'expiring') return $c['status'] === 'active' && $c['days_left'] >= 0 && $c['days_left'] <= 21;
    return true;
});

$pageTitle = 'Мои курсы';
$activeTab = 'courses';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Column -->
<div class="flex-1 flex flex-col min-w-0 h-full">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto px-4 sm:px-6 pt-4 sm:pt-6 pb-16 lg:pb-6">
        <div class="space-y-6 max-w-5xl mx-auto">
            
            <!-- Page Header -->
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#0D1B2A] leading-tight mb-1">
                    Мои курсы
                </h2>
                <p class="text-sm text-[#7A8899]">
                    Все курсы, к которым у вас есть доступ. Продолжайте обучение или просматривайте завершённые.
                </p>
            </div>

            <!-- Filter Tabs -->
            <div class="flex items-center gap-1 border-b border-[#E4E1DA]">
                <?php
                $filterTabs = [
                    ['id' => 'all', 'label' => 'Все'],
                    ['id' => 'active', 'label' => 'Активные'],
                    ['id' => 'completed', 'label' => 'Завершённые'],
                    ['id' => 'expiring', 'label' => 'Истекающие'],
                ];
                foreach ($filterTabs as $ft): 
                    $isSelected = ($currentFilter === $ft['id']);
                ?>
                    <a href="?filter=<?= htmlspecialchars($ft['id']) ?>"
                       class="relative flex items-center gap-2 px-3.5 py-2.5 text-sm font-medium transition-colors <?= $isSelected ? 'text-[#0D1B2A]' : 'text-[#7A8899] hover:text-[#4A607A]' ?>">
                        <span><?= htmlspecialchars($ft['label']) ?></span>
                        <?php if ($counts[$ft['id']] > 0): ?>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded font-[var(--font-mono)] leading-none <?= $isSelected ? 'bg-[#EEF3FD] text-[#1B5EE8]' : 'bg-[#F0EDE8] text-[#7A8899]' ?>">
                                <?= $counts[$ft['id']] ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($isSelected): ?>
                            <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#1B5EE8] rounded-t-full"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Course Cards List -->
            <?php if (empty($filteredCourses)): ?>
                <div class="border border-dashed border-[#CCC9C0] rounded-xl py-12 flex flex-col items-center text-center">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="text-[#CCC9C0] mb-2">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                    </svg>
                    <p class="text-sm font-medium text-[#7A8899]">Курсов не найдено</p>
                    <p class="text-xs sm:text-sm text-[#7A8899] mt-0.5 max-w-xs">В этой категории пока нет курсов. Попробуйте другой фильтр.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3 sm:space-y-3.5">
                    <?php foreach ($filteredCourses as $c): 
                        $openUrl = '/profile/course.php?course_id=' . $c['course_id'];
                    ?>
                        <div class="border border-[#E4E1DA] rounded-xl bg-[#FAFAF8] p-4 sm:p-5 shadow-sm hover:border-[#1B5EE8]/40 transition-colors">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                                <!-- Left Colored Accent Bar -->
                                <div class="w-1.5 self-stretch rounded-full hidden sm:block shrink-0" style="background-color: <?= htmlspecialchars($c['color']) ?>;"></div>
                                
                                <!-- Content Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider truncate">
                                            <?= htmlspecialchars($c['subject'] ?: 'Курс') ?>
                                        </span>
                                        <?php if ($c['status'] === 'pending'): ?>
                                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-[#F0EDE8] text-[#7A8899]">Ожидает оплаты</span>
                                        <?php elseif ($c['status'] === 'completed'): ?>
                                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-[#D1FAE5] text-[#059669]">Завершён</span>
                                        <?php elseif ($c['status'] === 'active' && $c['days_left'] >= 0 && $c['days_left'] <= 14): ?>
                                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-[#FEE2E2] text-[#DC2626]">Осталось <?= $c['days_left'] ?> дн.</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-base sm:text-lg font-semibold text-[#0D1B2A] leading-snug">
                                        <a href="<?= htmlspecialchars($openUrl) ?>" class="hover:text-[#1B5EE8]">
                                            <?= htmlspecialchars($c['title']) ?>
                                        </a>
                                    </h3>
                                    <?php if (!empty($c['description'])): ?>
                                        <p class="text-xs sm:text-sm text-[#7A8899] line-clamp-1 mt-1">
                                            <?= htmlspecialchars($c['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Progress line -->
                                    <div class="flex items-center gap-3 mt-2.5 max-w-sm">
                                        <div class="flex-1 rounded-full overflow-hidden bg-[#E8E5DE]" style="height: 5px;">
                                            <div class="h-full rounded-full transition-all duration-500" style="width: <?= $c['progress'] ?>%; background-color: <?= htmlspecialchars($c['color']) ?>;"></div>
                                        </div>
                                        <span class="text-xs sm:text-sm font-semibold text-[#0D1B2A] font-[var(--font-mono)] shrink-0"><?= $c['progress'] ?>%</span>
                                        <span class="text-xs text-[#7A8899] shrink-0"><?= $c['solved_materials'] ?>/<?= $c['total_materials'] ?> ур.</span>
                                    </div>
                                </div>

                                <!-- Right Actions & Expiration -->
                                <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 sm:flex-col sm:items-end sm:border-l sm:border-[#F0EDE8] sm:pl-5">
                                    <span class="text-xs sm:text-sm text-[#7A8899] font-[var(--font-mono)]">
                                        <?= htmlspecialchars($c['expires_formatted']) ?>
                                    </span>
                                    <a href="<?= htmlspecialchars($openUrl) ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1B5EE8] text-white text-xs sm:text-sm font-medium rounded-xl hover:bg-[#1650CC] active:bg-[#1244B8] transition-colors">
                                        <span><?= $c['progress'] > 0 && $c['progress'] < 100 ? 'Продолжить' : 'Открыть' ?></span>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 12h14 M12 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="h-2"></div>
        </div>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>
