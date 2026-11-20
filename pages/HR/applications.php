<?php
require __DIR__ . '/../../includes/hr_data.php';
$pageTitle = 'Applications';
$active = 'applications';
require __DIR__ . '/../../includes/hr_header.php';

$statusUi = [
  'pending'  => ['bg-warning text-dark', 'Pending'],
  'approved' => ['bg-success', 'Approved'],
  'denied'   => ['bg-danger', 'Denied'],
];
?>

<div class="card-hr p-3 table-responsive">
  <table class="table align-middle">
    <thead><tr><th>Student</th><th>Institution</th><th>Course</th><th>Date</th><th>Documents</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($applications as $a):
      $st = strtolower($a['status'] ?? 'pending');
      [$cls, $label] = $statusUi[$st] ?? $statusUi['pending'];
    ?>
      <tr id="row-<?= (int)$a['id'] ?>">
        <td><?= htmlspecialchars($a['name']) ?></td>
        <td><?= htmlspecialchars($a['institution']) ?></td>
        <td><?= htmlspecialchars($a['course']) ?></td>
        <td><?= date('d M Y', strtotime($a['date'])) ?></td>
        <td><span class="badge bg-secondary"><?= count(array_filter($a['docs'])) ?>/6 submitted</span></td>
        <td><span class="badge <?= $cls ?> status"><?= $label ?></span></td>
        <td class="actions">
          <button class="btn btn-gold btn-sm" onclick="viewApplication(<?= (int)$a['id'] ?>)">
            <i class="bi bi-eye"></i> View
          </button>
          <button class="btn btn-blue btn-sm" onclick="review(<?= (int)$a['id'] ?>,'approved')">Approve</button>
          <button class="btn btn-outline-danger btn-sm" onclick="review(<?= (int)$a['id'] ?>,'denied')">Deny</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- View Application Modal -->
<div class="modal fade" id="appModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header modal-hr-header">
        <h5 class="modal-title">Application Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
          <div class="col-12"><small class="text-muted">Cover Letter</small><div id="m_letter" class="border rounded p-2 bg-light" style="white-space:pre-wrap"></div></div>

          <div class="col-12">
            <small class="text-muted">Documents submitted</small>
            <div id="m_docs" class="list-group mt-1"></div>

            <div id="docPreview" class="mt-3 d-none">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong id="docTitle"></strong>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="closePreview()">Close preview</button>
              </div>
              <iframe id="docFrame" title="Document preview" style="width:100%;height:450px;border:1px solid #e6e9f0;border-radius:8px;"></iframe>
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
const applications = <?= json_encode($applications, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let currentId = null;

const STATUS_UI = {
  pending:  ['bg-warning text-dark', 'Pending'],
  approved: ['bg-success', 'Approved'],
  denied:   ['bg-danger', 'Denied']
};

const $ = id => document.getElementById(id);
const byId = id => applications.find(a => Number(a.id) === Number(id));
const pick = (a, ...keys) => { for (const k of keys) if (a[k] != null && a[k] !== '') return a[k]; return ''; };
const setText = (id, v) => { $(id).textContent = v || '—'; };

function safeUrl(path) {
  try {
    const u = new URL(path, location.href);
    return (u.protocol === 'http:' || u.protocol === 'https:') ? u.href : null;
  } catch { return null; }
}

function viewApplication(id) {
  const a = byId(id);
  if (!a) return;
  currentId = a.id;

  setText('m_name', a.name);
  setText('m_reg', pick(a, 'reg_no', 'reg', 'registration_number'));
  setText('m_email', a.email);
  setText('m_phone', a.phone);
  setText('m_inst', a.institution);
  setText('m_course', [a.course, pick(a, 'year', 'year_of_study')].filter(Boolean).join(' / '));
  setText('m_dept', pick(a, 'department', 'dept', 'preferred_department'));
  setText('m_duration', [pick(a, 'duration'), pick(a, 'start_date', 'start')].filter(Boolean).join(' / '));
  setText('m_skills', Array.isArray(a.skills) ? a.skills.join(', ') : a.skills);
  setText('m_letter', pick(a, 'cover_letter', 'letter'));

  // documents list (built with DOM methods so data is never parsed as HTML)
  const list = $('m_docs');
  list.replaceChildren();
  Object.entries(a.docs || {}).forEach(([key, value]) => {
    const label = isNaN(key) ? key : String(value).split('/').pop();
    const item = document.createElement(value ? 'button' : 'div');
    item.className = 'list-group-item d-flex justify-content-between align-items-center' + (value ? ' list-group-item-action' : '');
    const name = document.createElement('span');
    name.textContent = label;
    const tag = document.createElement('span');
    tag.className = 'badge ' + (value ? 'bg-success' : 'bg-secondary');
    tag.textContent = value ? 'View' : 'Not submitted';
    item.append(name, tag);
    if (value) { item.type = 'button'; item.onclick = () => previewDoc(label, value); }
    list.appendChild(item);
  });

  closePreview();
  bootstrap.Modal.getOrCreateInstance($('appModal')).show();
}

function previewDoc(label, path) {
  const url = safeUrl(path);
  if (!url) { alert('This document link is not valid.'); return; }
  $('docTitle').textContent = label;
  $('docFrame').src = url;
  $('docPreview').classList.remove('d-none');
  $('docPreview').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closePreview() {
  $('docFrame').removeAttribute('src');
  $('docPreview').classList.add('d-none');
}

async function review(id, decision) {
  const a = byId(id);
  if (!a) return;
  const verb = decision === 'approved' ? 'Approve' : 'Deny';
  if (!confirm(verb + ' the application from ' + a.name + '?')) return;

  try {
    const res = await fetch('review_application.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: a.id, status: decision })
    });
    if (!res.ok) throw new Error('Server returned ' + res.status);
  } catch (err) {
    alert('Could not save the decision: ' + err.message);
    return;
  }

  a.status = decision;
  const badge = document.querySelector('#row-' + a.id + ' .status');
  if (badge) {
    const [cls, label] = STATUS_UI[decision];
    badge.className = 'badge ' + cls + ' status';
    badge.textContent = label;
  }
  bootstrap.Modal.getOrCreateInstance($('appModal')).hide();
}

function reviewFromModal(decision) {
  if (currentId != null) review(currentId, decision);
}

$('appModal').addEventListener('hidden.bs.modal', closePreview);
</script>

<?php require __DIR__ . '/../../includes/hr_footer.php'; ?>