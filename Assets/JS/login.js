const password = document.getElementById("password");

const toggleButton = document.querySelector(".toggle-password");

const toggleIcon = toggleButton.querySelector("span");

toggleButton.addEventListener("click", () => {

    if(password.type === "password"){

        password.type = "text";
        toggleIcon.textContent = "visibility_off";

    }else{

        password.type = "password";
        toggleIcon.textContent = "visibility";

    }

});