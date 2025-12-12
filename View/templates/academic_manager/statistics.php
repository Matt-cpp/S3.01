<?php
/**
 * Fichier: statistics.php
 * 
 * Template des statistiques pour le responsable pédagogique - Analyse complète et interactive des absences.
 * Fonctionnalités principales :
 * - Mode double : statistiques générales OU statistiques par étudiant
 * - Filtres avancés (dates, groupe, ressource, année, type de cours, étudiant)
 * - Cartes statistiques récapitulatives (total, heures, demi-journées, évaluations, taux de justification)
 * - Graphiques interactifs avec Chart.js :
 *   - Répartition par type de cours (camembert)
 *   - Répartition par ressource/matière (barres horizontales)
 *   - Évolution mensuelle des absences (ligne)
 *   - Répartition par semestre
 *   - Top 10 des étudiants les plus absents
 * - Export de données possible via les graphiques
 * Utilise AcademicManagerStatisticsPresenter pour les données et calculs.
 */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Responsable Pédagogique</title>
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../../assets/css/academic_manager/navbar.css">
    <link rel="stylesheet" href="../../assets/css/academic_manager/statistics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body>
    <?php
    require_once __DIR__ . '/../../../controllers/auth_guard.php';
    $user = requireRole('academic_manager');

    require_once __DIR__ . '/../../../Presenter/academic_manager/statistics-presenter.php';
    $presenter = new AcademicManagerStatisticsPresenter();

    // Get filters
    $filters = $presenter->getFilters();
    $studentIdentifier = $_GET['student'] ?? null;

    // Get data based on view mode
    if ($studentIdentifier) {
        $studentStats = $presenter->getStudentStatistics($studentIdentifier, $filters);
        $studentResourceData = $presenter->getStudentResourceData($studentIdentifier, $filters);
        $studentTrends = $presenter->getStudentTrends($studentIdentifier, $filters);
    } else {
        $generalStats = $presenter->getGeneralStats($filters);
        $courseTypeData = $presenter->getCourseTypeData($filters);
        $resourceData = $presenter->getResourceData($filters);
        $evaluationResourceData = $presenter->getEvaluationResourceData($filters);
        $justificationRateData = $presenter->getJustificationRateData($filters);
        $monthlyTrends = $presenter->getMonthlyTrends($filters);
        $resourceTrends = $presenter->getResourceTrends($filters);
        $semesterData = $presenter->getSemesterData($filters);
        $topStudents = $presenter->getTopAbsentStudents(10, $filters);
    }

    // Get filter options
    $groups = $presenter->getAllGroups();
    $resources = $presenter->getAllResources();
    $years = $presenter->getAvailableYears();
    ?>

    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="main-content">
        <div class="stats-header">
            <h1>Statistiques des absences</h1>
            <p class="subtitle">Analyse complète et interactive des absences</p>
        </div>

        <!-- Filters Section -->
        <div class="filters-container">
            <button class="toggle-filters-btn" onclick="toggleFilters()">
                <span class="filter-icon"></span>
                <span>Filtres</span>
                <span class="arrow">▼</span>
            </button>

            <div class="filters-content" id="filtersContent">
                <form method="GET" action="" class="filters-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="start_date">Date de début</label>
                            <input type="date" id="start_date" name="start_date"
                                value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="end_date">Date de fin</label>
                            <input type="date" id="end_date" name="end_date"
                                value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="year">Année</label>
                            <select id="year" name="year">
                                <option value="">Toutes les années</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo htmlspecialchars($year['year']); ?>" <?php echo (isset($_GET['year']) && $_GET['year'] == $year['year']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="semester">Semestre</label>
                            <select id="semester" name="semester">
                                <option value="">Tous les semestres</option>
                                <option value="S1" <?php echo (isset($_GET['semester']) && $_GET['semester'] === 'S1') ? 'selected' : ''; ?>>
                                    Semestre 1
                                </option>
                                <option value="S2" <?php echo (isset($_GET['semester']) && $_GET['semester'] === 'S2') ? 'selected' : ''; ?>>
                                    Semestre 2
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="group_id">Groupe</label>
                            <select id="group_id" name="group_id">
                                <option value="">Tous les groupes</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo htmlspecialchars($group['id']); ?>" <?php echo (isset($_GET['group_id']) && $_GET['group_id'] == $group['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="resource_id">Matière</label>
                            <select id="resource_id" name="resource_id">
                                <option value="">Toutes les matières</option>
                                <?php foreach ($resources as $resource): ?>
                                    <option value="<?php echo htmlspecialchars($resource['id']); ?>" <?php echo (isset($_GET['resource_id']) && $_GET['resource_id'] == $resource['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($resource['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="course_type">Type de cours</label>
                            <select id="course_type" name="course_type">
                                <option value="">Tous les types</option>
                                <option value="CM" <?php echo (isset($_GET['course_type']) && $_GET['course_type'] === 'CM') ? 'selected' : ''; ?>>
                                    CM
                                </option>
                                <option value="TD" <?php echo (isset($_GET['course_type']) && $_GET['course_type'] === 'TD') ? 'selected' : ''; ?>>
                                    TD
                                </option>
                                <option value="TP" <?php echo (isset($_GET['course_type']) && $_GET['course_type'] === 'TP') ? 'selected' : ''; ?>>
                                    TP
                                </option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="justified">Justification</label>
                            <select id="justified" name="justified">
                                <option value="">Toutes</option>
                                <option value="1" <?php echo (isset($_GET['justified']) && $_GET['justified'] === '1') ? 'selected' : ''; ?>>
                                    Justifiées
                                </option>
                                <option value="0" <?php echo (isset($_GET['justified']) && $_GET['justified'] === '0') ? 'selected' : ''; ?>>
                                    Non justifiées
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
                        <a href="?" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student Search -->
        <?php if (!$studentIdentifier): ?>
            <div class="student-search-container">
                <div class="search-box">
                    <input type="text" id="studentSearch" placeholder="Rechercher un étudiant par nom ou identifiant..."
                        onkeyup="searchStudent()">
                    <div id="searchResults" class="search-results"></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($studentIdentifier && $studentStats): ?>
            <!-- Student-Specific View -->
            <div class="student-view">
                <div class="student-header">
                    <h2>Statistiques de <?php echo htmlspecialchars($studentStats['student_name']); ?></h2>
                    <p>Identifiant: <?php echo htmlspecialchars($studentStats['identifier']); ?></p>
                    <a href="?" class="btn btn-secondary">← Retour à la vue générale</a>
                </div>

                <!-- Student Summary Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Total des absences</div>
                            <div class="stat-number"><?php echo $studentStats['total_absences'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-success">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Absences justifiées</div>
                            <div class="stat-number"><?php echo $studentStats['justified_absences'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-danger">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Absences non justifiées</div>
                            <div class="stat-number"><?php echo $studentStats['unjustified_absences'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-purple">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Absences en évaluation</div>
                            <div class="stat-number"><?php echo $studentStats['evaluation_absences'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-info">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Taux de justification</div>
                            <div class="stat-number">
                                <?php
                                $total = $studentStats['total_absences'] ?? 0;
                                $justified = $studentStats['justified_absences'] ?? 0;
                                echo $total > 0 ? round(($justified / $total) * 100) . '%' : '0%';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student Charts -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <h3>Absences par type de cours</h3>
                        <canvas id="studentCourseTypeChart"></canvas>
                    </div>

                    <div class="chart-card">
                        <h3>Absences par matière (Top 10)</h3>
                        <canvas id="studentResourceChart"></canvas>
                    </div>

                    <div class="chart-card chart-card-large">
                        <h3>Évolution des absences dans le temps</h3>
                        <canvas id="studentTrendChart"></canvas>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- General Statistics View -->

            <!-- Summary Cards -->
            <?php if ($generalStats): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Total des absences</div>
                            <div class="stat-number"><?php echo $generalStats['total_absences'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-info">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Étudiants concernés</div>
                            <div class="stat-number"><?php echo $generalStats['total_students'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-success">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Absences justifiées</div>
                            <div class="stat-number"><?php echo $generalStats['justified_absences'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-danger">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Absences non justifiées</div>
                            <div class="stat-number"><?php echo $generalStats['unjustified_absences'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-purple">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Absences en évaluation</div>
                            <div class="stat-number"><?php echo $generalStats['evaluation_absences'] ?? 0; ?></div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-warning">
                        <div class="stat-icon"></div>
                        <div class="stat-content">
                            <div class="stat-title">Moyenne par étudiant</div>
                            <div class="stat-number"><?php echo $generalStats['avg_absences_per_student'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Semester Analysis -->
            <?php if (!empty($semesterData)): ?>
                <div class="semester-section">
                    <h2>Analyse par semestre</h2>
                    <div class="semester-grid">
                        <?php foreach ($semesterData as $semester => $data): ?>
                            <div class="semester-card">
                                <h3><?php echo htmlspecialchars($semester); ?></h3>
                                <div class="semester-stats">
                                    <div class="semester-stat">
                                        <span class="semester-label">CM:</span>
                                        <span class="semester-value"><?php echo $data['CM']; ?></span>
                                    </div>
                                    <div class="semester-stat">
                                        <span class="semester-label">TD:</span>
                                        <span class="semester-value"><?php echo $data['TD']; ?></span>
                                    </div>
                                    <div class="semester-stat">
                                        <span class="semester-label">TP:</span>
                                        <span class="semester-value"><?php echo $data['TP']; ?></span>
                                    </div>
                                    <div class="semester-stat semester-total">
                                        <span class="semester-label">Total:</span>
                                        <span class="semester-value"><?php echo $data['CM'] + $data['TD'] + $data['TP']; ?></span>
                                    </div>
                                </div>
                                <div class="semester-chart-mini">
                                    <canvas id="semesterChart<?php echo str_replace(' ', '', $semester); ?>"></canvas>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Main Charts -->
            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Répartition par type de cours</h3>
                    <canvas id="courseTypeChart"></canvas>
                </div>

                <div class="chart-card">
                    <h3>Répartition par matière (Top 10)</h3>
                    <canvas id="resourceChart"></canvas>
                </div>

                <div class="chart-card">
                    <h3>Absences en évaluation par matière</h3>
                    <canvas id="evaluationResourceChart"></canvas>
                </div>

                <div class="chart-card">
                    <h3>Taux de justification par matière</h3>
                    <canvas id="justificationRateChart"></canvas>
                </div>

                <div class="chart-card chart-card-large">
                    <h3>Évolution mensuelle des absences</h3>
                    <canvas id="monthlyTrendChart"></canvas>
                </div>

                <div class="chart-card chart-card-large">
                    <h3>Tendances par matière (Top 5)</h3>
                    <canvas id="resourceTrendChart"></canvas>
                </div>
            </div>

            <!-- Top Absent Students -->
            <?php if (!empty($topStudents)): ?>
                <div class="top-students-section">
                    <h2>Étudiants avec le plus d'absences</h2>
                    <div class="students-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Étudiant</th>
                                    <th>Identifiant</th>
                                    <th>Total absences</th>
                                    <th>Non justifiées</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topStudents as $index => $student): ?>
                                    <tr>
                                        <td class="rank-cell">
                                            <span class="rank-badge rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['identifier']); ?></td>
                                        <td><strong><?php echo $student['total_absences']; ?></strong></td>
                                        <td>
                                            <span class="badge badge-danger"><?php echo $student['unjustified_absences']; ?></span>
                                        </td>
                                        <td>
                                            <a href="?student=<?php echo urlencode($student['identifier']); ?>"
                                                class="btn btn-sm">Voir
                                                détails</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="team-section">
                <h3 class="team-title">Équipe de développement</h3>
                <div class="team-names">
                    <p>CIPOLAT Matteo • BOLTZ Louis • NAVREZ Louis • COLLARD Yony • BISIAUX Ambroise • FOURNIER
                        Alexandre</p>
                </div>
            </div>
            <div class="footer-info">
                <p>&copy; 2025 UPHF - Système de gestion des absences</p>
            </div>
        </div>
    </footer>

    <?php renderThemeScript(); ?>

    <script>
        // Toggle filters visibility
        function toggleFilters() {
            const content = document.getElementById('filtersContent');
            const arrow = document.querySelector('.toggle-filters-btn .arrow');
            content.classList.toggle('show');
            arrow.textContent = content.classList.contains('show') ? '▲' : '▼';
        }

        // Student search functionality
        let searchTimeout;
        function searchStudent() {
            clearTimeout(searchTimeout);
            const query = document.getElementById('studentSearch').value;

            if (query.length < 2) {
                document.getElementById('searchResults').innerHTML = '';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`../../Presenter/api/secretary/search-students.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        const resultsDiv = document.getElementById('searchResults');
                        if (data.length === 0) {
                            resultsDiv.innerHTML = '<div class="no-results">Aucun étudiant trouvé</div>';
                        } else {
                            resultsDiv.innerHTML = data.map(student =>
                                `<div class="search-result-item" onclick="selectStudent('${student.identifier}')">
                                    <strong>${student.first_name} ${student.last_name}</strong>
                                    <span>${student.identifier}</span>
                                </div>`
                            ).join('');
                        }
                    });
            }, 300);
        }

        function selectStudent(identifier) {
            window.location.href = `?student=${encodeURIComponent(identifier)}`;
        }

        // Chart colors
        const chartColors = {
            primary: '#4338ca',
            secondary: '#7c3aed',
            success: '#059669',
            danger: '#dc2626',
            warning: '#ea580c',
            info: '#0891b2'
        };

        const colorPalette = [
            '#4338ca', '#7c3aed', '#db2777', '#059669', '#dc2626',
            '#ea580c', '#0891b2', '#6366f1', '#8b5cf6', '#ec4899'
        ];

        // Chart.js default options
        Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
        Chart.defaults.color = '#6b7280';

        <?php if ($studentIdentifier && $studentStats): ?>
            // Student course type chart
            const studentCourseData = {
                labels: ['CM', 'TD', 'TP'],
                datasets: [{
                    data: [
                        <?php echo $studentStats['cm_absences'] ?? 0; ?>,
                        <?php echo $studentStats['td_absences'] ?? 0; ?>,
                        <?php echo $studentStats['tp_absences'] ?? 0; ?>
                    ],
                    backgroundColor: [chartColors.primary, chartColors.secondary, chartColors.danger]
                }]
            };

            new Chart(document.getElementById('studentCourseTypeChart'), {
                type: 'doughnut',
                data: studentCourseData,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // Student resource chart
            <?php if (!empty($studentResourceData['labels'])): ?>
                const studentResourceData = {
                    labels: <?php echo json_encode($studentResourceData['labels']); ?>,
                    datasets: [{
                        label: 'Absences',
                        data: <?php echo json_encode($studentResourceData['values']); ?>,
                        backgroundColor: colorPalette
                    }]
                };

                new Chart(document.getElementById('studentResourceChart'), {
                    type: 'bar',
                    data: studentResourceData,
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            <?php endif; ?>

            // Student trend chart
            <?php if (!empty($studentTrends['months'])): ?>
                const studentTrendData = {
                    labels: <?php echo json_encode($studentTrends['months']); ?>,
                    datasets: [{
                        label: 'Absences',
                        data: <?php echo json_encode($studentTrends['values']); ?>,
                        borderColor: chartColors.primary,
                        backgroundColor: chartColors.primary + '20',
                        tension: 0.4,
                        fill: true
                    }]
                };

                new Chart(document.getElementById('studentTrendChart'), {
                    type: 'line',
                    data: studentTrendData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            <?php endif; ?>

        <?php else: ?>
            // Course type pie chart
            <?php if (!empty($courseTypeData['labels'])): ?>
                const courseTypeData = {
                    labels: <?php echo json_encode($courseTypeData['labels']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($courseTypeData['values']); ?>,
                        backgroundColor: <?php echo json_encode($courseTypeData['colors']); ?>
                    }]
                };

                new Chart(document.getElementById('courseTypeChart'), {
                    type: 'pie',
                    data: courseTypeData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            <?php endif; ?>

            // Resource pie chart
            <?php if (!empty($resourceData['labels'])): ?>
                const resourceData = {
                    labels: <?php echo json_encode($resourceData['labels']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($resourceData['values']); ?>,
                        backgroundColor: colorPalette
                    }]
                };

                new Chart(document.getElementById('resourceChart'), {
                    type: 'doughnut',
                    data: resourceData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            <?php endif; ?>

            // Evaluation resource bar chart
            <?php if (!empty($evaluationResourceData['labels'])): ?>
                const evaluationResourceData = {
                    labels: <?php echo json_encode($evaluationResourceData['labels']); ?>,
                    datasets: [{
                        label: 'Absences en évaluation',
                        data: <?php echo json_encode($evaluationResourceData['values']); ?>,
                        backgroundColor: [
                            '#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3',
                            '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#cddc39'
                        ],
                        borderWidth: 0,
                        borderRadius: 6
                    }]
                };

                new Chart(document.getElementById('evaluationResourceChart'), {
                    type: 'bar',
                    data: evaluationResourceData,
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            <?php endif; ?>

            // Justification rate by resource chart
            <?php if (!empty($justificationRateData['labels'])): ?>
                const justificationRateData = {
                    labels: <?php echo json_encode($justificationRateData['labels']); ?>,
                    datasets: [{
                        label: 'Taux de justification (%)',
                        data: <?php echo json_encode($justificationRateData['values']); ?>,
                        backgroundColor: <?php echo json_encode($justificationRateData['values']); ?>.map(value => {
                            if (value >= 80) return '#22c55e';
                            if (value >= 50) return '#f59e0b';
                            return '#ef4444';
                        }),
                        borderWidth: 0,
                        borderRadius: 6
                    }]
                };

                new Chart(document.getElementById('justificationRateChart'), {
                    type: 'bar',
                    data: justificationRateData,
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.parsed.x + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function (value) {
                                        return value + '%';
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>

            // Monthly trend chart
            <?php if (!empty($monthlyTrends['months'])): ?>
                const monthlyTrendData = {
                    labels: <?php echo json_encode($monthlyTrends['months']); ?>,
                    datasets: [
                        {
                            label: 'Total',
                            data: <?php echo json_encode($monthlyTrends['total']); ?>,
                            borderColor: chartColors.primary,
                            backgroundColor: chartColors.primary + '20',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Justifiées',
                            data: <?php echo json_encode($monthlyTrends['justified']); ?>,
                            borderColor: chartColors.success,
                            backgroundColor: chartColors.success + '20',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Non justifiées',
                            data: <?php echo json_encode($monthlyTrends['unjustified']); ?>,
                            borderColor: chartColors.danger,
                            backgroundColor: chartColors.danger + '20',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                };

                new Chart(document.getElementById('monthlyTrendChart'), {
                    type: 'line',
                    data: monthlyTrendData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            <?php endif; ?>

            // Resource trends chart
            <?php if (!empty($resourceTrends['months'])): ?>
                const resourceTrendData = {
                    labels: <?php echo json_encode($resourceTrends['months']); ?>,
                    datasets: [
                        <?php foreach ($resourceTrends['datasets'] as $index => $dataset): ?>
                                                                                                                                                                                                    {
                                label: <?php echo json_encode($dataset['label']); ?>,
                                data: <?php echo json_encode($dataset['data']); ?>,
                                borderColor: '<?php echo $dataset['color']; ?>',
                                backgroundColor: '<?php echo $dataset['color']; ?>20',
                                tension: 0.4,
                                fill: true
                            }<?php echo $index < count($resourceTrends['datasets']) - 1 ? ',' : ''; ?>
                                                                                                        <?php endforeach; ?>
                    ]
                };

                new Chart(document.getElementById('resourceTrendChart'), {
                    type: 'line',
                    data: resourceTrendData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            <?php endif; ?>

            // Semester mini charts
            <?php foreach ($semesterData as $semester => $data): ?>
                <?php
                $semesterId = str_replace(' ', '', $semester);
                $total = $data['CM'] + $data['TD'] + $data['TP'];
                ?>
                new Chart(document.getElementById('semesterChart<?php echo $semesterId; ?>'), {
                    type: 'doughnut',
                    data: {
                        labels: ['CM', 'TD', 'TP'],
                        datasets: [{
                            data: [<?php echo $data['CM']; ?>, <?php echo $data['TD']; ?>, <?php echo $data['TP']; ?>],
                            backgroundColor: [chartColors.primary, chartColors.secondary, chartColors.danger]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            <?php endforeach; ?>
        <?php endif; ?>
    </script>
</body>

</html>