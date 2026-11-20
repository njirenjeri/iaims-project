<?php
$pageTitle = 'Placements';
$active = 'placements';

$placements = [
  ['student' => 'James Nderitu', 'host' => 'Kenya Power', 'department' => 'ICT', 'supervisor' => 'Mr. Mwaura', 'start' => '2026-12-01', 'status' => 'Active'],
  ['student' => 'Nancy Achieng', 'host' => 'Nation Media Group', 'department' => 'Broadcasting', 'supervisor' => 'Ms. Otieno', 'start' => '2026-11-15', 'status' => 'Monitoring'],
  ['student' => 'Daniel Kariuki', 'host' => 'KRA', 'department' => 'Finance', 'supervisor' => 'Mr. Kinyanjui', 'start' => '2026-11-20', 'status' => 'Planned'],
  ['student' => 'Esther Njeri', 'host' => 'KEMRI', 'department' => 'Research', 'supervisor' => 'Dr. Shikuku', 'start' => '2026-12-08', 'status' => 'Active'],
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
        <a href="placements.php" class="active"><i class="bi bi-briefcase"></i> Placements</a>
        <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
      </nav>
    </aside>

    <main class="director-main">
      <header class="director-topbar">
        <div>
          <div class="eyebrow">Operations</div>
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions">
          <button class="btn btn-director-primary btn-sm"><i class="bi bi-file-earmark-bar-graph"></i> Department report</button>
          <div class="user-chip"><i class="bi bi-person-circle"></i> Director</div>
        </div>
      </header>

      <div class="director-content">
        <div class="panel-card">
          <div class="section-header">
            <h3>Attachment placements</h3>
            <div class="d-flex gap-2">
              <button class="btn btn-director-secondary btn-sm">Export CSV</button>
              <button class="btn btn-director-primary btn-sm">Filter</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="table director-table sortable-table" id="placementsTable">
              <thead>
                <tr>
                  <th data-sort="string">Student</th>
                  <th data-sort="string">Host organisation</th>
                  <th data-sort="string">Department</th>
                  <th data-sort="string">Supervisor</th>
                  <th data-sort="date">Start date</th>
                  <th data-sort="string">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($placements as $placement): ?>
                  <tr>
                    <td><?= htmlspecialchars($placement['student']) ?></td>
                    <td><?= htmlspecialchars($placement['host']) ?></td>
                    <td><?= htmlspecialchars($placement['department']) ?></td>
                    <td><?= htmlspecialchars($placement['supervisor']) ?></td>
                    <td><?= htmlspecialchars($placement['start']) ?></td>
                    <td><span class="status-badge status-active"><?= htmlspecialchars($placement['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/director-app.js"></script>
</body>
</html>
