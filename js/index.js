// ============================================
// Auth State Management
// ============================================
let authState = {
  email: '',
  verificationCode: '',
  firstName: '',
  lastName: '',
  purpose: '', // 'registration' or 'password_reset'
};
let resendTimers = {};

function showAuthView(viewId) {
  document.querySelectorAll('.auth-view').forEach(v => v.style.display = 'none');
  const view = document.getElementById(viewId);
  if (view) view.style.display = 'block';

  const tabs = document.getElementById('authTabs');
  const regBtn = document.getElementById('registerBtn');
  const loginBtn = document.getElementById('loginBtn');

  if (viewId.startsWith('register-') || viewId === 'register-success') {
    tabs.style.display = 'flex';
    regBtn.classList.add('active');
    loginBtn.classList.remove('active');
  } else if (viewId === 'login') {
    tabs.style.display = 'flex';
    regBtn.classList.remove('active');
    loginBtn.classList.add('active');
  } else if (viewId.startsWith('forgot-') || viewId === 'forgot-success') {
    tabs.style.display = 'none';
  }
}

function showMessage(elementId, message, type) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.textContent = message;
  el.className = 'form-message ' + type;
  el.style.display = 'block';
}

function clearMessage(elementId) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.style.display = 'none';
  el.className = 'form-message';
}

function setLoading(btn, loading) {
  const el = (btn instanceof jQuery) ? btn[0] : btn;
  if (!el) return;
  if (loading) {
    el.disabled = true;
    el.dataset.origText = el.textContent;
    el.textContent = t('sending') || 'Yuborilmoqda...';
  } else {
    el.disabled = false;
    el.textContent = el.dataset.origText || el.textContent;
  }
}

function startResendTimer(btnId, timerId, seconds) {
  const btn = document.getElementById(btnId);
  const timer = document.getElementById(timerId);
  btn.style.display = 'none';
  timer.style.display = 'inline';
  let remaining = seconds;
  timer.textContent = t('resend_in').replace('{seconds}', remaining) || `${remaining} soniyadan keyin`;

  const interval = setInterval(() => {
    remaining--;
    if (remaining <= 0) {
      clearInterval(interval);
      btn.style.display = 'inline-block';
      btn.disabled = false;
      timer.style.display = 'none';
    } else {
      timer.textContent = t('resend_in').replace('{seconds}', remaining) || `${remaining} soniyadan keyin`;
    }
  }, 1000);

  resendTimers[btnId] = interval;
}

// ============================================
// Password visibility toggle
// ============================================
function togglePassword(inputId) {
  const input = document.getElementById(inputId);
  const icon = input.nextElementSibling.querySelector("i");
  if (input.type === "password") {
    input.type = "text";
    icon.classList.replace("fa-eye", "fa-eye-slash");
  } else {
    input.type = "password";
    icon.classList.replace("fa-eye-slash", "fa-eye");
  }
}

// ============================================
// Modal functions
// ============================================
function openModal() {
  document.getElementById("myModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("myModal").style.display = "none";
  // Reset to initial state
  showAuthView('register-step1');
  clearMessage('regStep1Message');
  clearMessage('regStep2Message');
  clearMessage('regStep3Message');
  clearMessage('loginMessage');
  clearMessage('forgotStep1Message');
  clearMessage('forgotStep2Message');
  clearMessage('forgotStep3Message');
  document.querySelectorAll('.auth-view form').forEach(f => f.reset());
}

window.onclick = function (event) {
  const modal = document.getElementById("myModal");
  if (event.target === modal) closeModal();
};

// ============================================
// Navigation helpers
// ============================================
function goToProfile() {
  window.location.href = "profile/";
}

// ============================================
// Sidebar toggle
// ============================================
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  let overlay = document.getElementById("overlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.id = "overlay";
    Object.assign(overlay.style, {
      position: "fixed", top: "0", left: "0", width: "100%", height: "100%",
      backgroundColor: "rgba(0, 0, 0, 0.5)", zIndex: "998", display: "none",
    });
    overlay.addEventListener("click", () => {
      sidebar.classList.remove("active");
      overlay.style.display = "none";
    });
    document.body.appendChild(overlay);
  }
  const isActive = sidebar.classList.toggle("active");
  overlay.style.display = isActive ? "block" : "none";
}

// ============================================
// Load user info
// ============================================
function loadUserInfo() {
  $.ajax({
    url: "login/get_user.php",
    method: "GET",
    dataType: "json",
    success: (response) => {
      const userInfo = document.getElementById("userInfo");
      const titleButton = document.querySelector(".title");
      if (response.error) {
        if (titleButton) titleButton.classList.remove("hidden");
      } else {
        if (userInfo) {
          userInfo.innerHTML = `
            <h3>${response.first_name} ${response.last_name}</h3>
            <br>
            <p>Email: ${response.email}</p>
            <p>Ballar: ${response.points}</p>
            <p><a href="profile/">Mening profilim</a></p>
          `;
        }
        if (titleButton) titleButton.classList.add("hidden");
      }
    },
    error: () => {
      const titleButton = document.querySelector(".title");
      if (titleButton) titleButton.classList.remove("hidden");
    },
  });
}

// ============================================
// Open modal with session check
// ============================================
function openModalWithSessionCheck() {
  $.ajax({
    url: "login/get_user.php",
    method: "GET",
    dataType: "json",
    success: (response) => {
      if (response.error) {
        openModal();
        showAuthView('register-step1');
      } else {
        window.location.href = "profile/";
      }
    },
    error: () => {
      openModal();
      showAuthView('register-step1');
    },
  });
}

// ============================================
// REGISTRATION FLOW
// ============================================
$(document).ready(function () {

  // Animation init
  $("nav, .hero-section, .classes-section, .about-section").addClass("fade-in");
  $(".section-title, .section-subtitle, .class-card").addClass("fade-in");
  setTimeout(() => $(".hero-section").addClass("visible"), 100);
  setTimeout(() => $("nav").addClass("visible"), 300);
  $(".classes-section > *").each(function (i) { $(this).css("--order", i); });
  $(".class-card").each(function (i) { $(this).css("--order", i + 2); });
  $(".section-title").css("--order", 0);
  $(".section-subtitle").css("--order", 1);

  function checkScroll() {
    const wT = window.scrollY, wB = wT + window.innerHeight;
    $(".fade-in:not(.visible)").each(function () {
      const e = $(this), eT = e.offset().top, eB = eT + e.outerHeight();
      if (eB >= wT && eT <= wB) $(this).addClass("visible");
    });
  }
  checkScroll();
  $(window).on("scroll", checkScroll);
  loadUserInfo();

  // ---- REG STEP 1: Check email + send code ----
  $("#regStep1Form").on("submit", function (e) {
    e.preventDefault();
    const firstName = $("#reg_first_name").val().trim();
    const lastName = $("#reg_last_name").val().trim();
    const email = $("#reg_email").val().trim();

    if (!firstName) { showMessage('regStep1Message', t('enter_name') || 'Ismni kiriting', 'error'); return; }
    if (!email) { showMessage('regStep1Message', t('enter_email') || 'Emailni kiriting', 'error'); return; }

    const $btn = $(this).find('button[type="submit"]');
    setLoading($btn, true);

    // Check email availability
    $.ajax({
      url: "login/register.php",
      method: "POST",
      dataType: "json",
      data: { action: 'check_email', email: email, first_name: firstName, last_name: lastName },
      success: (res) => {
        if (res.error) {
          showMessage('regStep1Message', res.error, 'error');
          setLoading($btn, false);
          return;
        }
        // Send verification code
        $.ajax({
          url: "login/sms.php",
          method: "POST",
          dataType: "json",
          data: { email: email, purpose: 'registration' },
          success: (res2) => {
            if (res2.error) {
              showMessage('regStep1Message', res2.error, 'error');
              setLoading($btn, false);
              return;
            }
            authState.email = email;
            authState.firstName = firstName;
            authState.lastName = lastName;
            $("#regVerifyEmail").text(email);
            showAuthView('register-step2');
            startResendTimer('regResendBtn', 'regResendTimer', 45);
            setLoading($btn, false);
          },
          error: () => {
            showMessage('regStep1Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
            setLoading($btn, false);
          },
        });
      },
      error: () => {
        showMessage('regStep1Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
        setLoading($btn, false);
      },
    });
  });

  // ---- REG STEP 2: Verify code (server-side) ----
  $("#regStep2Form").on("submit", function (e) {
    e.preventDefault();
    const code = $("#reg_code").val().trim();
    if (!code) { showMessage('regStep2Message', t('enter_code') || 'Kodni kiriting', 'error'); return; }

    const $btn = $(this).find('button[type="submit"]');
    setLoading($btn, true);

    $.ajax({
      url: "login/register.php",
      method: "POST",
      dataType: "json",
      data: { action: 'verify_email', code: code },
      success: (res) => {
        if (res.error) {
          showMessage('regStep2Message', res.error, 'error');
          setLoading($btn, false);
          return;
        }
        showAuthView('register-step3');
        setLoading($btn, false);
      },
      error: () => {
        showMessage('regStep2Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
        setLoading($btn, false);
      },
    });
  });

  // ---- REG STEP 2: Resend code ----
  $("#regResendBtn").on("click", function () {
    resendRegCode();
  });

  // ---- REG STEP 3: Create account ----
  $("#regStep3Form").on("submit", function (e) {
    e.preventDefault();
    const password = $("#reg_password").val();
    const confirmPassword = $("#reg_confirm_password").val();

    if (!password || password.length < 6) {
      showMessage('regStep3Message', t('password_too_short') || 'Parol kamida 6 ta belgi', 'error');
      return;
    }
    if (password !== confirmPassword) {
      showMessage('regStep3Message', t('passwords_do_not_match') || 'Parollar mos emas', 'error');
      return;
    }

    const $btn = $(this).find('button[type="submit"]');
    setLoading($btn, true);

    $.ajax({
      url: "login/register.php",
      method: "POST",
      dataType: "json",
      data: {
        action: 'create_account',
        password: password,
        confirm_password: confirmPassword,
      },
      success: (res) => {
        if (res.error) {
          showMessage('regStep3Message', res.error, 'error');
          setLoading($btn, false);
          return;
        }
        $("#regSuccessName").text(res.first_name || authState.firstName);
        showAuthView('register-success');
        setLoading($btn, false);
      },
      error: () => {
        showMessage('regStep3Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
        setLoading($btn, false);
      },
    });
  });

  // ---- LOGIN ----
  $("#loginFormElement").on("submit", function (e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    setLoading($btn, true);

    $.ajax({
      url: "login/login.php",
      method: "POST",
      dataType: "json",
      data: $(this).serialize(),
      success: (res) => {
        if (res.error) {
          showMessage('loginMessage', res.error, 'error');
          setLoading($btn, false);
          return;
        }
        closeModal();
        loadUserInfo();
        window.location.href = "profile/";
      },
      error: () => {
        showMessage('loginMessage', t('error_occurred') || 'Xatolik yuz berdi', 'error');
        setLoading($btn, false);
      },
    });
  });

  // ---- FORGOT STEP 1: Send reset code ----
  $("#forgotStep1Form").on("submit", function (e) {
    e.preventDefault();
    const email = $("#forgot_email").val().trim();
    if (!email) { showMessage('forgotStep1Message', t('enter_email') || 'Emailni kiriting', 'error'); return; }

    const $btn = $(this).find('button[type="submit"]');
    setLoading($btn, true);

    $.ajax({
      url: "login/sms.php",
      method: "POST",
      dataType: "json",
      data: { email: email, purpose: 'password_reset' },
      success: (res) => {
        if (res.error) {
          showMessage('forgotStep1Message', res.error, 'error');
          setLoading($btn, false);
          return;
        }
        authState.email = email;
        $("#forgotVerifyEmail").text(email);
        showAuthView('forgot-step2');
        startResendTimer('forgotResendBtn', 'forgotResendTimer', 45);
        setLoading($btn, false);
      },
      error: () => {
        showMessage('forgotStep1Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
        setLoading($btn, false);
      },
    });
  });

  // ---- FORGOT STEP 2: Verify code ----
  $("#forgotStep2Form").on("submit", function (e) {
    e.preventDefault();
    const code = $("#forgot_code").val().trim();
    if (!code) { showMessage('forgotStep2Message', t('enter_code') || 'Kodni kiriting', 'error'); return; }

    const $btn = $(this).find('button[type="submit"]');
    setLoading($btn, true);

    $.ajax({
      url: "login/forgot_password.php",
      method: "POST",
      dataType: "json",
      data: { action: 'verify_code', email: authState.email, code: code },
      success: (res) => {
        if (res.error) {
          showMessage('forgotStep2Message', res.error, 'error');
          setLoading($btn, false);
          return;
        }
        showAuthView('forgot-step3');
        setLoading($btn, false);
      },
      error: () => {
        showMessage('forgotStep2Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
        setLoading($btn, false);
      },
    });
  });

  // ---- FORGOT STEP 2: Resend code ----
  $("#forgotResendBtn").on("click", function () {
    resendForgotCode();
  });

  // ---- FORGOT STEP 3: Set new password ----
  $("#forgotStep3Form").on("submit", function (e) {
    e.preventDefault();
    const password = $("#forgot_new_password").val();
    const confirmPassword = $("#forgot_confirm_password").val();

    if (!password || password.length < 6) {
      showMessage('forgotStep3Message', t('password_too_short') || 'Parol kamida 6 ta belgi', 'error');
      return;
    }
    if (password !== confirmPassword) {
      showMessage('forgotStep3Message', t('passwords_do_not_match') || 'Parollar mos emas', 'error');
      return;
    }

    const $btn = $(this).find('button[type="submit"]');
    setLoading($btn, true);

    $.ajax({
      url: "login/forgot_password.php",
      method: "POST",
      dataType: "json",
      data: {
        action: 'reset_password',
        password: password,
        confirm_password: confirmPassword,
      },
      success: (res) => {
        if (res.error) {
          showMessage('forgotStep3Message', res.error, 'error');
          setLoading($btn, false);
          return;
        }
        showAuthView('forgot-success');
        setLoading($btn, false);
      },
      error: () => {
        showMessage('forgotStep3Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
        setLoading($btn, false);
      },
    });
  });
});

// ============================================
// Resend code functions
// ============================================
function resendRegCode() {
  const email = authState.email;
  if (!email) return;
  const btn = document.getElementById('regResendBtn');
  btn.disabled = true;
  btn.textContent = t('sending') || 'Yuborilmoqda...';

  $.ajax({
    url: "login/sms.php",
    method: "POST",
    dataType: "json",
    data: { email: email, purpose: 'registration' },
    success: (res) => {
      if (res.error) {
        showMessage('regStep2Message', res.error, 'error');
      } else {
        showMessage('regStep2Message', t('code_resent') || 'Kod qayta yuborildi', 'success');
      }
      startResendTimer('regResendBtn', 'regResendTimer', 45);
    },
    error: () => {
      showMessage('regStep2Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
      btn.disabled = false;
      btn.textContent = t('resend_code') || 'Qayta yuborish';
    },
  });
}

function resendForgotCode() {
  const email = authState.email;
  if (!email) return;
  const btn = document.getElementById('forgotResendBtn');
  btn.disabled = true;
  btn.textContent = t('sending') || 'Yuborilmoqda...';

  $.ajax({
    url: "login/sms.php",
    method: "POST",
    dataType: "json",
    data: { email: email, purpose: 'password_reset' },
    success: (res) => {
      if (res.error) {
        showMessage('forgotStep2Message', res.error, 'error');
      } else {
        showMessage('forgotStep2Message', t('code_resent') || 'Kod qayta yuborildi', 'success');
      }
      startResendTimer('forgotResendBtn', 'forgotResendTimer', 45);
    },
    error: () => {
      showMessage('forgotStep2Message', t('error_occurred') || 'Xatolik yuz berdi', 'error');
      btn.disabled = false;
      btn.textContent = t('resend_code') || 'Qayta yuborish';
    },
  });
}

// ============================================
// Theme
// ============================================
function getCSSVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function updateThemeColorFromCSS() {
  const themeColor = getCSSVar("--primary-color");
  const metaTag = document.querySelector('meta[name="theme-color"]');
  if (metaTag && themeColor) metaTag.setAttribute("content", themeColor);
}

function initTheme() {
  const savedTheme = localStorage.getItem("theme") || "dark";
  document.documentElement.setAttribute("data-theme", savedTheme);
  updateThemeColorFromCSS();
}

function toggleTheme() {
  const current = document.documentElement.getAttribute("data-theme") || "dark";
  const newTheme = current === "dark" ? "light" : "dark";
  document.documentElement.setAttribute("data-theme", newTheme);
  localStorage.setItem("theme", newTheme);
  updateThemeColorFromCSS();
}

document.addEventListener("DOMContentLoaded", () => initTheme());

// ============================================
// Video
// ============================================
const videoSources = {
  "koproq-malumot": "/other_resources/more_info/koproq-malumot.mp4",
  natijalar: "/other_resources/project_results/natijalar.mp4",
};

function checkVideoExists(url, callback) {
  fetch(url, { method: "HEAD" }).then((r) => callback(r.ok)).catch(() => callback(false));
}

function openFullscreenVideo(videoKey) {
  const videoPath = videoSources[videoKey];
  if (!videoPath) return;
  checkVideoExists(videoPath, (exists) => {
    if (!exists) return;
    const videoModal = document.getElementById("videoModal");
    const fullscreenVideo = document.getElementById("fullscreenVideo");
    videoModal.style.display = "flex";
    fullscreenVideo.src = videoPath;
    fullscreenVideo.play().catch(() => {});
    const elem = fullscreenVideo;
    if (elem.requestFullscreen) elem.requestFullscreen();
    else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
  });
}

function closeVideo() {
  const videoModal = document.getElementById("videoModal");
  const fullscreenVideo = document.getElementById("fullscreenVideo");
  fullscreenVideo.pause();
  fullscreenVideo.currentTime = 0;
  videoModal.style.display = "none";
  if (document.exitFullscreen) document.exitFullscreen();
  else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
}

document.querySelector(".secondary-button")?.addEventListener("click", () => openFullscreenVideo("koproq-malumot"));
document.querySelector(".results-button")?.addEventListener("click", () => openFullscreenVideo("natijalar"));
document.querySelector(".close-video")?.addEventListener("click", closeVideo);
document.getElementById("videoModal")?.addEventListener("click", (e) => { if (e.target === e.currentTarget) closeVideo(); });

// ============================================
// Service Worker
// ============================================
if ("serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker.register("/service-worker.js").catch(() => {});
  });
}
