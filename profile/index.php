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
if (!$user) {
    header("Location: /login/logout.php");
    exit();
}

$tenant = Tenant::get();

// 2. Fetch enrolled courses with subscriptions
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

// Color palette for courses
$courseColors = ['#1B5EE8', '#059669', '#7C3AED', '#D97706', '#2563EB', '#0D9488'];

// 3. For each course, calculate materials count, tests count, and progress
$coursesWithProgress = [];
$continueLearningCourse = null;
$continueLearningLesson = null;
$todos = [];

foreach ($enrolledCourses as $idx => $c) {
    $cId = (int)$c['course_id'];
    $color = $courseColors[$idx % count($courseColors)];
    
    // Total materials in course
    $stmtMat = $pdo->prepare("SELECT material_id, lesson_id, title, description, duration, file_path, thumbnail_path FROM materials WHERE course_id = ? AND tenant_id = ? ORDER BY lesson_id ASC");
    $stmtMat->execute([$cId, $tenant_id]);
    $materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
    $totalMat = count($materials);

    // Total daily tests in course
    $stmtDT = $pdo->prepare("SELECT daily_test_id, material_id, title, end_date FROM daily_tests WHERE course_id = ? AND tenant_id = ?");
    $stmtDT->execute([$cId, $tenant_id]);
    $dailyTests = $stmtDT->fetchAll(PDO::FETCH_ASSOC);
    $totalTests = count($dailyTests);

    // Solved tests in course by this student
    $solvedInCourse = 0;
    $solvedMaterialIds = [];
    $solvedDailyTestIds = [];
    if (!empty($user['slug'])) {
        $stmtSolvedCount = $pdo->prepare("SELECT DISTINCT dt.material_id, tr.test_id
            FROM test_results tr
            JOIN daily_tests dt ON tr.test_id = dt.daily_test_id AND tr.test_type = 'daily'
            WHERE tr.slug = ? AND tr.tenant_id = ? AND dt.course_id = ?");
        $stmtSolvedCount->execute([$user['slug'], $tenant_id, $cId]);
        $solvedRows = $stmtSolvedCount->fetchAll(PDO::FETCH_ASSOC);
        $solvedInCourse = count($solvedRows);
        foreach ($solvedRows as $sr) {
            if (!empty($sr['material_id'])) {
                $solvedMaterialIds[] = (int)$sr['material_id'];
            }
            if (!empty($sr['test_id'])) {
                $solvedDailyTestIds[] = (int)$sr['test_id'];
            }
        }
    }
    
    $progressPercent = $totalMat > 0 ? min(100, round(($solvedInCourse / $totalMat) * 100)) : 0;
    
    // Days left formatting — only meaningful for an active subscription with a real
    // access_expires_at. A pending subscription (no payment yet, access_expires_at is
    // NULL) must never be shown as "expiring today".
    $hasRealAccess = ($c['status'] ?? '') === 'active' && !empty($c['access_expires_at']);
    $daysLeft = $hasRealAccess ? (int)($c['days_left'] ?? 0) : 0;
    $isExpiring = $hasRealAccess && $daysLeft >= 0 && $daysLeft <= 14;
    $isExpired = $hasRealAccess && $daysLeft < 0;
    
    $expiresFormatted = '';
    if (!empty($c['access_expires_at'])) {
        $expiresTs = strtotime($c['access_expires_at']);
        $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        $expiresFormatted = 'до ' . date('j', $expiresTs) . ' ' . ($months[(int)date('n', $expiresTs) - 1] ?? '') . ' ' . date('Y', $expiresTs);
    }
    
    $c['color'] = $color;
    $c['total_materials'] = $totalMat;
    $c['total_tests'] = $totalTests;
    $c['solved_materials'] = $solvedInCourse;
    $c['progress_percent'] = $progressPercent;
    $c['expires_formatted'] = $expiresFormatted;
    $c['days_left'] = $daysLeft;
    $c['is_expiring'] = $isExpiring;
    $c['is_expired'] = $isExpired;
    $c['materials'] = $materials;
    $c['solved_material_ids'] = $solvedMaterialIds;
    
    $coursesWithProgress[] = $c;
    
    // Pick continue learning candidate: first course with real (active) access that
    // has incomplete materials. A pending subscription must never be offered here.
    if ($continueLearningCourse === null && $hasRealAccess) {
        $continueLearningCourse = $c;
        // Find next incomplete lesson
        $nextLesson = null;
        foreach ($materials as $m) {
            if (!in_array((int)$m['material_id'], $solvedMaterialIds)) {
                $nextLesson = $m;
                break;
            }
        }
        $continueLearningLesson = $nextLesson ?: ($materials[0] ?? null);
    }
    
    // Check for "Требует внимания" (Todos)
    // 1) Expiring subscription
    if ($isExpiring && count($todos) < 4) {
        $todos[] = [
            'type' => 'expiring',
            'dot_color' => '#DC2626',
            'title' => 'Истекает доступ',
            'subtitle' => $c['title'],
            'badge' => $daysLeft === 0 ? 'Сегодня' : 'Через ' . $daysLeft . ' дн.',
            'badge_color' => '#DC2626',
            'days_left' => $daysLeft,
            'url' => '/profile/settings.php?tab=payments'
        ];
    }
    
    // 2) Unsolved tests with deadline
    foreach ($dailyTests as $dt) {
        if (!empty($dt['end_date']) && strtotime($dt['end_date']) > time() && strtotime($dt['end_date']) < time() + 86400 * 3) {
            if (!in_array((int)$dt['daily_test_id'], $solvedDailyTestIds)) {
                // Not yet solved
                $diffHours = round((strtotime($dt['end_date']) - time()) / 3600);
                $badgeText = $diffHours <= 24 ? 'Сегодня, ' . date('H:i', strtotime($dt['end_date'])) : 'Через ' . round($diffHours / 24) . ' дн.';
                $todos[] = [
                    'type' => 'test',
                    'dot_color' => '#D97706',
                    'title' => $dt['title'] ?: 'Тест к уроку',
                    'subtitle' => $c['title'],
                    'badge' => $badgeText,
                    'badge_color' => '#D97706',
                    'days_left' => $diffHours <= 24 ? 0 : (int)round($diffHours / 24),
                    'url' => '/profile/course.php?course_id=' . $cId
                ];
                break;
            }
        }
    }
}

// Fallback for continue learning if all real-access courses are 100% complete.
// Must still only consider courses with real (active) access — never a pending
// subscription with no real access.
if ($continueLearningCourse === null) {
    foreach ($coursesWithProgress as $candidate) {
        if (($candidate['status'] ?? '') === 'active') {
            $continueLearningCourse = $candidate;
            $continueLearningLesson = $candidate['materials'][0] ?? null;
            break;
        }
    }
}

// If todos are empty, add helpful prompt
if (empty($todos) && $continueLearningLesson) {
    $todos[] = [
        'type' => 'lesson',
        'dot_color' => '#1B5EE8',
        'title' => 'Урок ' . ($continueLearningLesson['lesson_id'] ?? 1) . ' — ' . $continueLearningLesson['title'],
        'subtitle' => $continueLearningCourse['title'],
        'badge' => 'Не завершён',
        'badge_color' => '#7A8899',
        'days_left' => null,
        'url' => '/profile/course.php?course_id=' . $continueLearningCourse['course_id'] . '&lesson_id=' . ($continueLearningLesson['material_id'] ?? 0)
    ];
}

// Subject icon for course badges/cards
function dashboardSubjectEmoji(?string $subject): string
{
    $s = mb_strtolower((string)$subject);
    if (str_contains($s, 'англ') || str_contains($s, 'english') || str_contains($s, 'ingliz')) return '🇬🇧';
    if (str_contains($s, 'матем') || str_contains($s, 'math') || str_contains($s, 'matemat')) return '📐';
    if (str_contains($s, 'физ') || str_contains($s, 'physic') || str_contains($s, 'fizika')) return '⚛️';
    if (str_contains($s, 'хим') || str_contains($s, 'chem') || str_contains($s, 'kimyo')) return '🧪';
    if (str_contains($s, 'программ') || str_contains($s, 'dasturlash') || str_contains($s, 'program')) return '💻';
    if (str_contains($s, 'tabiatshunoslik') || str_contains($s, 'природ') || str_contains($s, 'natur')) return '🌱';
    if (str_contains($s, 'рус')) return '🇷🇺';
    if (str_contains($s, 'узб') || str_contains($s, 'uzb')) return '🇺🇿';
    return '📚';
}

// Russian plural helper (1 урок / 2 урока / 5 уроков)
function ruPlural(int $n, string $one, string $few, string $many): string
{
    $n100 = $n % 100;
    if ($n100 >= 11 && $n100 <= 14) return $many;
    switch ($n % 10) {
        case 1: return $one;
        case 2: case 3: case 4: return $few;
        default: return $many;
    }
}

$pageTitle = 'Главная';
$activeTab = 'dashboard';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content Column -->
<div class="flex-1 flex flex-col min-w-0 h-full">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 overflow-y-auto px-4 sm:px-6 pt-4 sm:pt-6 pb-16 lg:pb-6">
        <div class="space-y-6 max-w-5xl mx-auto">
            
            <!-- Welcome Header -->
            <div>
                <h2 class="text-2xl font-bold text-slate-900">
                    Привет, <?= htmlspecialchars($user['first_name'] ?: 'Студент') ?> 👋
                </h2>
                <?php if ($continueLearningCourse): ?>
                    <p class="text-sm text-slate-500 mt-1">
                        Продолжайте обучение: следующий урок ждёт вас.
                    </p>
                <?php else: ?>
                    <p class="text-sm text-slate-500 mt-1">
                        Добро пожаловать в учебный кабинет.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Hero Card: Твой следующий урок -->
            <?php if ($continueLearningCourse):
                $clProgress = $continueLearningCourse['progress_percent'];
                $clLessonNum = $continueLearningLesson['lesson_id'] ?? 1;
                $clDuration = (int)($continueLearningLesson['duration'] ?: 25);
                $clUrl = '/profile/course.php?course_id=' . $continueLearningCourse['course_id'] . ($continueLearningLesson ? '&lesson_id=' . $continueLearningLesson['material_id'] : '');
                $clMatId = (int)($continueLearningLesson['material_id'] ?? 0);
                $clFilePath = $continueLearningLesson['file_path'] ?? null;
                $clThumbPath = $continueLearningLesson['thumbnail_path'] ?? null;
                $clYtId = VideoHelper::extractYouTubeId($clFilePath);
                $clMatExt = strtolower(pathinfo($clFilePath ?? '', PATHINFO_EXTENSION));
                $clIsPdf = ($clMatExt === 'pdf');
                $clHasRealVideo = !$clYtId && !empty($clFilePath) && !$clIsPdf && TenantFile::fileExists($tenant_id, $clFilePath);
                $clHasRealThumb = !empty($clThumbPath) && (str_starts_with($clThumbPath, 'http') || TenantFile::fileExists($tenant_id, $clThumbPath));
            ?>
                <div class="border border-slate-200/80 rounded-2xl p-6 bg-white shadow-xs mb-8">
                    <div class="flex flex-col lg:flex-row gap-6 items-center justify-between">
                        <div class="flex-1 min-w-0 w-full">
                            <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-md inline-block">
                                <?= dashboardSubjectEmoji($continueLearningCourse['subject']) ?> <?= htmlspecialchars($continueLearningCourse['subject'] ?: 'Курс') ?> · Урок <?= $clLessonNum ?>
                            </span>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mt-2">Твой следующий урок</p>
                            <h3 class="text-xl font-bold text-slate-900 mt-1 leading-snug">
                                <?= $continueLearningLesson ? htmlspecialchars($continueLearningLesson['title']) : htmlspecialchars($continueLearningCourse['title']) ?>
                            </h3>
                            <div class="flex items-center gap-2 mt-3 text-xs text-slate-500 flex-wrap">
                                <span>⏱ <?= $clDuration ?> мин видео</span>
                                <span>📝 Тест к уроку открыт</span>
                                <span>📄 Конспект</span>
                            </div>
                            <a href="<?= htmlspecialchars($clUrl) ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-semibold text-sm rounded-xl hover:bg-blue-700 transition-colors mt-5">
                                ▶ Начать урок
                            </a>
                        </div>

                        <div class="w-full lg:w-80 shrink-0">
                            <div class="w-full aspect-video rounded-xl bg-black relative overflow-hidden shadow-inner flex items-center justify-center">
                                <?php if ($clYtId): ?>
                                    <iframe class="w-full h-full"
                                            src="<?= VideoHelper::getEmbedUrl($clYtId) ?>"
                                            title="<?= htmlspecialchars($continueLearningLesson['title'] ?? 'Урок') ?>"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                            allowfullscreen>
                                    </iframe>
                                <?php elseif ($clHasRealVideo): ?>
                                    <video controls controlsList="nodownload" preload="metadata" playsinline class="w-full h-full object-contain"
                                           src="/file.php?type=material&id=<?= $clMatId ?><?= !$clHasRealThumb ? '#t=0.001' : '' ?>"
                                           <?= $clHasRealThumb ? 'poster="/file.php?type=preview&id=' . $clMatId . '"' : '' ?>>
                                        Ваш браузер не поддерживает воспроизведение видео.
                                    </video>
                                <?php elseif ($clHasRealThumb): ?>
                                    <a href="<?= htmlspecialchars($clUrl) ?>" class="w-full h-full relative group block">
                                        <img src="/file.php?type=preview&id=<?= $clMatId ?>" alt="Превью урока" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/30 group-hover:bg-black/20 flex items-center justify-center transition-colors">
                                            <div class="w-14 h-14 rounded-full bg-white/30 backdrop-blur-sm flex items-center justify-center group-hover:scale-110 transition-transform">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M8 5v14l11-7-11-7z"></path></svg>
                                            </div>
                                        </div>
                                        <span class="absolute bottom-2 right-3 text-xs font-medium text-white bg-black/60 px-1.5 py-0.5 rounded"><?= $clDuration ?>:00</span>
                                    </a>
                                <?php elseif ($clIsPdf): ?>
                                    <a href="/file.php?type=material&id=<?= $clMatId ?>" target="_blank" class="w-full h-full flex flex-col items-center justify-center text-center p-4 bg-slate-900 hover:bg-slate-850 transition-colors">
                                        <div class="w-12 h-12 rounded-full bg-red-500/20 text-red-400 flex items-center justify-center mb-2">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                        </div>
                                        <p class="text-xs font-semibold text-white">Учебный конспект (PDF)</p>
                                        <span class="text-[11px] text-slate-400 mt-1">Открыть документ ↗</span>
                                    </a>
                                <?php else: ?>
                                    <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M8 5v14l11-7-11-7z"></path></svg>
                                    </div>
                                    <span class="absolute bottom-2 right-3 text-xs font-medium text-white bg-black/50 px-1.5 py-0.5 rounded"><?= $clDuration ?>:00</span>
                                <?php endif; ?>
                            </div>
                            <div class="h-1.5 w-full bg-slate-100 rounded-full mt-3">
                                <div class="h-full rounded-full bg-blue-600" style="width: <?= $clProgress ?>%;"></div>
                            </div>
                            <p class="text-xs font-medium text-slate-500 mt-1.5">Программа курса: <?= $clProgress ?>%</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Grid: Мои курсы + Виджеты -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- Left: Мои курсы -->
                <div class="lg:col-span-3">
                    <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Мои курсы</h2>
                    <?php if (empty($coursesWithProgress)): ?>
                        <div class="border border-slate-200/80 rounded-xl bg-white py-6 text-center text-sm text-slate-500">
                            Нет активных курсов. <a href="/profile/courses.php" class="text-blue-600 hover:underline font-medium">Перейти в каталог курсов</a>
                        </div>
                    <?php else: ?>
                        <?php $gridColsClass = count($coursesWithProgress) === 1 ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2'; ?>
                        <div class="grid <?= $gridColsClass ?> gap-4">
                            <?php foreach ($coursesWithProgress as $c):
                                $statusLabels = ['active' => 'Активен', 'pending' => 'Ожидает оплаты', 'paused' => 'Приостановлен', 'cancelled' => 'Отменён'];
                                $statusLabel = $statusLabels[$c['status'] ?? ''] ?? 'Неактивен';
                            ?>
                                <div class="border border-slate-200/80 rounded-xl bg-white overflow-hidden flex flex-col justify-between">
                                    <div class="h-12 flex items-center justify-between px-4 shrink-0" style="background-color: <?= htmlspecialchars($c['color']) ?>;">
                                        <span class="text-lg"><?= dashboardSubjectEmoji($c['subject']) ?></span>
                                        <span class="text-[11px] font-semibold text-white bg-white/20 px-2 py-0.5 rounded-md"><?= htmlspecialchars($statusLabel) ?></span>
                                    </div>
                                    <div class="p-4 flex-1 flex flex-col">
                                        <p class="text-base font-bold text-slate-900 leading-snug">
                                            <?= htmlspecialchars($c['title']) ?>
                                        </p>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            <?= htmlspecialchars($c['classLevel'] ?: ($c['subject'] ?: 'Курс')) ?>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-2">
                                            <?= $c['total_materials'] ?> <?= ruPlural((int)$c['total_materials'], 'урок', 'урока', 'уроков') ?> ·
                                            <?= $c['total_tests'] ?> <?= ruPlural((int)$c['total_tests'], 'тест', 'теста', 'тестов') ?>
                                        </p>

                                        <div class="mt-3">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs text-slate-500"><?= $c['solved_materials'] ?>/<?= $c['total_materials'] ?> ур.</span>
                                                <span class="text-xs font-semibold text-slate-900"><?= $c['progress_percent'] ?>%</span>
                                            </div>
                                            <div class="w-full rounded-full overflow-hidden bg-slate-100" style="height: 4px;">
                                                <div class="h-full rounded-full" style="width: <?= $c['progress_percent'] ?>%; background-color: <?= htmlspecialchars($c['color']) ?>;"></div>
                                            </div>
                                            <p class="text-xs text-slate-400 mt-1.5">
                                                <?= $c['expires_formatted'] ? 'Доступен ' . htmlspecialchars($c['expires_formatted']) : 'Ожидает активации' ?>
                                            </p>
                                        </div>

                                        <a href="/profile/course.php?course_id=<?= $c['course_id'] ?>" class="text-xs font-semibold text-blue-600 hover:underline pt-2 mt-auto inline-block">
                                            Перейти в курс →
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right: Виджеты -->
                <div class="lg:col-span-2">
                    <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Ближайшие дедлайны</h2>
                    <div class="border border-slate-200/80 rounded-xl bg-white px-4 py-0.5 shadow-xs">
                        <?php if (empty($todos)): ?>
                            <div class="py-6 text-center text-sm text-slate-500">
                                Все текущие задачи выполнены!
                            </div>
                        <?php else: ?>
                            <?php foreach ($todos as $idx => $t):
                                $isLast = ($idx === count($todos) - 1);
                                if ($t['days_left'] === null) {
                                    $deadlineBadge = $t['badge'];
                                } elseif ($t['days_left'] <= 0) {
                                    $deadlineBadge = '⏳ Сегодня';
                                } else {
                                    $deadlineBadge = '⏳ Осталось ' . $t['days_left'] . ' ' . ruPlural((int)$t['days_left'], 'день', 'дня', 'дней');
                                }
                            ?>
                                <div class="flex items-center gap-3 py-3 <?= !$isLast ? 'border-b border-slate-100' : '' ?>">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-slate-900 leading-snug truncate"><?= htmlspecialchars($t['title']) ?></p>
                                        <p class="text-xs text-slate-400 mt-0.5 truncate"><?= htmlspecialchars($t['subtitle']) ?></p>
                                        <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($deadlineBadge) ?></p>
                                    </div>
                                    <a href="<?= htmlspecialchars($t['url']) ?>" class="text-xs font-semibold text-blue-600 hover:underline shrink-0">
                                        Пройти →
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="border border-slate-200/80 rounded-xl p-4 bg-white shadow-xs mt-4">
                        <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Помощь куратора</h2>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', (string)($tenant['phone'] ?? ''))) ?>" class="flex items-center gap-2 text-xs font-semibold text-slate-800 hover:text-blue-600 mb-3">
                            📞 <?= htmlspecialchars($tenant['phone'] ?: '+998 90 123-45-67') ?>
                        </a>
                        <a href="https://t.me/demoacademy" target="_blank" class="w-full py-2.5 bg-blue-600 text-white text-xs font-semibold rounded-lg flex items-center justify-center gap-2 hover:bg-blue-700 transition-colors">
                            ✈️ Написать в Telegram
                        </a>
                    </div>
                </div>
            </div>

            <div class="h-2"></div>
        </div>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>