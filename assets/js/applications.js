document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("applicationForm");
    const formStatus = document.getElementById("applicationFormStatus");
    const ministrySelect = document.getElementById("ministry");
    const departmentSelect = document.getElementById("state_department");
    const departmentsByMinistry = window.applicationDepartments || {};
    const fileInputs = document.querySelectorAll(".application-file");
    const maxFileSize = 10 * 1024 * 1024;
    const institutionInput = document.getElementById("institution");
    const institutionSuggestions = document.getElementById("institutionSuggestions");
    const institutionLookupStatus = document.getElementById("institutionLookupStatus");
    const schoolDirectoryUrl = "https://universities.hipolabs.com/search";
    let selectedInstitution = "";
    let searchTimer;
    let activeSearch;

    function updateDepartments(keepSelection = false) {
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

    function closeInstitutionSuggestions() {
        institutionSuggestions.replaceChildren();
        institutionSuggestions.hidden = true;
        institutionInput.setAttribute("aria-expanded", "false");
    }

    function showInstitutionSuggestions(schools) {
        institutionSuggestions.replaceChildren();
        const uniqueSchools = [...new Map(
            schools
                .filter((school) => school && typeof school.name === "string")
                .map((school) => [school.name.trim(), school])
                .filter(([name]) => name.length > 0)
        ).values()].slice(0, 8);

        if (!uniqueSchools.length) {
            institutionLookupStatus.textContent = "No matching university was found in the directory. Try another spelling.";
            closeInstitutionSuggestions();
            return;
        }

        uniqueSchools.forEach((school, index) => {
            const option = document.createElement("button");
            option.type = "button";
            option.className = "list-group-item list-group-item-action";
            option.id = `institution-option-${index}`;
            option.setAttribute("role", "option");
            option.textContent = school.name.trim();
            option.addEventListener("click", () => {
                selectedInstitution = school.name.trim();
                institutionInput.value = selectedInstitution;
                institutionInput.setCustomValidity("");
                institutionLookupStatus.textContent = "University selected from the directory.";
                closeInstitutionSuggestions();
            });
            institutionSuggestions.append(option);
        });

        institutionLookupStatus.textContent = "Choose your university from the matching results.";
        institutionSuggestions.hidden = false;
        institutionInput.setAttribute("aria-expanded", "true");
    }

    institutionInput.addEventListener("input", () => {
        selectedInstitution = "";
        institutionInput.setCustomValidity("");
        closeInstitutionSuggestions();
        window.clearTimeout(searchTimer);
        if (activeSearch) {
            activeSearch.abort();
        }

        const query = institutionInput.value.trim();
        if (query.length < 2) {
            institutionLookupStatus.textContent = "Type at least 2 characters to search the Kenyan university directory.";
            return;
        }

        institutionLookupStatus.textContent = "Searching the university directory…";
        searchTimer = window.setTimeout(async () => {
            activeSearch = new AbortController();
            try {
                const url = new URL(schoolDirectoryUrl);
                url.searchParams.set("country", "Kenya");
                url.searchParams.set("name", query);
                const response = await fetch(url, {
                    headers: { Accept: "application/json" },
                    signal: activeSearch.signal,
                });
                if (!response.ok) {
                    throw new Error("The school directory returned an error.");
                }
                const schools = await response.json();
                if (institutionInput.value.trim() === query) {
                    showInstitutionSuggestions(Array.isArray(schools) ? schools : []);
                }
            } catch (error) {
                if (error.name !== "AbortError") {
                    institutionLookupStatus.textContent = "Could not reach the university directory. Check your connection and try again.";
                }
            }
        }, 300);
    });

    institutionInput.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeInstitutionSuggestions();
        }
        if (event.key === "ArrowDown" && !institutionSuggestions.hidden) {
            event.preventDefault();
            institutionSuggestions.querySelector("button")?.focus();
        }
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".institution-lookup")) {
            closeInstitutionSuggestions();
        }
    });

    fileInputs.forEach((input) => {
        input.addEventListener("change", () => {
            const file = input.files && input.files[0];
            input.setCustomValidity("");
            if (file && file.size > maxFileSize) {
                input.setCustomValidity(`${input.dataset.label} must be no larger than 10 MB.`);
            } else if (file && !file.name.toLowerCase().endsWith(".pdf")) {
                input.setCustomValidity(`${input.dataset.label} must be a PDF file.`);
            }
        });
    });

    form.addEventListener("submit", (event) => {
        event.preventDefault();
        if (institutionInput.value.trim() !== selectedInstitution) {
            institutionInput.setCustomValidity("Choose an institution from the directory suggestions.");
        } else {
            institutionInput.setCustomValidity("");
        }
        if (!form.checkValidity()) {
            event.stopPropagation();
            formStatus.hidden = true;
        } else {
            formStatus.className = "alert alert-info";
            formStatus.textContent = "Your form looks complete. This frontend preview does not submit or save applications yet.";
            formStatus.hidden = false;
        }
        form.classList.add("was-validated");
    });
});