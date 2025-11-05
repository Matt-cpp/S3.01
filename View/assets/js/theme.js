/**
 * Global Theme Manager
 * Handles dark/light mode across all pages
 * Theme preference is stored in cookies
 */

(function () {
  "use strict";

  // Initialize theme immediately on page load (before DOM ready to prevent flash)
  initializeThemeImmediately();

  // Also initialize after DOM is ready for any dynamic elements
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeTheme);
  } else {
    initializeTheme();
  }

  /**
   * Initialize theme immediately (before DOM ready)
   * This prevents the white flash on page load
   */
  function initializeThemeImmediately() {
    const savedTheme = getCookie("theme") || "light";
    if (savedTheme === "dark") {
      document.documentElement.classList.add("dark-mode");
      if (document.body) {
        document.body.classList.add("dark-mode");
      }
    }
  }

  /**
   * Initialize theme after DOM is ready
   */
  function initializeTheme() {
    const savedTheme = getCookie("theme") || "light";
    applyTheme(savedTheme);
  }

  /**
   * Apply theme to the page
   */
  function applyTheme(theme) {
    const isDark = theme === "dark";

    // Apply to html and body
    document.documentElement.classList.toggle("dark-mode", isDark);
    document.body.classList.toggle("dark-mode", isDark);

    // Update meta theme-color for mobile browsers
    updateMetaThemeColor(isDark);

    // Dispatch event for any components that need to know about theme changes
    window.dispatchEvent(
      new CustomEvent("themeChanged", { detail: { theme } })
    );
  }

  /**
   * Update meta theme-color for mobile browsers
   */
  function updateMetaThemeColor(isDark) {
    let metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
      metaTheme = document.createElement("meta");
      metaTheme.setAttribute("name", "theme-color");
      document.head.appendChild(metaTheme);
    }
    metaTheme.setAttribute("content", isDark ? "#1a1a1a" : "#ffffff");
  }

  /**
   * Get cookie value
   */
  function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(";");
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) === " ") c = c.substring(1, c.length);
      if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
  }

  /**
   * Set cookie value
   */
  function setCookie(name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
  }

  /**
   * Toggle theme (for theme toggle buttons)
   */
  function toggleTheme() {
    const currentTheme = getCookie("theme") || "light";
    const newTheme = currentTheme === "light" ? "dark" : "light";
    setCookie("theme", newTheme, 365);
    applyTheme(newTheme);
    return newTheme;
  }

  // Export functions to window for use in other scripts
  window.themeManager = {
    apply: applyTheme,
    toggle: toggleTheme,
    get: function () {
      return getCookie("theme") || "light";
    },
    set: function (theme) {
      setCookie("theme", theme, 365);
      applyTheme(theme);
    },
  };
})();
