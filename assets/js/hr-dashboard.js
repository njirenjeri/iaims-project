const uploadsPath = '../../assets/uploads/';

// The 6 documents: key => [label, icon]
const docLabels = {
  attachment_letter:  ['Attachment Letter',          'bi-envelope-paper'],
  application_letter: ['Application Letter',         'bi-file-earmark-text'],
  cv:                 ['Curriculum Vitae (CV)',      'bi-file-earmark-person'],
  good_conduct:       ['Certificate of Good Conduct','bi-shield-check'],
  medical_insurance:  ['Medical Insurance',          'bi-heart-pulse'],
  id_passport:        ['ID / Passport',              'bi-person-vcard']
};

// Approve / Deny
function review(id, decision) {
  const row = document.getElementById('row-' + id);
  const badge = row.querySelector('.status');
  badge.className = 'badge status ' + (decision === 'approved' ? 'bg-success' : 'bg-danger');
  badge.textContent = decision === 'approved' ? 'Approved' : 'Denied';
  row.querySelector('.actions').innerHTML = '<span class="text-muted">Reviewed</span>';
}

// Add supervisor (frontend only)
function addSupervisor(e) {
  e.preventDefault();
  const f = e.target;
  const row = `<tr>
    <td>${f.name.value}</td>
    <td>${f.department.value}</td>
    <td>${f.email.value}</td>
    <td>0</td></tr>`;
  document.getElementById('supList').insertAdjacentHTML('beforeend', row);
  f.reset();
}

// View application in a modal
let currentAppId = null;

function viewApplication(id) {
  const a = applications.find(x => x.id === id);
  currentAppId = id;
  closePreview();

  document.getElementById('m_name').textContent     = a.name;
  document.getElementById('m_reg').textContent      = a.reg_no;
  document.getElementById('m_email').textContent    = a.email;
  document.getElementById('m_phone').textContent    = a.phone;
  document.getElementById('m_inst').textContent     = a.institution;
  document.getElementById('m_course').textContent   = a.course + ' - ' + a.year;
  document.getElementById('m_dept').textContent     = a.department;
  document.getElementById('m_duration').textContent = a.duration + ' from ' + a.preferred_start;
  document.getElementById('m_skills').textContent   = a.skills;
  document.getElementById('m_letter').textContent   = a.letter;

  // Build the 6 document rows
  let html = '';
  for (const key in docLabels) {
    const file = a.docs[key];
    const label = `<span><i class="bi ${docLabels[key][1]} text-primary"></i> ${docLabels[key][0]}</span>`;

    if (!file) {
      html += `<div class="list-group-item d-flex justify-content-between align-items-center">
        ${label}<span class="badge bg-danger">Not submitted</span></div>`;
    } else {
      html += `<div class="list-group-item d-flex justify-content-between align-items-center">
        ${label}
        <span>
          <button class="btn btn-blue btn-sm" onclick="previewDoc('${key}')">View</button>
          <a href="${uploadsPath + file}" download class="btn btn-gold btn-sm">Download</a>
        </span></div>`;
    }
  }
  document.getElementById('m_docs').innerHTML = html;

  new bootstrap.Modal(document.getElementById('appModal')).show();
}

// Preview a document inside the popup
function previewDoc(key) {
  const a = applications.find(x => x.id === currentAppId);
  document.getElementById('docTitle').textContent = docLabels[key][0];
  document.getElementById('docFrame').src = uploadsPath + a.docs[key];
  const box = document.getElementById('docPreview');
  box.classList.remove('d-none');
  box.scrollIntoView({ behavior: 'smooth' });
}

function closePreview() {
  document.getElementById('docFrame').src = '';
  document.getElementById('docPreview').classList.add('d-none');
}

// Approve/Deny from inside the modal
function reviewFromModal(decision) {
  review(currentAppId, decision);
  bootstrap.Modal.getInstance(document.getElementById('appModal')).hide();
}
