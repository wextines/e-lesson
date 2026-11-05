<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../login/Login_functions.php';
require_once __DIR__ . '/../php/tenant_file.php';

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

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if (!$course_id) {
    header("Location: /profile/courses.php");
    exit();
}

// 2. Fetch course data
$stmtCourse = $pdo->prepare("SELECT course_id, title, subject, description, classLevel, price FROM courses WHERE course_id = ? AND tenant_id = ?");
$stmtCourse->execute([$course_id, $tenant_id]);
$course = $stmtCourse->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    header("Location: /profile/courses.php");
    exit();
}

// 3. Verify enrollment / subscription — access is controlled by status = 'active',
// not by comparing access_expires_at to NOW() (an overdue active subscription must
// still have access; access_expires_at is informational only).
$stmtSub = $pdo->prepare("SELECT subscription_id, access_expires_at, DATEDIFF(access_expires_at, NOW()) as days_left
    FROM course_subs
    WHERE user_id = ? AND course_id = ? AND tenant_id = ? AND status = 'active'
    LIMIT 1");
$stmtSub->execute([$user_id, $course_id, $tenant_id]);
$sub = $stmtSub->fetch(PDO::FETCH_ASSOC);

$hasAccess = (bool)$sub;
if (!$hasAccess && (int)$course['price'] === 0 && $course['classLevel'] === $user['class']) {
    $hasAccess = true;
}

if (!$hasAccess) {
    header("Location: /profile/courses.php?error=no_access");
    exit();
}

// 4. Fetch materials (lessons)
$stmtMat = $pdo->prepare("SELECT material_id, lesson_id, title, description, file_path, thumbnail_path, duration, uploaded_at 
    FROM materials 
    WHERE course_id = ? AND tenant_id = ? 
    ORDER BY lesson_id ASC, material_id ASC");
$stmtMat->execute([$course_id, $tenant_id]);
$materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

if (empty($materials)) {
    // If no materials yet, create a dummy placeholder so student doesn't see blank crash
    $materials = [[
        'material_id' => 0,
        'lesson_id' => 1,
        'title' => 'Вводный урок',
        'description' => 'Уроки курса загружаются преподавателем.',
        'file_path' => '',
        'thumbnail_path' => '',
        'duration' => 15
    ]];
}

// Select active material
$requestedMatId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$currentMaterial = null;
$currentIndex = 0;

foreach ($materials as $idx => $m) {
    if ($requestedMatId > 0 && (int)$m['material_id'] === $requestedMatId) {
        $currentMaterial = $m;
        $currentIndex = $idx;
        break;
    }
}
if (!$currentMaterial) {
    $currentMaterial = $materials[0];
    $currentIndex = 0;
}

// 5. Fetch daily tests attached to this material
$currentTest = null;
$currentTestResult = null;
if (!empty($currentMaterial['material_id'])) {
    $stmtTest = $pdo->prepare("SELECT daily_test_id, material_id, title, description, start_date, end_date, min_score, max_score 
        FROM daily_tests 
        WHERE material_id = ? AND tenant_id = ? 
        LIMIT 1");
    $stmtTest->execute([(int)$currentMaterial['material_id'], $tenant_id]);
    $currentTest = $stmtTest->fetch(PDO::FETCH_ASSOC);

    if ($currentTest && !empty($user['slug'])) {
        $stmtTR = $pdo->prepare("SELECT result_id, score, get_points, completed_at 
            FROM test_results 
            WHERE slug = ? AND test_id = ? AND test_type = 'daily' AND tenant_id = ? 
            LIMIT 1");
        $stmtTR->execute([$user['slug'], $currentTest['daily_test_id'], $tenant_id]);
        $currentTestResult = $stmtTR->fetch(PDO::FETCH_ASSOC);
    }
}

// 6. Fetch all solved tests for progress calculation
$solvedMaterialIds = [];
if (!empty($user['slug'])) {
    $stmtSolved = $pdo->prepare("SELECT DISTINCT dt.material_id 
        FROM test_results tr 
        JOIN daily_tests dt ON tr.test_id = dt.daily_test_id AND tr.test_type = 'daily' 
        WHERE tr.slug = ? AND tr.tenant_id = ? AND dt.course_id = ?");
    $stmtSolved->execute([$user['slug'], $tenant_id, $course_id]);
    $solvedMaterialIds = array_column($stmtSolved->fetchAll(PDO::FETCH_ASSOC), 'material_id');
}

// 7. Fetch weekly tests for this course
$stmtWeekly = $pdo->prepare("SELECT weekly_test_id, title, description, start_date, end_date, min_score, max_score 
    FROM weekly_tests 
    WHERE course_id = ? AND tenant_id = ? 
    ORDER BY weekly_test_id ASC");
$stmtWeekly->execute([$course_id, $tenant_id]);
$weeklyTests = $stmtWeekly->fetchAll(PDO::FETCH_ASSOC);

$totalLessons = count($materials);
$currentLessonNum = $currentIndex + 1;
$progressPercent = $totalLessons > 0 ? round((count($solvedMaterialIds) / $totalLessons) * 100) : 0;

$prevMaterial = $currentIndex > 0 ? $materials[$currentIndex - 1] : null;
$nextMaterial = $currentIndex < $totalLessons - 1 ? $materials[$currentIndex + 1] : null;

$pageTitle = $currentMaterial['title'] . ' — ' . $course['title'];
$activeTab = 'courses';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Column -->
<div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
    
    <!-- Sticky Course Player Top Bar -->
    <div class="sticky top-0 z-20 bg-[#FAFAF8]/95 backdrop-blur-sm border-b border-[#E4E1DA] px-4 sm:px-6 h-12 flex items-center gap-4 shrink-0">
        <a href="/profile/courses.php" class="flex items-center gap-1.5 text-xs text-[#7A8899] hover:text-[#0D1B2A] transition-colors">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5 M12 19l-7-7 7-7"></path>
            </svg>
            <span class="hidden sm:inline">Назад к курсам</span>
        </a>
        
        <div class="flex-1 min-w-0 hidden md:block">
            <p class="text-xs text-[#7A8899] truncate">
                <?= htmlspecialchars($course['title']) ?> &nbsp;›&nbsp; <?= htmlspecialchars($currentMaterial['title']) ?>
            </p>
        </div>

        <!-- Mobile Drawer Toggle for Lessons list -->
        <button onclick="toggleMobileLessons()" class="lg:hidden flex items-center gap-1.5 text-xs text-[#1B5EE8] font-medium">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <line x1="8" y1="6" x2="21" y2="6"></line>
                <line x1="8" y1="12" x2="21" y2="12"></line>
                <line x1="8" y1="18" x2="21" y2="18"></line>
                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
            <span>Содержание</span>
        </button>

        <!-- Progress bar in top bar -->
        <div class="hidden md:flex items-center gap-3 ml-auto">
            <div class="w-28 rounded-full overflow-hidden bg-[#E8E5DE]" style="height: 4px;">
                <div class="h-full bg-[#1B5EE8] transition-all" style="width: <?= $progressPercent ?>%;"></div>
            </div>
            <span class="text-[11px] text-[#7A8899] font-[var(--font-mono)] shrink-0">
                Урок <?= $currentLessonNum ?> из <?= $totalLessons ?>
            </span>
        </div>
    </div>

    <!-- Mobile Lessons Drawer -->
    <div id="mobile-lessons-backdrop" class="fixed inset-0 bg-black/20 z-30 hidden lg:hidden" onclick="toggleMobileLessons()"></div>
    <div id="mobile-lessons" class="fixed right-0 top-12 bottom-0 w-80 bg-[#FAFAF8] z-40 flex flex-col shadow-xl translate-x-full transition-transform duration-200 lg:hidden overflow-y-auto p-4 border-l border-[#E4E1DA]">
        <div class="flex items-center justify-between mb-3 border-b border-[#E4E1DA] pb-2">
            <h3 class="text-xs font-semibold text-[#0D1B2A] uppercase tracking-wider">Программа курса</h3>
            <button onclick="toggleMobileLessons()" class="text-[#7A8899] p-1">✕</button>
        </div>
        <div class="space-y-1">
            <?php foreach ($materials as $i => $m): 
                $isAct = ((int)$m['material_id'] === (int)$currentMaterial['material_id']);
                $isDone = in_array((int)$m['material_id'], $solvedMaterialIds);
            ?>
                <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $m['material_id'] ?>" class="flex items-center gap-2.5 p-2 rounded-lg text-xs <?= $isAct ? 'bg-[#EEF3FD] text-[#1B5EE8] font-medium' : 'text-[#4A607A] hover:bg-[#F0EDE8]' ?>">
                    <span class="w-5 text-center font-mono <?= $isDone ? 'text-[#059669]' : 'text-[#7A8899]' ?>">
                        <?= $isDone ? '✓' : ($i + 1) ?>
                    </span>
                    <span class="flex-1 truncate"><?= htmlspecialchars($m['title']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Two-column Learning Layout (Video/Content on Left, Lesson Playlist on Right) -->
    <div class="flex-1 flex overflow-hidden">
        
        <!-- Left / Center: Video and Lesson Content -->
        <div class="flex-1 overflow-y-auto px-4 sm:px-8 py-6 max-w-4xl">
            <div class="mb-4">
                <p class="text-xs font-semibold text-[#7A8899] uppercase tracking-wider mb-1">
                    <?= htmlspecialchars($course['subject'] ?: 'Курс') ?> · Урок <?= $currentLessonNum ?>
                </p>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#0D1B2A] leading-tight mb-2">
                    <?= htmlspecialchars($currentMaterial['title']) ?>
                </h1>
                <?php if (!empty($currentMaterial['duration'])): ?>
                    <div class="flex items-center gap-1.5 text-xs text-[#7A8899]">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span><?= (int)$currentMaterial['duration'] ?> мин</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- HTML5 Video Player Container (16:9 Aspect Ratio) -->
            <?php
            $matExt = strtolower(pathinfo($currentMaterial['file_path'] ?? '', PATHINFO_EXTENSION));
            $isPdf = ($matExt === 'pdf');
            $hasRealVideo = !empty($currentMaterial['file_path']) && TenantFile::fileExists($tenant_id, $currentMaterial['file_path']);
            $hasRealThumb = !empty($currentMaterial['thumbnail_path']) && TenantFile::fileExists($tenant_id, $currentMaterial['thumbnail_path']);
            $ytId = VideoHelper::extractYouTubeId($currentMaterial['file_path'] ?? null);
            ?>
            <?php if ($ytId): ?>
                <div class="aspect-video rounded-xl bg-black relative overflow-hidden mb-6 shadow-sm border border-[#E4E1DA]">
                    <iframe class="w-full h-full"
                            src="<?= VideoHelper::getEmbedUrl($ytId) ?>"
                            title="<?= htmlspecialchars($currentMaterial['title'] ?? 'Урок') ?>"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen>
                    </iframe>
                </div>
            <?php elseif ($isPdf && $hasRealVideo): ?>
                <div class="aspect-video rounded-xl bg-[#FAFAF8] border border-[#E4E1DA] flex flex-col items-center justify-center text-center p-6 mb-6 shadow-sm">
                    <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-3">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                    <p class="text-sm font-semibold text-[#0D1B2A] mb-1">Учебный материал (PDF)</p>
                    <p class="text-xs text-[#7A8899] mb-4">Материал доступен для изучения в онлайн-режиме.</p>
                    <a href="/file.php?type=material&id=<?= (int)$currentMaterial['material_id'] ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#1B5EE8] hover:bg-[#154ec2] shadow-sm transition-colors">
                        Открыть документ ↗
                    </a>
                </div>
            <?php elseif ($hasRealVideo): ?>
                <div class="aspect-video rounded-xl bg-[#0D1B2A] relative overflow-hidden flex items-center justify-center mb-6 shadow-sm border border-[#E4E1DA]">
                    <video controls controlsList="nodownload" preload="metadata" playsinline class="w-full h-full object-contain"
                           src="/file.php?type=material&id=<?= (int)$currentMaterial['material_id'] ?><?= !$hasRealThumb ? '#t=0.001' : '' ?>"
                           <?= $hasRealThumb ? 'poster="/file.php?type=preview&id=' . (int)$currentMaterial['material_id'] . '"' : '' ?>>
                        Ваш браузер не поддерживает встроенное воспроизведение видео.
                    </video>
                </div>
            <?php else: ?>
                <div class="aspect-video rounded-xl bg-[#0D1B2A]/5 border border-dashed border-[#CCC9C0] flex flex-col items-center justify-center text-center p-6 mb-6">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-[#7A8899] mb-2">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <p class="text-sm font-medium text-[#0D1B2A]">Видеоматериал к уроку</p>
                    <p class="text-xs text-[#7A8899] mt-0.5">Видеоматериал подготавливается. Ознакомьтесь с описанием и пройдите проверочный тест ниже.</p>
                </div>
            <?php endif; ?>

            <!-- Lesson Description -->
            <?php if (!empty($currentMaterial['description'])): ?>
                <div class="border border-[#E4E1DA] rounded-2xl bg-[#FAFAF8] p-5 mb-6 shadow-sm">
                    <h3 class="text-xs font-semibold text-[#0D1B2A] uppercase tracking-wider mb-2">Описание урока</h3>
                    <p class="text-sm text-[#4A607A] leading-relaxed whitespace-pre-line">
                        <?= htmlspecialchars($currentMaterial['description']) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Attached Daily Test Card -->
            <?php if ($currentTest): 
                $hasPassed = false;
                $testScorePercent = 0;
                if ($currentTestResult && !empty($currentTestResult['score'])) {
                    if (strpos($currentTestResult['score'], '/') !== false) {
                        [$sc, $st] = explode('/', $currentTestResult['score']);
                        $testScorePercent = (int)$st > 0 ? round(((int)$sc / (int)$st) * 100) : 0;
                    } else {
                        $testScorePercent = (int)$currentTestResult['score'];
                    }
                    $hasPassed = ($testScorePercent >= 70);
                }
            ?>
                <div class="border border-[#E4E1DA] rounded-2xl bg-[#FAFAF8] p-5 mb-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[11px] font-medium text-[#7A8899] uppercase tracking-widest">Проверка знаний</span>
                                <?php if ($currentTestResult): ?>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded <?= $hasPassed ? 'bg-[#D1FAE5] text-[#059669]' : 'bg-[#FEE2E2] text-[#DC2626]' ?>">
                                        <?= $hasPassed ? 'Сдан · ' . $testScorePercent . '%' : 'Не сдан · ' . $testScorePercent . '%' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-base font-semibold text-[#0D1B2A] leading-snug">
                                <?= htmlspecialchars($currentTest['title'] ?: 'Тест к уроку') ?>
                            </h3>
                            <p class="text-xs text-[#7A8899] mt-1">
                                Ответьте на вопросы по пройденному материалу для закрепления знаний.
                            </p>
                        </div>

                        <div>
                            <?php if ($currentTestResult): ?>
                                <a href="/profile/test.php?test_id=<?= $currentTest['daily_test_id'] ?>&type=daily" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#F0EDE8] text-[#0D1B2A] text-xs font-medium rounded-lg hover:bg-[#E4E1DA] transition-colors">
                                    <span>Результаты теста</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14 M12 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            <?php else: ?>
                                <a href="/profile/test.php?test_id=<?= $currentTest['daily_test_id'] ?>&type=daily" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1B5EE8] text-white text-xs font-medium rounded-lg hover:bg-[#1650CC] active:bg-[#1244B8] transition-colors">
                                    <span>Пройти тест</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Prev / Next Lesson Navigation Buttons -->
            <div class="flex items-center justify-between gap-3 pt-4 border-t border-[#E4E1DA] mb-12">
                <?php if ($prevMaterial): ?>
                    <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $prevMaterial['material_id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-medium text-[#4A607A] bg-[#FAFAF8] border border-[#E4E1DA] hover:bg-[#F0EDE8] hover:text-[#0D1B2A] transition-colors">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5 M12 19l-7-7 7-7"></path>
                        </svg>
                        <span>Предыдущий урок</span>
                    </a>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>

                <?php if ($nextMaterial): ?>
                    <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $nextMaterial['material_id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-medium text-white bg-[#1B5EE8] hover:bg-[#1650CC] transition-colors">
                        <span>Следующий урок</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14 M12 5l7 7-7 7"></path>
                        </svg>
                    </a>
                <?php else: ?>
                    <a href="/profile/courses.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-medium text-[#059669] bg-[#D1FAE5] hover:bg-[#A7F3D0] transition-colors">
                        <span>Завершить курс ✓</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Desktop Lesson Navigation Sidebar (Hidden on mobile < lg) -->
        <div class="hidden lg:flex w-72 shrink-0 flex-col h-full bg-[#FAFAF8] border-l border-[#E4E1DA] overflow-y-auto p-5">
            <div class="mb-4">
                <h3 class="text-xs font-semibold text-[#0D1B2A] uppercase tracking-wider mb-1">
                    Программа курса
                </h3>
                <div class="flex items-center justify-between text-xs text-[#7A8899]">
                    <span><?= $totalLessons ?> уроков</span>
                    <span class="font-mono"><?= $progressPercent ?>% пройдено</span>
                </div>
                <div class="w-full rounded-full overflow-hidden bg-[#E8E5DE] mt-2" style="height: 4px;">
                    <div class="h-full bg-[#1B5EE8]" style="width: <?= $progressPercent ?>%;"></div>
                </div>
            </div>

            <!-- Lessons Playlist -->
            <div class="space-y-1">
                <?php foreach ($materials as $idx => $m): 
                    $isCurrent = ((int)$m['material_id'] === (int)$currentMaterial['material_id']);
                    $isSolved = in_array((int)$m['material_id'], $solvedMaterialIds);
                ?>
                    <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $m['material_id'] ?>" 
                       class="flex items-start gap-2.5 p-2.5 rounded-xl text-xs transition-colors <?= $isCurrent ? 'bg-[#EEF3FD] text-[#1B5EE8] font-medium border border-[#1B5EE8]/20' : 'text-[#4A607A] hover:bg-[#F0EDE8] hover:text-[#0D1B2A]' ?>">
                        <!-- Checkmark / Status icon -->
                        <div class="w-4 h-4 rounded-full flex items-center justify-center mt-0.5 shrink-0 text-[10px] <?= $isSolved ? 'bg-[#D1FAE5] text-[#059669] font-bold' : ($isCurrent ? 'bg-[#1B5EE8] text-white' : 'border border-[#CCC9C0] text-[#7A8899]') ?>">
                            <?= $isSolved ? '✓' : ($isCurrent ? '▶' : ($idx + 1)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="leading-snug truncate <?= $isCurrent ? 'text-[#0D1B2A] font-semibold' : '' ?>">
                                <?= htmlspecialchars($m['title']) ?>
                            </p>
                            <?php if (!empty($m['duration'])): ?>
                                <p class="text-[10px] text-[#7A8899] mt-0.5 font-mono"><?= (int)$m['duration'] ?> мин</p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Weekly Exam / Final tests section -->
            <?php if (!empty($weeklyTests)): ?>
                <div class="mt-6 pt-4 border-t border-[#E4E1DA]">
                    <h4 class="text-[11px] font-semibold text-[#7A8899] uppercase tracking-widest mb-2">Рубежные экзамены</h4>
                    <div class="space-y-1.5">
                        <?php foreach ($weeklyTests as $wt): ?>
                            <a href="/profile/test.php?test_id=<?= $wt['weekly_test_id'] ?>&type=weekly" class="flex items-center gap-2 p-2.5 rounded-xl border border-[#E4E1DA] bg-white text-xs hover:border-[#1B5EE8] transition-colors">
                                <span class="text-base">📝</span>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-[#0D1B2A] truncate"><?= htmlspecialchars($wt['title']) ?></p>
                                    <span class="text-[10px] text-[#D97706] font-mono">Экзамен курса</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleMobileLessons() {
    const drawer = document.getElementById('mobile-lessons');
    const backdrop = document.getElementById('mobile-lessons-backdrop');
    if (!drawer || !backdrop) return;
    if (drawer.classList.contains('translate-x-full')) {
        drawer.classList.remove('translate-x-full');
        backdrop.classList.remove('hidden');
    } else {
        drawer.classList.add('translate-x-full');
        backdrop.classList.add('hidden');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
