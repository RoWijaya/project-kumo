const toggles = document.querySelectorAll(".toggle-password");

toggles.forEach((toggle) => {
  toggle.addEventListener("click", () => {
    const input = toggle.previousElementSibling;
    const icon = toggle.querySelector("span");

    if (input.type === "password") {
      input.type = "text";
      icon.textContent = "visibility_off";
    } else {
      input.type = "password";
      icon.textContent = "visibility";
    }
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const urlParams = new URLSearchParams(window.location.search);
  const error = urlParams.get("error");
  const errorContainer = document.getElementById("error-message");
  const errorText = document.getElementById("error-text");

  if (error && errorContainer && errorText) {
    errorContainer.style.display = "flex";

    switch (error) {
      case "password_mismatch":
        errorText.textContent = "Your passwords don't match. Please try again.";
        break;
      case "email_exists":
        errorText.textContent = "An account with this email already exists.";
        break;
      case "password_length":
        errorText.textContent =
          "Your password must be between 8 and 20 characters long.";
        break;
      case "password_complexity":
        errorText.textContent =
          "Password needs an uppercase, lowercase, number, and special character.";
        break;
      case "database":
        errorText.textContent =
          "A server error occurred. Please try again later.";
        break;
      default:
        errorText.textContent = "Something went wrong. Please try again.";
    }
  }
});
