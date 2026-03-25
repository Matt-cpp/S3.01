<?php

declare(strict_types=1);

/**
 * Absence management template for the academic manager - Complete list of absences.
 * Main features:
 * - Paginated display of all absences in the system
 * - Table with key information (date, time, student, course, type, status)
 * - Pagination navigation to browse the full history
 * - Link to detailed history with advanced filters
 * Uses AcademicManagerDashboardPresenter to retrieve paginated data.
 */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <title>Absences - Responsable Pédagogique</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../../assets/css/shared/accueil.css">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <link rel="stylesheet" href="../../assets/css/academic_manager/navbar.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
</head>

<body>
    <?php
    require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
    $user = requireRole('academic_manager');

    require_once __DIR__ . '/../../../Presenter/academic_manager/dashboard.php';
    $donnes = new AcademicManagerDashboardPresenter();
    ?>
    <?php include __DIR__ . '/../shared/navbar.php'; ?>

    <div class="main-content">
        <div class="stats-header">
            <h1>Gestion des Absences</h1>
            <p class="subtitle">Consultation et gestion de toutes les absences</p>
        </div>

        <div class="absences-section">
            <h2 class="section-title">Liste des Absences</h2>
            <p class="section-subtitle">Absences signalées dans le système</p>
            <?php
            if (isset($_GET['page'])) {
                $page = intval($_GET['page']);
                $donnes->setPage($page);
            }
            ?>

            <div class="pagination">
                <div>Page <?php echo ($donnes->getCurrentPage()) + 1; ?> sur <?php echo $donnes->getTotalPages(); ?>
                </div>
                <div class="pagination-buttons">
                    <a href="?page=<?php echo $donnes->getPreviousPage(); ?>">
                        <button class="btn btn-secondary" type="button">Précédent</button>
                    </a>
                    <a href="?page=<?php echo $donnes->getNextPage(); ?>">
                        <button class="btn btn-secondary" type="button">Suivant</button>
                    </a>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Étudiant</th>
                        <th>Cours</th>
                        <th>Type</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $f = $donnes->buildTable();
                    $tabel = json_decode(json_encode($f), true);
                    $labels = ['Date', 'Heure', 'Étudiant', 'Cours', 'Type', 'Statut'];

                    foreach ($tabel as $row) {
                        echo "<tr>";
                        $cellIndex = 0;
                        foreach ($row as $cell) {
                            $dataLabel = $labels[$cellIndex] ?? '';
                            if ($cellIndex === 5) {
                                $statusClass = '';
                                $statusValue = strtolower(trim($cell));

                                switch ($statusValue) {
                                    case 'absent':
                                        $statusClass = 'status-rejetee';
                                        break;
                                    case 'présent':
                                    case 'present':
                                        $statusClass = 'status-acceptee';
                                        break;
                                    case 'excusé':
                                    case 'excuse':
                                        $statusClass = 'status-justifiee';
                                        break;
                                    case 'non justifié':
                                    case 'non justifie':
                                        $statusClass = 'status-non-justifiee';
                                        break;
                                    case 'en attente':
                                        $statusClass = 'status-en-attente';
                                        break;
                                    case 'acceptée':
                                    case 'acceptee':
                                        $statusClass = 'status-acceptee';
                                        break;
                                    case 'rejetée':
                                    case 'rejetee':
                                        $statusClass = 'status-rejetee';
                                        break;
                                    case 'en cours d\'examen':
                                    case 'en cours':
                                        $statusClass = 'status-en-cours';
                                        break;
                                    case 'justifiée':
                                    case 'justifiee':
                                        $statusClass = 'status-justifiee';
                                        break;
                                    default:
                                        $statusClass = 'status-default';
                                }

                                echo '<td data-label="' . $dataLabel . '" class="status-cell"><span class="status-text ' . $statusClass . '">' . htmlspecialchars($cell) . '</span></td>';
                            } else {
                                echo '<td data-label="' . $dataLabel . '">' . htmlspecialchars($cell) . '</td>';
                            }
                            $cellIndex++;
                        }
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <div class="history-section">
                <a href="historique.php" class="btn-history btn-primary-action">Consulter l'historique complet</a>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    <?php renderThemeScript(); ?>
</body>

</html>
