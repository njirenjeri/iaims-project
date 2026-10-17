document.addEventListener("DOMContentLoaded", () => {
    const authForm = document.getElementById("authForm");
    const authActionInput = document.getElementById("authAction");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const passwordFeedback = document.getElementById("passwordFeedback");
    const confirmPasswordFeedback = document.getElementById("confirmPasswordFeedback");
    const confirmPasswordWrapper = document.getElementById("confirmPasswordWrapper");
    const forgotPasswordWrapper = document.getElementById("forgotPasswordWrapper");
    const submitButton = document.getElementById("submitButton");
    const toggleAuthButton = document.getElementById("toggleAuth");
    const togglePrompt = document.getElementById("togglePrompt");

    // false = login, true = registration
    let isRegistering = false;

    const passwordRules = [
        { message: "At least 8 characters", test: (v) => v.length >= 8 },
        { message: "At least one uppercase letter", test: (v) => /[A-Z]/.test(v) },
        { message: "At least one lowercase letter", test: (v) => /[a-z]/.test(v) },
        { message: "At least one special character", test: (v) => /[^A-Za-z0-9\s]/.test(v) },
    ];

    function clearPasswordFeedback() {
        passwordFeedback.replaceChildren();
        passwordFeedback.hidden = true;
    }

    function clearConfirmFeedback() {
        confirmPasswordFeedback.textContent = "";
        confirmPasswordFeedback.className = "small mt-2";
    }

    // Registration only: show the rules the password hasn't met yet.
    function validatePassword() {
        const password = passwordInput.value;

        if (password.length === 0) {
            clearPasswordFeedback();
            return false;
        }

        const missingRules = passwordRules.filter((rule) => !rule.test(password));

        if (missingRules.length === 0) {
            clearPasswordFeedback();
            return true;
        }

        const items = missingRules.map((rule) => {
            const item = document.createElement("div");
            item.className = "text-danger mb-1";

            const icon = document.createElement("i");
            icon.className = "bi bi-exclamation-circle me-2";
            icon.setAttribute("aria-hidden", "true");

            const message = document.createElement("span");
            message.textContent = rule.message;

            item.append(icon, message);
            return item;
        });

        passwordFeedback.replaceChildren(...items);
        passwordFeedback.hidden = false;
        return false;
    }

    function validatePasswordMatch() {
        const confirmation = confirmPasswordInput.value;

        if (confirmation.length === 0) {
            clearConfirmFeedback();
            return false;
        }

        const matches = passwordInput.value === confirmation;
        confirmPasswordFeedback.textContent = matches
            ? "Passwords match."
            : "Passwords do not match.";
        confirmPasswordFeedback.className =
            "small mt-2 " + (matches ? "text-success" : "text-danger");

        return matches;
    }

    // Eye buttons
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            const icon = button.querySelector("i");
            const show = input.type === "password";

            input.type = show ? "text" : "password";
            icon.classList.toggle("bi-eye", !show);
            icon.classList.toggle("bi-eye-slash", show);
            button.setAttribute("aria-label", show ? "Hide password" : "Show password");
        });
    });

    passwordInput.addEventListener("input", () => {
        if (!isRegistering) return; // no rule checking on login

        validatePassword();

        if (confirmPasswordInput.value.length > 0) {
            validatePasswordMatch();
        }
    });

    confirmPasswordInput.addEventListener("input", () => {
        if (isRegistering) validatePasswordMatch();
    });

    toggleAuthButton.addEventListener("click", () => {
        isRegistering = !isRegistering;

        submitButton.textContent = isRegistering ? "Create account" : "Sign in";
        togglePrompt.textContent = isRegistering
            ? "Already have an account?"
            : "Don't have an account?";
        toggleAuthButton.textContent = isRegistering ? "Sign in" : "Register";

        authActionInput.value = isRegistering ? "register" : "login";
        confirmPasswordWrapper.hidden = !isRegistering;
        confirmPasswordInput.required = isRegistering;
        forgotPasswordWrapper.hidden = isRegistering;
        passwordInput.autocomplete = isRegistering ? "new-password" : "current-password";

        if (isRegistering) {
            // Check anything already typed in the password field.
            validatePassword();
        } else {
            confirmPasswordInput.value = "";
            clearConfirmFeedback();
            clearPasswordFeedback();
        }

        authForm.classList.remove("was-validated");
    });

    authForm.addEventListener("submit", (event) => {
        event.preventDefault();

        if (!authForm.checkValidity()) {
            authForm.classList.add("was-validated");
            authForm.reportValidity();
            return;
        }

        if (isRegistering && !(validatePassword() & validatePasswordMatch())) {
            return;
        }

        authForm.submit();
    });
});
