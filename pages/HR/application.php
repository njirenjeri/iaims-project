<?php
require __DIR__ . '/../../includes/hr_data.php';
$pageTitle = 'Applications';
$active = 'applications';
require __DIR__ . '/../../includes/hr_header.php';
?>

<div class="card-hr p-3 table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Student</th><th>Institution</th><th>Course</th><th>Date</th><th>Documents</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($applications as $a): ?>
      <tr id="row-<?= $a['id'] ?>">
        <td><?= htmlspecialchars($a['name']) ?></td>
        <td><?= htmlspecialchars($a['institution']) ?></td>
        <td><?= htmlspecialchars($a['course']) ?></td>
        <td><?= date('d M Y', strtotime($a['date'])) ?></td>
        <td><span class="badge bg-secondary"><?= count(array_filter($a['docs'])) ?>/6 submitted</span></td>
        <td><span class="badge bg-warning text-dark status">Pending</span></td>
        <td class="actions">
          <button class="btn btn-gold btn-sm" onclick="viewApplication(<?= $a['id'] ?>)">
            <i class="bi bi-eye"></i> View
          </button>
          <button class="btn btn-blue btn-sm" onclick="review(<?= $a['id'] ?>,'approved')">Approve</button>
          <button class="btn btn-outline-danger btn-sm" onclick="review(<?= $a['id'] ?>,'denied')">Deny</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- View Application Modal -->
<div class="modal fade" id="appModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header modal-hr-header">
        <h5 class="modal-title">Application Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><small class="text-muted">Full Name</small><div id="m_name" class="fw-bold"></div></div>
          <div class="col-md-6"><small class="text-muted">Reg. Number</small><div id="m_reg" class="fw-bold"></div></div>
          <div class="col-md-6"><small class="text-muted">Email</small><div id="m_email"></div></div>
          <div class="col-md-6"><small class="text-muted">Phone</small><div id="m_phone"></div></div>
          <div class="col-md-6"><small class="text-muted">Institution</small><div id="m_inst"></div></div>
          <div class="col-md-6"><small class="text-muted">Course / Year</small><div id="m_course"></div></div>
          <div class="col-md-6"><small class="text-muted">Preferred Department</small><div id="m_dept"></div></div>
          <div class="col-md-6"><small class="text-muted">Duration / Start Date</small><div id="m_duration"></div></div>
          <div class="col-12"><small class="text-muted">Skills</small><div id="m_skills"></div></div>
          <div class="col-12"><small class="text-muted">Cover Letter</small><div id="m_letter" class="border rounded p-2 bg-light"></div></div>

          <div class="col-12">
            <small class="text-muted">Documents submitted</small>
            <div id="m_docs" class="list-group mt-1"></div>

            <!-- Preview area -->
            <div id="docPreview" class="mt-3 d-none">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong id="docTitle"></strong>
                <button class="btn btn-sm btn-outline-secondary" onclick="closePreview()">Close preview</button>
              </div>
              <iframe id="docFrame" style="width:100%;height:450px;border:1px solid #e6e9f0;border-radius:8px;"></iframe>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-blue" onclick="reviewFromModal('approved')">Approve</button>
        <button class="btn btn-outline-danger" onclick="reviewFromModal('denied')">Deny</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  const applications = <?= json_encode($applications) ?>;
</script>

<?php require __DIR__ . '/../../includes/hr_footer.php'; ?>