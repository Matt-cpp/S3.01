document.addEventListener("DOMContentLoaded", () => {
  const forms = document.querySelectorAll("form.register-form");

  forms.forEach((form) => {
    const actionField = form.querySelector('input[name="action"]');
    const submitButton = form.querySelector('button[type="submit"]');

    if (!actionField || !submitButton) {
      return;
    }

    const action = actionField.value;
    const supportedActions = [
      "send_code",
      "send_reset_code",
      "verify_code",
      "verify_reset_code",
      "complete_registration",
      "reset_password",
    ];

    if (!supportedActions.includes(action)) {
      return;
    }

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const originalText = submitButton.textContent;
      const messageContainer = getOrCreateMessageContainer(form);

      clearMessage(messageContainer);
      submitButton.disabled = true;
      submitButton.classList.add("is-loading");
      const loadingTextByAction = {
        send_code: "Envoi en cours...",
        send_reset_code: "Envoi en cours...",
        verify_code: "Verification en cours...",
        verify_reset_code: "Verification en cours...",
        complete_registration: "Creation du compte...",
        reset_password: "Mise a jour...",
      };

      submitButton.textContent =
        loadingTextByAction[action] || "Traitement en cours...";

      try {
        const response = await fetch(form.action, {
          method: "POST",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
          body: new FormData(form),
          credentials: "same-origin",
        });

        const payload = await safeParseJson(response);

        if (!response.ok || !payload || payload.success !== true) {
          const errorMessage =
            (payload && payload.message) ||
            "Erreur lors de l'envoi. Veuillez reessayer.";
          showMessage(messageContainer, errorMessage, "error-message");
          return;
        }

        showMessage(
          messageContainer,
          payload.message || "Email envoye avec succes.",
          "success-message",
        );

        if (payload.redirectUrl) {
          setTimeout(() => {
            window.location.href = payload.redirectUrl;
          }, 450);
        }
      } catch (error) {
        showMessage(
          messageContainer,
          "Erreur reseau. Veuillez verifier votre connexion.",
          "error-message",
        );
      } finally {
        submitButton.disabled = false;
        submitButton.classList.remove("is-loading");
        submitButton.textContent = originalText;
      }
    });
  });
});

function getOrCreateMessageContainer(form) {
  const parent = form.parentElement;
  let container = parent ? parent.querySelector(".ajax-feedback") : null;

  if (!container) {
    container = document.createElement("div");
    container.className = "ajax-feedback";
    form.parentNode.insertBefore(container, form);
  }

  return container;
}

function showMessage(container, message, className) {
  container.textContent = message;
  container.className = `ajax-feedback ${className}`;
}

function clearMessage(container) {
  container.textContent = "";
  container.className = "ajax-feedback";
}

async function safeParseJson(response) {
  try {
    return await response.json();
  } catch (_) {
    return null;
  }
}
