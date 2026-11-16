<?php
$pageTitle = 'Director Dashboard';
$active = 'dashboard';
$stats = [
  ['label' => 'Total Attachees', 'value' => '248', 'icon' => 'bi-file-earmark-text', 'trend' => '+12% this month'],
  ['label' => 'HR Admin Assigned', 'value' => '18', 'icon' => 'bi-person-check', 'trend' => '6 new this month'],
  ['label' => 'Total HR', 'value' => '24', 'icon' => 'bi-people', 'trend' => '2 on leave'],
  ['label' => 'Active placements', 'value' => '186', 'icon' => 'bi-briefcase', 'trend' => '+8% this week'],
];

$applications = [
  ['name' => 'John Wanjiku', 'department' => 'ICT', 'institution' => 'Kenyatta University', 'status' => 'Approved', 'date' => '2026-11-02'],
  ['name' => 'Aisha Njeri', 'department' => 'Broadcasting', 'institution' => 'Multimedia University', 'status' => 'Review', 'date' => '2026-11-01'],
  ['name' => 'Brian Otieno', 'department' => 'ICT', 'institution' => 'Strathmore University', 'status' => 'Pending', 'date' => '2026-10-30'],
  ['name' => 'Grace Muthoni', 'department' => 'ICT', 'institution' => 'JKUAT', 'status' => 'Approved', 'date' => '2026-10-29'],
];

$departments = [
  ['name' => 'ICT', 'count' => 86, 'percent' => 58],
  ['name' => 'Broadcasting', 'count' => 58, 'percent' => 42],
];

$recentActivity = [
  ['title' => 'Placement letter issued', 'detail' => 'Jane Ndirangu assigned to Kenya Power', 'time' => '15 mins ago'],
  ['title' => 'New application received', 'detail' => 'Martin Ouma submitted for ICT placement', 'time' => '1 hour ago'],
  ['title' => 'Supervisor confirmation', 'detail' => 'Mr. Kivuva approved 4 new workplans', 'time' => '3 hours ago'],
  ['title' => 'Report due', 'detail' => 'Quarterly placement report due tomorrow', 'time' => '1 day ago'],
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
        <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Overview</a>
        <a href="applications.php"><i class="bi bi-file-earmark-text"></i> Applications</a>
        <a href="placements.php"><i class="bi bi-briefcase"></i> Placements</a>
        <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
      </nav>
    </aside>

    <main class="director-main">
      <header class="director-topbar">
        <div>
          <div class="eyebrow">Management</div>
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions">
          <button class="btn btn-outline-primary btn-sm"><i class="bi bi-bell"></i> Alerts</button>
          <div class="user-chip"><i class="bi bi-person-circle"></i> Director</div>
        </div>
      </header>

      <div class="director-content">
        <section class="row g-4 mb-4">
          <?php foreach ($stats as $stat): ?>
            <div class="col-md-6 col-xl-3">
              <div class="stat-card">
                <div class="stat-icon"><i class="bi <?= $stat['icon'] ?>"></i></div>
                <div>
                  <div class="stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                  <div class="stat-value"><?= htmlspecialchars($stat['value']) ?></div>
                  <div class="stat-trend"><?= htmlspecialchars($stat['trend']) ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </section>

        <section class="row g-4 mb-4">
          <div class="col-xl-8">
            <div class="panel-card">
              <div class="section-header">
                <h3>Recent Attachees</h3>
                <a href="applications.php" class="link-btn">View all</a>
              </div>
              <div class="table-wrap">
                <table class="table director-table">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Department</th>
                      <th>Institution</th>
                      <th>Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($applications as $application): ?>
                      <tr>
                        <td><?= htmlspecialchars($application['name']) ?></td>
                        <td><?= htmlspecialchars($application['department']) ?></td>
                        <td><?= htmlspecialchars($application['institution']) ?></td>
                        <td><?= htmlspecialchars($application['date']) ?></td>
                        <td>
                          <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $application['status'])) ?>"><?= htmlspecialchars($application['status']) ?></span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-xl-4">
            <div class="panel-card">
              <div class="section-header">
                <h3>Attachees by department</h3>
              </div>
              <?php foreach ($departments as $department): ?>
                <div class="mini-progress-group">
                  <div class="d-flex justify-content-between small fw-semibold mb-1">
                    <span><?= htmlspecialchars($department['name']) ?></span>
                    <span><?= htmlspecialchars($department['count']) ?></span>
                  </div>
                  <div class="progress director-progress">
                    <div class="progress-bar" role="progressbar" style="width: <?= $department['percent'] ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

        <section class="row g-4">
          <div class="col-lg-7">
            <div class="panel-card">
              <div class="section-header">
                <h3>Recent activity</h3>
              </div>
              <div class="activity-list">
                <?php foreach ($recentActivity as $activity): ?>
                  <div class="activity-item">
                    <div class="activity-dot"></div>
                    <div>
                      <div class="fw-semibold"><?= htmlspecialchars($activity['title']) ?></div>
                      <div class="text-muted small"><?= htmlspecialchars($activity['detail']) ?></div>
                    </div>
                    <span class="activity-time"><?= htmlspecialchars($activity['time']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="panel-card highlight-panel">
              <div class="section-header">
                <h3>Quick actions</h3>
              </div>
              <div class="d-grid gap-2">
                <button class="btn btn-director-primary"><i class="bi bi-plus-circle"></i> View all attachees</button>
                <button class="btn btn-director-secondary"><i class="bi bi-file-earmark-arrow-down"></i> Export summary</button>
                <button class="btn btn-director-light"><i class="bi bi-clipboard-data"></i> Open reports</button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/director-app.js"></script>
</body>
</html>
