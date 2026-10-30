/* IAMS supervisor page behaviour: view titles and deep links, students, logbook reviews, reports, messages, profile.
   Sample data below stands in for your server: replace the arrays with data loaded from your backend. */
(function () {
  'use strict';

  var TODAY = '2026-10-21';
  var me = { name: 'Dr. Jane Mwangi', email: 'jane.mwangi@example.com', staffNo: 'SUP-0142', dept: 'Computer Science', phone: '+254 700 000 000' };
  var students = [
    { id: 1, name: 'Amina Otieno', reg: 'CS/0231/22', course: 'BSc Computer Science', org: 'Nova Tech Ltd', start: '2026-09-07', end: '2026-12-04', progress: 42, status: 'On track' },
    { id: 2, name: 'Brian Kamau', reg: 'CS/0187/22', course: 'BSc Computer Science', org: 'Greenfield Energy', start: '2026-09-07', end: '2026-12-04', progress: 35, status: 'Needs attention' },
    { id: 3, name: 'Cynthia Wanjiru', reg: 'IT/0412/22', course: 'BSc Information Technology', org: 'Lakeside Hospital', start: '2026-09-14', end: '2026-12-11', progress: 55, status: 'On track' },
    { id: 4, name: 'David Mutua', reg: 'IT/0399/22', course: 'BSc Information Technology', org: 'Summit Bank Ltd', start: '2026-09-14', end: '2026-12-11', progress: 48, status: 'On track' },
    { id: 5, name: 'Esther Njeri', reg: 'CS/0256/22', course: 'BSc Computer Science', org: 'Apex Engineering', start: '2026-08-03', end: '2026-10-30', progress: 92, status: 'On track' },
    { id: 6, name: 'Felix Omondi', reg: 'SE/0120/22', course: 'BSc Software Engineering', org: 'BrightPath Media', start: '2026-09-07', end: '2026-12-04', progress: 28, status: 'Needs attention' },
    { id: 7, name: 'Grace Achieng', reg: 'SE/0145/22', course: 'BSc Software Engineering', org: 'Nova Tech Ltd', start: '2026-06-01', end: '2026-08-28', progress: 100, status: 'Completed' },
    { id: 8, name: 'Hassan Abdi', reg: 'IT/0377/22', course: 'BSc Information Technology', org: 'Summit Bank Ltd', start: '2026-09-14', end: '2026-12-11', progress: 40, status: 'On track' }
  ];
  var logs = [
    { id: 1, sid: 1, week: 6, period: '12 to 16 Oct 2026', hours: 40, submitted: '2026-10-19', status: 'Pending', comment: '', work: 'Built the customer search page and wrote unit tests for the API.', learn: 'Test-driven development, REST API design.' },
    { id: 2, sid: 2, week: 6, period: '12 to 16 Oct 2026', hours: 38, submitted: '2026-10-19', status: 'Pending', comment: '', work: 'Assisted with site monitoring and prepared the weekly energy report.', learn: 'Data collection, report writing.' },
    { id: 3, sid: 3, week: 6, period: '12 to 16 Oct 2026', hours: 40, submitted: '2026-10-18', status: 'Pending', comment: '', work: 'Updated patient records module and trained two nurses on the new screens.', learn: 'User training, database updates.' },
    { id: 4, sid: 6, week: 5, period: '5 to 9 Oct 2026', hours: 32, submitted: '2026-10-12', status: 'Returned', comment: 'Please list the specific tasks you completed each day.', work: 'Worked on the media archive.', learn: 'File management.' },
    { id: 5, sid: 4, week: 5, period: '5 to 9 Oct 2026', hours: 40, submitted: '2026-10-12', status: 'Approved', comment: 'Good detail. Keep it up.', work: 'Reconciled mobile banking transactions and documented exceptions.', learn: 'Financial controls, documentation.' },
    { id: 6, sid: 8, week: 6, period: '12 to 16 Oct 2026', hours: 40, submitted: '2026-10-20', status: 'Pending', comment: '', work: 'Configured branch network monitoring dashboards.', learn: 'Network monitoring, dashboards.' },
    { id: 7, sid: 5, week: 12, period: '12 to 16 Oct 2026', hours: 40, submitted: '2026-10-20', status: 'Pending', comment: '', work: 'Completed final site drawings and handed over project files.', learn: 'CAD drawing standards, project handover.' }
  ];
  var visits = [
    { id: 1, sid: 2, date: '2026-10-27', time: '10:00', mode: 'Physical', purpose: 'Mid-attachment assessment', status: 'Scheduled' },
    { id: 2, sid: 6, date: '2026-10-29', time: '14:30', mode: 'Virtual', purpose: 'Progress check and logbook discussion', status: 'Scheduled' },
    { id: 3, sid: 5, date: '2026-10-15', time: '09:00', mode: 'Physical', purpose: 'Final assessment', status: 'Completed' }
  ];
  var assessments = [{ sid: 7, pct: 87, date: '2026-08-28', comment: 'Excellent attachment.' }];
  var reports = [
    { sid: 7, title: 'Attachment report: web development', submitted: '2026-09-05', status: 'Graded', grade: 84 },
    { sid: 5, title: 'Attachment report: structural drawing', submitted: '2026-10-20', status: 'Submitted', grade: '' },
    { sid: 1, title: '', submitted: '', status: 'Not submitted', grade: '' },
    { sid: 2, title: '', submitted: '', status: 'Not submitted', grade: '' },
    { sid: 3, title: '', submitted: '', status: 'Not submitted', grade: '' },
    { sid: 4, title: '', submitted: '', status: 'Not submitted', grade: '' },
    { sid: 6, title: '', submitted: '', status: 'Not submitted', grade: '' },
    { sid: 8, title: '', submitted: '', status: 'Not submitted', grade: '' }
  ];
  var sent = [{ to: 'Brian Kamau', subject: 'Logbook reminder', body: 'Please add daily task details to your weekly logbook entries.', date: '2026-10-14' }];

  /* ---- Saved state from the server (PHP session); falls back to the sample data above ---- */
  var saved = window.IAMS_SAVED;
  if (saved) { logs = saved.logs; visits = saved.visits; assessments = saved.assessments; reports = saved.reports; sent = saved.sent; }
  function post(action, payload) {
    return fetch(window.IAMS_ENDPOINT + '?action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.IAMS_CSRF },
      body: JSON.stringify(payload)
    }).then(function (res) { return res.json().then(function (data) { data.ok = res.ok; return data; }); });
  }
  function save() {
    post('save', { logs: logs, visits: visits, assessments: assessments, reports: reports, sent: sent })
      .then(function (d) { if (!d.ok) { toast(d.error || 'Your changes could not be saved.', 'warn'); } })
      .catch(function () { toast('Your changes could not be saved.', 'warn'); });
  }
  function commit() { renderAll(); save(); }

  var titles = { dashboard: 'Dashboard', students: 'My students', logbooks: 'Logbook reviews', reports: 'Final reports', messages: 'Messages', profile: 'My profile' };
  var logFilter = 'Pending', selectedLog = null, toastTimer;

  /* ---- Helpers ---- */
  function $(s) { return document.querySelector(s); }
  function $$(s) { return Array.prototype.slice.call(document.querySelectorAll(s)); }
  function esc(t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function sById(id) { return students.filter(function (s) { return s.id === Number(id); })[0]; }
  function fmt(d) { return d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : ''; }
  function pill(status) {
    var m = { 'On track': 'ok', 'Needs attention': 'warn', 'Completed': 'ok', 'Pending': 'warn', 'Approved': 'ok', 'Returned': 'bad', 'Scheduled': 'info', 'Cancelled': 'bad', 'Submitted': 'info', 'Graded': 'ok', 'Not submitted': 'warn' };
    return '<span class="pill pill-' + (m[status] || 'info') + '">' + esc(status) + '</span>';
  }
  function meter(p) { return '<div class="strength" role="progressbar" aria-valuenow="' + p + '" aria-valuemin="0" aria-valuemax="100"><span style="width:' + p + '%"></span></div>'; }
  function toast(msg, type) {
    var t = $('#toast');
    t.className = type || 'ok'; t.textContent = msg; t.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.hidden = true; }, 3500);
  }
  function empty(msg) { return '<li class="empty">' + msg + '</li>'; }
  function checkForm(form) {
    if (!form.checkValidity()) { form.reportValidity(); return false; }
    return true;
  }

  /* ---- Which view is showing (CSS shows the pane; this sets titles, deep links, focus) ---- */
  function viewFromHash() {
    var v = window.location.hash.slice(1);
    return titles[v] ? v : 'dashboard';
  }
  function applyView(view, focusFirst) {
    $('#view-' + view).checked = true;
    document.title = titles[view] + ' | IAMS Supervisor';
    if (focusFirst) {
      var first = $('.pane-' + view + ' .form-control, .pane-' + view + ' .form-select');
      if (first) { first.focus({ preventScroll: true }); }
    }
  }
  function go(view, focusFirst) {
    applyView(view, focusFirst);
    if (history.replaceState) { history.replaceState(null, '', '#' + view); }
    window.scrollTo(0, 0);
  }
  $$('.view-radio').forEach(function (radio) {
    radio.addEventListener('change', function () { go(radio.value, true); });
  });
  window.addEventListener('hashchange', function () { applyView(viewFromHash(), false); });

  /* ---- Renderers ---- */
  function renderStats() {
    var pend = logs.filter(function (l) { return l.status === 'Pending'; }).length;
    $('#stStudents').textContent = students.length;
    $('#stPending').textContent = pend;
    $('#navPending').textContent = pend;
    $('#navPending').hidden = !pend;
  }

  function renderDashboard() {
    var pend = logs.filter(function (l) { return l.status === 'Pending'; }).slice(0, 4);
    $('#dashLogs').innerHTML = pend.length ? pend.map(function (l) {
      var s = sById(l.sid);
      return '<li><div><strong>' + esc(s.name) + '</strong><small>Week ' + l.week + ' logbook, submitted ' + fmt(l.submitted) + '</small></div><button class="btn btn-outline-secondary btn-sm" type="button" data-act="open-log" data-id="' + l.id + '">Review</button></li>';
    }).join('') : empty('No logbooks are waiting for review.');
    $('#dashProgress').innerHTML = students.map(function (s) {
      return '<div class="col-md-6 col-xl-3"><div class="prog-card h-100"><div><strong>' + esc(s.name) + '</strong><span>' + s.progress + '%</span></div>' + meter(s.progress) + '</div></div>';
    }).join('');
  }

  function renderStudents() {
    var q = $('#stuSearch').value.trim().toLowerCase(), f = $('#stuStatus').value;
    var rows = students.filter(function (s) {
      return (!f || s.status === f) && (!q || (s.name + ' ' + s.reg + ' ' + s.org).toLowerCase().indexOf(q) > -1);
    });
    $('#stuBody').innerHTML = rows.length ? rows.map(function (s) {
      return '<tr><td><strong>' + esc(s.name) + '</strong><small>' + esc(s.reg) + ', ' + esc(s.course) + '</small></td><td>' + esc(s.org) + '</td><td>' + fmt(s.start) + '<small>to ' + fmt(s.end) + '</small></td><td style="min-width:130px">' + meter(s.progress) + '<small>' + s.progress + '%</small></td><td>' + pill(s.status) + '</td><td class="text-end"><button class="btn btn-outline-secondary btn-sm" type="button" data-act="msg" data-id="' + s.id + '">Message</button></td></tr>';
    }).join('') : '<tr><td colspan="6" class="text-center text-secondary py-4">No students match your search.</td></tr>';
    $('#stuCount').textContent = 'Showing ' + rows.length + ' of ' + students.length + ' students';
  }

  function renderLogs() {
    var list = logs.filter(function (l) { return logFilter === 'All' || l.status === logFilter; });
    $('#logList').innerHTML = list.length ? list.map(function (l) {
      return '<button class="log-item' + (l.id === selectedLog ? ' active' : '') + '" type="button" data-act="pick-log" data-id="' + l.id + '"><span><strong>' + esc(sById(l.sid).name) + '</strong><small>Week ' + l.week + ', ' + esc(l.period) + '</small></span>' + pill(l.status) + '</button>';
    }).join('') : '<p class="text-secondary mb-0">No ' + (logFilter === 'All' ? '' : logFilter.toLowerCase() + ' ') + 'entries.</p>';
    var l = logs.filter(function (x) { return x.id === selectedLog; })[0], d = $('#logDetail');
    if (!l) { d.innerHTML = '<div class="placeholder-box">Select a logbook entry to read and review it.</div>'; return; }
    var s = sById(l.sid);
    d.innerHTML = '<div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h2 class="panel-title mb-1">' + esc(s.name) + ': Week ' + l.week + '</h2><span class="text-secondary small">' + esc(s.org) + ', ' + esc(l.period) + ', ' + l.hours + ' hours, submitted ' + fmt(l.submitted) + '</span></div>' + pill(l.status) + '</div>' +
      '<div class="form-label">Work done</div><div class="log-text">' + esc(l.work) + '</div>' +
      '<div class="form-label">Skills and knowledge gained</div><div class="log-text">' + esc(l.learn) + '</div>' +
      '<label class="form-label" for="logComment">Your comment</label><textarea class="form-control mb-3" id="logComment" rows="3" placeholder="Required when returning an entry for correction">' + esc(l.comment) + '</textarea>' +
      '<div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="button" data-act="decide" data-decision="Approved" data-id="' + l.id + '">Approve entry</button><button class="btn btn-outline-secondary" type="button" data-act="decide" data-decision="Returned" data-id="' + l.id + '">Return for correction</button></div>';
  }

  function renderReports() {
    $('#repBody').innerHTML = reports.map(function (r) {
      var can = r.status !== 'Not submitted';
      return '<tr><td><strong>' + esc(sById(r.sid).name) + '</strong></td><td>' + (r.title ? esc(r.title) : '<span class="text-secondary">Nothing submitted yet</span>') + '</td><td>' + (r.submitted ? fmt(r.submitted) : '-') + '</td><td>' + pill(r.status) + '</td><td>' +
        (can ? '<div class="d-flex gap-2"><input class="form-control grade-input" type="number" min="0" max="100" value="' + esc(r.grade) + '" aria-label="Grade for ' + esc(sById(r.sid).name) + '" id="g' + r.sid + '"><button class="btn btn-outline-secondary btn-sm" type="button" data-act="grade" data-id="' + r.sid + '">Save grade</button></div>' : '-') + '</td></tr>';
    }).join('');
  }

  function renderMessages() {
    $('#msgList').innerHTML = sent.length ? sent.map(function (m) {
      return '<li><div><strong>' + esc(m.subject) + '</strong><small>To ' + esc(m.to) + ', ' + fmt(m.date) + '</small><small>' + esc(m.body) + '</small></div></li>';
    }).join('') : empty('You have not sent any messages yet.');
  }

  function fillSelect(id, extra) {
    var el = $(id), cur = el.value;
    el.innerHTML = '<option value="">Select a student</option>' + (extra ? '<option value="all">All my students</option>' : '') + students.map(function (s) {
      return '<option value="' + s.id + '">' + esc(s.name) + '</option>';
    }).join('');
    el.value = cur;
  }

  function renderAll() {
    renderStats(); renderDashboard(); renderStudents(); renderLogs(); renderReports(); renderMessages();
    fillSelect('#msgTo', true);
  }

  /* ---- Actions (buttons inside rendered content) ---- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-act]');
    if (!t) { return; }
    var act = t.getAttribute('data-act'), id = Number(t.getAttribute('data-id'));
    if (act === 'open-log' || act === 'pick-log') {
      selectedLog = id;
      var l = logs.filter(function (x) { return x.id === id; })[0];
      if (act === 'open-log') { logFilter = l.status; $('#logFilter').value = logFilter; go('logbooks', false); }
      renderLogs();
    } else if (act === 'decide') {
      var entry = logs.filter(function (x) { return x.id === id; })[0], c = $('#logComment').value.trim(), dec = t.getAttribute('data-decision');
      if (dec === 'Returned' && !c) { $('#logComment').focus(); toast('Add a comment explaining what needs correction.', 'warn'); return; }
      entry.status = dec; entry.comment = c;
      var next = logs.filter(function (x) { return x.status === 'Pending'; })[0];
      selectedLog = logFilter === 'Pending' ? (next ? next.id : null) : entry.id;
      commit();
      toast(dec === 'Approved' ? 'Entry approved.' : 'Entry returned to the student.');
    } else if (act === 'grade') {
      var inp = $('#g' + id), g = inp.value;
      if (g === '' || Number(g) < 0 || Number(g) > 100) { inp.focus(); toast('Enter a grade between 0 and 100.', 'warn'); return; }
      var r = reports.filter(function (x) { return x.sid === id; })[0]; r.grade = Number(g); r.status = 'Graded';
      commit(); toast('Grade saved.');
    } else if (act === 'msg') {
      $('#msgTo').value = id; go('messages', false); $('#msgSubject').focus();
    }
  });

  $('#stuSearch').addEventListener('input', renderStudents);
  $('#stuStatus').addEventListener('change', renderStudents);
  $('#logFilter').addEventListener('change', function () { logFilter = this.value; selectedLog = null; renderLogs(); });

  /* ---- Forms ---- */
  $('#msgForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!checkForm(this)) { return; }
    var to = $('#msgTo').value;
    sent.unshift({ to: to === 'all' ? 'All my students' : sById(to).name, subject: $('#msgSubject').value.trim(), body: $('#msgBody').value.trim(), date: TODAY });
    this.reset(); commit(); toast('Message sent.');
  });

  $('#passForm').addEventListener('submit', function (e) {
    e.preventDefault();
    $('#pwConf').setCustomValidity($('#pwConf').value === $('#pwNew').value ? '' : 'Passwords do not match.');
    if (!checkForm(this)) { return; }
    var form = this;
    post('password', { old: $('#pwOld').value, next: $('#pwNew').value, confirm: $('#pwConf').value }).then(function (d) {
      if (d.ok) { form.reset(); }
      toast(d.message || d.error || 'Password could not be checked.', d.ok ? 'ok' : 'warn');
    }).catch(function () { toast('Password could not be checked.', 'warn'); });
  });
  $('#pwNew').addEventListener('input', function () { $('#pwConf').setCustomValidity(''); });

  /* ---- Start ---- */
  var initials = me.name.replace(/^(Dr|Prof|Mr|Mrs|Ms)\.?\s+/i, '').split(' ').map(function (p) { return p.charAt(0); }).join('').slice(0, 2);
  $('#avatar').textContent = initials;
  $('#sideName').textContent = me.name;
  $('#sideDept').textContent = me.dept;
  $('#greet').textContent = 'Hello, ' + me.name;
  $('#profDetails').innerHTML = [['Full name', me.name], ['Email', me.email], ['Staff number', me.staffNo], ['Department', me.dept], ['Phone', me.phone]].map(function (r) {
    return '<dt>' + r[0] + '</dt><dd>' + esc(r[1]) + '</dd>';
  }).join('');
  renderAll();
  applyView(viewFromHash(), false);
})();
