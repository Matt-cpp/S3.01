// teacher_statistics.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize filters toggle
    initFiltersToggle();
    
    // Initialize charts
    initCourseTypeChart();
    initSubjectChart();
    initMonthlyChart();
    initSemesterCharts();
    
    // Initialize filter events
    initFilterEvents();
});

// Filters Toggle
function initFiltersToggle() {
    const toggle = document.getElementById('filters-toggle');
    const content = document.getElementById('filters-content');
    const arrow = toggle.querySelector('.toggle-arrow');
    
    toggle.addEventListener('click', () => {
        content.classList.toggle('show');
        arrow.classList.toggle('rotated');
    });
}

// Course Type Pie Chart
function initCourseTypeChart() {
    const ctx = document.getElementById('courseTypeChart');
    if (!ctx) return;
    
    const data = statsData.courseTypes || { CM: 42, TD: 39, TP: 30 };
    const colors = ['#5c6bc0', '#9575cd', '#f06292'];
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.raw / total) * 100).toFixed(1);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Create legend
    const legendContainer = document.getElementById('courseTypeLegend');
    if (legendContainer) {
        Object.keys(data).forEach((label, index) => {
            const item = document.createElement('div');
            item.className = 'chart-legend-item';
            item.innerHTML = `
                <span class="legend-color" style="background-color: ${colors[index]}"></span>
                <span>${label}</span>
            `;
            legendContainer.appendChild(item);
        });
    }
}

// Subject Donut Chart
function initSubjectChart() {
    const ctx = document.getElementById('subjectChart');
    if (!ctx) return;
    
    // Default data if not provided
    const defaultSubjects = {
        'INFFIS2-DEV OBJETS': 15,
        'INFFIS1-PROGRAMMATION': 12,
        'INFFIS1-DEV WEB': 11,
        'INFFIS1-INTRO BDD': 10,
        'INFFIS1-ARCHITECTURE': 9,
        'INFFIS2-GESTION PROJET': 8,
        'INFFIS2-BDD AVANCEES': 7,
        'INFFIS2-RESEAUX': 6,
        'INFFIS2-ALGO AVANCEE': 5
    };
    
    const data = statsData.subjects && Object.keys(statsData.subjects).length > 0 
        ? statsData.subjects 
        : defaultSubjects;
    
    const colors = [
        '#3f51b5', '#7c4dff', '#e91e63', '#4caf50', '#f44336',
        '#ff9800', '#00bcd4', '#9c27b0', '#673ab7', '#2196f3'
    ];
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: colors.slice(0, Object.keys(data).length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Create vertical legend
    const legendContainer = document.getElementById('subjectLegend');
    if (legendContainer) {
        Object.keys(data).forEach((label, index) => {
            const item = document.createElement('div');
            item.className = 'chart-legend-item';
            item.innerHTML = `
                <span class="legend-color" style="background-color: ${colors[index]}"></span>
                <span>${label}</span>
            `;
            legendContainer.appendChild(item);
        });
    }
}

// Monthly Evolution Area Chart
function initMonthlyChart() {
    const ctx = document.getElementById('monthlyChart');
    if (!ctx) return;
    
    // Default data
    const defaultMonthly = {
        labels: ['January 2024', 'February 2024', 'March 2024', 'April 2024', 'November 2025'],
        total: [42, 22, 20, 20, 7],
        justified: [8, 6, 4, 5, 0],
        unjustified: [34, 16, 16, 15, 7]
    };
    
    const data = statsData.monthly && statsData.monthly.labels 
        ? statsData.monthly 
        : defaultMonthly;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Total',
                    data: data.total,
                    borderColor: '#5c6bc0',
                    backgroundColor: 'rgba(92, 107, 192, 0.2)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#5c6bc0'
                },
                {
                    label: 'Justifiées',
                    data: data.justified,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.2)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#4caf50'
                },
                {
                    label: 'Non justifiées',
                    data: data.unjustified,
                    borderColor: '#f44336',
                    backgroundColor: 'rgba(244, 67, 54, 0.2)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#f44336'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Semester Mini Donut Charts
function initSemesterCharts() {
    const semesters = statsData.semesters || [
        { id: 1, cm: 0, td: 7, tp: 0 },
        { id: 2, cm: 26, td: 20, tp: 18 },
        { id: 3, cm: 16, td: 12, tp: 12 }
    ];
    
    semesters.forEach(semester => {
        const ctx = document.getElementById(`semester-chart-${semester.id}`);
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['CM', 'TD', 'TP'],
                datasets: [{
                    data: [semester.cm || 0, semester.td || 0, semester.tp || 0],
                    backgroundColor: ['#5c6bc0', '#f44336', '#e8eaf6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
}

// Filter Events
function initFilterEvents() {
    const applyBtn = document.getElementById('apply-filters');
    if (applyBtn) {
        applyBtn.addEventListener('click', applyFilters);
    }
    
    // Enter key support for search
    const searchInput = document.getElementById('student-search');
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }
}

function applyFilters() {
    const studentSearch = document.getElementById('student-search').value;
    const semester = document.getElementById('semester-filter').value;
    const courseType = document.getElementById('course-type-filter').value;
    const resource = document.getElementById('resource-filter').value;
    
    // Build query params
    const params = new URLSearchParams();
    if (studentSearch) params.append('student', studentSearch);
    if (semester) params.append('semester', semester);
    if (courseType) params.append('type', courseType);
    if (resource) params.append('resource', resource);
    
    // Reload page with filters
    window.location.href = `teacher_statistics.php?${params.toString()}`;
}

// ===== TENDANCES PAR MATIÈRE (Top 5) =====
function initSubjectTrendsChart() {
    const ctx = document.getElementById('subjectTrendsChart');
    if (! ctx) return;
    
    const defaultTrends = {
        labels: ['2024-01', '2024-02', '2024-03', '2024-04', '2025-11'],
        datasets: [
            {
                label: 'INFFIS2-DEV OBJETS',
                data: [3, 2, 1, 2, 7],
                borderColor: '#5c6bc0',
                backgroundColor: '#5c6bc0',
                fill: false,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#5c6bc0',
                borderWidth: 2
            },
            {
                label: 'INFFIS1-DEV WEB',
                data: [4, 2, 2, 1, 0],
                borderColor: '#9575cd',
                backgroundColor: '#9575cd',
                fill: false,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#9575cd',
                borderWidth: 2
            },
            {
                label: 'INFFIS1-PROGRAMMATION',
                data: [5, 3, 2, 2, 0],
                borderColor: '#f44336',
                backgroundColor: '#f44336',
                fill: false,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#f44336',
                borderWidth: 2
            },
            {
                label: 'INFFIS1-ARCHITECTURE',
                data: [3, 1, 3, 2, 4],
                borderColor: '#4caf50',
                backgroundColor: '#4caf50',
                fill: false,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#4caf50',
                borderWidth: 2
            },
            {
                label: 'INFFIS1-INTRO BDD',
                data: [3, 1, 0, 1, 0],
                borderColor: '#ff9800',
                backgroundColor: '#ff9800',
                fill: false,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#ff9800',
                borderWidth: 2
            }
        ]
    };
    
    const data = statsData.subjectTrends && statsData.subjectTrends.labels && statsData.subjectTrends.labels.length > 0
        ? statsData.subjectTrends 
        : defaultTrends;
    
    // S'assurer que chaque dataset n'a PAS de fill
    const colors = ['#5c6bc0', '#9575cd', '#f44336', '#4caf50', '#ff9800'];
    if (data.datasets) {
        data. datasets.forEach((dataset, index) => {
            dataset.fill = false;  // Pas de remplissage
            dataset.tension = 0.4;
            dataset.pointRadius = 5;
            dataset.borderWidth = 2;
            dataset.borderColor = colors[index] || colors[0];
            dataset.backgroundColor = colors[index] || colors[0];
            dataset.pointBackgroundColor = colors[index] || colors[0];
        });
    }
    
    new Chart(ctx, {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { 
                        usePointStyle: true, 
                        padding: 15, 
                        font: { size: 11 } 
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    ticks: { stepSize: 1 }, 
                    grid: { color: 'rgba(0, 0, 0, 0.05)' } 
                },
                x: { 
                    grid: { display: false } 
                }
            }
        }
    });
}

// ===== MODAL DÉTAILS ÉTUDIANT =====
let studentCourseTypeChartInstance = null;
let studentSubjectChartInstance = null;

function showStudentDetails(studentId) {
    // Show modal
    const modal = document.getElementById('student-detail-modal');
    modal.style.display = 'flex';
    document.body.style. overflow = 'hidden';
    
    // Fetch student details via AJAX
    fetch(`/Presenter/api/get_student_statistics. php?id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            updateStudentModal(data);
        })
        .catch(error => {
            console.error('Error fetching student details:', error);
            // Use default data for demo
            updateStudentModal({
                name: 'Thomas Robert',
                student_number: '55667788',
                total: 6,
                justified: 1,
                unjustified: 5,
                rate: 17,
                courseTypes: { CM: 2, TD: 2, TP: 2 },
                subjects: {
                    'INFFIS2-GESTION DE PROJET': 2,
                    'INFFIS2-ALGORITHMIQUE AVANCEE': 1,
                    'INFFIS2-BASES DE DONNEES AVANCEES': 1,
                    'INFFIS2-DEVELOPPEMENT ORIENTE OBJETS': 1,
                    'INFFIS2-RESEAUX ET PROTOCOLES': 1
                }
            });
        });
}

function updateStudentModal(data) {
    // Update header
    document.getElementById('detail-student-name'). textContent = `Statistiques de ${data.name}`;
    document.getElementById('detail-student-id').textContent = `Identifiant: ${data.student_number}`;
    
    // Update KPIs
    document. getElementById('detail-total'). textContent = data. total;
    document.getElementById('detail-justified').textContent = data.justified;
    document.getElementById('detail-unjustified').textContent = data.unjustified;
    document.getElementById('detail-rate').textContent = data.rate + '%';
    
    // Destroy existing charts
    if (studentCourseTypeChartInstance) {
        studentCourseTypeChartInstance. destroy();
    }
    if (studentSubjectChartInstance) {
        studentSubjectChartInstance.destroy();
    }
    
    // Create course type donut chart
    const ctxCourse = document. getElementById('studentCourseTypeChart');
    if (ctxCourse) {
        const courseData = data.courseTypes || { CM: 2, TD: 2, TP: 2 };
        studentCourseTypeChartInstance = new Chart(ctxCourse, {
            type: 'doughnut',
            data: {
                labels: Object.keys(courseData),
                datasets: [{
                    data: Object.values(courseData),
                    backgroundColor: ['#5c6bc0', '#f44336', '#9575cd'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true }
                    }
                }
            }
        });
    }
    
    // Create subject horizontal bar chart
    const ctxSubject = document.getElementById('studentSubjectChart');
    if (ctxSubject) {
        const subjects = data.subjects || {};
        const colors = ['#5c6bc0', '#9575cd', '#e91e63', '#4caf50', '#f44336', '#ff9800', '#00bcd4', '#673ab7', '#2196f3', '#795548'];
        
        studentSubjectChartInstance = new Chart(ctxSubject, {
            type: 'bar',
            data: {
                labels: Object.keys(subjects),
                datasets: [{
                    data: Object.values(subjects),
                    backgroundColor: colors. slice(0, Object.keys(subjects). length),
                    borderRadius: 4,
                    barThickness: 25
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 0.5 },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 10 },
                            callback: function(value) {
                                const label = this.getLabelForValue(value);
                                return label. length > 40 ? label.substring(0, 40) + '...' : label;
                            }
                        }
                    }
                }
            }
        });
    }
}

function closeStudentDetails() {
    const modal = document.getElementById('student-detail-modal');
    modal.style.display = 'none';
    document.body. style.overflow = '';
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('student-detail-modal');
    if (e.target === modal) {
        closeStudentDetails();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStudentDetails();
    }
});

// Add to initialization
document.addEventListener('DOMContentLoaded', function() {
    initSubjectTrendsChart();
});