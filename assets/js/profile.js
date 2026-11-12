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
        const pwdCheckbox = document.getElementById("is_pwd");
    const disabilityDetailsContainer = document.getElementById("disability_details_container");
    const disabilityDetailsInput = document.getElementById("disability_details");

    if (pwdCheckbox && disabilityDetailsContainer && disabilityDetailsInput) {
        // Check on page load
        if (pwdCheckbox.checked) {
            disabilityDetailsContainer.classList.remove("d-none");
        }
        // Check when the user clicks the checkbox
        pwdCheckbox.addEventListener("change", function() {
            if (this.checked) {
                disabilityDetailsContainer.classList.remove("d-none"); 
            } else {
                disabilityDetailsContainer.classList.add("d-none"); 
                disabilityDetailsInput.value = ""; 
            }
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
