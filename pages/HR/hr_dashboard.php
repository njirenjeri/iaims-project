<?php
require __DIR__ . '/../../includes/hr_data.php';
$pageTitle = 'Dashboard';
$active = 'dashboard';

// Latest 5 applications, newest first
$recent = $applications;
usort($recent, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
$recent = array_slice($recent, 0, 5);

// Applications per department (only 2 departments)
$departments = ['ICT', 'Broadcasting'];
$byDept = array_fill_keys($departments, 0);
foreach ($applications as $a) {
  if (isset($byDept[$a['department']])) $byDept[$a['department']]++;
}
$total = max(1, count($applications));

require __DIR__ . '/../../includes/hr_header.php';
?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-3"><div class="stat-card">
    <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
    <div><small>Pending Applications</small><h2><?= count($applications) ?></h2></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card">
    <div class="stat-icon"><i class="bi bi-people"></i></div>
    <div><small>Active Attachees</small><h2><?= count($attachees) ?></h2></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card">
    <div class="stat-icon"><i class="bi bi-person-badge"></i></div>
    <div><small>Supervisors</small><h2><?= count($supervisors) ?></h2></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card">
    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
    <div><small>Finishing Soon</small><h2>1</h2></div></div></div>
</div>

<div class="row g-3">
  <!-- Recent Applications -->
  <div class="col-lg-8">
    <div class="card-hr p-3 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Recent Applications</h5>
        <a href="applications.php" class="btn btn-gold btn-sm">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Student</th><th>Institution</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['name']) ?></td>
              <td><?= htmlspecialchars($a['institution']) ?></td>
              <td><?= htmlspecialchars($a['course']) ?></td>
              <td><?= date('d M Y', strtotime($a['date'])) ?></td>
              <td><span class="badge bg-warning text-dark">Pending</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right column -->
  <div class="col-lg-4">
    <div class="card-hr p-3 mb-3">
      <h5 class="mb-3">Applications by Department</h5>
      <?php foreach ($byDept as $dept => $count): $pct = round($count / $total * 100); ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between small">
            <span><?= htmlspecialchars($dept) ?></span><span><?= $count ?></span>
          </div>
          <div class="progress" style="height:8px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card-hr p-3">
      <h5 class="mb-3">Quick Actions</h5>
      <div class="d-grid gap-2">
        <a href="applications.php" class="btn btn-blue"><i class="bi bi-file-earmark-text"></i> Review applications</a>
        <a href="attachee.php" class="btn btn-outline-secondary"><i class="bi bi-people"></i> View attachees</a>
        <a href="supervisors.php" class="btn btn-outline-secondary"><i class="bi bi-person-badge"></i> Manage supervisors</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/hr_footer.php'; ?>
