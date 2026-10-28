// Behaviour shared by the admin pages: invite form, user detail modals and the directory page.
// Each feature switches itself on only if its elements are on the page.
document.addEventListener("DOMContentLoaded", () => {
    const directory = Array.isArray(window.adminDirectory) ? window.adminDirectory : [];

    /* ---------- Role -> ministry / department pickers ---------- */

    document.querySelectorAll("[data-scope-picker]").forEach((container) => {
        const roleInputs = [...container.querySelectorAll('[name="role"]')];
        const ministrySelect = container.querySelector('[name="ministry_id"]');
        const departmentSelect = container.querySelector('[name="department_id"]');
        const ministryWrap = container.querySelector("[data-scope-ministry]");
        const departmentWrap = container.querySelector("[data-scope-department]");

        if (!roleInputs.length || !ministrySelect || !departmentSelect || !ministryWrap || !departmentWrap) return;

        const currentRole = () => {
            if (roleInputs[0].tagName === "SELECT") return roleInputs[0].value;
            const checked = roleInputs.find((input) => input.checked);
            return checked ? checked.value : "";
        };

        // Fill the department list for the chosen ministry, keeping an earlier choice if it still fits.
        function fillDepartments() {
            const ministryId = Number(ministrySelect.value) || 0;
            const keep = departmentSelect.dataset.selected || departmentSelect.value;
            const ministry = directory.find((item) => item.id === ministryId);
            const departments = ministry ? ministry.departments : [];

            departmentSelect.replaceChildren(
                new Option(departments.length ? "Select state department" : "Select a ministry first", "")
            );
            departments.forEach((department) => {
                departmentSelect.add(new Option(department.name, String(department.id)));
            });

            if (keep && departments.some((department) => String(department.id) === String(keep))) {
                departmentSelect.value = String(keep);
            }
            departmentSelect.dataset.selected = "";
        }

        // HR roles need a ministry; supervisors need a department; system admins need neither.
        function update() {
            const role = currentRole();
            const needsMinistry = role === "HR" || role === "HR_ADMIN" || role === "SUPERVISOR";
            const needsDepartment = role === "SUPERVISOR";

            ministryWrap.hidden = !needsMinistry;
            departmentWrap.hidden = !needsDepartment;

            // A ministry fixed by the server (HR admins) stays as it is.
            if (ministrySelect.dataset.locked !== "true") {
                ministrySelect.disabled = !needsMinistry;
                ministrySelect.required = needsMinistry;
            }

            departmentSelect.disabled = !needsDepartment;
            departmentSelect.required = needsDepartment;
        }

        roleInputs.forEach((input) => input.addEventListener("change", update));
        ministrySelect.addEventListener("change", () => {
            departmentSelect.dataset.selected = "";
            fillDepartments();
        });

        fillDepartments();
        update();
    });

    /* ---------- Confirmation modal on the user detail page ---------- */

    const actionModal = document.getElementById("actionModal");

    if (actionModal) {
        const title = actionModal.querySelector("[data-modal-title]");
        const body = actionModal.querySelector("[data-modal-body]");
        const actionInput = actionModal.querySelector('[name="action"]');
        const reasonWrap = actionModal.querySelector("[data-reason-wrap]");
        const reasonInput = actionModal.querySelector('[name="reason"]');
        const reasonStar = actionModal.querySelector("[data-reason-star]");
        const submit = actionModal.querySelector("[data-modal-submit]");

        actionModal.addEventListener("show.bs.modal", (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const reason = trigger.dataset.reason || "none"; // required | optional | none

            actionInput.value = trigger.dataset.action || "";
            title.textContent = trigger.dataset.title || "";
            body.textContent = trigger.dataset.body || "";

            reasonInput.value = "";
            reasonInput.required = reason === "required";
            reasonWrap.hidden = reason === "none";
            reasonStar.hidden = reason !== "required";

            submit.className = "btn " + (trigger.dataset.danger === "1" ? "btn-danger" : "btn-primary");
        });
    }

    /* ---------- Rename modal on the ministries page ---------- */

    const renameModal = document.getElementById("renameModal");

    if (renameModal) {
        renameModal.addEventListener("show.bs.modal", (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            renameModal.querySelector('[name="action"]').value = trigger.dataset.action || "";
            renameModal.querySelector('[name="id"]').value = trigger.dataset.id || "";
            renameModal.querySelector("[data-modal-title]").textContent = trigger.dataset.title || "Rename";
            renameModal.querySelector('[name="name"]').value = trigger.dataset.name || "";
        });

        renameModal.addEventListener("shown.bs.modal", () => {
            renameModal.querySelector('[name="name"]').select();
        });
    }

    /* ---------- Copy a link ---------- */

    document.querySelectorAll("[data-copy-target]").forEach((button) => {
        button.addEventListener("click", async () => {
            const input = document.getElementById(button.dataset.copyTarget);
            if (!input) return;

            try {
                await navigator.clipboard.writeText(input.value);
            } catch (error) {
                // Older browsers or non-secure pages: fall back to selecting the text.
                input.select();
                document.execCommand("copy");
            }

            const original = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>Copied';
            window.setTimeout(() => {
                button.innerHTML = original;
            }, 2000);
        });
    });

    /* ---------- Confirm before submitting marked forms ---------- */

    document.querySelectorAll("form[data-confirm]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    /* ---------- Bootstrap validation styling on forms that ask for it ---------- */

    document.querySelectorAll("form.needs-validation").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                form.classList.add("was-validated");
                form.querySelector(":invalid")?.focus();
            }
        });
    });
});
