<?php
$pageTitle = 'Applications';
$active = 'applications';

$applications = [
  ['id' => 1001, 'name' => 'Faith Wamae', 'department' => 'ICT', 'institution' => 'KCA University', 'course' => 'Computer Science', 'status' => 'Approved', 'date' => '2026-11-01'],
  ['id' => 1002, 'name' => 'Kevin Omondi', 'department' => 'Broadcasting', 'institution' => 'Multimedia University', 'course' => 'Journalism', 'status' => 'Review', 'date' => '2026-10-29'],
  ['id' => 1003, 'name' => 'Sarah Kendi', 'department' => 'Finance', 'institution' => 'USIU', 'course' => 'Finance', 'status' => 'Pending', 'date' => '2026-10-28'],
  ['id' => 1004, 'name' => 'Peter Karanja', 'department' => 'ICT', 'institution' => 'JKUAT', 'course' => 'Software Engineering', 'status' => 'Approved', 'date' => '2026-10-24'],
  ['id' => 1005, 'name' => 'Lilian Kivuva', 'department' => 'Broadcasting', 'institution' => 'Daystar University', 'course' => 'Mass Communication', 'status' => 'Pending', 'date' => '2026-10-18'],
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
        <a href="applications.php" class="active"><i class="bi bi-file-earmark-text"></i> Applications</a>
        <a href="placements.php"><i class="bi bi-briefcase"></i> Placements</a>
        <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
      </nav>
    </aside>

    <main class="director-main">
      <header class="director-topbar">
        <div>
          <div class="eyebrow">Students</div>
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions">
          <input type="search" id="directorSearch" class="search-box" placeholder="Search applicant...">
          <div class="user-chip"><i class="bi bi-person-circle"></i> Director</div>
        </div>
      </header>

      

      <div class="director-content">
        <div class="panel-card">
          <div class="section-header">
            <h3>Applications list</h3>
            <div class="d-flex gap-2">
              <button class="btn btn-director-secondary btn-sm" type="button">Export CSV</button>
              <button class="btn btn-director-primary btn-sm" type="button" disabled>Read-only view</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="table director-table" id="applicationsTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Department</th>
                  <th>Institution</th>
                  <th>Course</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($applications as $application): ?>
                  <tr data-search="<?= htmlspecialchars(strtolower($application['name'] . ' ' . $application['department'] . ' ' . $application['institution'])) ?>">
                    <td><?= htmlspecialchars($application['id']) ?></td>
                    <td><?= htmlspecialchars($application['name']) ?></td>
                    <td><?= htmlspecialchars($application['department']) ?></td>
                    <td><?= htmlspecialchars($application['institution']) ?></td>
                    <td><?= htmlspecialchars($application['course']) ?></td>
                    <td><?= htmlspecialchars($application['date']) ?></td>
                    <td>
                      <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $application['status'])) ?>"><?= htmlspecialchars($application['status']) ?></span>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-outline-primary action-btn" type="button">View</button>
                    </td>
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
