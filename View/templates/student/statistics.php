<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <title>Mes Statistiques - Étudiant</title>
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../../assets/css/student/statistics.css">
    <link rel="stylesheet" href="../../assets/css/shared/navbar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body>
    <?php
    require_once __DIR__ . '/../../../controllers/auth_guard.php';
    $user = requireRole('student');

    require_once __DIR__ . '/../../../Presenter/student/statistics_presenter.php';

    // Get student identifier from user ID
    $studentIdentifier = StudentStatisticsPresenter::getStudentIdentifierFromUserId($user['id']);

    if (!$studentIdentifier) {
        echo '<div class="no-data"><p>Impossible de récupérer vos informations.</p></div>';
        exit;
    }

    $presenter = new StudentStatisticsPresenter($studentIdentifier);

    // Get filters
    $filters = $presenter->getFilters();

    // Get statistics data
    $generalStats = $presenter->getGeneralStats($filters);
    $courseTypeData = $presenter->getCourseTypeData($filters);
    $resourceData = $presenter->getResourceData($filters);
    $monthlyTrends = $presenter->getDetailedMonthlyTrends($filters);
    $recentAbsences = $presenter->getRecentAbsences(10);
    ?>

    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="stats-header">
            <h1>Mes Statistiques d'absences</h1>
            <p class="subtitle">Vue d'ensemble de vos absences</p>
        </div>

        <!-- Filters Section -->
        <div class="filters-container">
            <button class="toggle-filters-btn" onclick="toggleFilters()">
                <span class="filter-icon">🔍</span>
                <span>Filtres</span>
                <span class="arrow">▼</span>
            </button>

            <div class="filters-content" id="filtersContent">
                <form method="GET" action="" class="filters-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="start_date">Date de début</label>
                            <input type="date" id="start_date" name="start_date"
                                value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="end_date">Date de fin</label>
                            <input type="date" id="end_date" name="end_date"
                                value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="course_type">Type de cours</label>
                            <select id="course_type" name="course_type">
                                <option value="">Tous les types</option>
                                <option value="CM" <?php echo ($filters['course_type'] ?? '') === 'CM' ? 'selected' : ''; ?>>CM</option>
                                <option value="TD" <?php echo ($filters['course_type'] ?? '') === 'TD' ? 'selected' : ''; ?>>TD</option>
                                <option value="TP" <?php echo ($filters['course_type'] ?? '') === 'TP' ? 'selected' : ''; ?>>TP</option>
                                <option value="DS" <?php echo ($filters['course_type'] ?? '') === 'DS' ? 'selected' : ''; ?>>DS (Évaluation)</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <a href="student_statistics.php" class="btn btn-secondary">Réinitialiser</a>
                        <button type="submit" class="btn btn-primary">Appliquer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <?php if ($generalStats): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-title">Total des absences</div>
                        <div class="stat-number"><?php echo $generalStats['total_absences'] ?? 0; ?></div>
                    </div>
                </div>

                <div class="stat-card stat-card-success">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <div class="stat-title">Absences justifiées</div>
                        <div class="stat-number"><?php echo $generalStats['justified_absences'] ?? 0; ?></div>
                    </div>
                </div>

                <div class="stat-card stat-card-danger">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-content">
                        <div class="stat-title">Absences non justifiées</div>
                        <div class="stat-number"><?php echo $generalStats['unjustified_absences'] ?? 0; ?></div>
                    </div>
                </div>

                <div class="stat-card stat-card-purple">
                    <div class="stat-icon">📝</div>
                    <div class="stat-content">
                        <div class="stat-title">Absences en évaluation</div>
                        <div class="stat-number"><?php echo $generalStats['evaluation_absences'] ?? 0; ?></div>
                    </div>
                </div>

                <div class="stat-card stat-card-info">
                    <div class="stat-icon">📈</div>
                    <div class="stat-content">
                        <div class="stat-title">Taux de justification</div>
                        <div class="stat-number">
                            <?php
                            $total = $generalStats['total_absences'] ?? 0;
                            $justified = $generalStats['justified_absences'] ?? 0;
                            echo $total > 0 ? round(($justified / $total) * 100) . '%' : '0%';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="no-data">
                <div class="no-data-icon">🎉</div>
                <p>Aucune absence enregistrée. Continuez comme ça !</p>
            </div>
        <?php endif; ?>

        <!-- Charts -->
        <?php if ($generalStats && $generalStats['total_absences'] > 0): ?>
            <div class="charts-grid">
                <!-- Course Type Chart -->
                <div class="chart-card">
                    <h3>Absences par type de cours</h3>
                    <div class="chart-container">
                        <canvas id="courseTypeChart"></canvas>
                    </div>
                </div>

                <!-- Resource Chart -->
                <div class="chart-card">
                    <h3>Absences par matière (Top 10)</h3>
                    <div class="chart-container">
                        <canvas id="resourceChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Trend Chart -->
                <div class="chart-card chart-card-large">
                    <h3>Évolution des absences dans le temps</h3>
                    <div class="chart-container-large">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Absences Table -->
        <?php if (!empty($recentAbsences)): ?>
            <div class="recent-section">
                <h3>Absences récentes</h3>
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Matière</th>
                            <th>Type</th>
                            <th>Enseignant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAbsences as $absence): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($absence['course_date'])); ?></td>
                                <td><?php echo substr($absence['start_time'], 0, 5) . ' - ' . substr($absence['end_time'], 0, 5); ?>
                                </td>
                                <td><?php echo htmlspecialchars($absence['resource_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($absence['course_type']); ?>
                                    <?php if ($absence['is_evaluation']): ?>
                                        <span class="status-badge status-evaluation">📝</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($absence['teacher_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($absence['justified']): ?>
                                        <span class="status-badge status-justified">Justifiée</span>
                                    <?php else: ?>
                                        <span class="status-badge status-unjustified">Non justifiée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php renderThemeScript(); ?>

    <script>
        // Toggle filters visibility
        function toggleFilters() {
            const content = document.getElementById('filtersContent');
            const arrow = document.querySelector('.toggle-filters-btn .arrow');
            content.classList.toggle('show');
            arrow.textContent = content.classList.contains('show') ? '▲' : '▼';
        }

        // Chart colors
        const chartColors = {
            primary: '#667eea',
            secondary: '#764ba2',
            success: '#10b981',
            danger: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6',
            purple: '#8b5cf6'
        };

        const colorPalette = [
            '#667eea', '#764ba2', '#10b981', '#ef4444', '#f59e0b',
            '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'
        ];

        // Chart.js default options
        Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
        Chart.defaults.color = '#6b7280';

        <?php if ($generalStats && $generalStats['total_absences'] > 0): ?>

            // Course type pie chart
            <?php if (!empty($courseTypeData['labels'])): ?>
                const courseTypeData = {
                    labels: <?php echo json_encode($courseTypeData['labels']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($courseTypeData['values']); ?>,
                        backgroundColor: <?php echo json_encode($courseTypeData['colors']); ?>,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                };

                new Chart(document.getElementById('courseTypeChart'), {
                    type: 'doughnut',
                    data: courseTypeData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>

            // Resource bar chart
            <?php if (!empty($resourceData['labels'])): ?>
                const resourceData = {
                    labels: <?php echo json_encode($resourceData['labels']); ?>,
                    datasets: [{
                        label: 'Absences',
                        data: <?php echo json_encode($resourceData['values']); ?>,
                        backgroundColor: colorPalette,
                        borderRadius: 6
                    }]
                };

                new Chart(document.getElementById('resourceChart'), {
                    type: 'bar',
                    data: resourceData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>

            // Monthly trend line chart
            <?php if (!empty($monthlyTrends['months'])): ?>
                const monthlyTrendData = {
                    labels: <?php echo json_encode($monthlyTrends['months']); ?>,
                    datasets: [
                        {
                            label: 'Total',
                            data: <?php echo json_encode($monthlyTrends['total']); ?>,
                            borderColor: chartColors.primary,
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Justifiées',
                            data: <?php echo json_encode($monthlyTrends['justified']); ?>,
                            borderColor: chartColors.success,
                            backgroundColor: 'transparent',
                            borderDash: [5, 5],
                            tension: 0.4
                        },
                        {
                            label: 'Non justifiées',
                            data: <?php echo json_encode($monthlyTrends['unjustified']); ?>,
                            borderColor: chartColors.danger,
                            backgroundColor: 'transparent',
                            borderDash: [5, 5],
                            tension: 0.4
                        }
                    ]
                };

                new Chart(document.getElementById('monthlyTrendChart'), {
                    type: 'line',
                    data: monthlyTrendData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>

        <?php endif; ?>
    </script>
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
</body>

</html>