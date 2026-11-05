(function () {
    'use strict';

    if (window.lucide && window.lucide.createIcons) {
        window.lucide.createIcons();
    }

    // ---- Mobile nav sheet ----
    var burgerBtn = document.getElementById('burgerBtn');
    var mobileSheet = document.getElementById('mobileSheet');
    if (burgerBtn && mobileSheet) {
        burgerBtn.addEventListener('click', function () {
            mobileSheet.classList.toggle('open');
            var isOpen = mobileSheet.classList.contains('open');
            burgerBtn.innerHTML = '<i data-lucide="' + (isOpen ? 'x' : 'menu') + '"></i>';
            if (window.lucide) window.lucide.createIcons();
        });
        mobileSheet.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                mobileSheet.classList.remove('open');
                burgerBtn.innerHTML = '<i data-lucide="menu"></i>';
                if (window.lucide) window.lucide.createIcons();
            });
        });
    }

    // ---- FAQ accordion (height handled by CSS grid-template-rows) ----
    document.querySelectorAll('.faq-item').forEach(function (item) {
        var btn = item.querySelector('.faq-q');
        btn.addEventListener('click', function () {
            var isOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item.open').forEach(function (openItem) {
                if (openItem !== item) {
                    openItem.classList.remove('open');
                }
            });
            item.classList.toggle('open', !isOpen);
        });
    });

    // ---- Scroll reveal ----
    // Content is visible by default (see .reveal in CSS). Only now, once this
    // script has actually run, do we arm the hidden-until-scrolled-into-view
    // state — so a blocked/slow/failed script never hides real content.
    var revealEls = document.querySelectorAll('.reveal');
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if ('IntersectionObserver' in window && revealEls.length && !reduceMotion) {
        revealEls.forEach(function (el) { el.classList.add('reveal-armed'); });
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { io.observe(el); });
    }

    // ---- Lead form ----
    var form = document.getElementById('leadForm');
    var errorBox = document.getElementById('leadError');
    var successBox = document.getElementById('leadSuccess');
    var submitBtn = document.getElementById('leadSubmit');

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.add('visible');
    }

    function hideError() {
        errorBox.classList.remove('visible');
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideError();

            var name = document.getElementById('leadName').value.trim();
            var phone = document.getElementById('leadPhone').value.trim();

            if (name.length < 2) {
                showError('Пожалуйста, укажите имя.');
                return;
            }
            var digits = phone.replace(/\D/g, '');
            if (digits.length < 9) {
                showError('Пожалуйста, укажите корректный номер телефона.');
                return;
            }

            var payload = {
                name: name,
                phone: phone,
                center_name: document.getElementById('leadCenter').value.trim(),
                message: document.getElementById('leadMessage').value.trim(),
                website: document.getElementById('leadWebsite').value
            };

            submitBtn.disabled = true;
            submitBtn.textContent = 'Отправляем...';

            fetch('submit_lead.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (result.ok && result.data.success) {
                        form.classList.add('hidden');
                        successBox.classList.add('visible');
                    } else {
                        showError((result.data && result.data.error) || 'Не удалось отправить заявку. Попробуйте ещё раз.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Отправить заявку <i data-lucide="arrow-right"></i>';
                        if (window.lucide) window.lucide.createIcons();
                    }
                })
                .catch(function () {
                    showError('Не удалось отправить заявку. Проверьте соединение и попробуйте ещё раз.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Отправить заявку <i data-lucide="arrow-right"></i>';
                    if (window.lucide) window.lucide.createIcons();
                });
        });
    }
})();
