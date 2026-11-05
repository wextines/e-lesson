<?php
$currentPage = $activeTab ?? 'dashboard';

$mobileTabs = [
    ['id' => 'dashboard', 'label' => 'Главная', 'url' => '/profile/', 'svg' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10"></path>'],
    ['id' => 'courses', 'label' => 'Мои', 'url' => '/profile/courses.php', 'svg' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>'],
    ['id' => 'tests', 'label' => 'Тесты', 'url' => '/profile/tests.php', 'svg' => '<path d="M9 11l3 3L22 4 M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>'],
    ['id' => 'profile', 'label' => 'Профиль', 'url' => '/profile/settings.php', 'svg' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path>'],
];
?>

<!-- Mobile Bottom Fixed Navigation (Hidden on lg+) -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-[#FAFAF8] border-t border-[#E4E1DA] z-30" style="padding-bottom: env(safe-area-inset-bottom, 0px);">
    <div class="flex">
        <?php foreach ($mobileTabs as $tab): 
            $isActive = ($currentPage === $tab['id']);
        ?>
            <a href="<?= htmlspecialchars($tab['url']) ?>"
               class="flex-1 flex flex-col items-center justify-center py-2.5 gap-1 text-[10px] font-medium transition-colors <?= $isActive ? 'text-[#1B5EE8]' : 'text-[#7A8899] hover:text-[#0D1B2A]' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <?= $tab['svg'] ?>
                </svg>
                <span class="truncate max-w-[56px] text-center"><?= htmlspecialchars($tab['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
