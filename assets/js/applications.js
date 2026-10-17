document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("applicationForm");
    if (!form) return;

    const ministrySelect = document.getElementById("ministry");
    const departmentSelect = document.getElementById("state_department");
    const departmentsByMinistry = window.applicationDepartments || {};
    const fileInputs = document.querySelectorAll(".application-file");
    const maxFileSize = 10 * 1024 * 1024;

    /* ---------- Ministry and state department ---------- */

    function updateDepartments(keepSelection = false) {
        // After a failed submit the server prints the earlier choice in data-selected.
        const previouslySelected = keepSelection
            ? departmentSelect.value || departmentSelect.dataset.selected
            : "";
        const departments = departmentsByMinistry[ministrySelect.value] || [];

        departmentSelect.replaceChildren(new Option(
            departments.length ? "Select state department" : "Select a ministry first",
            ""
        ));
        departmentSelect.disabled = departments.length === 0;

        departments.forEach((department) => {
            departmentSelect.add(new Option(department, department));
        });

        if (departments.includes(previouslySelected)) {
            departmentSelect.value = previouslySelected;
        }
        departmentSelect.dataset.selected = "";
    }

    ministrySelect.addEventListener("change", () => updateDepartments());
    updateDepartments(true);

    /* ---------- PDF checks (the server repeats these) ---------- */

    fileInputs.forEach((input) => {
        input.addEventListener("change", () => {
            const file = input.files && input.files[0];
            input.setCustomValidity("");

            if (!file) return;

            if (file.size === 0) {
                input.setCustomValidity(`${input.dataset.label} is empty.`);
            } else if (file.size > maxFileSize) {
                input.setCustomValidity(`${input.dataset.label} must be no larger than 10 MB.`);
            } else if (!file.name.toLowerCase().endsWith(".pdf")) {
                input.setCustomValidity(`${input.dataset.label} must be a PDF file.`);
            }
        });
    });

    /* ---------- Submitting ---------- */

    form.addEventListener("submit", (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add("was-validated");
            form.querySelector(":invalid")?.focus();
            return;
        }

        // Uploading six PDFs takes a moment; stop impatient double clicks.
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Uploading…';
        }
    });

    // Restore the button if the page comes back from the browser's back/forward cache.
    window.addEventListener("pageshow", () => {
        const button = form.querySelector('button[type="submit"]');
        if (button && button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            button.disabled = false;
        }
    });
});
