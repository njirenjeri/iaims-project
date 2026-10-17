document.addEventListener("DOMContentLoaded", () => {
    // Each profile section is its own form and is saved independently.
    const sectionForms = document.querySelectorAll(".profile-section-form");
    if (!sectionForms.length) return;

    /* ---------- Identity document ---------- */

    const identityType = document.getElementById("identity_type");
    const identityInput = document.getElementById("identity_number");
    const identityLabel = document.getElementById("identityNumberLabel");

    function updateIdentityLabel() {
        const isPassport = identityType.value === "passport";
        const label = isPassport ? "Passport number" : "National ID number";
        identityLabel.replaceChildren(document.createTextNode(label + " "));

        const requiredMark = document.createElement("span");
        requiredMark.className = "text-danger";
        requiredMark.textContent = "*";
        identityLabel.append(requiredMark);

        // These patterns match the server-side rules in validateProfileSection().
        if (isPassport) {
            identityInput.pattern = "[A-Za-z0-9]{6,20}";
            identityInput.inputMode = "text";
            identityInput.placeholder = "Enter passport number";
            identityInput.title = "Passport numbers are 6 to 20 letters or digits.";
            return;
        }

        identityInput.pattern = "[0-9]{7,8}";
        identityInput.inputMode = "numeric";
        identityInput.placeholder = "e.g. 12345678";
        identityInput.title = "National ID numbers are 7 or 8 digits.";
    }

    if (identityType && identityInput && identityLabel) {
        identityType.addEventListener("change", updateIdentityLabel);
        updateIdentityLabel();
    }

    /* ---------- Alternate phone must differ from the main number ---------- */

    const phoneInput = document.getElementById("phone_number");
    const alternatePhoneInput = document.getElementById("alternate_phone_number");
    const cleanPhone = (value) => value.replace(/[\s().-]/g, "");

    function checkAlternatePhone() {
        const isSame =
            alternatePhoneInput.value.trim() !== "" &&
            cleanPhone(alternatePhoneInput.value) === cleanPhone(phoneInput.value);

        alternatePhoneInput.setCustomValidity(
            isSame ? "The alternate phone number must differ from your main number." : ""
        );
    }

    if (phoneInput && alternatePhoneInput) {
        phoneInput.addEventListener("input", checkAlternatePhone);
        alternatePhoneInput.addEventListener("input", checkAlternatePhone);
        checkAlternatePhone();
    }

    /* ---------- Institution lookup (academic section) ---------- */

    const institutionInput = document.getElementById("institution");
    const institutionSuggestions = document.getElementById("institutionSuggestions");
    const institutionLookupStatus = document.getElementById("institutionLookupStatus");
    const schoolDirectoryUrl = "https://universities.hipolabs.com/search";
    // A saved institution counts as already chosen.
    let selectedInstitution = institutionInput ? institutionInput.value.trim() : "";
    let searchTimer;
    let activeSearch;

    function closeInstitutionSuggestions() {
        institutionSuggestions.replaceChildren();
        institutionSuggestions.hidden = true;
        institutionInput.setAttribute("aria-expanded", "false");
    }

    function chooseInstitution(name, message) {
        selectedInstitution = name;
        institutionInput.value = name;
        institutionInput.setCustomValidity("");
        institutionLookupStatus.textContent = message;
        closeInstitutionSuggestions();
        institutionInput.focus();
    }

    function buildInstitutionOption(name, index, isUnlisted) {
        const option = document.createElement("button");
        option.type = "button";
        option.className = "list-group-item list-group-item-action" + (isUnlisted ? " fst-italic" : "");
        option.id = `institution-option-${index}`;
        option.setAttribute("role", "option");
        option.textContent = isUnlisted ? `Use "${name}" (not in the list)` : name;
        option.addEventListener("click", () => {
            chooseInstitution(
                name,
                isUnlisted ? "Using the institution name you typed." : "Institution selected from the directory."
            );
        });
        return option;
    }

    // The directory only covers universities. Colleges and institutes
    // (common for diplomas and certificates) can use what the student typed.
    function showInstitutionSuggestions(schools, query) {
        institutionSuggestions.replaceChildren();

        const names = [...new Set(
            schools
                .filter((school) => school && typeof school.name === "string")
                .map((school) => school.name.trim())
                .filter((name) => name.length > 0)
        )].slice(0, 8);

        names.forEach((name, index) => {
            institutionSuggestions.append(buildInstitutionOption(name, index, false));
        });
        institutionSuggestions.append(buildInstitutionOption(query, names.length, true));

        institutionLookupStatus.textContent = names.length
            ? "Choose your institution from the results, or use what you typed."
            : "No match in the directory. You can use what you typed.";
        institutionSuggestions.hidden = false;
        institutionInput.setAttribute("aria-expanded", "true");
    }

    function syncInstitutionValidity() {
        institutionInput.setCustomValidity(
            institutionInput.value.trim() !== selectedInstitution
                ? "Choose your institution from the suggestions."
                : ""
        );
    }

    if (institutionInput && institutionSuggestions && institutionLookupStatus) {
        institutionInput.addEventListener("input", () => {
            selectedInstitution = "";
            institutionInput.setCustomValidity("");
            closeInstitutionSuggestions();
            window.clearTimeout(searchTimer);
            if (activeSearch) activeSearch.abort();

            const query = institutionInput.value.trim();
            if (query.length < 2) {
                institutionLookupStatus.textContent = "Type at least 2 characters to search the directory.";
                return;
            }

            institutionLookupStatus.textContent = "Searching the directory…";
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
                    if (!response.ok) throw new Error("The directory returned an error.");
                    const schools = await response.json();
                    if (institutionInput.value.trim() === query) {
                        showInstitutionSuggestions(Array.isArray(schools) ? schools : [], query);
                    }
                } catch (error) {
                    if (error.name !== "AbortError" && institutionInput.value.trim() === query) {
                        showInstitutionSuggestions([], query);
                        institutionLookupStatus.textContent =
                            "Could not reach the directory. You can use what you typed.";
                    }
                }
            }, 300);
        });

        institutionInput.addEventListener("keydown", (event) => {
            if (event.key === "Escape") closeInstitutionSuggestions();
            if (event.key === "ArrowDown" && !institutionSuggestions.hidden) {
                event.preventDefault();
                institutionSuggestions.querySelector("button")?.focus();
            }
        });

        institutionSuggestions.addEventListener("keydown", (event) => {
            const options = [...institutionSuggestions.querySelectorAll("button")];
            const index = options.indexOf(document.activeElement);

            if (event.key === "ArrowDown") {
                event.preventDefault();
                options[Math.min(index + 1, options.length - 1)]?.focus();
            } else if (event.key === "ArrowUp") {
                event.preventDefault();
                if (index <= 0) institutionInput.focus();
                else options[index - 1].focus();
            } else if (event.key === "Escape") {
                closeInstitutionSuggestions();
                institutionInput.focus();
            }
        });

        document.addEventListener("click", (event) => {
            if (!event.target.closest(".institution-lookup")) closeInstitutionSuggestions();
        });
    }

    /* ---------- County, constituency and ward lookups (residence section) ---------- */

    // The list ships with the project (assets/data/kenya-locations.json):
    // { "County": { "Constituency": ["Ward", ...] } }, using the same county names as the dropdown.
    const residenceForm = document.getElementById("residence");
    const countySelect = document.getElementById("county");
    const constituencySelect = document.getElementById("constituency");
    const wardSelect = document.getElementById("ward");
    const locationLookupStatus = document.getElementById("locationLookupStatus");
    let locations = {};

    function resetSelect(select, placeholder) {
        select.replaceChildren(new Option(placeholder, ""));
        select.value = "";
        select.disabled = true;
    }

    function updateConstituencies(keepSelection = false) {
        const selectedValue = keepSelection ? constituencySelect.dataset.selected : "";
        const names = Object.keys(locations[countySelect.value] || {});

        resetSelect(constituencySelect, names.length ? "Select constituency" : "Select a county first");
        resetSelect(wardSelect, "Select a constituency first");

        names.forEach((name) => constituencySelect.add(new Option(name, name)));
        constituencySelect.disabled = names.length === 0;

        if (names.includes(selectedValue)) {
            constituencySelect.value = selectedValue;
            updateWards(true);
        }
        constituencySelect.dataset.selected = "";
    }

    function updateWards(keepSelection = false) {
        const selectedValue = keepSelection ? wardSelect.dataset.selected : "";
        const names = (locations[countySelect.value] || {})[constituencySelect.value] || [];

        resetSelect(wardSelect, names.length ? "Select ward" : "Select a constituency first");

        names.forEach((name) => wardSelect.add(new Option(name, name)));
        wardSelect.disabled = names.length === 0;

        if (names.includes(selectedValue)) {
            wardSelect.value = selectedValue;
        }
        wardSelect.dataset.selected = "";
    }

    async function loadLocationData() {
        try {
            const response = await fetch(residenceForm.dataset.locationsUrl, {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Location data request failed.");

            locations = await response.json();
            if (!locations || typeof locations !== "object" || Array.isArray(locations)) {
                throw new Error("Location data is invalid.");
            }

            locationLookupStatus.textContent = "Select your county, then choose your constituency and ward.";
            updateConstituencies(true);
        } catch (error) {
            locationLookupStatus.textContent =
                "Could not load the constituency and ward list. Reload the page, and contact support if this keeps happening.";
            resetSelect(constituencySelect, "Location list unavailable");
            resetSelect(wardSelect, "Location list unavailable");
        }
    }

    if (residenceForm && countySelect && constituencySelect && wardSelect && locationLookupStatus) {
        countySelect.addEventListener("change", () => updateConstituencies());
        constituencySelect.addEventListener("change", () => updateWards());
        loadLocationData();
    }

    /* ---------- Submitting a section ---------- */

    sectionForms.forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (institutionInput && form.contains(institutionInput)) {
                syncInstitutionValidity();
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                form.classList.add("was-validated");
                form.querySelector(":invalid")?.focus();
                return;
            }

            // Prevent double submits while the request is in flight.
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.dataset.originalHtml = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving…';
            }
        });
    });

    // Restore the buttons if the page comes back from the browser's back/forward cache.
    window.addEventListener("pageshow", () => {
        document.querySelectorAll(".profile-section-form button[type='submit']").forEach((button) => {
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
                button.disabled = false;
            }
        });
    });
});
