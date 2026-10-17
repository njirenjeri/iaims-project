<?php

$navItems = [
    'dashboard' => ['Dashboard', 'bi-speedometer2'],
    'profile' => ['My profile', 'bi-person'],
    'applications' => ['Applications', 'bi-send'],
];
$sidebarEmail = $_SESSION['user_email'] ?? '';
?>
<header class="student-topbar d-lg-none">
    <button class="btn btn-menu" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#studentSidebar" aria-controls="studentSidebar" aria-label="Open navigation">
        <i class="bi bi-list fs-5" aria-hidden="true"></i>
    </button>
    <span class="fw-semibold">IAIMS <span class="fw-normal text-white-50">Student Portal</span></span>
</header>

<aside class="offcanvas-lg offcanvas-start student-sidebar" tabindex="-1" id="studentSidebar" aria-label="Student navigation">
    <div class="offcanvas-header">
        <span class="offcanvas-title fw-semibold text-white">IAIMS <span class="fw-normal text-white-50">Student Portal</span></span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                data-bs-target="#studentSidebar" aria-label="Close navigation"></button>
    </div>

    <div class="offcanvas-body">
        <div class="sidebar-inner">
            <a class="sidebar-brand d-none d-lg-flex" href="<?= escapeHtml(BASE_URL) ?>/?page=dashboard">
                <span class="sidebar-brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                <span>IAIMS <small>Student Portal</small></span>
            </a>

            <nav aria-label="Main">
                <ul class="sidebar-nav">
                    <?php foreach ($navItems as $page => [$label, $icon]): ?>
                        <li>
                            <a class="sidebar-link<?= $currentPage === $page ? ' active' : '' ?>"
                               <?= $currentPage === $page ? 'aria-current="page"' : '' ?>
                               href="<?= escapeHtml(BASE_URL) ?>/?page=<?= $page ?>">
                                <i class="bi <?= $icon ?>" aria-hidden="true"></i>
                                <span><?= escapeHtml($label) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <?php if ($sidebarEmail !== ''): ?>
                    <p class="small text-white-50 text-truncate mb-2" title="<?= escapeHtml($sidebarEmail) ?>">
                        <?= escapeHtml($sidebarEmail) ?>
                    </p>
                <?php endif; ?>

                <form method="post" action="<?= escapeHtml(BASE_URL) ?>/?page=logout">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                    <button type="submit" class="sidebar-signout">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
