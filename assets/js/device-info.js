// Reports a few traits of this browser to the server at sign-in, through a hidden field
// named device_info. The server validates every value and treats it as a hint only.
(() => {
    const field = document.getElementById("deviceInfo");
    if (!field) return;

    try {
        const uaData = navigator.userAgentData;

        const info = {
            screen: `${Math.round(window.screen.width)}x${Math.round(window.screen.height)}`,
            viewport: `${Math.round(window.innerWidth)}x${Math.round(window.innerHeight)}`,
            pixel_ratio: window.devicePixelRatio || 1,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || "",
            language: navigator.language || "",
            languages: Array.from(navigator.languages || []).slice(0, 5),
            platform: (uaData && uaData.platform) || navigator.platform || "",
            mobile: uaData ? uaData.mobile : undefined,
            touch: navigator.maxTouchPoints || 0,
            cores: navigator.hardwareConcurrency || 0,
            memory: navigator.deviceMemory || 0,
            color_scheme: window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
                ? "dark"
                : "light",
        };

        const write = () => {
            field.value = JSON.stringify(info);
        };

        write();

        // Chromium browsers can also report the device model and OS version, when asked.
        if (uaData && typeof uaData.getHighEntropyValues === "function") {
            uaData
                .getHighEntropyValues(["model", "platformVersion"])
                .then((values) => {
                    info.model = values.model || "";
                    info.platform_version = values.platformVersion || "";
                    write();
                })
                .catch(() => {});
        }
    } catch (error) {
        // Signing in must never depend on this script.
    }
})();
