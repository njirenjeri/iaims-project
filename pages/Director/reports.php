<?php
$pageTitle = 'Reports';
$active = 'reports';

$reports = [
  ['name' => 'Monthly placement summary', 'date' => '2026-11-02', 'owner' => 'Director Office'],
  ['name' => 'Supervisor performance report', 'date' => '2026-10-31', 'owner' => 'HR & Operations'],
  ['name' => 'Department demand analysis', 'date' => '2026-10-25', 'owner' => 'Planning Unit'],
  ['name' => 'Compliance checklist', 'date' => '2026-10-15', 'owner' => 'Administration'],
];

$kpis = [
  ['label' => 'Total Applicants', 'value' => '248', 'icon' => 'bi-people-fill'],
  ['label' => 'Approved Applications', 'value' => '186', 'icon' => 'bi-check-circle-fill'],
  ['label' => 'Rejected Applications', 'value' => '24', 'icon' => 'bi-x-circle-fill'],
  ['label' => 'Pending Applications', 'value' => '38', 'icon' => 'bi-clock-history'],
  ['label' => 'Departments Offering Attachments', 'value' => '12', 'icon' => 'bi-building'],
  ['label' => 'Students on Attachment', 'value' => '132', 'icon' => 'bi-mortarboard-fill'],
  ['label' => 'Placement Rate', 'value' => '89%', 'icon' => 'bi-percent'],
  ['label' => 'Ending This Month', 'value' => '21', 'icon' => 'bi-calendar-event'],
  ['label' => 'Evaluations Completed', 'value' => '95', 'icon' => 'bi-clipboard-check'],
  ['label' => 'Active Supervisors', 'value' => '18', 'icon' => 'bi-person-badge'],
];

$departmentPlacements = [
  ['label' => 'ICT', 'value' => 78],
  ['label' => 'Broadcasting', 'value' => 52],
  ['label' => 'Finance', 'value' => 41],
  ['label' => 'Research', 'value' => 31],
];

$vacancies = [
  ['label' => 'ICT', 'value' => 8],
  ['label' => 'Broadcasting', 'value' => 6],
  ['label' => 'Finance', 'value' => 5],
  ['label' => 'Research', 'value' => 4],
];

$evaluations = [
  ['label' => 'Excellent', 'value' => 54],
  ['label' => 'Good', 'value' => 31],
  ['label' => 'Average', 'value' => 11],
  ['label' => 'Needs Improvement', 'value' => 4],
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
        <a href="reports.php" class="active"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
      </nav>
    </aside>

    <main class="director-main">
      <header class="director-topbar">
        <div>
          <div class="eyebrow">Analytics</div>
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions">
          <button class="btn btn-director-primary btn-sm"><i class="bi bi-file-earmark-text"></i> Generate dept report</button>
          <button class="btn btn-director-secondary btn-sm"><i class="bi bi-printer"></i> Print</button>
          <div class="user-chip"><i class="bi bi-person-circle"></i> Director</div>
        </div>
      </header>

      <div class="director-content">
        <div class="row g-4 mb-4">
          <?php foreach ($kpis as $kpi): ?>
            <div class="col-md-6 col-xl-2">
              <div class="mini-stat">
                <span><i class="bi <?= htmlspecialchars($kpi['icon']) ?> me-2"></i><?= htmlspecialchars($kpi['label']) ?></span>
                <strong><?= htmlspecialchars($kpi['value']) ?></strong>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-lg-4">
            <div class="panel-card">
              <div class="section-header">
                <h3>Department placements</h3>
              </div>
              <div class="chart-bars">
                <?php foreach ($departmentPlacements as $item): ?>
                  <div class="chart-row">
                    <div class="d-flex justify-content-between small mb-1"><span><?= htmlspecialchars($item['label']) ?></span><strong><?= htmlspecialchars($item['value']) ?></strong></div>
                    <div class="mini-bar"><span style="width: <?= min(100, $item['value']) ?>%"></span></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="panel-card">
              <div class="section-header">
                <h3>Vacancies</h3>
              </div>
              <div class="chart-bars">
                <?php foreach ($vacancies as $item): ?>
                  <div class="chart-row">
                    <div class="d-flex justify-content-between small mb-1"><span><?= htmlspecialchars($item['label']) ?></span><strong><?= htmlspecialchars($item['value']) ?></strong></div>
                    <div class="mini-bar mini-bar-alt"><span style="width: <?= min(100, $item['value'] * 12) ?>%"></span></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="panel-card">
              <div class="section-header">
                <h3>Evaluations</h3>
              </div>
              <div class="chart-bars">
                <?php foreach ($evaluations as $item): ?>
                  <div class="chart-row">
                    <div class="d-flex justify-content-between small mb-1"><span><?= htmlspecialchars($item['label']) ?></span><strong><?= htmlspecialchars($item['value']) ?>%</strong></div>
                    <div class="mini-bar mini-bar-gold"><span style="width: <?= min(100, $item['value']) ?>%"></span></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="panel-card">
          <div class="section-header">
            <h3>Generated reports</h3>
            <button class="btn btn-director-secondary btn-sm">Generate department report</button>
          </div>
          <div class="table-wrap">
            <table class="table director-table">
              <thead>
                <tr>
                  <th>Report name</th>
                  <th>Created</th>
                  <th>Owner</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reports as $report): ?>
                  <tr>
                    <td><?= htmlspecialchars($report['name']) ?></td>
                    <td><?= htmlspecialchars($report['date']) ?></td>
                    <td><?= htmlspecialchars($report['owner']) ?></td>
                    <td><button class="btn btn-sm btn-outline-primary action-btn">Open</button></td>
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
