(() => {
  const password = document.getElementById("password");
  const toggleButton = document.querySelector(".toggle-password");

  if (password && toggleButton) {
    const toggleIcon = toggleButton.querySelector("span");
    toggleButton.addEventListener("click", () => {
      const shouldShow = password.type === "password";
      password.type = shouldShow ? "text" : "password";
      if (toggleIcon) toggleIcon.textContent = shouldShow ? "visibility_off" : "visibility";
      toggleButton.setAttribute("aria-label", shouldShow ? "Hide password" : "Show password");
    });
  }

  const params = new URLSearchParams(window.location.search);
  const box = document.getElementById("login-message");
  const text = document.getElementById("login-message-text");
  const messages = {
    registered: "Your account has been created. Check your email to verify it before signing in.",
    verified: "Your email is verified. You can now sign in.",
    email_unverified: "Please verify your email before signing in. Use the link sent to your inbox or request a new one.",
    reset_success: "Your password has been updated. Sign in with your new password.",
    reset_invalid: "That password reset link is invalid or expired. Request a new one.",
    logged_out: "You have been signed out.",
    invalid: "The email/username or password is incorrect.",
    database: "Kumo could not complete the request. Please check the database configuration.",
    csrf: "Your secure form session expired. Refresh the page and try again.",
    rate_limited: "Too many sign-in attempts. Please wait 15 minutes and try again.",
    google_failed: "Google sign-in failed. Please try again.",
    google_email: "Google did not return a verified email address.",
    google_not_configured: "Google sign-in is not configured yet. Add the OAuth settings to Kumo's private config file.",
    two_factor_unavailable: "Two-factor authentication is flagged for this account, but its enrollment and verification screens are not configured in this build. Ask the project owner to finish the 2FA setup before signing in.",
  };
  const key = params.has("verified") ? "verified" : (params.has("registered") ? "registered" : (params.has("reset") ? "reset_success" : (params.get("error") || (params.has("logged_out") ? "logged_out" : ""))));
  if (box) box.hidden = true;
  if (key && box && text) {
    const message = messages[key] || (params.has("error")
      ? "We couldn't sign you in. Please check your details and try again."
      : "");
    if (message) {
      text.textContent = message;
      box.classList.toggle("alert-error", key !== "registered" && key !== "logged_out");
      box.classList.toggle("alert-success", key === "registered" || key === "verified" || key === "logged_out");
      if (key === "email_unverified") {
        const link = document.createElement("a");
        link.href = "verify-email.html";
        link.textContent = " Request a verification link";
        text.appendChild(link);
      }
      box.hidden = false;
    }
  }

  const googleButton = document.querySelector(".google-btn");
  if (googleButton) {
    googleButton.addEventListener("click", () => {
      window.location.assign("../assets/backend/google_login.php");
    });
  }
})();
