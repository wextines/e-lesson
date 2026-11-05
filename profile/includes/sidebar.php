<?php
$currentPage = $activeTab ?? 'dashboard';

$navItems = [
    [
        'id' => 'dashboard',
        'label' => 'Главная',
        'url' => '/profile/',
        'svg' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10"></path>'
    ],
    [
        'id' => 'courses',
        'label' => 'Мои курсы',
        'url' => '/profile/courses.php',
        'svg' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>'
    ],
    [
        'id' => 'tests',
        'label' => 'Тесты',
        'url' => '/profile/tests.php',
        'svg' => '<path d="M9 11l3 3L22 4 M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>'
    ],
    [
        'id' => 'profile',
        'label' => 'Профиль',
        'url' => '/profile/settings.php',
        'svg' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path>'
    ],
];
?>

<!-- Desktop Sidebar (Hidden on mobile < lg) -->
<div class="hidden lg:flex lg:w-52 xl:w-56 shrink-0 flex-col h-full">
    <aside class="flex flex-col h-full bg-[#FAFAF8] border-r border-[#E4E1DA]">
        <!-- Brand Header -->
        <div class="flex items-center gap-2.5 px-5 h-14 border-b border-[#E4E1DA] shrink-0">
            <?php if (!empty($tenantLogo)): ?>
                <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="Logo" class="w-7 h-7 rounded-lg object-contain">
            <?php else: ?>
                <div class="w-7 h-7 rounded-lg bg-[#1B5EE8] flex items-center justify-center shrink-0">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <rect x="1" y="1" width="5" height="5" rx="1" fill="white"></rect>
                        <rect x="8" y="1" width="5" height="5" rx="1" fill="white" fill-opacity="0.5"></rect>
                        <rect x="1" y="8" width="5" height="5" rx="1" fill="white" fill-opacity="0.5"></rect>
                        <rect x="8" y="8" width="5" height="5" rx="1" fill="white"></rect>
                    </svg>
                </div>
            <?php endif; ?>
            <span class="font-[var(--font-display)] text-[#0D1B2A] text-lg leading-none tracking-tight truncate"><?= htmlspecialchars($tenantName) ?></span>
        </div>

        <!-- Navigation items -->
        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
            <?php foreach ($navItems as $item): 
                $isActive = ($currentPage === $item['id']);
            ?>
                <a href="<?= htmlspecialchars($item['url']) ?>"
                   class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 text-left <?= $isActive ? 'bg-[#EEF3FD] text-[#1B5EE8]' : 'text-[#4A607A] hover:bg-[#F0EDE8] hover:text-[#0D1B2A]' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="<?= $isActive ? 'text-[#1B5EE8]' : 'text-[#7A8899]' ?>">
                        <?= $item['svg'] ?>
                    </svg>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- User Footer Strip -->
        <div class="px-3 pb-4 border-t border-[#E4E1DA] pt-4 shrink-0">
            <div class="flex items-center gap-3 px-2">
                <div class="w-8 h-8 rounded-full bg-[#1B5EE8] text-white text-xs font-semibold flex items-center justify-center shrink-0">
                    <?= htmlspecialchars($userInitials) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-[#0D1B2A] truncate"><?= htmlspecialchars($userFullName) ?></p>
                    <p class="text-[10px] text-[#7A8899] truncate"><?= !empty($user['class']) ? htmlspecialchars($user['class'] . ' класс') : 'Студент' ?></p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <a href="/profile/settings.php" title="Настройки" class="w-7 h-7 flex items-center justify-center rounded-md text-[#7A8899] hover:text-[#0D1B2A] hover:bg-[#F0EDE8] transition-colors">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16z M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"></path>
                        </svg>
                    </a>
                    <a href="/login/logout.php" title="Выйти" class="w-7 h-7 flex items-center justify-center rounded-md text-[#7A8899] hover:text-[#DC2626] hover:bg-[#FEE2E2] transition-colors">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </aside>
</div>

<!-- Mobile Slide-over Drawer (Shown on toggle) -->
<div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-black/20 z-40 hidden lg:hidden transition-opacity" onclick="toggleMobileSidebar()"></div>
<div id="mobile-sidebar" class="fixed left-0 top-0 bottom-0 w-64 bg-[#FAFAF8] z-50 flex flex-col shadow-xl -translate-x-full transition-transform duration-200 ease-in-out lg:hidden">
    <div class="flex items-center gap-2.5 px-5 h-14 border-b border-[#E4E1DA] shrink-0">
        <?php if (!empty($tenantLogo)): ?>
            <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="Logo" class="w-7 h-7 rounded-lg object-contain">
        <?php else: ?>
            <div class="w-7 h-7 rounded-lg bg-[#1B5EE8] flex items-center justify-center shrink-0">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <rect x="1" y="1" width="5" height="5" rx="1" fill="white"></rect>
                    <rect x="8" y="1" width="5" height="5" rx="1" fill="white" fill-opacity="0.5"></rect>
                    <rect x="1" y="8" width="5" height="5" rx="1" fill="white" fill-opacity="0.5"></rect>
                    <rect x="8" y="8" width="5" height="5" rx="1" fill="white"></rect>
                </svg>
            </div>
        <?php endif; ?>
        <span class="font-[var(--font-display)] text-[#0D1B2A] text-lg leading-none tracking-tight truncate"><?= htmlspecialchars($tenantName) ?></span>
        <button onclick="toggleMobileSidebar()" class="ml-auto text-[#7A8899] hover:text-[#0D1B2A] p-1.5">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6L6 18 M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <?php foreach ($navItems as $item): 
            $isActive = ($currentPage === $item['id']);
        ?>
            <a href="<?= htmlspecialchars($item['url']) ?>"
               class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 text-left <?= $isActive ? 'bg-[#EEF3FD] text-[#1B5EE8]' : 'text-[#4A607A] hover:bg-[#F0EDE8] hover:text-[#0D1B2A]' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="<?= $isActive ? 'text-[#1B5EE8]' : 'text-[#7A8899]' ?>">
                    <?= $item['svg'] ?>
                </svg>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="px-3 pb-6 border-t border-[#E4E1DA] pt-4 shrink-0">
        <div class="flex items-center gap-3 px-2 mb-3">
            <div class="w-8 h-8 rounded-full bg-[#1B5EE8] text-white text-xs font-semibold flex items-center justify-center shrink-0">
                <?= htmlspecialchars($userInitials) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-[#0D1B2A] truncate"><?= htmlspecialchars($userFullName) ?></p>
                <p class="text-[10px] text-[#7A8899] truncate"><?= !empty($user['class']) ? htmlspecialchars($user['class'] . ' класс') : 'Студент' ?></p>
            </div>
        </div>
        <a href="/login/logout.php" class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-[#DC2626] hover:bg-[#FEE2E2] rounded-lg transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9"></path>
            </svg>
            <span>Выйти из аккаунта</span>
        </a>
    </div>
</div>
