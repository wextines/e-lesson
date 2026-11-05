<!DOCTYPE html>
<html lang="uz" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            <?php
            require_once __DIR__ . '/config.php';
            echo Tenant::getBrandingCSS();
            ?>
            --text-color: #e6f1ff;
            --text-secondary: #8892b0;
            --card-bg: #112240;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --paid-color: #64ffda;
            --unpaid-color: #ff6b6b;
            --success-color: #2b8a3e;
            --error-color: #c92a2a;
            --warning-color: #e67700;
            --shadow: 0 8px 10px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 8px;
            --menu-links-bottom: rgba(255, 255, 255, 0.135);
        }

        [data-theme="light"] {
            --primary-color: #f8fafc;
            --primary-color-blur: #f9fafbc3;
            --secondary-color: #f1f5f9;
            --accent-color: #0d9488;
            --text-color: rgba(30, 41, 59, 0.83);
            --text-secondary: #64748b;
            --card-bg: #ffffff;
            --white: #ffffff;
            --light-gray: #e2e8f0;
            --paid-color: #0d9488;
            --unpaid-color: #dc2626;
            --shadow: 0 8px 10px rgba(0, 0, 0, 0.05);
            --menu-links-bottom: rgba(255, 255, 255, 0.386);
            --transition: all 0.3s ease;
        }

        html {
            scroll-behavior: smooth;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background-color: var(--primary-color);
            color: var(--text-color);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .no-scroll {
            overflow: hidden;
        }

        h1,
        h2,
        h3,
        h4 {
            font-family: "Space Grotesk", sans-serif;
            font-weight: 700;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Навигационная панель */
        .main-nav {
            font-family: "Quicksand", sans-serif;
            font-optical-sizing: auto;
            font-weight: 700;
            font-style: normal;
            background-color: var(--primary-color);
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 8px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            cursor: pointer;
        }

        .logo-container img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .logo-text {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--accent-color);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-links a {
            font-family: "Quicksand", sans-serif;
            font-optical-sizing: auto;
            font-weight: 700;
            font-style: normal;
            font-size: 1rem;
            transition: var(--transition);
            position: relative;
            color: var(--text-color);
        }

        .nav-links a:hover {
            color: var(--accent-color);
        }


        .nav-button {
            font-family: "Quicksand", sans-serif;
            font-optical-sizing: auto;
            font-weight: 700;
            font-style: normal;
            background-color: transparent;
            color: var(--accent-color);
            padding: 0.6rem 1.2rem;
            border-radius: 5px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-button:hover {
            background-color: rgba(100, 255, 218, 0.1);
        }

        .mobile-menu-btn {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Мобильное меню */
        .mobile-menu {
            font-family: "Quicksand", sans-serif;
            font-optical-sizing: auto;
            font-weight: 700;
            font-style: normal;
            position: fixed;
            top: 0;
            right: -100%;
            width: 80%;
            max-width: 350px;
            height: 100vh;
            background-color: var(--primary-color-blur);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1100;
            transition: right 0.3s ease;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }

        .mobile-menu.active {
            right: 0;
        }

        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.564);
            z-index: 1099;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .mobile-menu-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .close-menu-btn {
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .mobile-menu-links {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            flex-grow: 1;
        }

        .mobile-menu-links a {
            font-weight: 500;
            font-size: 1.1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--menu-links-bottom);
            color: var(--text-color);
        }

        .mobile-menu-button {
            margin-top: 0.5rem;
            background-color: var(--accent-color);
            color: var(--primary-color);
            border: none;
            padding: 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Quicksand", sans-serif;
            font-optical-sizing: auto;
            font-style: normal;
            font-weight: 700;
            font-size: 1rem;
            gap: 0.5rem;
        }

        .mobile-menu-button:hover {
            background-color: #52e0c4;
        }

        .lang-dropdown {
            position: relative;
            font-family: "Quicksand", sans-serif;
        }

        .lang-toggle {
            margin: auto -1rem;
            background-color: transparent;
            color: var(--text-color);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 1rem;
            transition: var(--transition);
        }

        .lang-toggle:hover {
            background-color: rgba(100, 255, 218, 0.1);
        }

        .lang-options {
            position: absolute;
            top: 110%;
            right: 0;
            background-color: var(--secondary-color);
            border: none;
            border-radius: 5px;
            display: none;
            flex-direction: column;
            z-index: 999;
            overflow: hidden;
        }

        .lang-options button {
            background: none;
            color: var(--text-color);
            border: none;
            padding: 0.6rem 1rem;
            text-align: left;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.2s ease;
        }

        .lang-options button:hover {
            background-color: rgba(100, 255, 218, 0.1);
        }

        /* Show dropdown on hover (desktop) */
        .lang-dropdown:hover .lang-options {
            display: flex;
        }

        .mobile-lang-mini {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-top: 1rem;
        }

        .lang-icon-btn {
            background-color: transparent;
            color: var(--text-color);
            border: 1px solid var(--menu-links-bottom);
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .lang-icon-btn:hover {
            background-color: rgba(100, 255, 218, 0.1);
        }

        .mini-lang-options {
            display: none;
            position: absolute;
            top: 110%;
            left: 0;
            background-color: var(--primary-color);
            border: 1px solid var(--menu-links-bottom);
            border-radius: 6px;
            flex-direction: column;
            overflow: hidden;
            z-index: 999;
            width: max-content;
            min-width: 70px;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
        }

        .mini-lang-options.show {
            display: flex;
            max-height: 300px;
            /* достаточно для двух кнопок */
            opacity: 1;
        }

        .mini-lang-options button {
            background-color: transparent;
            color: var(--text-color);
            border: none;
            padding: 0.4rem 0.7rem;
            text-align: left;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .mini-lang-options button:hover {
            background-color: rgba(100, 255, 218, 0.1);
        }

        .hrBottom {
            margin-bottom: 7vw;
        }

        /* Переключатель темы */
        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: var(--text-color);
            transition: transform 0.3s ease, var(--transition);
            position: relative;
        }

        .theme-toggle:hover {
            background-color: rgba(100, 255, 218, 0.1);
        }

        .theme-toggle:active {
            transform: scale(0.9);
        }

        .theme-toggle i {
            position: absolute;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .icon-sun {
            color: var(--accent-color);
        }

        .icon-moon {
            opacity: 0;
            transform: rotate(90deg);
        }

        .mini-lang-options button img {
            width: 24px;
            height: 18px;
            border-radius: 1px;
            margin-right: 6px;
            object-fit: cover;
            vertical-align: middle;
        }

        .lang-flag {
            width: 2em;
            height: auto;
            aspect-ratio: 4 / 2.5;
            border-radius: 1px;
            object-fit: cover;
            margin-right: 6px;
            vertical-align: middle;
        }

        .lang-options button {
            display: flex;
            align-items: center;
            padding: 6px 10px;
            font-size: 1rem;
        }

        .lang-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
        }



        [data-theme="light"] .icon-sun {
            opacity: 0;
            transform: rotate(-90deg);
        }

        [data-theme="light"] .icon-moon {
            opacity: 1;
            transform: rotate(0);
            color: var(--accent-color);
        }

        [data-theme="light"] .nav-links a {
            color: var(--accent-color);
        }

        @media (max-width: 940px) {
            .hrBottom {
                margin-bottom: 12vw;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .hrBottom {
                margin-bottom: 16vw;
            }
        }

        @media (max-width: 400px) {
            .hrBottom {
                margin-bottom: 20vw;
            }

            .mobile-menu .logo-text {
                font-size: 1.1rem;
            }
        }
    </style>
</head>

<body>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300..700&display=swap" rel="stylesheet">

    <!-- Навигация -->
    <nav class="main-nav">
        <div class="nav-container">
            <div class="logo-container" onclick="window.location.href='/'">
                <img src="<?= htmlspecialchars(Tenant::getLogoUrl() ?: '/img/homepage/logo.png?v=4') ?>" alt="Logo">
                <span class="logo-text"><?= htmlspecialchars(Tenant::getName()) ?></span>
            </div>
            <div class="nav-links">
                <button class="theme-toggle">
                    <i class="fas fa-moon icon-moon"></i>
                    <i class="fas fa-sun icon-sun"></i>
                </button>
                <!-- Desktop language selector -->
                <div class="lang-dropdown">
                    <button class="lang-toggle">
                        <span id="current-lang-flag">
                            <img src="https://flagcdn.com/uz.svg" class="lang-flag" alt="Flag" />
                        </span>
                        <span id="current-lang">UZ</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="lang-options">
                        <button data-lang="uz" data-flag="uz">
                            <img src="https://flagcdn.com/uz.svg" class="lang-flag" alt="UZ Flag" />
                            UZ
                        </button>
                        <button data-lang="ru" data-flag="ru">
                            <img src="https://flagcdn.com/ru.svg" class="lang-flag" alt="RU Flag" />
                            RU
                        </button>
                        <button data-lang="en" data-flag="gb">
                            <img src="https://flagcdn.com/gb.svg" class="lang-flag" alt="EN Flag" />
                            EN
                        </button>
                        <button data-lang="fr" data-flag="fr">
                            <img src="https://flagcdn.com/fr.svg" class="lang-flag" alt="FR Flag" />
                            FR
                        </button>
                    </div>
                </div>

                <?php
                $requestUri = $_SERVER['REQUEST_URI'];
                $is_profile_page = preg_match('#^/profile/?#', $requestUri);

                if ($is_profile_page && $is_own_profile): ?>
                    <!-- Кнопка "Редактировать профиль" -->
                    <a id="toggleInstall" class="nav-button" style="display: none;">
                        <i class="fas fa-download"></i>
                        <span class="desktop-text" data-i18n="nav_download">Yuklash</span>
                    </a>
                    <a href="/profile/edit" class="nav-button">
                        <i class="fas fa-edit"></i>
                        <span class="desktop-text" data-i18n="nav_edit_profile">Tahrirlash</span>
                    </a>
                <?php else: ?>
                    <!-- Кнопка "Профиль" -->
                    <a href="/profile/" class="nav-button">
                        <i class="fas fa-user"></i>
                        <span class="desktop-text" data-i18n="nav_profile">Profil</span>
                    </a>
                <?php endif; ?>

                <a href="/" class="nav-button">
                    <i class="fas fa-home"></i>
                    <span class="desktop-text" data-i18n="nav_home">Bosh sahifa</span>
                </a>
            </div>
            <div class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="toggleMobileMenu()"></div>

    <!-- Мобильное меню -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <div class="logo-container" onclick="window.location.href='/'">
                <img src="<?= htmlspecialchars(Tenant::getLogoUrl() ?: '/img/homepage/logo.png?v=4') ?>" alt="Logo">
                <span class="logo-text"><?= htmlspecialchars(Tenant::getName()) ?></span>
            </div>
            <button class="theme-toggle">
                <i class="fas fa-moon icon-moon"></i>
                <i class="fas fa-sun icon-sun"></i>
            </button>
            <button class="close-menu-btn" onclick="toggleMobileMenu()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mobile-menu-links">
            <!-- Mobile language selector -->
            <div class="mobile-lang-mini">
                <button class="lang-icon-btn" onclick="toggleMiniLangDropdown()">
                    <span id="current-lang-flag-mini">
                        <img src="https://flagcdn.com/uz.svg" class="lang-flag" alt="UZ Flag" />
                    </span>
                    <span id="current-lang-mini">UZ</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="mini-lang-options" id="miniLangOptions">
                    <button data-lang="uz">
                        <img src="https://flagcdn.com/uz.svg" class="lang-flag" alt="UZ Flag" /> UZ
                    </button>
                    <button data-lang="ru">
                        <img src="https://flagcdn.com/ru.svg" class="lang-flag" alt="RU Flag" /> RU
                    </button>
                    <button data-lang="en">
                        <img src="https://flagcdn.com/gb.svg" class="lang-flag" alt="EN Flag" /> EN
                    </button>
                    <button data-lang="fr">
                        <img src="https://flagcdn.com/fr.svg" class="lang-flag" alt="FR Flag" /> FR
                    </button>
                </div>
            </div>



            <a href="/" onclick="toggleMobileMenu()">
                <i class="fas fa-home"></i>
                <span data-i18n="nav_home">Bosh sahifa</span></a>
            <?php if ($is_profile_page && $is_own_profile): ?>
                <a href="/profile/edit" onclick="toggleMobileMenu()">
                    <i class="fas fa-edit"></i>
                    <span data-i18n="nav_edit_profile">Tahrirlash</span></a>
                <a id="mobileInstallBtn" style="display: none;" onclick="toggleMobileMenu()">
                    <i class="fas fa-download"></i>
                    <span data-i18n="nav_download">Yuklash</span></a>
            <?php else: ?>
                <a href="/profile/" onclick="toggleMobileMenu()">
                    <i class="fas fa-user"></i>
                    <span data-i18n="nav_profile">Profil</span></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="hrBottom"></div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function getCSSVar(name) {
            return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        }

        function updateThemeColorFromCSS() {
            const themeColor = getCSSVar('--primary-color');
            const metaTag = document.querySelector('meta[name="theme-color"]');
            if (metaTag && themeColor) metaTag.setAttribute('content', themeColor);
        }

        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeColorFromCSS();
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeColorFromCSS();
        }

        function isPWAInstalled() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        }

        function isIOS() {
            return /iphone|ipad|ipod/i.test(navigator.userAgent) && /safari/i.test(navigator.userAgent);
        }

        function toggleMobileMenu() {
            const mobileMenu = document.getElementById("mobileMenu");
            const overlay = document.getElementById("mobileMenuOverlay");
            mobileMenu.classList.toggle("active");
            overlay.classList.toggle("active");
            document.body.classList.toggle("no-scroll");
        }

        document.addEventListener('DOMContentLoaded', () => {
            initTheme();

            $('.theme-toggle').on('click', toggleTheme);

            const $installBtn = $('#toggleInstall');
            const $mobileInstallBtn = $('#mobileInstallBtn');
            let deferredPrompt = null;

            // Если приложение уже работает как PWA
            if (isPWAInstalled()) {
                console.log('PWA уже установлено — можно выполнять логику для standalone');
                $installBtn.hide();
                $mobileInstallBtn.hide();
                return;
            }

            // Отлавливаем событие beforeinstallprompt для Android/Chrome
            $(window).on('beforeinstallprompt', function(e) {
                e.preventDefault();
                deferredPrompt = e.originalEvent;
                $installBtn.show();
                $mobileInstallBtn.show();
            });

            // Если iOS и в браузере Safari
            if (isIOS()) {
                $installBtn.show();
                $mobileInstallBtn.show();
            }

            // Обработка нажатия на кнопку установки
            $installBtn.on('click', function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(() => deferredPrompt = null);
                } else if (isIOS()) {
                    alert('Чтобы установить приложение:\n1. Нажмите "Поделиться" внизу Safari\n2. Выберите "На экран Домой"\n3. Подтвердите');
                } else {
                    $installBtn.hide();
                    $mobileInstallBtn.hide();
                }
            });

            $mobileInstallBtn.on('click', function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(() => deferredPrompt = null);
                } else if (isIOS()) {
                    alert('Чтобы установить приложение:\n1. Нажмите "Поделиться" внизу Safari\n2. Выберите "На экран Домой"\n3. Подтвердите');
                } else {
                    $installBtn.hide();
                    $mobileInstallBtn.hide();
                }
            });
        });
    </script>
    <script src="/js/i18n.js"></script>
</body>

</html>