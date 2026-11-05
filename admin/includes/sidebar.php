<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$tenant = Tenant::get();
$tenantName = htmlspecialchars($tenant['name'] ?? 'Demo Academy');
$logoUrl = Tenant::getLogoUrl();
$adminUsername = htmlspecialchars($_SESSION['admin_username'] ?? 'admin');
$sidebarCollapsed = $_COOKIE['sidebar_collapsed'] ?? '0';
$isTeacher = ($_SESSION['admin_role'] ?? '') === 'teacher';

function isActive($page = null, $dir = null) {
    global $currentPage, $currentDir;
    if ($page !== null && $currentPage === $page) return true;
    if ($dir !== null && $currentDir === $dir) return true;
    if ($dir === 'courses' && in_array($currentDir, ['materials', 'tests', 'questions'])) return true;
    return false;
}

$current_lang = get_admin_lang();

// The online receipt queue is hidden by default: most centers take payment at the desk,
// and an empty moderation inbox is pure noise for them. It reappears when the center has
// opted into online receipts, or whenever unprocessed receipts still exist — so turning
// the setting off can never strand a student's pending receipt somewhere unreachable.
$sidebarPendingReceipts = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND status = 'pending' AND deleted_at IS NULL");
        $s->execute([Tenant::getId()]);
        $sidebarPendingReceipts = (int)$s->fetchColumn();
    } catch (PDOException $e) {
        $sidebarPendingReceipts = 0;
    }
}
$showReceiptsNav = Tenant::getConfig('allow_receipt_uploads', '0') === '1' || $sidebarPendingReceipts > 0;
?>
<style>
/* ── New Sidebar: collapse/expand (desktop only) ── */
@media (min-width: 769px) {
body.sidebar-collapsed .admin-sidebar { width: var(--sidebar-collapsed-width) !important; }
body.sidebar-collapsed .admin-content { margin-left: var(--sidebar-collapsed-width); }
body.sidebar-collapsed .sidebar-text { display: none !important; }
body.sidebar-collapsed .sidebar-link { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; }
body.sidebar-collapsed .lang-switcher { flex-direction: column !important; }
body.sidebar-collapsed .lang-btn { width: 100% !important; text-align: center !important; }
body.sidebar-collapsed .sidebar-header { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; }
body.sidebar-collapsed .collapse-icon-expanded { display: none !important; }
body.sidebar-collapsed .collapse-icon-collapsed { display: block !important; }
}
.collapse-icon-collapsed { display: none; }
@media (max-width: 768px) {
.sidebar-collapse-btn { display: none !important; }
}

.admin-sidebar {
    overflow: visible !important;
    box-shadow: 2px 0 8px -2px rgba(15, 23, 42, 0.06), 1px 0 3px 0 rgba(15, 23, 42, 0.03);
}
.sidebar-collapse-btn {
    position: absolute;
    top: 28px;
    right: -12px;
    width: 24px;
    height: 24px;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1), 0 1px 2px rgba(15, 23, 42, 0.06);
    cursor: pointer;
    outline: none;
    transition: all 0.2s ease;
}
.sidebar-collapse-btn:hover {
    background: #f8fafc;
    color: #0f172a;
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.12);
}
</style>

<aside class="admin-sidebar fixed inset-y-0 left-0 z-50 flex w-[var(--sidebar-width)] flex-col border-r border-slate-200 bg-white transition-all duration-300">
    <!-- Collapse/Expand Button -->
    <button type="button" onclick="toggleSidebar()" aria-label="Toggle sidebar" class="sidebar-collapse-btn">
        <i data-lucide="chevron-left" class="size-3 collapse-icon-expanded"></i>
        <i data-lucide="chevron-right" class="size-3 collapse-icon-collapsed"></i>
    </button>

    <!-- Branding Section -->
    <div class="sidebar-header flex shrink-0 items-center justify-between gap-3 px-6 pb-2 pt-6">
        <div class="flex items-center gap-3 min-w-0">
            <?php if ($logoUrl): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="size-10 rounded-xl object-contain shrink-0">
            <?php else: ?>
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm">
                    <i data-lucide="graduation-cap" class="size-[22px]"></i>
                </div>
            <?php endif; ?>
            <div class="sidebar-text flex flex-col justify-center overflow-hidden">
                <p class="truncate text-[15px] font-semibold leading-tight text-slate-900"><?= $tenantName ?></p>
                <p class="truncate text-[13px] leading-tight text-slate-500 mt-0.5"><?= $adminUsername ?></p>
            </div>
        </div>
        <button type="button" onclick="closeMobileSidebar()" aria-label="Close sidebar" class="md:hidden flex size-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors border-none bg-transparent cursor-pointer">
            <i data-lucide="x" class="size-5"></i>
        </button>
    </div>

    <!-- Navigation Section -->
    <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-4 py-4">
        <?php if (!$isTeacher): ?>
            <a href="/admin/dashboard.php" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive('dashboard.php') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
                <i data-lucide="layout-dashboard" class="size-5 shrink-0 <?= isActive('dashboard.php') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
                <span class="sidebar-text truncate"><?= htmlspecialchars(__('nav_dashboard')) ?></span>
            </a>
            <a href="/admin/students/" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive(null, 'students') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
                <i data-lucide="users" class="size-5 shrink-0 <?= isActive(null, 'students') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
                <span class="sidebar-text truncate"><?= htmlspecialchars(__('nav_students')) ?></span>
            </a>
        <?php endif; ?>
        <a href="/admin/courses/" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive(null, 'courses') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
            <i data-lucide="book-open" class="size-5 shrink-0 <?= isActive(null, 'courses') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
            <span class="sidebar-text truncate"><?= htmlspecialchars(__('nav_courses')) ?></span>
        </a>
        <a href="/admin/groups/index.php" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive(null, 'groups') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
            <i data-lucide="users-round" class="size-5 shrink-0 <?= isActive(null, 'groups') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
            <span class="sidebar-text truncate"><?= htmlspecialchars(__($isTeacher ? 'adm_my_groups' : 'adm_groups')) ?></span>
        </a>
        <a href="/admin/attendance/index.php" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive(null, 'attendance') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
            <i data-lucide="calendar-check" class="size-5 shrink-0 <?= isActive(null, 'attendance') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
            <span class="sidebar-text truncate"><?= htmlspecialchars(__('adm_attendance')) ?></span>
        </a>
        <a href="/admin/adminBooks/" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive(null, 'adminBooks') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
            <i data-lucide="library" class="size-5 shrink-0 <?= isActive(null, 'adminBooks') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
            <span class="sidebar-text truncate"><?= htmlspecialchars(__('nav_books')) ?></span>
        </a>
        <?php if (!$isTeacher): ?>
            <!-- Финансы -->
            <div class="sidebar-text px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('adm_finance')) ?></div>
            <a href="/admin/finance/index.php" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive('index.php') && $currentDir === 'finance' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
                <i data-lucide="wallet" class="size-5 shrink-0 <?= isActive('index.php') && $currentDir === 'finance' ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
                <span class="sidebar-text truncate"><?= htmlspecialchars(__('adm_cashbox')) ?></span>
            </a>
            <a href="/admin/finance/debtors.php" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive('debtors.php') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
                <i data-lucide="alert-circle" class="size-5 shrink-0 <?= isActive('debtors.php') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
                <span class="sidebar-text truncate"><?= htmlspecialchars(__('adm_debtors')) ?></span>
            </a>
            <?php if ($showReceiptsNav): ?>
                <a href="/admin/payments/" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive(null, 'payments') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i data-lucide="credit-card" class="size-5 shrink-0 <?= isActive(null, 'payments') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
                    <span class="sidebar-text truncate"><?= htmlspecialchars(__('adm_receipts_review')) ?></span>
                    <?php if ($sidebarPendingReceipts > 0): ?>
                        <span class="sidebar-text ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800"><?= $sidebarPendingReceipts ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <!-- Bottom Section -->
    <div class="flex shrink-0 flex-col px-4 pb-6 pt-2">
        <!-- Language Switcher -->
        <div class="lang-switcher flex w-full items-center gap-1 rounded-[10px] bg-slate-100 p-1 mb-4">
            <?php foreach (['ru'=>'RU', 'uz'=>'UZ', 'en'=>'EN'] as $code => $label): ?>
                <button onclick="setAdminLang('<?= $code ?>')" class="lang-btn flex-1 appearance-none rounded-md border-none m-0 py-1.5 text-[12px] font-bold outline-none cursor-pointer transition-colors <?= $current_lang === $code ? 'bg-white text-slate-900 shadow-sm' : 'bg-transparent text-slate-500 hover:text-slate-900' ?>">
                    <?= $label ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Divider -->
        <div class="h-px w-full bg-slate-200 mb-4"></div>

        <!-- Settings & Logout -->
        <div class="flex flex-col gap-0.5">
            <?php if (!$isTeacher): ?>
                <a href="/admin/settings/" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline transition-colors <?= isActive(null, 'settings') ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' ?>">
                    <i data-lucide="settings" class="size-5 shrink-0 <?= isActive(null, 'settings') ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-500' ?>"></i>
                    <span class="sidebar-text truncate"><?= htmlspecialchars(__('nav_settings')) ?></span>
                </a>
            <?php endif; ?>
            <a href="/admin/php/logout.php" onclick="return confirm('<?= htmlspecialchars(__('nav_logout')) ?>?')" class="sidebar-link group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[14px] font-medium no-underline text-slate-500 transition-colors hover:bg-red-50 hover:text-red-600">
                <i data-lucide="log-out" class="size-5 shrink-0 text-slate-400 group-hover:text-red-500"></i>
                <span class="sidebar-text truncate"><?= htmlspecialchars(__('nav_logout')) ?></span>
            </a>
        </div>
    </div>
</aside>

<script>
function setAdminLang(lang) {
    document.cookie = 'admin_lang=' + lang + ';path=/;max-age=31536000';
    window.location.reload();
}
function toggleSidebar(){
    document.body.classList.toggle('sidebar-collapsed');
    document.cookie='sidebar_collapsed='+(document.body.classList.contains('sidebar-collapsed')?'1':'0')+';path=/;max-age=31536000';
    // Re-render lucide icons for the new chevron
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
function closeMobileSidebar() {
    var sb = document.querySelector('.admin-sidebar');
    var ov = document.querySelector('.sidebar-overlay');
    if (sb) sb.classList.remove('open');
    if (ov) ov.classList.remove('open');
}
(function(){
    var c=document.cookie.match(/sidebar_collapsed=([^;]+)/);
    if(c&&c[1]==='1')document.body.classList.add('sidebar-collapsed');
})();
</script>
