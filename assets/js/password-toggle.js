// Show/hide buttons for password fields: <button data-password-toggle="inputId">.
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;

            const show = input.type === "password";
            input.type = show ? "text" : "password";
            button.querySelector("i").className = show ? "bi bi-eye-slash" : "bi bi-eye";
            button.setAttribute("aria-label", show ? "Hide password" : "Show password");
        });
    });
});
