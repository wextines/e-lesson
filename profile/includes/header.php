<?php
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// User initials helper
$userInitials = 'ST';
if (!empty($user['first_name'])) {
    $firstI = mb_substr($user['first_name'], 0, 1, 'UTF-8');
    $lastI = !empty($user['last_name']) ? mb_substr($user['last_name'], 0, 1, 'UTF-8') : '';
    $userInitials = mb_strtoupper($firstI . $lastI, 'UTF-8');
}
$userFullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if (empty($userFullName)) {
    $userFullName = 'Студент';
}
$userShortName = ($user['first_name'] ?? 'Студент') . (!empty($user['last_name']) ? ' ' . mb_substr($user['last_name'], 0, 1, 'UTF-8') . '.' : '');

$tenantName = Tenant::getName() ?: 'e-Lesson';
$tenantLogo = Tenant::getLogoUrl();
?>
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle ?? 'Личный кабинет') ?> — <?= htmlspecialchars($tenantName) ?></title>
    
    <!-- Google Fonts: Instrument Serif, Inter, DM Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Inter"', 'system-ui', 'sans-serif'],
                        display: ['"Instrument Serif"', 'Georgia', 'serif'],
                        mono: ['"DM Mono"', '"Courier New"', 'monospace'],
                    },
                    colors: {
                        canvas: '#F7F5F1',
                        navy: '#0D1B2A',
                        cobalt: {
                            DEFAULT: '#1B5EE8',
                            hover: '#1650CC',
                            active: '#1244B8',
                            light: '#EEF3FD'
                        },
                        surface: '#FAFAF8',
                        borderCol: '#E4E1DA',
                        slateBody: '#4A607A',
                        slateMeta: '#7A8899'
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --font-display: "Instrument Serif", Georgia, serif;
            --font-mono: "DM Mono", "Courier New", monospace;
            --font-sans: "Inter", system-ui, sans-serif;
            --color-canvas: #f7f5f1;
            --color-navy: #0d1b2a;
        }
        body {
            font-family: var(--font-sans);
            background-color: #F7F5F1;
            color: #0D1B2A;
            -webkit-font-smoothing: antialiased;
        }
        .font-display {
            font-family: var(--font-display);
        }
        .font-mono {
            font-family: var(--font-mono);
        }
        /* Hide scrollbars for clean UI */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="h-full overflow-hidden bg-[#F7F5F1] text-[#0D1B2A]">
<div class="flex h-full bg-[#F7F5F1]">
