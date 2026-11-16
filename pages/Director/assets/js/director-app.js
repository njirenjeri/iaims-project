document.addEventListener('DOMContentLoaded', function () {
  const tableSearch = document.getElementById('directorSearch');
  const applicationRows = document.querySelectorAll('#applicationsTable tbody tr');

  if (tableSearch && applicationRows.length) {
    tableSearch.addEventListener('input', function () {
      const term = this.value.trim().toLowerCase();

      applicationRows.forEach((row) => {
        const haystack = (row.dataset.search || row.textContent || '').toLowerCase();
        row.style.display = haystack.includes(term) ? '' : 'none';
      });
    });
  }

  document.querySelectorAll('.action-btn').forEach((button) => {
    button.addEventListener('click', function () {
      alert('Applicant details view is ready for backend integration.');
    });
  });

  const printButtons = document.querySelectorAll('.btn-director-secondary');
  printButtons.forEach((button) => {
    button.addEventListener('click', function () {
      if (this.textContent.toLowerCase().includes('print')) {
        window.print();
      }
    });
  });

  const sortableTables = document.querySelectorAll('.sortable-table');

  sortableTables.forEach((table) => {
    const headers = table.querySelectorAll('th[data-sort]');
    headers.forEach((header) => {
      header.style.cursor = 'pointer';
      header.addEventListener('click', function () {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const current = header.dataset.sortDirection || 'asc';
        const next = current === 'asc' ? 'desc' : 'asc';
        header.dataset.sortDirection = next;

        rows.sort((rowA, rowB) => {
          const cellA = rowA.children[Array.from(headers).indexOf(header)].textContent.trim();
          const cellB = rowB.children[Array.from(headers).indexOf(header)].textContent.trim();

          if (header.dataset.sort === 'date') {
            return next === 'asc'
              ? new Date(cellA) - new Date(cellB)
              : new Date(cellB) - new Date(cellA);
          }

          return next === 'asc'
            ? cellA.localeCompare(cellB)
            : cellB.localeCompare(cellA);
        });

        rows.forEach((row) => tbody.appendChild(row));
      });
    });
  });
});
