<?php
session_start();
require_once __DIR__ . '/php/config.php';

// The platform's own apex production domain (no tenant subdomain, no custom
// domain match) gets e-Lesson's own marketing homepage instead of falling
// back to tenant #1's branded site. Scoped to the exact production host so
// local dev (localhost) and every tenant subdomain/custom domain keep their
// existing behavior untouched.
$__apexHost = strtolower(trim(explode(':', $_SERVER['HTTP_HOST'] ?? '', 2)[0]));
if (in_array($__apexHost, ['e-lesson.uz', 'www.e-lesson.uz'], true)) {
    require __DIR__ . '/marketing/home.php';
    exit;
}

// Tenant #3 (Demo Academy) renders the modern React landing from project/dist
if (Tenant::getId() === 3) {
    $distIndex = __DIR__ . '/project/dist/index.html';
    if (file_exists($distIndex)) {
        readfile($distIndex);
        exit;
    }
}

$tenantName = Tenant::getName();
$tenantPrimary = Tenant::getPrimaryColor();
$tenantSecondary = Tenant::getSecondaryColor();
$tenantAccent = Tenant::getAccentColor();
$tenantLogo = Tenant::getLogoUrl() ?: '/img/homepage/logo.png?v=4';
$tenantEmail = Tenant::getEmail();
$tenantPhone = Tenant::getPhone();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Tenant::getDefaultLanguage()) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($tenantName) ?></title>
    <link rel="manifest" href="/manifest.json?v=4">
    <meta name="theme-color" content="<?= htmlspecialchars($tenantPrimary) ?>">
    <link rel="stylesheet" href="css/index.css?v=895">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="<?= htmlspecialchars($tenantLogo) ?>">
    <link rel="apple-touch-icon" href="/img/homepage/icon.png?v=5">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($tenantPrimary) ?>;
            --primary-color-blur: <?= htmlspecialchars($tenantPrimary) ?>a8;
            --secondary-color: <?= htmlspecialchars($tenantSecondary) ?>;
            --accent-color: <?= htmlspecialchars($tenantAccent) ?>;
            --accent-color-hover: <?= htmlspecialchars($tenantAccent) ?>e6;
        }
    </style>
    <script>
        window.__tenantName = <?= json_encode($tenantName) ?>;
    </script>
</head>

<body>
    <!-- Навигация -->
    <nav class="main-nav">
        <div class="nav-container">
            <a href="/">
                <div class="logo-container">
                    <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="Logo">
                    <span class="logo-text"><?= htmlspecialchars($tenantName) ?></span>
                </div>
            </a>
            <div class="nav-links">
                <button class="theme-toggle" onclick="toggleTheme()">
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
                <a href="#about" class="aboutNav" data-i18n="about">Biz haqimizda</a>
                <a href="#features" data-i18n="features">Imkoniyatlar</a>
                <a href="#contacts" data-i18n="contacts">Aloqa</a>
                <button class="nav-button" data-i18n="personal_cabinet" onclick="openModalWithSessionCheck()">
                    <i class="fas fa-sign-in-alt"></i> Shaxsiy kabinet
                </button>


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
            <a href="/">
                <div class="logo-container">
                    <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="Logo">
                    <span class="logo-text"><?= htmlspecialchars($tenantName) ?></span>
                </div>
            </a>
            <button class="theme-toggle" onclick="toggleTheme()">
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




            <a href="#about" data-i18n="about" onclick="toggleMobileMenu()">Biz haqimizda</a>
            <a href="#features" data-i18n="features" onclick="toggleMobileMenu()">Imkoniyatlar</a>
            <a href="#contacts" data-i18n="contacts" onclick="toggleMobileMenu()">Aloqa</a>
            <button class="mobile-menu-button" data-i18n="personal_cabinet"
                onclick="openModalWithSessionCheck(); toggleMobileMenu()">
                <i class="fas fa-sign-in-alt"></i> Shaxsiy kabinet
            </button>
        </div>
    </div>

    <!-- Герой-секция -->
    <header class="hero-section">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-graduation-cap"></i>
                <span data-i18n="hero_badge">Ta'lim platformasi</span>
            </div>
            <h1 data-i18n="with_iq_creative"><span><?= htmlspecialchars($tenantName) ?></span> bilan intellektni rivojlantiring</h1>
            <p class="hero-subtitle" data-i18n="hero_subtitle">Fanlarni chuqur o'rganish va imtihonlarga tayyorlash
                uchun innovatsion platforma
            </p>
            <div class="hero-buttons">
                <div class="top-buttons">
                    <button class="secondary-button" data-i18n="more_info">Ko'proq ma'lumot</button>
                    <button class="results-button" data-i18n="results">Natijalar</button>
                </div>
                <button class="primary-button" data-i18n="start_learning" onclick="openModalWithSessionCheck()">O'qishni
                    boshlash</button>
            </div>
        </div>
        <div class="hero-image">
            <img src="img/homepage/smartFace.jpg">
        </div>
    </header>

    <!-- Классы -->
    <section class="classes-section">
        <h2 class="section-title" data-i18n="choose_level">Darajangizni tanlang</h2>
        <p class="section-subtitle" data-i18n="personalized_learning">Har bir ta'lim bosqichi uchun ixtisoslashgan
            o'qitish</p>

        <div class="classes-grid">
            <a href="classes/index.php?class=1-2" class="class-card">
                <span class="class-level-badge">1-2</span>
                <div class="class-icon">
                    <i class="fas fa-pencil-alt"></i>
                </div>
                <h3 data-i18n="grade_1_2">1-2 sinflar</h3>
                <p data-i18n="logic_thinking">Mantiq va fikrlash asoslari</p>
            </a>
            <a href="classes/index.php?class=3-4" class="class-card">
                <span class="class-level-badge">3-4</span>
                <div class="class-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <h3 data-i18n="grade_3_4">3-4 sinflar</h3>
                <p data-i18n="analytical_skills">Tahliliy ko'nikmalarni rivojlantirish</p>
            </a>
            <a href="classes/index.php?class=5-6" class="class-card">
                <span class="class-level-badge">5-6</span>
                <div class="class-icon">
                    <i class="fas fa-atom"></i>
                </div>
                <h3 data-i18n="grade_5_6">5-6 sinflar</h3>
                <p data-i18n="scientific_thinking">Ilmiy fikrlash asoslari</p>
            </a>
            <a href="classes/index.php?class=7-8" class="class-card">
                <span class="class-level-badge">7-8</span>
                <div class="class-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <h3 data-i18n="grade_7_8">7-8 sinflar</h3>
                <p data-i18n="deep_study">Fanlarni chuqur o'rganish</p>
            </a>
            <a href="classes/index.php?class=9-11" class="class-card">
                <span class="class-level-badge">9-11</span>
                <div class="class-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3 data-i18n="grade_9_11">9-11 sinflar</h3>
                <p data-i18n="exam_prep">O'qishga tayyorlov</p>
            </a>
            <a href="classes/index.php?course=1-4" class="class-card">
                <span class="class-level-badge">1-4 kurs</span>
                <div class="class-icon">
                    <i class="fas fa-university"></i>
                </div>
                <h3 data-i18n="course_1_4">1-4 kurslar</h3>
                <p data-i18n="academic_development">Akademik rivojlanish</p>
            </a>
        </div>
    </section>

    <!-- Возможности платформы -->
    <section class="features-section" id="features">
        <h2 class="section-title" data-i18n="platform_features">Platforma imkoniyatlari</h2>
        <p class="section-subtitle" data-i18n="platform_features_subtitle">Bilim olishning zamonaviy usuli — hammasi bir joyda</p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-book-open"></i></div>
                <h3 data-i18n="feature_courses">Kurslar</h3>
                <p data-i18n="feature_courses_desc">Har bir sinf darajasi uchun maxsus kurslar va dars materiallari</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-clipboard-check"></i></div>
                <h3 data-i18n="feature_tests">Testlar</h3>
                <p data-i18n="feature_tests_desc">Kunlik va haftalik testlar bilan bilimingizni tekshiring</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3 data-i18n="feature_progress">Rivojlanish</h3>
                <p data-i18n="feature_progress_desc">Ballar, reyting va natijalarni kuzatib boring</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-trophy"></i></div>
                <h3 data-i18n="feature_leaderboard">Liderboard</h3>
                <p data-i18n="feature_leaderboard_desc">Boshqa o'quvchilar bilan raqobatda o'zingizni sinab ko'ring</p>
            </div>
        </div>
    </section>

    <!-- О платформе -->
    <section class="about-section" id="about">
        <div class="about-content">
            <h2 class="section-title"><?= htmlspecialchars($tenantName) ?></h2>
            <p class="about-text" data-i18n="about_platform">
                Bizning platformamiz o'quvchilarning bilimlarini chuqurlashtirish va Prezident maktablariga,
                Al-Xorazmiy nomidagi ixtisoslashtirilgan maktablarga, shuningdek boshqa nufuzli o'quv
                muassasalariga tayyorlash uchun mo'ljallangan. Biz shuningdek SAT va IELTS kabi xalqaro
                imtihonlarga tayyorlovni taklif qilamiz.
            </p>
            <p class="about-highlight" data-i18n="slogan_knowledge">
                <i class="fas fa-lightbulb"></i> Bilim - kelajak kaliti. Kreativ bo'l, organ, yuksal!
            </p>
        </div>
        <div class="about-image">
            <img src="img/homepage/vector.png?v=68" alt="Platforma haqida">
        </div>
    </section>

    <!-- Auth Modal -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>

            <!-- Tabs: Register / Login -->
            <div class="form-toggle" id="authTabs">
                <button id="registerBtn" class="active" onclick="showAuthView('register-step1')" data-i18n="register">Ro'yxatdan o'tish</button>
                <button id="loginBtn" onclick="showAuthView('login')" data-i18n="login">Kirish</button>
            </div>

            <!-- ===================== REGISTRATION FLOW ===================== -->

            <!-- REG STEP 1: Name + Email -->
            <div id="register-step1" class="auth-view" style="display: block;">
                <h2 data-i18n="register">Ro'yxatdan o'tish</h2>
                <p class="modal-subtitle" data-i18n="register_subtitle">Ro'yxatdan o'tish uchun ma'lumotlaringizni kiriting</p>
                <div id="regStep1Message" class="form-message"></div>
                <form id="regStep1Form">
                    <div class="form-group">
                        <label for="reg_first_name" data-i18n="first_name">Ism:</label>
                        <input type="text" id="reg_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="reg_last_name" data-i18n="last_name_optional">Familiya (ixtiyoriy):</label>
                        <input type="text" id="reg_last_name" name="last_name">
                    </div>
                    <div class="form-group">
                        <label for="reg_email" data-i18n="email">Email:</label>
                        <input type="email" id="reg_email" name="email" required>
                    </div>
                    <button type="submit" data-i18n="continue">Davom etish</button>
                </form>
                <p class="auth-switch"> <span data-i18n="already_have_account">Hisobingiz bormi?</span> <a href="#" onclick="showAuthView('login')" data-i18n="login">Kirish</a></p>
            </div>

            <!-- REG STEP 2: Email Verification -->
            <div id="register-step2" class="auth-view" style="display: none;">
                <h2 data-i18n="verify_email_title">Emailni tasdiqlang</h2>
                <p class="modal-subtitle"><span data-i18n="code_sent_to">Kod yuborildi:</span> <strong id="regVerifyEmail"></strong></p>
                <div id="regStep2Message" class="form-message"></div>
                <form id="regStep2Form">
                    <div class="form-group">
                        <label for="reg_code" data-i18n="verification_code">Tasdiqlash kodi:</label>
                        <input type="text" id="reg_code" class="code-input" placeholder="______" maxlength="6" inputmode="numeric" required>
                    </div>
                    <button type="submit" data-i18n="confirm">Tasdiqlash</button>
                </form>
                <div class="resend-row">
                    <span id="regResendTimer" class="resend-timer"></span>
                    <button id="regResendBtn" class="btn-resend" onclick="resendRegCode()" style="display:none;" data-i18n="resend_code">Qayta yuborish</button>
                </div>
                <p class="auth-back"><a href="#" onclick="showAuthView('register-step1')"><i class="fas fa-arrow-left"></i> <span data-i18n="back">Orqaga</span></a></p>
            </div>

            <!-- REG STEP 3: Create Password -->
            <div id="register-step3" class="auth-view" style="display: none;">
                <h2 data-i18n="create_password">Parol yarating</h2>
                <p class="modal-subtitle" data-i18n="create_password_subtitle">Hisobingiz uchun parol o'rnating</p>
                <div id="regStep3Message" class="form-message"></div>
                <form id="regStep3Form">
                    <div class="form-group">
                        <label for="reg_password" data-i18n="password">Parol:</label>
                        <div class="password-field">
                            <input type="password" id="reg_password" name="password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('reg_password')" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reg_confirm_password" data-i18n="confirm_password">Parolni takrorlang:</label>
                        <div class="password-field">
                            <input type="password" id="reg_confirm_password" name="confirm_password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('reg_confirm_password')" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" data-i18n="create_account">Akkaunt yaratish</button>
                </form>
                <p class="auth-back"><a href="#" onclick="showAuthView('register-step2')"><i class="fas fa-arrow-left"></i> <span data-i18n="back">Orqaga</span></a></p>
            </div>

            <!-- REG STEP 4: Success -->
            <div id="register-success" class="auth-view" style="display: none;">
                <div class="auth-success">
                    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                    <h2 data-i18n="account_created">Akkaunt yaratildi!</h2>
                    <p class="modal-subtitle"><span data-i18n="welcome_message">Xush kelibsiz, </span><strong id="regSuccessName"></strong>!</p>
                    <button onclick="goToProfile()" data-i18n="go_to_profile">Shaxsiy kabinetga o'tish</button>
                </div>
            </div>

            <!-- ===================== LOGIN FLOW ===================== -->

            <div id="login" class="auth-view" style="display: none;">
                <h2 data-i18n="login">Kirish</h2>
                <p class="modal-subtitle" data-i18n="login_subtitle">Hisobingizga kiring</p>
                <div id="loginMessage" class="form-message"></div>
                <form id="loginFormElement">
                    <div class="form-group">
                        <label for="login_email" data-i18n="email">Email:</label>
                        <input type="email" id="login_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="login_password" data-i18n="password">Parol:</label>
                        <div class="password-field">
                            <input type="password" id="login_password" name="password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('login_password')" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" data-i18n="login_submit">Kirish</button>
                </form>
                <p class="auth-switch"><a href="#" onclick="showAuthView('forgot-step1')" data-i18n="forgot_password">Parolni unutdingizmi?</a></p>
                <p class="auth-switch"><span data-i18n="no_account">Akkauntingiz yo'qmi?</span> <a href="#" onclick="showAuthView('register-step1')" data-i18n="register">Ro'yxatdan o'tish</a></p>
            </div>

            <!-- ===================== FORGOT PASSWORD FLOW ===================== -->

            <!-- FORGOT STEP 1: Enter Email -->
            <div id="forgot-step1" class="auth-view" style="display: none;">
                <h2 data-i18n="forgot_password_title">Parolni tiklash</h2>
                <p class="modal-subtitle" data-i18n="forgot_password_subtitle">Email manzilingizni kiriting</p>
                <div id="forgotStep1Message" class="form-message"></div>
                <form id="forgotStep1Form">
                    <div class="form-group">
                        <label for="forgot_email" data-i18n="email">Email:</label>
                        <input type="email" id="forgot_email" name="email" required>
                    </div>
                    <button type="submit" data-i18n="continue">Davom etish</button>
                </form>
                <p class="auth-back"><a href="#" onclick="showAuthView('login')"><i class="fas fa-arrow-left"></i> <span data-i18n="back_to_login">Kirishga qaytish</span></a></p>
            </div>

            <!-- FORGOT STEP 2: Verify Code -->
            <div id="forgot-step2" class="auth-view" style="display: none;">
                <h2 data-i18n="verify_email_title">Emailni tasdiqlang</h2>
                <p class="modal-subtitle"><span data-i18n="code_sent_to">Kod yuborildi:</span> <strong id="forgotVerifyEmail"></strong></p>
                <div id="forgotStep2Message" class="form-message"></div>
                <form id="forgotStep2Form">
                    <div class="form-group">
                        <label for="forgot_code" data-i18n="verification_code">Tasdiqlash kodi:</label>
                        <input type="text" id="forgot_code" class="code-input" placeholder="______" maxlength="6" inputmode="numeric" required>
                    </div>
                    <button type="submit" data-i18n="confirm">Tasdiqlash</button>
                </form>
                <div class="resend-row">
                    <span id="forgotResendTimer" class="resend-timer"></span>
                    <button id="forgotResendBtn" class="btn-resend" onclick="resendForgotCode()" style="display:none;" data-i18n="resend_code">Qayta yuborish</button>
                </div>
                <p class="auth-back"><a href="#" onclick="showAuthView('forgot-step1')"><i class="fas fa-arrow-left"></i> <span data-i18n="back">Orqaga</span></a></p>
            </div>

            <!-- FORGOT STEP 3: New Password -->
            <div id="forgot-step3" class="auth-view" style="display: none;">
                <h2 data-i18n="new_password">Yangi parol</h2>
                <p class="modal-subtitle" data-i18n="new_password_subtitle">Yangi parol o'rnating</p>
                <div id="forgotStep3Message" class="form-message"></div>
                <form id="forgotStep3Form">
                    <div class="form-group">
                        <label for="forgot_new_password" data-i18n="new_password_label">Yangi parol:</label>
                        <div class="password-field">
                            <input type="password" id="forgot_new_password" name="password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('forgot_new_password')" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="forgot_confirm_password" data-i18n="confirm_password">Parolni takrorlang:</label>
                        <div class="password-field">
                            <input type="password" id="forgot_confirm_password" name="confirm_password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('forgot_confirm_password')" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" data-i18n="save_new_password">Yangi parolni saqlash</button>
                </form>
            </div>

            <!-- FORGOT STEP 4: Success -->
            <div id="forgot-success" class="auth-view" style="display: none;">
                <div class="auth-success">
                    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                    <h2 data-i18n="password_reset_success">Parol muvaffaqiyatli o'zgartirildi!</h2>
                    <button onclick="showAuthView('login')" data-i18n="login">Kirish</button>
                </div>
            </div>

        </div>
    </div>
    <div class="video-modal" id="videoModal" style="display: none;">
        <div class="video-container">
            <video id="fullscreenVideo" controls playsinline>
                Ваш браузер не поддерживает видео
            </video>
            <button class="close-video">&times;</button>
        </div>
    </div>
    <hr class="footer-divider">

    <!-- Футер -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-section">
                <div class="footer-logo">
                    <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="<?= htmlspecialchars($tenantName) ?> Logo">
                    <span><?= htmlspecialchars($tenantName) ?></span>
                </div>
                <p data-i18n="footer_description">Intellektni rivojlantirish va imtihonlarga tayyorlash uchun
                    innovatsion ta'lim platformasi</p>
                <div class="social-links">
                    <a href="#" aria-label="Telegram"><i class="fab fa-telegram"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h3 data-i18n="footer_links">Foydali havolalar</h3>
                <ul>
                    <li><a href="#about" data-i18n="about">Biz haqimizda</a></li>
                    <li><a href="#features" data-i18n="features">Imkoniyatlar</a></li>
                    <li><a href="#contacts" data-i18n="contacts">Aloqa</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3 data-i18n="footer_contact">Aloqa</h3>
                <ul class="contact-info">
                    <li data-i18n="footer_address"><i class="fas fa-map-marker-alt"></i> Toshkent shahar, Yunusobod
                        tumani</li>
                    <?php if ($tenantPhone): ?>
                    <li><i class="fas fa-phone"></i> <?= htmlspecialchars($tenantPhone) ?></li>
                    <?php endif; ?>
                    <?php if ($tenantEmail): ?>
                    <li><i class="fas fa-envelope"></i> <?= htmlspecialchars($tenantEmail) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($tenantName) ?>. <span data-i18n="all_rights_reserved">Barcha huquqlar himoyalangan.</span></p>
            <p class="developer-credit" data-i18n="developer">Developer: Abdubakiyev Otabek</p>
        </div>
    </footer>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/inputmask@5.0.8/dist/inputmask.min.js"></script>
    <script src="js/i18n.js"></script>
    <script src="js/index.js?v=65"></script>
</body>

</html>
