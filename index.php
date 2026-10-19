    <!-- enetry point -->
    <?php
    //  start session
    session_start();

    require_once __DIR__ . '/config/app.php';

    // define the pages that visitors are allowed to request

    $routes = [
        'login' => __DIR__ . '/pages/login.php',
        'register' => __DIR__ . '/pages/register.php',
        'dashboard' => __DIR__ . '/pages/dashboard.php',

        'profile' => __DIR__ . '/pages/student/profile.php',
        'qualifications' => __DIR__ . '/pages/student/qualifications.php',
        'applications' => __DIR__ . '/pages/student/applications.php',

    ];
    // get the requested file else direct to login page
    $page = $_GET['page'] ?? 'login';

    // only load pages that exist in the route
    if (!array_key_exists($page, $routes)) {
        http_response_code(404);
        exit('Page Not Found');
    }

    // load requested
    require $routes[$page];
    ?>
