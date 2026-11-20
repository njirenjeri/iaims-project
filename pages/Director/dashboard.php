<?php
require 'C:\xampp\htdocs\iaims-project\config\database.php';

// For testing, we will assume Director ID 1 is logged in.
// Later, you will replace this with $_SESSION['director_id'] from your login page.
$directorId = 1; 

// Fetch Director details from DB
$stmt = $pdo->prepare("SELECT * FROM directors WHERE id = ?");
$stmt->execute([$directorId]);
$director = $stmt->fetch();

// Log the action
logAction($auditPdo, $director['id'], $director['full_name'], 'Viewed Director Dashboard');

$pageTitle = 'Director Dashboard';
$active = 'dashboard';

// (Keep your existing $stats, $supervisors, $recentActivity arrays here...)
// I have included the DB fetch for supervisors to show you how it connects.
$stmt = $pdo->query("SELECT * FROM supervisors");
$supervisors = $stmt->fetchAll();
$stmt = $auditPdo->query("SELECT * FROM audit_log ORDER BY occurred_at DESC LIMIT 3");
$recentActivity = $stmt->fetchAll();

// Static stats for the top cards

$stats = [
  ['label' => 'Total Attaches', 'value' => '120', 'icon' => 'bi-people-fill', 'color' => 'text-primary'],
  ['label' => 'Total Supervisors', 'value' => '8', 'icon' => 'bi-shield-check', 'color' => 'text-warning'],
  ['label' => 'Signed In', 'value' => '95', 'icon' => 'bi-check-circle-fill', 'color' => 'text-info'],
  ['label' => 'Signed Out', 'value' => '45', 'icon' => 'bi-box-arrow-right', 'color' => 'text-warning'],
  ['label' => 'Absent Today', 'value' => '25', 'icon' => 'bi-x-circle-fill', 'color' => 'text-danger'],
  ['label' => 'Ending Soon', 'value' => '5', 'icon' => 'bi-hourglass-split', 'color' => 'text-warning'],
];

$supervisors = [
  ['name' => 'Sarah Wanjiru', 'dept' => 'IT', 'attachees' => '15', 'reports' => '14/15', 'status' => 'On Track', 'status_color' => 'success'],
  ['name' => 'James Otieno', 'dept' => 'Finance', 'attachees' => '12', 'reports' => '12/12', 'status' => 'On Track', 'status_color' => 'success'],
  ['name' => 'Grace Mwangi', 'dept' => 'HR', 'attachees' => '14', 'reports' => '10/14', 'status' => 'Pending', 'status_color' => 'warning'],
  ['name' => 'Peter Kamau', 'dept' => 'Sales', 'attachees' => '13', 'reports' => '13/13', 'status' => 'On Track', 'status_color' => 'success'],
  ['name' => 'David Ochieng', 'dept' => 'Operations', 'attachees' => '16', 'reports' => '15/16', 'status' => 'Pending', 'status_color' => 'warning'],
];

$recentActivity = [
  ['title' => 'Mary Wanjiku signed in', 'time' => 'Today at 08:12 AM', 'icon' => 'bi-check-circle-fill', 'color' => 'text-primary'],
  ['title' => 'Peter Kamau submitted a report', 'time' => 'Today at 08:10 AM', 'icon' => 'bi-file-earmark-text-fill', 'color' => 'text-warning'],
  ['title' => 'Amina Hassan signed out', 'time' => 'Today at 07:45 AM', 'icon' => 'bi-box-arrow-right', 'color' => 'text-info'],
  ['title' => '5 attachés are due to complete their attachment this week', 'time' => '', 'icon' => 'bi-info-circle-fill', 'color' => 'text-primary'],
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
  
  <!-- Make sure this link is here to load your custom CSS -->
  <link href="assets/css/director-theme.css" rel="stylesheet">
</head>
<body>
  <div class="director-shell">
    
    <!-- ========================================== -->
    <!-- YOUR EXACT SIDEBAR (DO NOT CHANGE)         -->
    <!-- ========================================== -->
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
      
      <!-- TOPBAR -->
      <header class="director-topbar">
        <div>
          <div class="eyebrow">Management</div>
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions d-flex align-items-center gap-3">
          <button class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="bi bi-bell"></i> Alerts</button>
          <div class="user-chip"><i class="bi bi-person-circle fs-5"></i> Director <i class="bi bi-chevron-down small ms-1"></i></div>
        </div>
      </header>

      <!-- ========================================== -->
      <!-- NEW DASHBOARD CONTENT (MATCHING PICTURE)   -->
      <!-- ========================================== -->
      <div class="director-content">
        
        <!-- 1. Top 6 Stats -->
        <section class="row g-3 mb-4">
          <?php foreach ($stats as $stat): ?>
            <div class="col-md-4 col-xl-2">
              <div class="stat-mini-card">
                <div class="stat-mini-icon <?= $stat['color'] ?>"><i class="bi <?= $stat['icon'] ?>"></i></div>
                <div>
                  <div class="stat-mini-label"><?= htmlspecialchars($stat['label']) ?></div>
                  <div class="stat-mini-value"><?= htmlspecialchars($stat['value']) ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </section>

        <!-- 2. Line & Donut Charts -->
        <section class="row g-4 mb-4">
          <div class="col-xl-7">
            <div class="flat-panel">
              <div class="flat-panel-header">
                <h3><i class="bi bi-bar-chart-line text-primary me-2"></i>Attendance Trend</h3>
                <select class="form-select form-select-sm w-auto border-0 bg-light fw-bold text-muted">
                  <option>Last 7 Days</option>
                </select>
              </div>
              <!-- Added position:relative for Chart.js -->
              <div class="chart-container" style="height: 250px; position: relative;">
                <canvas id="attendanceChart"></canvas>
              </div>
            </div>
          </div>
          
          <div class="col-xl-5">
            <div class="flat-panel">
              <div class="flat-panel-header">
                <h3><i class="bi bi-pie-chart-fill text-primary me-2"></i>Attaché Report Progress</h3>
                <a href="#" class="text-decoration-none small text-muted fw-bold">View Details &rarr;</a>
              </div>
              <div class="d-flex align-items-center justify-content-center" style="height: 250px;">
                  <!-- Added position:relative for Chart.js -->
                  <div style="width: 180px; height: 180px; position: relative;">
                      <canvas id="reportProgressChart"></canvas>
                  </div>
                  <div class="ms-4">
                      <div class="d-flex align-items-center mb-2"><span class="badge bg-primary me-2 rounded-circle p-1">&nbsp;</span> <small class="fw-semibold">Submitted <strong class="float-end ms-3">85</strong></small></div>
                      <div class="d-flex align-items-center mb-2"><span class="badge bg-warning me-2 rounded-circle p-1">&nbsp;</span> <small class="fw-semibold">Pending <strong class="float-end ms-3">20</strong></small></div>
                      <div class="d-flex align-items-center mb-2"><span class="badge bg-success me-2 rounded-circle p-1">&nbsp;</span> <small class="fw-semibold">Reviewed <strong class="float-end ms-3">70</strong></small></div>
                      <div class="d-flex align-items-center mb-2"><span class="badge bg-info me-2 rounded-circle p-1">&nbsp;</span> <small class="fw-semibold">Completing Soon <strong class="float-end ms-3">5</strong></small></div>
                      <div class="d-flex align-items-center mb-2"><span class="badge bg-dark me-2 rounded-circle p-1">&nbsp;</span> <small class="fw-semibold">Completed <strong class="float-end ms-3">40</strong></small></div>
                  </div>
              </div>
            </div>
          </div>
        </section>

        <!-- 3. Supervisors Table, Bar Chart & Activity -->
        <section class="row g-4 mb-4">
          <div class="col-xl-7">
            <div class="flat-panel">
              <div class="flat-panel-header">
                <h3><i class="bi bi-people-fill text-primary me-2"></i>Supervisors Overview</h3>
                <a href="#" class="text-decoration-none small text-muted fw-bold">View All &rarr;</a>
              </div>
              <div class="table-responsive">
                <table class="table table-clean table-hover">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Supervisor</th>
                      <th>Department</th>
                      <th>Attachés</th>
                      <th>Reports</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($supervisors as $index => $sup): ?>
                    <tr>
                      <td><?= $index + 1 ?></td>
                      <td class="fw-bold text-dark"><?= $sup['name'] ?></td>
                      <td><?= $sup['dept'] ?></td>
                      <td><?= $sup['attachees'] ?></td>
                      <td><?= $sup['reports'] ?></td>
                      <td><span class="badge bg-<?= $sup['status_color'] ?> bg-opacity-10 text-<?= $sup['status_color'] ?> border border-<?= $sup['status_color'] ?> rounded-pill px-3"><?= $sup['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-xl-5">
            <div class="row g-4 h-100">
              <div class="col-12">
                <div class="flat-panel">
                  <div class="flat-panel-header">
                    <h3><i class="bi bi-bar-chart-fill text-primary me-2"></i>Department Overview</h3>
                  </div>
                  <!-- Added position:relative for Chart.js -->
                  <div class="chart-container" style="height: 160px; position: relative;">
                    <canvas id="departmentChart"></canvas>
                  </div>
                </div>
              </div>
              
              <div class="col-12">
                <div class="flat-panel">
                  <div class="flat-panel-header bg-warning bg-opacity-10 rounded p-2 mb-3 border-start border-warning border-4" style="border-bottom: none;">
                    <h3 class="text-dark"><i class="bi bi-bell-fill text-warning me-2"></i>Recent Activity</h3>
                  </div>
                  <div class="activity-list">
                      <?php foreach ($recentActivity as $act): ?>
    <div class="activity-feed-item">
        <!-- Use a default icon since the DB doesn't have an icon column -->
        <i class="bi bi-info-circle-fill text-primary activity-feed-icon"></i>
        <div>
            <!-- Uses the 'action' column from your DB -->
            <p class="activity-feed-text"><?= htmlspecialchars($act['action']) ?></p>
            
            <!-- Uses the 'occurred_at' column from your DB -->
            <span class="activity-feed-time">
                <?= date('M d, Y - h:i A', strtotime($act['occurred_at'])) ?>
            </span>
        </div>
    </div>
<?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- 4. Footer Banner -->
        <div class="live-banner">
            <div class="d-flex align-items-center">
                <i class="bi bi-megaphone-fill fs-3 text-primary me-3"></i>
                <div>
                    <h6 class="mb-1 fw-bold text-dark">Live Company Monitoring</h6>
                    <small class="text-muted">All data is updated in real-time. You have a complete overview of attaché activities, supervisors and reports.</small>
                </div>
            </div>
            <button class="btn btn-primary px-4 rounded-pill fw-bold"><i class="bi bi-download me-2"></i> Generate Report</button>
        </div>

      </div>
    </main>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- This loads your chart logic -->
  <script src="assets/js/director-app.js"></script>
</body>
</html>