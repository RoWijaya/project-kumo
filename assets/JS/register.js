(() => {
  "use strict";

  const form = document.querySelector('form[action*="/backend/register.php"]');
  const status = document.getElementById("register-status");

  document.querySelectorAll(".toggle-password").forEach((toggle) => {
    toggle.addEventListener("click", () => {
      const targetId = toggle.dataset.target;
      const input = targetId ? document.getElementById(targetId) : toggle.previousElementSibling;
      const icon = toggle.querySelector("span");
      if (!input || !icon) return;
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      icon.textContent = show ? "visibility_off" : "visibility";
      toggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
    });
  });

  if (!form) return;
  const fields = {
    full_name: document.getElementById("fullname"),
    username: document.getElementById("username"),
    email: document.getElementById("email"),
    password: document.getElementById("password"),
    confirm_password: document.getElementById("confirm-password")
  };
  const messages = {
    invalid_name: ["full_name", "Please enter your full name (up to 100 characters)."],
    invalid_username: ["username", "Use 3–50 characters: letters, numbers, dots, underscores, or hyphens."],
    invalid_email: ["email", "Please enter a valid email address."],
    email_exists: ["email", "An account with this email already exists."],
    username_exists: ["username", "That username is already in use. Choose another one."],
    password_mismatch: ["confirm_password", "Your passwords do not match. Re-enter the confirmation."],
    password_length: ["password", "Use a password between 8 and 20 characters."],
    password_complexity: ["password", "Add an uppercase letter, lowercase letter, number, and special character."],
    csrf: ["", "Your secure form session expired. Refresh this page and try again."],
    database: ["", "A server error occurred. Please try again later."],
    send_failed: ["email", "Your account was created, but Kumo could not send the verification email. You can request another link from the verification page."]
  };

  function groupFor(name) {
    const field = fields[name];
    return field ? field.closest(".input-group") : null;
  }
  function clearError(name) {
    const field = fields[name];
    const group = groupFor(name);
    if (field) {
      field.classList.remove("field-invalid");
      field.removeAttribute("aria-invalid");
    }
    if (group) {
      group.classList.remove("has-error");
      const note = group.querySelector(".field-error");
      if (note) note.remove();
    }
  }
  function clearAllErrors() {
    Object.keys(fields).forEach(clearError);
    if (status) { status.hidden = true; status.textContent = ""; status.classList.remove("is-error", "is-success"); }
  }
  function showFieldError(name, message) {
    const field = fields[name];
    const group = groupFor(name);
    if (!field || !group) { showStatus(message, true); return; }
    clearError(name);
    field.classList.add("field-invalid");
    field.setAttribute("aria-invalid", "true");
    group.classList.add("has-error");
    const note = document.createElement("small");
    note.className = "field-error";
    note.setAttribute("role", "alert");
    note.textContent = message;
    group.appendChild(note);
    field.focus({ preventScroll: true });
  }
  function rejectField(name, message) {
    if (fields[name]) fields[name].value = "";
    showFieldError(name, message);
  }
  function showStatus(message, isError) {
    if (!status) return;
    status.hidden = false;
    status.textContent = message;
    status.classList.toggle("is-error", !!isError);
    status.classList.toggle("is-success", !isError);
  }

  Object.entries(fields).forEach(([name, field]) => {
    if (!field) return;
    field.addEventListener("input", () => clearError(name));
    field.addEventListener("change", () => clearError(name));
  });

  const params = new URLSearchParams(window.location.search);
  const queryError = params.get("error");
  if (queryError && messages[queryError]) {
    const [field, message] = messages[queryError];
    if (field) {
      // Legacy non-JavaScript redirects cannot retain values, but still identify the exact field.
      showFieldError(field, message);
    } else showStatus(message, true);
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearAllErrors();

    // Fast client-side checks; the server repeats all validation.
    const fullName = fields.full_name.value.trim();
    const username = fields.username.value.trim();
    const email = fields.email.value.trim();
    const password = fields.password.value;
    const confirm = fields.confirm_password.value;
    if (!fullName || fullName.length > 100) return rejectField("full_name", "Enter your full name (up to 100 characters).");
    if (!/^[A-Za-z0-9._-]{3,50}$/.test(username)) return rejectField("username", "Use 3–50 letters, numbers, dots, underscores, or hyphens.");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 254) return rejectField("email", "Enter a valid email address.");
    if (password.length < 8 || password.length > 20 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password) || !/[^a-zA-Z0-9]/.test(password)) {
      return rejectField("password", "Use 8–20 characters with uppercase, lowercase, a number, and a special character.");
    }
    if (password !== confirm) return rejectField("confirm_password", "Your passwords do not match. Re-enter the confirmation.");

    const submit = form.querySelector('button[type="submit"]');
    const originalText = submit ? submit.textContent : "Create Account";
    if (submit) { submit.disabled = true; submit.textContent = "Creating account…"; }
    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" }
      });
      let payload;
      try { payload = await response.json(); } catch { throw new Error("Kumo returned an unexpected response. Please refresh and try again."); }
      if (payload && payload.ok && payload.redirect) {
        window.location.href = new URL(payload.redirect, window.location.href).href;
        return;
      }
      const known = messages[payload?.error];
      const field = payload?.field || (known ? known[0] : "");
      const message = payload?.message || (known ? known[1] : "Something went wrong. Please try again.");
      if (field && fields[field]) {
        // Only clear the invalid input; keep all other fields exactly as entered.
        if (field === "password") {
          fields.password.value = "";
        } else if (field === "confirm_password") {
          fields.confirm_password.value = "";
        } else {
          fields[field].value = "";
        }
        showFieldError(field, message);
      } else {
        showStatus(message, true);
        if (payload?.redirect && payload.error === "send_failed") {
          const link = document.createElement("a");
          link.href = new URL(payload.redirect, window.location.href).href;
          link.textContent = " Open verification options.";
          status.appendChild(link);
        }
      }
    } catch (error) {
      showStatus(error instanceof Error ? error.message : "Network error. Please try again.", true);
    } finally {
      if (submit) { submit.disabled = false; submit.textContent = originalText; }
    }
  });
})();
