<?php
$pageTitle = 'Settings';
$active = 'settings';

$settings = [
  ['name' => 'Auto-approval rules', 'value' => 'Enabled', 'note' => 'Approve eligible applications that meet the review checklist.'],
  ['name' => 'Supervisor alerts', 'value' => 'Enabled', 'note' => 'Send alerts when the placement status changes.'],
  ['name' => 'Quarterly report reminders', 'value' => 'Disabled', 'note' => 'Push reminders to the review committee.'],
  ['name' => 'Document verification', 'value' => 'Enabled', 'note' => 'Check student submissions before placement approval.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | IAIMS Director</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/director-theme.css" rel="stylesheet">
</head>
<body>
  <div class="director-shell">
    <aside class="director-sidebar">
      <div class="brand-box">
        <div class="brand-icon"><i class="bi bi-building-check"></i></div>
        <div>
          <div class="brand-name">IAIMS</div>
          <div class="brand-subtitle">Director Portal</div>
        </div>
      </div>

      <nav class="nav flex-column director-nav">
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Overview</a>
        <a href="applications.php"><i class="bi bi-file-earmark-text"></i> Applications</a>
        <a href="placements.php"><i class="bi bi-briefcase"></i> Placements</a>
        <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="settings.php" class="active"><i class="bi bi-gear"></i> Settings</a>
      </nav>
    </aside>

    <main class="director-main">
      <header class="director-topbar">
        <div>
          <div class="eyebrow">System</div>
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions">
          <button class="btn btn-director-secondary btn-sm"><i class="bi bi-eye"></i> View access summary</button>
          <div class="user-chip"><i class="bi bi-person-circle"></i> Director</div>
        </div>
      </header>

      <div class="director-content">
        <div class="panel-card">
          <div class="section-header">
            <h3>Portal preferences</h3>
          </div>

          <div class="settings-list">
            <?php foreach ($settings as $setting): ?>
              <div class="setting-row">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars($setting['name']) ?></div>
                  <div class="small text-muted"><?= htmlspecialchars($setting['note']) ?></div>
                </div>
                <div class="ms-auto">
                  <span class="status-badge <?= $setting['value'] === 'Enabled' ? 'status-approved' : 'status-pending' ?>"><?= htmlspecialchars($setting['value']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/director-app.js"></script>
</body>
</html>
