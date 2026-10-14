<?php

// load application settings
require_once __DIR__ . '/../config/app.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >
    <link
         href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        href="<?= BASE_URL ?>/assets/css/style.css"
        rel="stylesheet"
    >
</head>
<body class="bg-body-tertiary">
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
        <section class="card shadow-sm border-0 auth-card">
            <header class="text-center p-4">
                <img
                    src="<?= BASE_URL ?>/assets/img/coat-of-arms.png"
                    alt="Coat of arms"
                    class="coat-of-arms mb-2"
                >
                <p class="text-secondary opacity-50 small fw-semibold mb-1 republic-heading">REPUBLIC OF KENYA</p>

                <h1 class="h4 fw-semibold mb-1">Industrial Attachment</h1>

                <p class="text-secondary small mb-0">Information Management System</p>
            </header>

            <hr class="gold-divider">

            <div class="card-body p-4">
                <form action="#" id="authForm" method="post" novalidate>
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                        </span>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            placeholder="name@example.com"
                            autocomplete="email"
                            aria-label="email"
                            required
                        >
                    </div>

                    <div class="text-end mt-2 mb-3" id="forgotPasswordWrapper">
                        <a href="#" id="forgotPasswordLink" class="small text-decoration-none">
                            Forgot Password?
                        </a>
                    </div>

                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                        </span>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="Password"
                            autocomplete="current-password"
                            aria-label="Password"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-password-toggle="password"
                            aria-label="show-password"
                        >
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- only missing password rewuirements will appear -->
                     <div
                        class="small mt-2 mb-3"
                        id="passwordFeedback"
                        aria-live="polite"
                        hidden
                    ></div>

                    <!-- registration only confirmation field -->
                     <div class="mt-3" id="confirmPasswordWrapper" hidden>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-lock-fill" aria-hidden=""true></i>
                            </span>

                            <input
                                type="password"
                                class="form-control"
                                id="conformPassword"
                                name="confirm_password"
                                placeholder="Confirm Password"
                                autocomplete="new-password"
                                aria-label="confirm password"
                            >

                            <button
                                type="button"
                                class="btn btn-btn-outline-secondary"
                                data-password-toggle="confirmPassword"
                                aria-label="Show Confirm Password"
                            >
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div
                            id="confirmPasswordFeedback"
                            class="small mt-2"
                            aria-live="polite"
                        ></div>
                     </div>

                     <!-- form submission -->
                      <button type="submit" class="btn btn-primary w-100 mt-3" id="submitButton">
                        Sign In
                      </button>

                      <!-- switch between login and registration -->
                       <p class="text-center small mt-4 mb-0">
                        <span id="togglePrompt">Don't have an account?</span>
                        <button
                            type="button"
                            id="toggleAuth"
                            class="btn btn-link p-0 ms-1 text-decoration-none"
                        >
                            Register
                        </button>
                       </p>

                </form>
            </div>
        </section>
    </main>

    <!-- bootstrap js -->
     <script src="https://cdn.jsdelivr.net/npm/boostrap@5.3.3/dist/js/bootsrap.bundle.min.js"></script>

     <!-- local js -->
      <script src="<?= BASE_URL ?>/assets/js/app.js"></script>

</body>
</html>
