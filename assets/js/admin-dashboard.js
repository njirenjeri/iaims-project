// Admin dashboard sidebar navigation.
(function () {
  'use strict';

  // ---------- Sidebar (mobile) ----------
  const sidebar  = document.getElementById('sidebar');
  const backdrop = document.getElementById('backdrop');
  const menuBtn  = document.getElementById('menuBtn');

  function setSidebar(open) {
    sidebar.classList.toggle('open', open);
    backdrop.classList.toggle('show', open);
  }
  menuBtn.addEventListener('click', () => setSidebar(!sidebar.classList.contains('open')));
  backdrop.addEventListener('click', () => setSidebar(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setSidebar(false); });

  // Highlight the clicked nav link
  document.querySelectorAll('.sidebar .nav-link').forEach((link) => {
    link.addEventListener('click', () => {
      document.querySelectorAll('.sidebar .nav-link').forEach((l) => l.classList.remove('active'));
      link.classList.add('active');
      setSidebar(false);
    });
  });

})();
