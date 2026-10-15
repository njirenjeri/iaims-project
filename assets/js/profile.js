document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("profileForm");
    if (!form) return;

    const identityType = document.getElementById("identity_type");
    const identityInput = document.getElementById("identity_number");
    const identityLabel = document.getElementById("identityNumberLabel");
    const requiredFields = [...form.querySelectorAll("[required]")];
    const completionCount = document.getElementById("completionCount");
    const formStatus = document.getElementById("profileFormStatus");
    const countySelect = document.getElementById("county");
    const constituencySelect = document.getElementById("constituency");
    const wardSelect = document.getElementById("ward");
    const locationLookupStatus = document.getElementById("locationLookupStatus");
    const locationDataUrl = "https://raw.githubusercontent.com/alvinchesaro/Kenya-Counties-SubCounties-and-Wards/main/Kenya-Counties-SubCounties-and-Wards.json";
    let constituenciesByCounty = {};
    const nairobiConstituencies = [
        "Dagoretti North", "Dagoretti South", "Embakasi Central", "Embakasi East",
        "Embakasi North", "Embakasi South", "Embakasi West", "Kamukunji",
        "Kasarani", "Kibra", "Lang'ata", "Makadara", "Mathare", "Roysambu",
        "Ruaraka", "Starehe", "Westlands",
    ];
    const nairobiWardsByConstituency = {
        "Dagoretti North": ["Kilimani", "Kawangware", "Gatina", "Kileleshwa", "Kabiro"],
        "Dagoretti South": ["Mutu-ini", "Ngando", "Riruta", "Uthiru/Ruthimitu", "Waithaka"],
        "Embakasi Central": ["Kayole North", "Kayole Central", "Kayole South", "Komarock", "Matopeni/Spring Valley"],
        "Embakasi East": ["Upper Savannah", "Lower Savannah", "Embakasi", "Utawala", "Mihango"],
        "Embakasi North": ["Kariobangi North", "Dandora Area I", "Dandora Area II", "Dandora Area III", "Dandora Area IV"],
        "Embakasi South": ["Imara Daima", "Kwa Njenga", "Kwa Reuben", "Pipeline", "Kware"],
        "Embakasi West": ["Umoja I", "Umoja II", "Mowlem", "Kariobangi South", "Maringo/Hamza"],
        "Kamukunji": ["Eastleigh South", "Airbase", "California", "Ngara", "Nairobi Central"],
        "Kasarani": ["Clay City", "Mwiki", "Kasarani", "Njiru", "Ruai"],
        "Kibra": ["Laini Saba", "Lindi", "Makina", "Woodley/Kenyatta Golf Course", "Sarang'ombe"],
        "Lang'ata": ["Karen", "Nairobi West", "Mugumoini", "South C", "Nyayo Highrise"],
        "Makadara": ["Viwandani", "Harambee", "Makongeni", "Pumwani", "Eastleigh North"],
        "Mathare": ["Mabatini", "Huruma", "Ngei", "Mlango Kubwa", "Kiamaiko"],
        "Roysambu": ["Githurai", "Kahawa West", "Zimmerman", "Roysambu", "Kahawa"],
        "Ruaraka": ["Baba Dogo", "Utalii", "Mathare North", "Lucky Summer", "Korogocho"],
        "Starehe": ["Pangani", "Ziwani/Kariokor", "Landimawe", "Nairobi South", "Hospital"],
        "Westlands": ["Kitisuru", "Parklands/Highridge", "Karura", "Kangemi", "Mountain View"],
    };

    function updateIdentityLabel() {
        const isPassport = identityType.value === "passport";
        const label = isPassport ? "Passport number" : "National ID number";
        identityLabel.replaceChildren(document.createTextNode(label + " "));

        const requiredMark = document.createElement("span");
        requiredMark.className = "text-danger";
        requiredMark.textContent = "*";
        identityLabel.append(requiredMark);

        if (isPassport) {
            identityInput.pattern = ".{3,}";
            identityInput.inputMode = "text";
            identityInput.placeholder = "Enter passport number";
            identityInput.title = "Enter your passport number.";
            return;
        }

        identityInput.pattern = "[1-9][0-9]{7,}";
        identityInput.inputMode = "numeric";
        identityInput.placeholder = "e.g. 12345678";
        identityInput.title = "National ID must start with a digit and contain at least 8 characters.";
    }

    function updateCompletion() {
        const enabledFields = requiredFields.filter((field) => !field.disabled);
        const completed = enabledFields.filter((field) => field.value.trim() !== "").length;
        const percent = enabledFields.length
            ? Math.round((completed / enabledFields.length) * 100)
            : 0;
        completionCount.textContent = `${percent}%`;
    }

    function normalizeCountyName(name) {
        return name.toLowerCase().replace(/[^a-z0-9]/g, "");
    }

    function findCountyData(countyName) {
        const matchingKey = Object.keys(constituenciesByCounty).find(
            (name) => normalizeCountyName(name) === normalizeCountyName(countyName)
        );
        return matchingKey ? constituenciesByCounty[matchingKey] : null;
    }

    function findConstituencyWards(countyData, constituencyName) {
        if (!countyData || typeof countyData !== "object") return null;
        const matchingKey = Object.keys(countyData).find(
            (name) => normalizeCountyName(name) === normalizeCountyName(constituencyName)
        );
        return matchingKey ? countyData[matchingKey] : null;
    }

    function resetSelect(select, placeholder) {
        select.replaceChildren(new Option(placeholder, ""));
        select.value = "";
        select.disabled = true;
    }

    function updateConstituencies(keepSelection = false) {
        const selectedValue = keepSelection ? constituencySelect.dataset.selected : "";
        const countyData = findCountyData(countySelect.value);
        const isNairobi = normalizeCountyName(countySelect.value) === "nairobi";
        const names = isNairobi
            ? nairobiConstituencies
            : countyData && typeof countyData === "object" ? Object.keys(countyData) : [];

        resetSelect(constituencySelect, names.length ? "Select constituency" : "No constituencies available");
        resetSelect(wardSelect, "Select a constituency first");
        constituencySelect.disabled = names.length === 0;

        names.forEach((name) => constituencySelect.add(new Option(name, name)));
        if (names.includes(selectedValue)) {
            constituencySelect.value = selectedValue;
            updateWards(true);
        }
        constituencySelect.dataset.selected = "";
        updateCompletion();
    }

    function updateWards(keepSelection = false) {
        const selectedValue = keepSelection ? wardSelect.dataset.selected : "";
        const countyData = findCountyData(countySelect.value);
        const isNairobi = normalizeCountyName(countySelect.value) === "nairobi";
        const wards = isNairobi
            ? nairobiWardsByConstituency[constituencySelect.value]
            : findConstituencyWards(countyData, constituencySelect.value);
        const wardNames = Array.isArray(wards) ? wards : [];

        resetSelect(wardSelect, wardNames.length ? "Select ward" : "No wards available");
        wardSelect.disabled = wardNames.length === 0;
        wardNames.forEach((name) => wardSelect.add(new Option(name, name)));
        if (wardNames.includes(selectedValue)) {
            wardSelect.value = selectedValue;
        }
        wardSelect.dataset.selected = "";
        updateCompletion();
    }

    async function loadLocationData() {
        const cacheKey = "iaims-kenya-constituencies-wards-v1";
        try {
            let cachedData = "";
            try {
                cachedData = window.localStorage.getItem(cacheKey) || "";
            } catch (error) {
                // Storage can be disabled; the directory can still be loaded from the network.
            }

            if (cachedData) {
                try {
                    constituenciesByCounty = JSON.parse(cachedData);
                } catch (error) {
                    constituenciesByCounty = {};
                }
            }

            if (!constituenciesByCounty || Object.keys(constituenciesByCounty).length === 0) {
                const response = await fetch(locationDataUrl, { headers: { Accept: "application/json" } });
                if (!response.ok) throw new Error("Location data request failed.");
                constituenciesByCounty = await response.json();
                try {
                    window.localStorage.setItem(cacheKey, JSON.stringify(constituenciesByCounty));
                } catch (error) {
                    // Ignore cache write failures; the loaded directory remains usable.
                }
            }

            if (!constituenciesByCounty || typeof constituenciesByCounty !== "object" || Array.isArray(constituenciesByCounty)) {
                throw new Error("Location data is invalid.");
            }
            locationLookupStatus.textContent = "Select your county, then choose your constituency and ward.";
            updateConstituencies(true);
        } catch (error) {
            const isNairobi = normalizeCountyName(countySelect.value) === "nairobi";
            locationLookupStatus.textContent = isNairobi
                ? "Nairobi constituencies are available, but the ward list could not load. Check your internet connection to select a ward."
                : "Could not load the constituency and ward list. Check your internet connection and reload the page.";
            if (isNairobi) {
                updateConstituencies();
                return;
            }
            resetSelect(constituencySelect, "Location list unavailable");
            resetSelect(wardSelect, "Location list unavailable");
            updateCompletion();
        }
    }

    identityType.addEventListener("change", updateIdentityLabel);
    countySelect.addEventListener("change", () => updateConstituencies());
    constituencySelect.addEventListener("change", () => updateWards());
    form.addEventListener("input", updateCompletion);
    form.addEventListener("change", updateCompletion);
    form.addEventListener("input", () => {
        formStatus.hidden = true;
    });
    form.addEventListener("submit", (event) => {
        event.preventDefault();
        if (!form.checkValidity()) {
            event.stopPropagation();
            form.classList.add("was-validated");
            form.querySelector(":invalid")?.focus();
            formStatus.hidden = true;
            return;
        }

        form.classList.add("was-validated");
        formStatus.className = "alert alert-info";
        formStatus.textContent = "Your profile looks complete. This frontend preview does not submit or save your information yet.";
        formStatus.hidden = false;
    });

    updateIdentityLabel();
    updateCompletion();
    loadLocationData();
});
