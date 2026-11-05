(() => {
  "use strict";

  const initializeForms = () => {
    const forms = Array.from(document.querySelectorAll(
      'form[action*="/backend/login.php"], form[action*="/backend/register.php"], form[action*="/backend/forgot_password.php"], form[action*="/backend/resend_verification.php"]'
    ));
    if (!forms.length) return;

    const submitButtons = forms.flatMap((form) => Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]')));
    submitButtons.forEach((button) => {
      button.dataset.csrfWasDisabled = button.disabled ? "1" : "0";
      button.disabled = true;
    });

    const showFailure = (form) => {
      const messageBox = form.querySelector(".csrf-form-error") || document.createElement("p");
      messageBox.className = "csrf-form-error";
      messageBox.setAttribute("role", "alert");
      messageBox.textContent = "Kumo couldn't start a secure form session. Refresh this page and try again.";
      if (!messageBox.isConnected) form.prepend(messageBox);
    };

    fetch("../assets/backend/csrf_token.php", {
      method: "GET",
      credentials: "same-origin",
      cache: "no-store",
      headers: { "Accept": "application/json" }
    }).then((response) => {
      if (!response.ok) throw new Error("CSRF token request failed");
      return response.json();
    }).then((payload) => {
      if (!payload || typeof payload.csrf_token !== "string" || payload.csrf_token.length < 32) {
        throw new Error("Missing CSRF token");
      }
      forms.forEach((form) => {
        let field = form.querySelector('input[name="csrf_token"]');
        if (!field) {
          field = document.createElement("input");
          field.type = "hidden";
          field.name = "csrf_token";
          form.appendChild(field);
        }
        field.value = payload.csrf_token;
      });
      submitButtons.forEach((button) => { button.disabled = button.dataset.csrfWasDisabled === "1"; });
    }).catch(() => forms.forEach((form) => showFailure(form)));

    forms.forEach((form) => form.addEventListener("submit", (event) => {
      const field = form.querySelector('input[name="csrf_token"]');
      if (!field || field.value.length < 32) {
        event.preventDefault();
        showFailure(form);
      }
    }));
  };

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initializeForms, { once: true });
  else initializeForms();
})();
