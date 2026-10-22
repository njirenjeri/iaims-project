<?php
// Set $pageTitle and $active BEFORE including this file
$menu = [
  'dashboard'    => ['hr_dashboard.php', 'bi-speedometer2',      'Dashboard'],
  'attachees'    => ['attachee.php',     'bi-people',            'Attachees'],
  'supervisors'  => ['supervisors.php',  'bi-person-badge',      'Supervisors'],
  'applications' => ['applications.php', 'bi-file-earmark-text', 'Applications'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $pageTitle ?> | IAIMS HR</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../../assets/css/hr-dashboard.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
  <aside class="sidebar">
      <div class="brand">
      <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
      <div>
        <div class="brand-name">IAIMS</div>
        <div class="brand-sub">HR Portal</div>
      </div>
    </div>
      <?php foreach ($menu as $key => [$link, $icon, $label]): ?>
        <a href="<?= $link ?>" class="<?= $active === $key ? 'active' : '' ?>">
          <i class="bi <?= $icon ?>"></i><?= $label ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <main class="content">
    <div class="page-head">
      <h1><?= $pageTitle ?></h1>
      <div class="head-right">
        <span class="eyebrow">HR Management</span>
        <span class="user-chip"><i class="bi bi-person-circle"></i> HR Officer</span>
      </div>
    </div>
    <div class="page-body">