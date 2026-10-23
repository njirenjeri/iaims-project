<?php
require __DIR__ . '/../../includes/hr_data.php';
$pageTitle = 'Attachees';
$active = 'attachees';
require __DIR__ . '/../../includes/hr_header.php';
?>

<div class="row g-3">
<?php foreach ($attachees as $t): [$pct, $left] = progress($t['start'], $t['end']); ?>
  <div class="col-md-6">
    <div class="card-hr p-3">
      <h5 class="mb-0 text-primary"><?= htmlspecialchars($t['name']) ?></h5>
      <small class="text-muted">Supervisor: <?= htmlspecialchars($t['supervisor']) ?></small>
      <div class="progress my-3" style="height:22px"><div class="progress-bar" style="width:<?= $pct ?>%"><?= $pct ?>%</div></div>
      <p class="mb-0">Started: <?= date('d M Y', strtotime($t['start'])) ?><br>
         Finishes: <strong><?= date('d M Y', strtotime($t['end'])) ?></strong> (<?= $left ?> days left)</p>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/../../includes/hr_footer.php'; ?>