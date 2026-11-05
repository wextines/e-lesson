    </div><!-- /flex-1 flex flex-col -->
</div><!-- /flex h-full -->

<?php include __DIR__ . '/mobilenav.php'; ?>

<script>
function toggleMobileSidebar() {
    const sidebar = document.getElementById('mobile-sidebar');
    const backdrop = document.getElementById('mobile-sidebar-backdrop');
    if (!sidebar || !backdrop) return;
    
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    }
}

function toggleUserDropdown() {
    const dropdown = document.getElementById('user-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

document.addEventListener('click', function(e) {
    const container = document.getElementById('user-menu-container');
    const dropdown = document.getElementById('user-dropdown');
    if (container && dropdown && !container.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>

</body>
</html>
