<?php
require_once __DIR__ . '/lang.php';
if (!isset($pageTitle)) $pageTitle = __('admin_panel');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(get_admin_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(Tenant::getName()) ?></title>
    <link rel="stylesheet" href="/admin/includes/admin.css">

    <!-- Tailwind CSS (V0 UI Framework) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        corePlugins: { preflight: false }
      }
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

</head>
<body>
<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<button class="mobile-menu-btn" aria-label="Open sidebar" onclick="document.querySelector('.admin-sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('open')">
    <i data-lucide="menu"></i>
</button>
<div class="sidebar-overlay" onclick="document.querySelector('.admin-sidebar').classList.remove('open');this.classList.remove('open')"></div>
<main class="admin-content">
