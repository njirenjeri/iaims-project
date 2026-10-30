<?php
/*
 * IAMS supervisor portal entry point.
 * Signing in is not part of this file: add your own check and redirect visitors who are not supervisors.
 */
declare(strict_types=1);

require __DIR__ . '/includes/session.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/actions.php';
require __DIR__ . '/includes/dashboard.php';
