let translations = {};

function interpolate(str, vars = {}) {
  return str.replace(/\{(\w+)\}/g, (_, key) => vars[key] || "");
}

async function loadLanguage(lang) {
  const res = await fetch(`/js/lang/${lang}.json`);
  translations = await res.json();

  const tenantName = window.__tenantName || 'e-Lesson';

  document.querySelectorAll("[data-i18n]").forEach((el) => {
    const key = el.getAttribute("data-i18n");
    const subValue = el.getAttribute("data-value");
    const isCourse = el.getAttribute("data-course") === "true";
    let translation = translations[key];

    if (!translation) return;

    if (typeof translation === "string") {
      translation = interpolate(translation, { value: subValue, TENANT_NAME: tenantName });
    } else if (typeof translation === "object") {
      const modeKey = isCourse ? "course" : "class";
      const template = translation[modeKey];

      if (template) {
        translation = interpolate(template, { value: subValue, TENANT_NAME: tenantName });
      } else {
        return;
      }
    } else {
      return;
    }

    const temp = document.createElement("div");
    temp.innerHTML = translation;

    const newNodes = Array.from(temp.childNodes);
    const originalNodes = Array.from(el.childNodes);

    el.innerHTML = "";

    newNodes.forEach((node) => {
      if (
        node.nodeType === Node.TEXT_NODE ||
        (node.nodeType === Node.ELEMENT_NODE &&
          node.tagName.toLowerCase() === "span")
      ) {
        el.appendChild(node);
      }
    });

    originalNodes.forEach((node) => {
      if (
        node.nodeType === Node.ELEMENT_NODE &&
        node.tagName.toLowerCase() !== "span"
      ) {
        el.appendChild(node.cloneNode(true));
      }
    });
  });

  updateLangLabels(lang);
}

function t(key, vars = {}) {
  const translation = translations[key];
  if (!translation) return key;
  return interpolate(translation, vars);
}

function setLanguage(lang) {
  localStorage.setItem("lang", lang);
  loadLanguage(lang);

  const miniDropdown = document.getElementById("miniLangOptions");
  if (miniDropdown) miniDropdown.classList.remove("show");
}

function updateLangLabels(lang) {
  const label = lang.toUpperCase();
  const flagMap = {
    uz: "uz",
    ru: "ru",
    en: "gb",
    fr: "fr",
  };

  const ids = ["current-lang", "current-lang-mobile", "current-lang-mini"];
  ids.forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.textContent = label;
  });

  const flagMiniEl = document.getElementById("current-lang-flag-mini");
  if (flagMiniEl && flagMap[lang]) {
    flagMiniEl.innerHTML = `<img src="https://flagcdn.com/${flagMap[lang]}.svg" class="lang-flag" alt="${label} Flag" />`;
  }

  const flagEl = document.getElementById("current-lang-flag");
  if (flagEl && flagMap[lang]) {
    flagEl.innerHTML = `<img src="https://flagcdn.com/${flagMap[lang]}.svg" class="lang-flag" alt="${label} Flag" />`;
  }
}

function toggleMiniLangDropdown() {
  const dropdown = document.getElementById("miniLangOptions");
  dropdown.classList.toggle("show");
}

document.addEventListener("click", (e) => {
  const langBtn = document.querySelector(".lang-icon-btn");
  const langOptions = document.getElementById("miniLangOptions");

  if (!langBtn.contains(e.target) && !langOptions.contains(e.target)) {
    langOptions.classList.remove("show");
  }
});

document.addEventListener("DOMContentLoaded", () => {
  const lang = localStorage.getItem("lang") || "ru";
  loadLanguage(lang);

  document.querySelectorAll("[data-lang]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const selectedLang = btn.getAttribute("data-lang");
      setLanguage(selectedLang);
    });
  });
});
