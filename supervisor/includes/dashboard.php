<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Industrial Attachment Management System supervisor portal">
  <title>Dashboard | IAMS Supervisor</title>
  <!-- 1) Bootstrap, 2) IAMS theme. Order matters: the theme must load last. -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/supervisor.css?v=20261026-3">
</head>
<body class="auth-page">

<!-- View switch works with CSS alone; the script at the bottom adds titles, deep links, focus handling and the page data. -->
<input type="radio" name="view" id="view-dashboard" value="dashboard" class="view-radio" checked>
<input type="radio" name="view" id="view-students" value="students" class="view-radio">
<input type="radio" name="view" id="view-logbooks" value="logbooks" class="view-radio">
<input type="radio" name="view" id="view-reports" value="reports" class="view-radio">
<input type="radio" name="view" id="view-messages" value="messages" class="view-radio">
<input type="radio" name="view" id="view-profile" value="profile" class="view-radio">

<nav class="navbar navbar-dark iams-navbar">
  <div class="container-xl page-pad">
    <a class="navbar-brand fw-bold" href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"><span class="brand-mark">I</span> IAMS <small class="fw-normal">Supervisor Portal</small></a>
  </div>
</nav>

<main class="auth-main dash-main">
  <div class="container-xl page-pad">
    <div class="row g-4">

      <!-- ===== Left: menu ===== -->
      <aside class="col-lg-3">
        <div class="auth-card side-card">
          <div class="side-brand">
            <span class="side-shield" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 19 6v5c0 4.6-2.9 8.2-7 10-4.1-1.8-7-5.4-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
            </span>
            <span><strong>IAMS</strong><small>Supervisor</small></span>
          </div>
          <nav class="side-nav" aria-label="Supervisor sections">
            <label for="view-dashboard" class="side-link side-dashboard"><svg class="side-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg><span>Dashboard</span></label>
            <label for="view-students" class="side-link side-students"><svg class="side-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M3 20v-1.5a6 6 0 0 1 12 0V20H3Zm13-9a3 3 0 1 0 0-6"/><path d="M18 14a4 4 0 0 1 3 4v2h-3"/></svg><span>My students</span></label>
            <label for="view-logbooks" class="side-link side-logbooks"><svg class="side-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.5h9l4 4V20a1 1 0 0 1-1 1H6a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2Z"/><path d="M14.5 4v4h4M8 12h8M8 16h8"/></svg><span>Logbook reviews</span><span class="count" id="navPending" hidden></span></label>
            <label for="view-reports" class="side-link side-reports"><svg class="side-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.5h9l4 4V20a1 1 0 0 1-1 1H6a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2Z"/><path d="M14.5 4v4h4M8 12h8M8 16h5"/></svg><span>Final reports</span></label>
            <label for="view-messages" class="side-link side-messages"><svg class="side-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v12H9l-5 3v-15Z"/><path d="M8 10h8M8 14h5"/></svg><span>Messages</span></label>
            <label for="view-profile" class="side-link side-profile"><svg class="side-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg><span>My profile</span></label>
          </nav>
          <div class="profile-mini">
            <span class="avatar" id="avatar"></span>
            <span class="profile-details"><strong id="sideName"></strong><small id="sideDept"></small></span>
            <a href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?signout=1" class="side-signout">Sign out</a>
          </div>
        </div>
      </aside>

      <!-- ===== Right: sections ===== -->
      <section class="col-lg-9">

        <!-- DASHBOARD -->
        <div class="pane pane-dashboard">
          <div class="pane-head mb-4"><div class="eyebrow">WELCOME BACK</div><h1 class="h3 fw-bold mt-2" id="greet"></h1><p class="text-secondary mb-0">Here is what needs your attention today.</p></div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-6"><div class="feature-card h-100"><span class="feature-icon" id="stStudents">0</span><span><strong>Students</strong><small>Assigned to you</small></span></div></div>
            <div class="col-sm-6 col-xl-6"><div class="feature-card h-100"><span class="feature-icon" id="stPending">0</span><span><strong>Logbooks</strong><small>Waiting for review</small></span></div></div>
          </div>
          <div class="row g-3">
            <div class="col-12"><div class="auth-card h-100"><h2 class="h5 fw-bold mb-3">Logbooks awaiting review</h2><ul class="item-list" id="dashLogs"></ul></div></div>
            <div class="col-12"><div class="auth-card"><h2 class="h5 fw-bold mb-3">Student progress</h2><div class="row g-3" id="dashProgress"></div></div></div>
          </div>
        </div>

        <!-- MY STUDENTS -->
        <div class="pane pane-students">
          <div class="pane-head mb-4"><div class="eyebrow">SUPERVISION</div><h1 class="h3 fw-bold mt-2">My students</h1><p class="text-secondary mb-0">Students assigned to you for this attachment period.</p></div>
          <div class="auth-card">
            <div class="row g-2 mb-3">
              <div class="col-md-7"><label class="visually-hidden" for="stuSearch">Search students</label><input class="form-control" id="stuSearch" type="search" placeholder="Search by name, registration number or organisation"></div>
              <div class="col-md-5"><label class="visually-hidden" for="stuStatus">Filter by status</label><select class="form-select" id="stuStatus"><option value="">All statuses</option><option>On track</option><option>Needs attention</option><option>Completed</option></select></div>
            </div>
            <div class="table-responsive"><table class="table align-middle mb-0">
              <thead><tr><th>Student</th><th>Host organisation</th><th>Period</th><th>Progress</th><th>Status</th><th></th></tr></thead>
              <tbody id="stuBody"></tbody></table></div>
            <p class="text-secondary small mt-3 mb-0" id="stuCount"></p>
          </div>
        </div>

        <!-- LOGBOOK REVIEWS -->
        <div class="pane pane-logbooks">
          <div class="pane-head mb-4"><div class="eyebrow">REVIEW</div><h1 class="h3 fw-bold mt-2">Logbook reviews</h1><p class="text-secondary mb-0">Read each weekly entry, then approve it or return it with a comment.</p></div>
          <div class="row g-3">
            <div class="col-xl-5"><div class="auth-card h-100">
              <label class="form-label" for="logFilter">Show</label>
              <select class="form-select mb-3" id="logFilter"><option>Pending</option><option>Approved</option><option>Returned</option><option value="All">All entries</option></select>
              <div class="log-list" id="logList"></div>
            </div></div>
            <div class="col-xl-7"><div class="auth-card h-100" id="logDetail"></div></div>
          </div>
        </div>

        <!-- FINAL REPORTS -->
        <div class="pane pane-reports">
          <div class="pane-head mb-4"><div class="eyebrow">END OF ATTACHMENT</div><h1 class="h3 fw-bold mt-2">Final reports</h1><p class="text-secondary mb-0">Grade the reports your students have submitted.</p></div>
          <div class="auth-card"><div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Student</th><th>Report</th><th>Submitted</th><th>Status</th><th>Grade (out of 100)</th></tr></thead><tbody id="repBody"></tbody></table></div></div>
        </div>

        <!-- MESSAGES -->
        <div class="pane pane-messages">
          <div class="pane-head mb-4"><div class="eyebrow">COMMUNICATION</div><h1 class="h3 fw-bold mt-2">Messages</h1><p class="text-secondary mb-0">Send guidance or reminders to your students.</p></div>
          <div class="row g-3">
            <div class="col-xl-5"><div class="auth-card h-100"><h2 class="h5 fw-bold mb-3">New message</h2>
              <form id="msgForm" novalidate>
                <div class="mb-3"><label class="form-label" for="msgTo">To</label><select class="form-select" id="msgTo" required></select></div>
                <div class="mb-3"><label class="form-label" for="msgSubject">Subject</label><input class="form-control" id="msgSubject" type="text" required></div>
                <div class="mb-4"><label class="form-label" for="msgBody">Message</label><textarea class="form-control" id="msgBody" rows="5" required></textarea></div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Send message <span aria-hidden="true">→</span></button>
              </form></div></div>
            <div class="col-xl-7"><div class="auth-card h-100"><h2 class="h5 fw-bold mb-3">Sent messages</h2><ul class="item-list" id="msgList"></ul></div></div>
          </div>
        </div>

        <!-- PROFILE -->
        <div class="pane pane-profile">
          <div class="pane-head mb-4"><div class="eyebrow">ACCOUNT</div><h1 class="h3 fw-bold mt-2">My profile</h1><p class="text-secondary mb-0">Your details and password.</p></div>
          <div class="row g-3">
            <div class="col-xl-6"><div class="auth-card h-100"><h2 class="h5 fw-bold mb-3">Details</h2><dl class="detail-grid" id="profDetails"></dl></div></div>
            <div class="col-xl-6"><div class="auth-card h-100"><h2 class="h5 fw-bold mb-3">Change password</h2>
              <form id="passForm" novalidate>
                <div class="mb-3"><label class="form-label" for="pwOld">Current password</label><input class="form-control" id="pwOld" type="password" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label" for="pwNew">New password</label><input class="form-control" id="pwNew" type="password" required minlength="10" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{10,}" autocomplete="new-password"><div class="form-text">At least 10 characters, with letters and numbers.</div></div>
                <div class="mb-4"><label class="form-label" for="pwConf">Confirm new password</label><input class="form-control" id="pwConf" type="password" required autocomplete="new-password"></div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Update password <span aria-hidden="true">→</span></button>
              </form>
              <div class="security-note mt-4"><span aria-hidden="true">🔒</span> Your password is stored as a secure hash, never as plain text.</div>
            </div></div>
          </div>
        </div>

      </section>
    </div>
  </div>
</main>

<div id="toast" role="status" aria-live="polite" hidden></div>

<footer class="container-xl page-pad text-center text-secondary small py-3">© 2026 Industrial Attachment Management System</footer>

<script>
window.IAMS_SAVED = <?= json_encode($_SESSION['state'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.IAMS_CSRF = <?= json_encode($_SESSION['csrf']) ?>;
window.IAMS_ENDPOINT = <?= json_encode($self, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/js/supervisor.js?v=20261026-1" defer></script>
</body>
</html>
