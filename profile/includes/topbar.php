<!-- TopBar Header -->
<header class="h-14 border-b border-[#E4E1DA] bg-[#FAFAF8] px-5 flex items-center gap-4 shrink-0 z-10">
    <button onclick="toggleMobileSidebar()" class="lg:hidden w-8 h-8 flex items-center justify-center rounded-md text-[#7A8899] hover:text-[#0D1B2A] hover:bg-[#F0EDE8]">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12h18 M3 6h18 M3 18h18"></path>
        </svg>
    </button>
    
    <h1 class="text-sm font-semibold text-[#0D1B2A] flex-1 truncate"><?= htmlspecialchars($topBarTitle ?? $pageTitle ?? 'Главная') ?></h1>
    
    <div class="flex items-center gap-2 shrink-0">
        <!-- Notification bell -->
        <button class="relative w-8 h-8 flex items-center justify-center rounded-md text-[#7A8899] hover:text-[#0D1B2A] hover:bg-[#F0EDE8] transition-colors" title="Уведомления">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9 M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-[#1B5EE8]"></span>
        </button>
        
        <!-- User Dropdown Menu -->
        <div class="relative" id="user-menu-container">
            <button onclick="toggleUserDropdown()" class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg hover:bg-[#F0EDE8] transition-colors">
                <div class="w-6 h-6 rounded-full bg-[#1B5EE8] text-white text-[10px] font-semibold flex items-center justify-center shrink-0">
                    <?= htmlspecialchars($userInitials) ?>
                </div>
                <span class="text-xs font-medium text-[#0D1B2A] hidden sm:block"><?= htmlspecialchars($userShortName) ?></span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="text-[#7A8899] hidden sm:block">
                    <path d="M6 9l6 6 6-6"></path>
                </svg>
            </button>
            
            <div id="user-dropdown" class="hidden absolute right-0 top-10 w-44 bg-[#FAFAF8] border border-[#E4E1DA] rounded-xl shadow-lg z-50 py-1.5">
                <a href="/profile/settings.php" class="block w-full text-left px-3.5 py-2 text-xs text-[#4A607A] hover:bg-[#F0EDE8] hover:text-[#0D1B2A] transition-colors">Профиль</a>
                <a href="/profile/settings.php#security" class="block w-full text-left px-3.5 py-2 text-xs text-[#4A607A] hover:bg-[#F0EDE8] hover:text-[#0D1B2A] transition-colors">Настройки</a>
                <div class="h-px bg-[#E4E1DA] my-1"></div>
                <a href="/login/logout.php" class="block w-full text-left px-3.5 py-2 text-xs text-[#DC2626] hover:bg-[#FEE2E2] transition-colors">Выйти</a>
            </div>
        </div>
    </div>
</header>
