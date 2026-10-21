// app.js – behaviour for every page. Each section only runs if its elements exist.
(function () {
  'use strict';

  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ---------- Sidebar (mobile) ---------- */
  const sidebar  = $('#sidebar');
  const backdrop = $('#backdrop');
  const menuBtn  = $('#menuBtn');
  function setSidebar(open) {
    if (sidebar) sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('show', open);
  }
  if (menuBtn) menuBtn.addEventListener('click', () => setSidebar(!sidebar.classList.contains('open')));
  if (backdrop) backdrop.addEventListener('click', () => setSidebar(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setSidebar(false); });

  /* ---------- Helpers ---------- */
  function showToast(message) {
    const body = $('#toastBody');
    const toast = $('#toast');
    if (!body || !toast || !window.bootstrap) {
      window.alert(message);
      return;
    }
    body.textContent = message;
    bootstrap.Toast.getOrCreateInstance(toast, { delay: 4000 }).show();
  }

  async function post(data) {
    const main = $('main[data-admin-api]');
    data.append('csrf_token', main.dataset.csrfToken);
    const res = await fetch(main.dataset.adminApi, { method: 'POST', body: data });
    if (res.redirected && new URL(res.url, window.location.href).searchParams.get('page') === 'login') {
      window.location.assign(res.url);
      return { ok: false, message: 'Your session has ended. Redirecting to sign in.' };
    }
    let json;
    try { json = await res.json(); } catch (e) { json = { ok: false, message: 'Unexpected server response.' }; }
    return json;
  }

  function clearErrors(form) {
    $$('.is-invalid', form).forEach((el) => el.classList.remove('is-invalid'));
    const re = $('#roleError'); if (re) re.hidden = true;
  }
  function showErrors(form, errors) {
    Object.entries(errors).forEach(([field, msg]) => {
      if (field === 'role') {
        const re = $('#roleError'); re.textContent = msg; re.hidden = false;
        return;
      }
      const input = form.elements[field];
      if (!input) return;
      input.classList.add('is-invalid');
      const fb = input.parentElement.querySelector('.invalid-feedback');
      if (fb) fb.textContent = msg;
    });
    const first = $('.is-invalid', form);
    if (first) first.focus();
  }

  /* ---------- Users list: search + filters ---------- */
  const usersTable = $('#usersTable');
  if (usersTable) {
    const rows   = $$('tbody tr[data-status]', usersTable);
    const search = $('#userSearch');
    const role   = $('#roleFilter');
    const min    = $('#ministryFilter');
    const chips  = $$('#statusChips .chip');
    const none   = $('#noResults');

    function filterUsers() {
      const q      = search.value.trim().toLowerCase();
      const status = ($('#statusChips .chip.active') || {}).dataset?.status || '';
      let shown = 0;
      rows.forEach((tr) => {
        const ok = (!q || tr.dataset.name.includes(q))
                && (!role.value || tr.dataset.role === role.value)
                && (!min.value  || tr.dataset.ministry === min.value)
                && (!status     || tr.dataset.status === status);
        tr.hidden = !ok;
        if (ok) shown++;
      });
      none.hidden = shown !== 0;
    }
    rows.forEach((row) => {
      row.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, select')) return;
        window.location.assign(row.dataset.userUrl);
      });
      row.addEventListener('keydown', (event) => {
        if (event.target === row && (event.key === 'Enter' || event.key === ' ')) {
          event.preventDefault();
          window.location.assign(row.dataset.userUrl);
        }
      });
    });
    search.addEventListener('input', filterUsers);
    [role, min].forEach((el) => el.addEventListener('change', filterUsers));
    chips.forEach((chip) => chip.addEventListener('click', () => {
      chips.forEach((c) => c.classList.remove('active'));
      chip.classList.add('active');
      filterUsers();
    }));
    filterUsers();
  }

  /* ---------- Audit log: filters, expand rows, export ---------- */
  const auditTable = $('#auditTable');
  if (auditTable) {
    const rows = $$('tr.audit-row', auditTable);
    const none = $('#noResults');
    const f = {
      actor: $('#actorFilter'), target: $('#targetFilter'), action: $('#actionFilter'), outcome: $('#outcomeFilter'),
      from: $('#dateFrom'), to: $('#dateTo'),
    };

    function detailOf(row) { return document.getElementById(row.getAttribute('aria-controls')); }

    function filterAudit() {
      const target = f.target.value.trim().toLowerCase();
      let shown = 0;
      rows.forEach((tr) => {
        const d = tr.dataset.date;
        const ok = (!f.actor.value || tr.dataset.actor === f.actor.value)
                && (!target || tr.dataset.target.toLowerCase().includes(target))
                && (!f.action.value  || tr.dataset.action === f.action.value)
                && (!f.outcome.value || tr.dataset.outcome === f.outcome.value)
                && (!f.from.value || d >= f.from.value)
                && (!f.to.value   || d <= f.to.value);
        tr.hidden = !ok;
        const detail = detailOf(tr);
        if (!ok) { detail.hidden = true; tr.setAttribute('aria-expanded', 'false'); tr.classList.remove('selected'); }
        if (ok) shown++;
      });
      none.hidden = shown !== 0;
    }
    [f.target, f.from, f.to].forEach((el) => el.addEventListener('input', filterAudit));
    [f.actor, f.action, f.outcome, f.from, f.to].forEach((el) => el.addEventListener('change', filterAudit));

    function toggleRow(tr) {
      const detail = detailOf(tr);
      const open = detail.hidden;
      detail.hidden = !open;
      tr.setAttribute('aria-expanded', String(open));
      tr.classList.toggle('selected', open);
    }
    rows.forEach((tr) => {
      tr.addEventListener('click', () => toggleRow(tr));
      tr.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleRow(tr); }
      });
    });

    // Export the rows currently visible as CSV
    function csvCell(v) {
      let s = String(v ?? '');
      if (/^[=+\-@]/.test(s)) s = "'" + s;           // stop spreadsheet formula injection
      return '"' + s.replace(/"/g, '""') + '"';
    }
    $('#exportCsv').addEventListener('click', async () => {
      const visible = rows.filter((tr) => !tr.hidden);
      if (!visible.length) { showToast('Nothing to export. Adjust the filters first.'); return; }
      const lines = [['Time', 'Actor', 'Action', 'Target', 'Outcome', 'IP'].map(csvCell).join(',')];
      visible.forEach((tr) => {
        const d = tr.dataset;
        lines.push([d.time, d.actor, d.action, d.target, d.outcome, d.ip].map(csvCell).join(','));
      });
      const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'audit-log.csv';
      document.body.appendChild(a); a.click(); a.remove();
      URL.revokeObjectURL(a.href);

      const fd = new FormData(); fd.append('action', 'log_export');
      try {
        const result = await post(fd);
        if (!result.ok) throw new Error(result.message || 'Could not record the export.');
        showToast('Exported ' + visible.length + ' event' + (visible.length === 1 ? '' : 's') + '.');
      } catch (error) {
        showToast('CSV downloaded, but the export event could not be recorded.');
      }
    });
  }

  /* ---------- Invite form ---------- */
  const invite = $('#inviteForm');
  if (invite) {
    const ministries = JSON.parse(invite.dataset.ministries || '{}');
    const ministry   = $('#ministry');
    const dept       = $('#department');
    const deptGroup  = $('#deptGroup');

    function fillDepartments() {
      dept.replaceChildren();
      const list = ministries[ministry.value] || [];
      const ph = new Option('Choose a state department', '');
      dept.add(ph);
      list.forEach((d) => dept.add(new Option(d, d)));
    }
    function isSupervisor() {
      const r = invite.querySelector('input[name="role"]:checked');
      return r && r.value === 'Supervisor';
    }
    function syncDept() {
      deptGroup.hidden = !isSupervisor();
      dept.required = isSupervisor();
    }
    ministry.addEventListener('change', fillDepartments);
    $$('input[name="role"]', invite).forEach((r) => r.addEventListener('change', syncDept));
    if (ministry.options.length === 1) ministry.selectedIndex = 0;
    fillDepartments(); syncDept();

    invite.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors(invite);
      const btn = $('[type="submit"]', invite);
      btn.disabled = true;
      try {
        const fd = new FormData(invite);
        fd.append('action', 'invite');
        const data = await post(fd);
        if (!data.ok) {
          if (data.errors) showErrors(invite, data.errors); else showToast(data.message);
          return;
        }
        $('#inviteSummary').textContent = data.summary;
        $('#inviteLink').value = data.link;
        $('#inviteResult').hidden = false;
        $('#inviteResult').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        invite.reset(); fillDepartments(); syncDept();
        showToast(data.message);
      } catch (err) {
        showToast('Could not reach the server. Check your connection and try again.');
      } finally {
        btn.disabled = false;
      }
    });

    $('#copyLink').addEventListener('click', async () => {
      const input = $('#inviteLink');
      try { await navigator.clipboard.writeText(input.value); }
      catch (e) { input.select(); document.execCommand('copy'); }
      showToast('Invite link copied.');
    });
  }

  /* ---------- User detail: account actions ---------- */
  const actions = $('#accountActions');
  if (actions) {
    const modalEl = $('#actionModal');
    const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form    = $('#actionForm');
    const roleSelect = $('#newRole');
    const scopeSelect = $('#newScope');
    const ministries = JSON.parse(actions.dataset.ministries || '{}');
    const originalScope = actions.dataset.userScope;
    const userMinistry = actions.dataset.userMinistry;
    const copy = {
      force_reset:   ['Force password reset', 'The user must set a new password at next sign-in. Their current sessions end.', false, true],
      resend_invite: ['Resend invite',        'A fresh invite link replaces the old one.',                                   false, false],
      change_role:   ['Change role or scope', 'Choose the new role. The change is recorded in the audit log.',               true,  true],
      suspend:       ['Suspend account',      'The user is signed out everywhere and cannot sign in until reactivated.',     false, true],
      reactivate:    ['Reactivate account',   'The user can sign in again with their existing password.',                    false, false],
    };
    let current = '';

    function syncScopes() {
      const role = roleSelect.value;
      const scopes = role === 'Supervisor' ? (ministries[userMinistry] || []) : [userMinistry];
      scopeSelect.replaceChildren(...scopes.map((scope) => new Option(scope, scope)));
      scopeSelect.value = scopes.includes(originalScope) ? originalScope : (scopes[0] || '');
    }

    $$('[data-account-action]', actions).forEach((btn) => btn.addEventListener('click', () => {
      current = btn.dataset.accountAction;
      const [title, text, showRole, showReason] = copy[current];
      $('#actionTitle').textContent = title + ': ' + actions.dataset.userName;
      $('#actionText').textContent = text;
      $('#roleGroup').hidden = !showRole;
      $('#reasonGroup').hidden = !showReason;
      if (showRole) syncScopes();
      $('#actionConfirm').textContent = title;
      modal.show();
    }));
    roleSelect.addEventListener('change', syncScopes);

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = $('#actionConfirm');
      btn.disabled = true;
      try {
        const fd = new FormData(form);
        fd.append('action', 'account_action');
        fd.append('type', current);
        fd.append('user_id', actions.dataset.userId);
        const data = await post(fd);
        modal.hide();
        showToast(data.message || (data.ok ? 'Done.' : 'Something went wrong.'));
        if (data.ok) window.setTimeout(() => window.location.reload(), 700);
      } catch (err) {
        showToast('Could not reach the server. Check your connection and try again.');
      } finally {
        btn.disabled = false;
      }
    });
    modalEl.addEventListener('hidden.bs.modal', () => form.reset());
  }

  ['settingsForm', 'securityForm'].forEach((id) => {
    const form = $('#' + id);
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = $('[type="submit"]', form);
      button.disabled = true;
      try {
        const data = await post(new FormData(form));
        showToast(data.message || (data.ok ? 'Changes saved.' : 'Unable to save changes.'));
      } catch (error) {
        showToast('Could not reach the server. Check your connection and try again.');
      } finally {
        button.disabled = false;
      }
    });
  });
})();
