<?php
// teacher_statistics.php
//require_once __DIR__ . '/../../controllers/auth_guard.php';
//$user = requireRole('teacher');

require_once __DIR__ . '/../../Presenter/teacher_statistics_presenter.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des absences - Enseignant</title>
    <?php include __DIR__ . '/../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="/View/assets/css/teacher_statistics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="statistics-container">
        <!-- Header Section -->
        <div class="stats-header">
            <div class="stats-title">
                <span class="stats-icon">📊</span>
                <div>
                    <h1>Statistiques des absences</h1>
                    <p>Analyse complète et interactive des absences</p>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-header" id="filters-toggle">
                <span class="filter-icon">🔍</span>
                <span>Filtres</span>
                <span class="toggle-arrow">▼</span>
            </div>
            <div class="filters-content" id="filters-content">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="student-search">Rechercher un étudiant</label>
                        <input type="text" id="student-search" placeholder="Nom ou prénom...">
                    </div>
                    <div class="filter-group">
                        <label for="semester-filter">Semestre</label>
                        <select id="semester-filter">
                            <option value="">Tous les semestres</option>
                            <option value="S1_2025">S1 2025</option>
                            <option value="S1_2024">S1 2024</option>
                            <option value="S2_2024">S2 2024</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="course-type-filter">Type de cours</label>
                        <select id="course-type-filter">
                            <option value="">Tous les types</option>
                            <option value="CM">CM</option>
                            <option value="TD">TD</option>
                            <option value="TP">TP</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="resource-filter">Matière</label>
                        <select id="resource-filter" name="resource">
                            <option value="">Toutes les matières</option>
                            <?php foreach (($resources ?? []) as $resource): ?>
                                <option value="<?= htmlspecialchars($resource['id']) ?>"
                                    <?= (($_GET['resource'] ?? '') == $resource['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($resource['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button class="btn-apply-filters" id="apply-filters">Appliquer les filtres</button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-cards">
            <div class="kpi-card kpi-blue">
                <div class="kpi-icon">📊</div>
                <div class="kpi-content">
                    <span class="kpi-label">Total des absences</span>
                    <span class="kpi-value" id="total-absences"><?= $stats['total_absences'] ?? 0 ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-cyan">
                <div class="kpi-icon">👥</div>
                <div class="kpi-content">
                    <span class="kpi-label">Étudiants concernés</span>
                    <span class="kpi-value" id="total-students"><?= $stats['total_students'] ?? 0 ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-icon">✅</div>
                <div class="kpi-content">
                    <span class="kpi-label">Absences justifiées</span>
                    <span class="kpi-value" id="justified-absences"><?= $stats['justified'] ?? 0 ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-red">
                <div class="kpi-icon">⚠️</div>
                <div class="kpi-content">
                    <span class="kpi-label">Absences non justifiées</span>
                    <span class="kpi-value" id="unjustified-absences"><?= $stats['unjustified'] ?? 0 ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-orange">
                <div class="kpi-icon">📈</div>
                <div class="kpi-content">
                    <span class="kpi-label">Moyenne par étudiant</span>
                    <span class="kpi-value" id="average-absences"><?= number_format($stats['average'] ?? 0, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Semester Analysis -->
        <div class="section-title">
            <span class="section-icon">📅</span>
            <h2>Analyse par semestre</h2>
        </div>
        <div class="semester-cards">
            <?php foreach ($semesterStats ?? [] as $semester): ?>
                <div class="semester-card">
                    <h3><?= htmlspecialchars($semester['name']) ?></h3>
                    <div class="semester-details">
                        <div class="semester-row">
                            <span>CM:</span>
                            <span><?= $semester['cm'] ?? 0 ?></span>
                        </div>
                        <div class="semester-row">
                            <span>TD:</span>
                            <span><?= $semester['td'] ?? 0 ?></span>
                        </div>
                        <div class="semester-row">
                            <span>TP:</span>
                            <span><?= $semester['tp'] ?? 0 ?></span>
                        </div>
                        <div class="semester-row semester-total">
                            <span>Total:</span>
                            <span><?= $semester['total'] ?? 0 ?></span>
                        </div>
                    </div>
                    <div class="semester-chart">
                        <canvas id="semester-chart-<?= $semester['id'] ?>"></canvas>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <!-- Course Type Distribution -->
            <div class="chart-card">
                <h3>Répartition par type de cours</h3>
                <div class="chart-container">
                    <canvas id="courseTypeChart"></canvas>
                </div>
                <div class="chart-legend" id="courseTypeLegend"></div>
            </div>

            <!-- Subject Distribution -->
            <div class="chart-card">
                <h3>Répartition par matière (Top 10)</h3>
                <div class="chart-container">
                    <canvas id="subjectChart"></canvas>
                </div>
                <div class="chart-legend-vertical" id="subjectLegend"></div>
            </div>
        </div>

        <!-- Monthly Evolution Chart -->
        <div class="chart-card chart-full">
            <h3>Évolution mensuelle des absences</h3>
            <div class="chart-container-large">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

            <!-- Tendances par matière (Top 5) -->
        <div class="chart-card chart-full">
            <h3>Tendances par matière (Top 5)</h3>
            <div class="chart-container-large">
                <canvas id="subjectTrendsChart"></canvas>
            </div>
        </div>

        <!-- Tableau des étudiants avec le plus d'absences -->
        <div class="ranking-card">
            <div class="ranking-header">
                <span class="ranking-icon">🎯</span>
                <h2>Étudiants avec le plus d'absences</h2>
            </div>
            <div class="ranking-table-container">
                <table class="ranking-table">
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
                    <tbody id="ranking-body">
                        <?php foreach ($topStudents ??  [] as $index => $student): ?>
                            <tr>
                                <td>
                                    <span class="rank-badge rank-<?= $index + 1 ?>"><?= $index + 1 ?></span>
                                </td>
                                <td class="student-name"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                <td class="student-id"><?= htmlspecialchars($student['student_number']) ?></td>
                                <td class="total-absences"><?= $student['total_absences'] ?></td>
                                <td>
                                    <span class="unjustified-badge"><?= $student['unjustified'] ?></span>
                                </td>
                                <td>
                                    <button class="btn-details" onclick="showStudentDetails(<?= $student['id'] ?>)">
                                        Voir détails
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal détails étudiant -->
        <div id="student-detail-modal" class="modal-overlay" style="display: none;">
            <div class="modal-content-large">
                <div class="modal-header-detail">
                    <div class="student-detail-title">
                        <span class="detail-icon">📊</span>
                        <div>
                            <h2 id="detail-student-name">Statistiques de Thomas Robert</h2>
                            <p id="detail-student-id">Identifiant: 55667788</p>
                        </div>
                    </div>
                    <button class="modal-close" onclick="closeStudentDetails()">&times;</button>
                </div>

                <button class="btn-back" onclick="closeStudentDetails()">
                    ← Retour à la vue générale
                </button>

                <!-- KPI Cards for student -->
                <div class="student-kpi-cards">
                    <div class="student-kpi-card kpi-blue">
                        <div class="student-kpi-icon">📊</div>
                        <div class="student-kpi-content">
                            <span class="student-kpi-label">Total des absences</span>
                            <span class="student-kpi-value" id="detail-total">6</span>
                        </div>
                    </div>
                    <div class="student-kpi-card kpi-green">
                        <div class="student-kpi-icon">✅</div>
                        <div class="student-kpi-content">
                            <span class="student-kpi-label">Absences justifiées</span>
                            <span class="student-kpi-value" id="detail-justified">1</span>
                        </div>
                    </div>
                    <div class="student-kpi-card kpi-red">
                        <div class="student-kpi-icon">⚠️</div>
                        <div class="student-kpi-content">
                            <span class="student-kpi-label">Absences non justifiées</span>
                            <span class="student-kpi-value" id="detail-unjustified">5</span>
                        </div>
                    </div>
                    <div class="student-kpi-card kpi-cyan">
                        <div class="student-kpi-icon">📈</div>
                        <div class="student-kpi-content">
                            <span class="student-kpi-label">Taux de justification</span>
                            <span class="student-kpi-value" id="detail-rate">17%</span>
                        </div>
                    </div>
                </div>

                <!-- Charts for student -->
                <div class="student-charts-section">
                    <div class="student-chart-card">
                        <h3>Absences par type de cours</h3>
                        <div class="student-chart-container">
                            <canvas id="studentCourseTypeChart"></canvas>
                        </div>
                    </div>
                    <div class="student-chart-card">
                        <h3>Absences par matière (Top 10)</h3>
                        <div class="student-bar-chart-container">
                            <canvas id="studentSubjectChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data from PHP
        const statsData = {
            courseTypes: <?= json_encode($courseTypeStats ??  ['CM' => 42, 'TD' => 39, 'TP' => 30]) ?>,
            subjects: <?= json_encode($subjectStats ?? []) ?>,
            monthly: <?= json_encode($monthlyStats ?? []) ?>,
            semesters: <?= json_encode($semesterStats ?? []) ?>,
            subjectTrends: <?= json_encode($subjectTrends ?? []) ?>,
            topStudents: <?= json_encode($topStudents ?? []) ?>
        };
    </script>

    <script>
        // Data from PHP
        const statsData = {
            courseTypes: <?= json_encode($courseTypeStats ?? ['CM' => 42, 'TD' => 39, 'TP' => 30]) ?>,
            subjects: <?= json_encode($subjectStats ?? []) ?>,
            monthly: <?= json_encode($monthlyStats ?? []) ?>,
            semesters: <?= json_encode($semesterStats ?? []) ?>
        };
    </script>
    <script src="/View/assets/js/teacher_statistics.js"></script>
    <?php renderThemeScript(); ?>
</body>

</html>