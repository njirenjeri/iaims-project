document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("applicationForm");
    if (!form) return;

    const ministrySelect = document.getElementById("ministry_id");
    const departmentSelect = document.getElementById("department_id");
    const directory = Array.isArray(window.applicationDirectory) ? window.applicationDirectory : [];
    const fileInputs = document.querySelectorAll(".application-file");
    const maxFileSize = 10 * 1024 * 1024;

    /* ---------- Ministry and state department ---------- */

    // Fill the department list for the chosen ministry. After a failed submit the server prints
    // the earlier choice in data-selected so it can be restored.
    function updateDepartments() {
        if (!ministrySelect || !departmentSelect) return;

        const ministryId = Number(ministrySelect.value) || 0;
        const keep = departmentSelect.dataset.selected || departmentSelect.value;
        const ministry = directory.find((item) => item.id === ministryId);
        const departments = ministry ? ministry.departments : [];

        departmentSelect.replaceChildren(new Option(
            departments.length ? "Select state department" : "Select a ministry first",
            ""
        ));
        departmentSelect.disabled = departments.length === 0;

        departments.forEach((department) => {
            departmentSelect.add(new Option(department.name, String(department.id)));
        });

        if (keep && departments.some((department) => String(department.id) === String(keep))) {
            departmentSelect.value = String(keep);
        }
        departmentSelect.dataset.selected = "";
    }

    if (ministrySelect && departmentSelect) {
        ministrySelect.addEventListener("change", () => {
            departmentSelect.dataset.selected = "";
            updateDepartments();
        });
        updateDepartments();
    }

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
