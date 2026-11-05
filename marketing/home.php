<?php
/**
 * e-Lesson platform homepage — markets the platform itself (not any one
 * tenant's school). Served at the production apex domain by root index.php;
 * reachable directly here for local preview/QA.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>e-Lesson — платформа для учебных центров</title>
    <meta name="description" content="e-Lesson — система управления учебным центром: ученики, оплаты, должники с автоматическими SMS, курсы, посещаемость и группы в одном месте.">
    <link rel="icon" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="home.css?v=1">
</head>
<body>

    <nav class="nav">
        <div class="container nav-row">
            <a href="#hero" class="brand">
                <span class="brand-mark"><i data-lucide="graduation-cap"></i></span>
                e-Lesson
            </a>
            <div class="nav-links">
                <a href="#features">Возможности</a>
                <a href="#how">Как это работает</a>
                <a href="#faq">Вопросы</a>
            </div>
            <div class="nav-actions">
                <a href="#lead-form" class="btn btn-primary">Оставить заявку</a>
                <button class="nav-burger" id="burgerBtn" aria-label="Меню">
                    <i data-lucide="menu"></i>
                </button>
            </div>
        </div>
        <div class="mobile-sheet" id="mobileSheet">
            <a href="#features">Возможности</a>
            <a href="#how">Как это работает</a>
            <a href="#faq">Вопросы</a>
            <a href="#lead-form" class="btn btn-primary btn-block">Оставить заявку</a>
        </div>
    </nav>

    <main>
        <!-- ============== HERO ============== -->
        <section class="hero" id="hero" style="padding-bottom:0;">
            <div class="hero-glow"></div>
            <div class="container">
                <div class="hero-grid">
                    <div class="hero-copy">
                        <h1>Школа в телефоне,<br><span class="accent-text">а не в тетради</span></h1>
                        <p class="hero-sub">
                            Ученики, оплаты, должники и посещаемость — в одной системе.
                            Без Excel, без потерянных чеков в WhatsApp и без кассы,
                            которая не сходится вечером.
                        </p>
                        <div class="hero-cta-row">
                            <a href="#lead-form" class="btn btn-accent">
                                Оставить заявку
                                <i data-lucide="arrow-right"></i>
                            </a>
                            <a href="#how" class="btn btn-ghost">
                                <i data-lucide="play-circle"></i>
                                Как это работает
                            </a>
                        </div>
                        <div class="hero-trust">
                            <i data-lucide="shield-check"></i>
                            Данные каждого центра изолированы и видны только ему
                        </div>
                    </div>
                    <div class="hero-visual reveal">
                        <div class="browser-frame">
                            <div class="browser-chrome">
                                <div class="browser-dots">
                                    <span></span><span></span><span></span>
                                </div>
                                <div class="browser-url">вашцентр.e-lesson.uz/admin</div>
                            </div>
                            <img src="assets/screens/dashboard.png" alt="Панель управления e-Lesson — дашборд администратора учебного центра" width="1280" height="760" loading="eager">
                        </div>
                        <div class="float-chip float-chip-revenue">
                            <span class="chip-icon"><i data-lucide="trending-up"></i></span>
                            <span>
                                17 200 000 сум
                                <span class="chip-sub">доход за 30 дней</span>
                            </span>
                        </div>
                        <div class="float-chip float-chip-sms">
                            <span class="chip-icon"><i data-lucide="mail-check"></i></span>
                            <span>
                                SMS-напоминания
                                <span class="chip-sub">отправляются сами</span>
                            </span>
                        </div>
                    </div>
                </div>
                <p class="hero-caption">Реальный интерфейс e-Lesson — демо-центр «Demo Academy»</p>
            </div>
        </section>

        <!-- ============== PAIN STRIP ============== -->
        <section class="pain">
            <div class="container">
                <div class="pain-card reveal">
                    <div class="pain-head">
                        <h2>Узнаёте своё утро?</h2>
                        <p>Так выглядит день директора центра без единой системы учёта.</p>
                    </div>
                    <div class="pain-list">
                        <div class="pain-item">
                            <i data-lucide="file-x"></i>
                            <p>Excel-таблица с учениками — половина строк устарела ещё в прошлом семестре</p>
                        </div>
                        <div class="pain-item">
                            <i data-lucide="phone-call"></i>
                            <p>Каждый месяц приходится самому обзванивать должников и неловко напоминать об оплате</p>
                        </div>
                        <div class="pain-item">
                            <i data-lucide="message-circle-off"></i>
                            <p>Видеоурок отправлен в WhatsApp три месяца назад — и уже никто не найдёт его в переписке</p>
                        </div>
                        <div class="pain-item">
                            <i data-lucide="calculator"></i>
                            <p>Вечером касса не сходится на несколько сотен тысяч сум — а почему, уже не вспомнить</p>
                        </div>
                    </div>
                    <div class="pain-resolve">
                        <i data-lucide="sparkles"></i>
                        e-Lesson убирает всё это — автоматически
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== FEATURES ============== -->
        <section id="features">
            <div class="container">
                <div class="section-head reveal">
                    <h2>Всё, что нужно центру — в одном месте</h2>
                    <p>Не отдельная программа для платежей и отдельная для журнала. Один вход, одна система.</p>
                </div>
                <div class="feature-grid">
                    <div class="feature-card reveal">
                        <div class="feature-icon icon-teal"><i data-lucide="users"></i></div>
                        <h3>Ученики и подписки</h3>
                        <p>Профиль, статус оплаты и курс каждого ученика видны с одного экрана — без поиска по вкладкам.</p>
                    </div>
                    <div class="feature-card reveal">
                        <div class="feature-icon icon-blue"><i data-lucide="wallet"></i></div>
                        <h3>Касса и платежи</h3>
                        <p>Наличные, карта, перевод — один журнал операций вместо Excel и бумажных чеков.</p>
                    </div>
                    <div class="feature-card reveal">
                        <div class="feature-icon icon-rose"><i data-lucide="alert-circle"></i></div>
                        <h3>Должники и SMS-напоминания</h3>
                        <p>Система сама напоминает об оплате за 3 дня до срока и на следующий день после просрочки.</p>
                    </div>
                    <div class="feature-card reveal">
                        <div class="feature-icon icon-violet"><i data-lucide="book-open"></i></div>
                        <h3>Курсы и видеоуроки</h3>
                        <p>Загрузили материал один раз — ученики пересматривают его в любое время, без WhatsApp-архивов.</p>
                    </div>
                    <div class="feature-card reveal">
                        <div class="feature-icon icon-amber"><i data-lucide="calendar-check"></i></div>
                        <h3>Группы и посещаемость</h3>
                        <p>Преподаватель отмечает урок с телефона за несколько секунд — «Был», «Не был», «Опоздал».</p>
                    </div>
                    <div class="feature-card reveal">
                        <div class="feature-icon icon-slate"><i data-lucide="smartphone"></i></div>
                        <h3>Доступ с телефона</h3>
                        <p>Админ-панель открывается в браузере на любом устройстве — ничего не нужно устанавливать.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== SHOWCASE ============== -->
        <section class="section-alt" id="how">
            <div class="container">
                <div class="section-head reveal">
                    <h2>Как это работает на практике</h2>
                    <p>Два экрана из настоящей панели управления e-Lesson.</p>
                </div>

                <div class="showcase-row reveal">
                    <div class="showcase-media">
                        <img src="assets/screens/debtors.png" alt="Экран «Должники» с автоматическим статусом SMS-напоминания" width="1280" height="560" loading="lazy">
                    </div>
                    <div class="showcase-copy">
                        <h3>Ни один должник не потеряется</h3>
                        <p>Журнал должников виден на одном экране, а систему напоминаний не нужно вести вручную.</p>
                        <ul class="showcase-points">
                            <li><i data-lucide="check-circle"></i>Автоматическое SMS за 3 дня до оплаты</li>
                            <li><i data-lucide="check-circle"></i>Повторное напоминание на следующий день после просрочки</li>
                            <li><i data-lucide="check-circle"></i>Приём оплаты в одном окне — без перехода на другую страницу</li>
                        </ul>
                    </div>
                </div>

                <div class="showcase-row reverse reveal">
                    <div class="showcase-media">
                        <img src="assets/screens/attendance.png" alt="Журнал посещаемости группы за месяц с процентом явки" width="1280" height="700" loading="lazy">
                    </div>
                    <div class="showcase-copy">
                        <h3>Посещаемость — за несколько секунд</h3>
                        <p>Каждый преподаватель видит только свои группы — без доступа к кассе и финансам центра.</p>
                        <ul class="showcase-points">
                            <li><i data-lucide="check-circle"></i>Три кнопки на ученика: «Был», «Не был», «Опоздал»</li>
                            <li><i data-lucide="check-circle"></i>Журнал за месяц и процент явки считаются сами</li>
                            <li><i data-lucide="check-circle"></i>Доступ ограничен ролью — учитель не видит чужие группы</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== WHITE LABEL ============== -->
        <section id="white-label">
            <div class="container">
                <div class="whitelabel">
                    <div class="whitelabel-copy reveal">
                        <h2>Это ваш сайт. Не наш.</h2>
                        <p>
                            Каждый центр получает собственный адрес и свой логотип.
                            Ученики видят бренд вашего центра — e-Lesson остаётся инструментом внутри,
                            а не чужим сервисом поверх вашей школы.
                        </p>
                    </div>
                    <div class="domain-card reveal">
                        <div class="domain-bar">
                            <i data-lucide="lock"></i>
                            <span>вашцентр<span class="slug">.e-lesson.uz</span></span>
                        </div>
                        <div class="swatch-row">
                            <div class="swatch" style="background:#0e9f8e"></div>
                            <div class="swatch" style="background:#0b1220"></div>
                            <div class="swatch" style="background:#2563eb"></div>
                            <div class="swatch" style="background:#be123c"></div>
                        </div>
                        <p class="swatch-label">Логотип и цвета — ваши собственные</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== FAQ ============== -->
        <section class="section-alt" id="faq">
            <div class="container">
                <div class="section-head reveal">
                    <h2>Вопросы, которые обычно задают</h2>
                </div>
                <div class="faq-list">
                    <div class="faq-item reveal">
                        <button class="faq-q" type="button">
                            <span>Сколько это стоит?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-a"><div class="faq-a-clip"><p class="faq-a-inner">Зависит от размера центра и набора функций. Оставьте заявку — посчитаем вместе и предложим вариант под вас.</p></div></div>
                    </div>
                    <div class="faq-item reveal">
                        <button class="faq-q" type="button">
                            <span>Нужно ли устанавливать программу?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-a"><div class="faq-a-clip"><p class="faq-a-inner">Нет. Всё работает в браузере — на компьютере или телефоне, без установки и без IT-специалиста.</p></div></div>
                    </div>
                    <div class="faq-item reveal">
                        <button class="faq-q" type="button">
                            <span>Что будет с данными из Excel?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-a"><div class="faq-a-clip"><p class="faq-a-inner">Перенесём учеников и историю платежей вместе с вами на этапе подключения — отдельно переделывать ничего не придётся.</p></div></div>
                    </div>
                    <div class="faq-item reveal">
                        <button class="faq-q" type="button">
                            <span>Видят ли центры данные друг друга?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-a"><div class="faq-a-clip"><p class="faq-a-inner">Нет. Каждый центр полностью изолирован: его ученики, платежи и курсы видны только ему.</p></div></div>
                    </div>
                    <div class="faq-item reveal">
                        <button class="faq-q" type="button">
                            <span>А если у меня несколько преподавателей?</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-a"><div class="faq-a-clip"><p class="faq-a-inner">У каждого преподавателя свой вход с доступом только к своим группам и посещаемости — без доступа к кассе и финансам центра.</p></div></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============== LEAD FORM ============== -->
        <section class="lead-section" id="lead-form">
            <div class="container">
                <div class="lead-grid reveal">
                    <div class="lead-copy">
                        <h2>Покажем на примере вашего центра</h2>
                        <p>Оставьте телефон — свяжемся, покажем панель управления и ответим на вопросы по цене и переносу данных.</p>
                        <ul class="lead-reassure">
                            <li><i data-lucide="check"></i>Без обязательств — просто демонстрация</li>
                            <li><i data-lucide="check"></i>Поможем перенести учеников из Excel</li>
                            <li><i data-lucide="check"></i>Свяжемся с вами напрямую, лично</li>
                        </ul>
                    </div>
                    <div class="lead-card">
                        <form id="leadForm" novalidate>
                            <div class="form-error" id="leadError"></div>

                            <div class="field">
                                <label for="leadName">Имя</label>
                                <input type="text" id="leadName" name="name" required autocomplete="name" placeholder="Как к вам обращаться">
                            </div>
                            <div class="field">
                                <label for="leadPhone">Телефон</label>
                                <input type="tel" id="leadPhone" name="phone" required autocomplete="tel" placeholder="+998 __ ___ __ __">
                            </div>
                            <div class="field">
                                <label for="leadCenter">Название центра <span style="text-transform:none;font-weight:500;">(необязательно)</span></label>
                                <input type="text" id="leadCenter" name="center_name" autocomplete="organization" placeholder="Например: Smart Study">
                            </div>
                            <div class="field">
                                <label for="leadMessage">Комментарий <span style="text-transform:none;font-weight:500;">(необязательно)</span></label>
                                <textarea id="leadMessage" name="message" placeholder="Что для вас сейчас самое больное место?"></textarea>
                            </div>
                            <div class="hp-field" aria-hidden="true">
                                <label for="leadWebsite">Website</label>
                                <input type="text" id="leadWebsite" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <button type="submit" class="btn btn-accent btn-block" id="leadSubmit">
                                Отправить заявку
                                <i data-lucide="arrow-right"></i>
                            </button>
                        </form>

                        <div class="lead-success" id="leadSuccess">
                            <span class="success-icon"><i data-lucide="check-circle"></i></span>
                            <h4>Заявка отправлена</h4>
                            <p>Спасибо! Мы свяжемся с вами в ближайшее время.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <div class="footer-brand">
                    <a href="#hero" class="brand">
                        <span class="brand-mark"><i data-lucide="graduation-cap"></i></span>
                        e-Lesson
                    </a>
                    <p>Система управления учебным центром: ученики, оплаты, курсы, посещаемость и группы — в одном месте, под брендом вашего центра.</p>
                </div>
                <div class="footer-col">
                    <h4>Платформа</h4>
                    <a href="#features">Возможности</a>
                    <a href="#how">Как это работает</a>
                    <a href="#white-label">White-label</a>
                </div>
                <div class="footer-col">
                    <h4>Начать</h4>
                    <a href="#faq">Вопросы</a>
                    <a href="#lead-form">Оставить заявку</a>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; <?= date('Y') ?> e-Lesson. Все права защищены.</span>
            </div>
        </div>
    </footer>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="home.js?v=1"></script>
</body>
</html>
