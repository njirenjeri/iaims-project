document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Attendance Trend Line Chart
    const ctxAttendance = document.getElementById('attendanceChart');
    if (ctxAttendance) {
        new Chart(ctxAttendance.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Mon Nov 9', 'Tue Nov 10', 'Wed Nov 11', 'Thu Nov 12', 'Fri Nov 13'],
                datasets: [
                    {
                        label: 'Present',
                        data: [95, 95, 92, 88, 85],
                        borderColor: '#0d6efd',
                        backgroundColor: '#0d6efd',
                        tension: 0.2,
                        pointRadius: 4,
                        borderWidth: 2
                    },
                    {
                        label: 'Absent',
                        data: [10, 11, 12, 14, 15],
                        borderColor: '#ffc107',
                        backgroundColor: '#ffc107',
                        tension: 0.2,
                        pointRadius: 4,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8 }
                    } 
                },
                scales: { 
                    y: { beginAtZero: true, max: 120, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. Report Progress Donut Chart
    const ctxProgress = document.getElementById('reportProgressChart');
    if (ctxProgress) {
        new Chart(ctxProgress.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Submitted', 'Pending', 'Reviewed', 'Completing Soon', 'Completed'],
                datasets: [{
                    data: [85, 20, 70, 5, 40],
                    backgroundColor: ['#0d6efd', '#ffc107', '#198754', '#0dcaf0', '#212529'],
                    borderWidth: 0,
                    cutout: '75%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }

    // 3. Department Overview Bar Chart
    const ctxDept = document.getElementById('departmentChart');
    if (ctxDept) {
        new Chart(ctxDept.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['IT', 'Finance', 'HR', 'Sales', 'Operations'],
                datasets: [{
                    label: 'Attachés',
                    data: [28, 22, 26, 24, 20],
                    backgroundColor: ['#0d6efd', '#ffc107', '#0d6efd', '#ffc107', '#0d6efd'],
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, max: 30, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});