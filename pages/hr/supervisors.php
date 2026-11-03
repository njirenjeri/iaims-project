<?php
require __DIR__ . '/../../includes/hr_data.php';
$pageTitle = 'Supervisors';
$active = 'supervisors';
require __DIR__ . '/../../includes/hr_header.php';
?>

<div class="row g-3">
  <div class="col-md-4">
    <form class="card-hr p-3" onsubmit="addSupervisor(event)">
      <h5 class="text-primary">Add Supervisor</h5>
      <input name="name" class="form-control mb-2" placeholder="Full name" required>
      <input name="department" class="form-control mb-2" placeholder="Department" required>
      <input name="email" type="email" class="form-control mb-3" placeholder="Email" required>
      <button class="btn btn-gold w-100">Add</button>
    </form>
  </div>

  <div class="col-md-8">
    <div class="card-hr p-3 table-responsive">
      <table class="table">
        <thead><tr><th>Name</th><th>Department</th><th>Email</th><th>Attachees</th></tr></thead>
        <tbody id="supList">
        <?php foreach ($supervisors as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['department']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td><?= $s['count'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/hr_footer.php'; ?>