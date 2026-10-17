document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".alert-dismissible").forEach((alertEl) => {
        if (alertEl.dataset.timeout === "0") return;
        const timeout = Number(alertEl.dataset.timeout) || 5000;

        setTimeout(() => {
            // Skip if the user already closed it.
            if (alertEl.isConnected) {
                bootstrap.Alert.getOrCreateInstance(alertEl).close();
            }
        }, timeout);
    });
});
