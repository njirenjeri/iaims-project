
document.addEventListener("DOMContentLoaded", () => {
    // Get references to the elements used by the authentication card.
    const authForm = document.getElementById("authForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirmPassword");

    const passwordFeedback = document.getElementById("passwordFeedback");
    const confirmPasswordFeedback = document.getElementById(
        "confirmPasswordFeedback"
    );

    const confirmPasswordWrapper = document.getElementById(
        "confirmPasswordWrapper"
    );

    const forgotPasswordWrapper = document.getElementById(
        "forgotPasswordWrapper"
    );

    const submitButton = document.getElementById("submitButton");
    const toggleAuthButton = document.getElementById("toggleAuth");
    const togglePrompt = document.getElementById("togglePrompt");

    // False means login mode; true means registration mode.
    let isRegistering = false;

    // Define the password rules in one place.
    const passwordRules = [
        {
            message: "At least 8 characters",
            test: (value) => value.length >= 8,
        },
        {
            message: "At least one uppercase letter",
            test: (value) => /[A-Z]/.test(value),
        },
        {
            message: "At least one lowercase letter",
            test: (value) => /[a-z]/.test(value),
        },
        {
            message: "At least one special character",
            test: (value) => /[^A-Za-z0-9\s]/.test(value),
        },
    ];

    // Check the password and display only the rules it has not met.
    function validatePassword() {
        const password = passwordInput.value;

        // Hide the feedback until the user starts typing.
        if (password.length === 0) {
            passwordFeedback.replaceChildren();
            passwordFeedback.hidden = true;
            return false;
        }

        const missingRules = passwordRules.filter(
            (rule) => !rule.test(password)
        );

        // If all rules pass, remove the feedback.
        if (missingRules.length === 0) {
            passwordFeedback.replaceChildren();
            passwordFeedback.hidden = true;
            return true;
        }

        // Build the feedback safely using DOM elements.
        const feedbackItems = missingRules.map((rule) => {
            const item = document.createElement("div");
            item.className = "password-rule";

            const icon = document.createElement("i");
            icon.className = "bi bi-exclamation-circle me-2";
            icon.setAttribute("aria-hidden", "true");

            const message = document.createElement("span");
            message.textContent = rule.message;

            item.append(icon, message);

            return item;
        });

        passwordFeedback.replaceChildren(...feedbackItems);
        passwordFeedback.hidden = false;

        return false;
    }

    // Check whether the confirmation matches the original password.
    function validatePasswordMatch() {
        const password = passwordInput.value;
        const confirmation = confirmPasswordInput.value;

        if (confirmation.length === 0) {
            confirmPasswordFeedback.textContent = "";
            confirmPasswordFeedback.className = "small mt-2";
            return false;
        }

        if (password === confirmation) {
            confirmPasswordFeedback.textContent = "Passwords match.";
            confirmPasswordFeedback.className =
                "small mt-2 password-match";

            return true;
        }

        confirmPasswordFeedback.textContent =
            "Passwords do not match.";

        confirmPasswordFeedback.className =
            "small mt-2 password-mismatch";

        return false;
    }

    // Show or hide the password for whichever eye button was clicked.
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const inputId = button.dataset.passwordToggle;
            const input = document.getElementById(inputId);
            const icon = button.querySelector("i");

            const showPassword = input.type === "password";

            input.type = showPassword ? "text" : "password";

            icon.classList.toggle("bi-eye", !showPassword);
            icon.classList.toggle("bi-eye-slash", showPassword);

            button.setAttribute(
                "aria-label",
                showPassword ? "Hide password" : "Show password"
            );
        });
    });

    // Update password feedback whenever the password changes.
    passwordInput.addEventListener("input", () => {
        validatePassword();

        // The confirmation can stop matching if the original password changes.
        if (isRegistering && confirmPasswordInput.value.length > 0) {
            validatePasswordMatch();
        }
    });

    // Check the confirmation as the user types.
    if (confirmPasswordInput){
        confirmPasswordInput.addEventListener(
            "input",
            validatePasswordMatch
        );
    }

    // Switch between the login and registration views.
    toggleAuthButton.addEventListener("click", () => {
        isRegistering = !isRegistering;

        if (isRegistering) {
            // Registration view.
            submitButton.textContent = "Create account";
            togglePrompt.textContent = "Already have an account?";
            toggleAuthButton.textContent = "Sign in";

            confirmPasswordWrapper.hidden = false;
            confirmPasswordInput.required = true;

            forgotPasswordWrapper.hidden = true;
            passwordInput.autocomplete = "new-password";
        } else {
            // Login view.
            submitButton.textContent = "Sign in";
            togglePrompt.textContent = "Don't have an account?";
            toggleAuthButton.textContent = "Register";

            confirmPasswordWrapper.hidden = true;
            confirmPasswordInput.required = false;

            forgotPasswordWrapper.hidden = false;
            passwordInput.autocomplete = "current-password";

            // Clear registration-only feedback when returning to login.
            confirmPasswordInput.value = "";
            confirmPasswordFeedback.textContent = "";
            confirmPasswordFeedback.className = "small mt-2";

            passwordFeedback.replaceChildren();
            passwordFeedback.hidden = true;
        }

        // Reset browser validation styling when switching views.
        authForm.classList.remove("was-validated");
    });

    // Front-end validation only; authentication will be connected later.
    authForm.addEventListener("submit", (event) => {
        event.preventDefault();

        if (!authForm.checkValidity()) {
            authForm.classList.add("was-validated");
            authForm.reportValidity();
            return;
        }

        if (isRegistering) {
            const passwordIsValid = validatePassword();
            const passwordsMatch = validatePasswordMatch();

            if (!passwordIsValid || !passwordsMatch) {
                return;
            }

            alert(
                "The form is valid. Registration still needs its PHP backend."
            );
            return;
        }

        alert(
            "The form is valid. Login still needs its PHP backend."
        );
    });

    // Keep the forgot-password link from navigating to a blank page
    // until we implement password recovery.
    document.getElementById("forgotPasswordLink").addEventListener(
        "click",
        (event) => {
            event.preventDefault();

            alert("Password recovery has not been implemented yet.");
        }
    );
});
