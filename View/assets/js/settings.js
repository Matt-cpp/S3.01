// Settings Page JavaScript

document.addEventListener("DOMContentLoaded", function () {
  initializeTheme();
  initializePasswordForm();
});

//Initialize theme functionality
function initializeTheme() {
  const themeInputs = document.querySelectorAll('input[name="theme"]');
  const body = document.body;

  // Load saved theme
  const savedTheme = getCookie("theme") || "light";
  if (savedTheme === "dark") {
    body.classList.add("dark-mode");
  }

  // Listen for theme changes
  themeInputs.forEach((input) => {
    input.addEventListener("change", function () {
      const theme = this.value;

      if (theme === "dark") {
        body.classList.add("dark-mode");
      } else {
        body.classList.remove("dark-mode");
      }

      // Save theme preference
      setCookie("theme", theme, 365);

      // Show success message
      showMessage("Theme changed successfully!", "success", 2000);
    });
  });
}

//Initialize password change form
function initializePasswordForm() {
  const form = document.getElementById("password-form");
  if (!form) return;

  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    const currentPassword = document.getElementById("current-password").value;
    const newPassword = document.getElementById("new-password").value;
    const confirmPassword = document.getElementById("confirm-password").value;
    const messageDiv = document.getElementById("password-message");
    const submitBtn = form.querySelector('button[type="submit"]');

    // Validate passwords match
    if (newPassword !== confirmPassword) {
      showFormMessage(
        messageDiv,
        "Les mots de passe ne correspondent pas.",
        "error"
      );
      return;
    }

    // Validate password strength
    if (newPassword.length < 8) {
      showFormMessage(
        messageDiv,
        "Le mot de passe doit contenir au moins 8 caractères.",
        "error"
      );
      return;
    }

    // Show loading state
    submitBtn.disabled = true;
    submitBtn.classList.add("loading");

    try {
      const response = await fetch("/Presenter/api/update-password.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          current_password: currentPassword,
          new_password: newPassword,
        }),
      });

      const result = await response.json();

      if (result.success) {
        showFormMessage(
          messageDiv,
          "Mot de passe modifié avec succès!",
          "success"
        );
        form.reset();
      } else {
        showFormMessage(
          messageDiv,
          result.message || "Erreur lors de la modification du mot de passe.",
          "error"
        );
      }
    } catch (error) {
      console.error("Error:", error);
      showFormMessage(
        messageDiv,
        "Une erreur est survenue. Veuillez réessayer.",
        "error"
      );
    } finally {
      submitBtn.disabled = false;
      submitBtn.classList.remove("loading");
    }
  });
}

//Show message in form
function showFormMessage(element, message, type) {
  if (!element) return;

  element.textContent = message;
  element.className = `message ${type}`;
  element.style.display = "block";

  // Auto-hide success messages
  if (type === "success") {
    setTimeout(() => {
      element.style.display = "none";
    }, 5000);
  }
}

//Show temporary toast message
function showMessage(message, type = "info", duration = 3000) {
  // Remove existing toast
  const existingToast = document.querySelector(".toast-message");
  if (existingToast) {
    existingToast.remove();
  }

  // Create toast element
  const toast = document.createElement("div");
  toast.className = `toast-message toast-${type}`;
  toast.textContent = message;

  // Style the toast
  Object.assign(toast.style, {
    position: "fixed",
    top: "20px",
    right: "20px",
    padding: "1rem 1.5rem",
    borderRadius: "8px",
    boxShadow: "0 4px 12px rgba(0, 0, 0, 0.15)",
    zIndex: "9999",
    fontSize: "0.875rem",
    fontWeight: "500",
    animation: "slideIn 0.3s ease-out",
    maxWidth: "400px",
  });

  // Set colors based on type
  const colors = {
    success: { bg: "#d1fae5", text: "#065f46", border: "#10b981" },
    error: { bg: "#fee2e2", text: "#991b1b", border: "#ef4444" },
    info: { bg: "#dbeafe", text: "#1e40af", border: "#3b82f6" },
  };

  const color = colors[type] || colors.info;
  toast.style.backgroundColor = color.bg;
  toast.style.color = color.text;
  toast.style.border = `2px solid ${color.border}`;

  // Add animation
  const style = document.createElement("style");
  style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
  document.head.appendChild(style);

  // Add to page
  document.body.appendChild(toast);

  // Remove after duration
  setTimeout(() => {
    toast.style.animation = "slideOut 0.3s ease-in";
    setTimeout(() => {
      toast.remove();
      style.remove();
    }, 300);
  }, duration);
}

//Cookie utilities
function setCookie(name, value, days) {
  const expires = new Date();
  expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
  document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
}

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
