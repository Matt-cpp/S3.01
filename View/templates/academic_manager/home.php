<?php
/**
 * Fichier: home.php
 * 
 * Template du tableau de bord du responsable pédagogique - Affiche une vue d'ensemble des absences.
 * Fonctionnalités principales :
 * - Affichage de cartes statistiques (absences du jour, du mois, non justifiées, justificatifs en attente)
 * - Liste paginnée des absences récentes avec détails complets
 * - Tableau des justificatifs récemment soumis (5 derniers)
 * - Système de pagination pour parcourir l'historique
 * - Liens rapides vers les pages de gestion détaillées
 * Utilise AcademicManagerDashboardPresenter pour récupérer les données.
 */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <title>Tableau de bord - Responsable Pédagogique</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../../assets/css/shared/accueil.css">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <link rel="stylesheet" href="../../assets/css/shared/base.css">
    <link rel="stylesheet" href="../../assets/css/academic_manager/home.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
</head>

<body>
    <?php
    require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
    $user = requireRole('academic_manager');

    require_once __DIR__ . '/../../../Presenter/academic_manager/dashboard.php';
    $donnes = new AcademicManagerDashboardPresenter();
    $recentProofs = $donnes->getRecentProofs(5);
    $pendingProofsCount = $donnes->pendingProofsCount();
    ?>
    <?php include __DIR__ . '/../navbar.php'; ?>

    <!-- Section des cartes statistiques (KPIs) -->
    <div class="main-content">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Absences du jour</div>
                <div class="stat-number"><?php echo $donnes->todayAbs() ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Cumul des absences du mois</div>
                <div class="stat-number"><?php echo $donnes->thisMonthAbs() ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Absences non justifiées</div>
                <div class="stat-number"><?php echo $donnes->unjustifiedAbs() ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Justificatifs en attente</div>
                <div class="stat-number"><?php echo $pendingProofsCount ?></div>
            </div>
        </div>

        <div class="absences-section">
            <h2 class="section-title">Absences Récentes</h2>
            <p class="section-subtitle">Dernières absences signalées dans le système</p>
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
                        <button class="btn" type="button">Précédent</button>
                    </a>
                    <a href="?page=<?php echo $donnes->getNextPage(); ?>">
                        <button class="btn" type="button">Suivant</button>
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
                    $f = $donnes->laTable();
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
                                        $statusClass = 'status-acceptee';
                                        break;
                                    case 'excusé':

                                        $statusClass = 'status-justifiee';
                                        break;
                                    case 'non justifié':
                                        $statusClass = 'status-non-justifiee';
                                        break;
                                    case 'en attente':
                                        $statusClass = 'status-en-attente';
                                        break;
                                    case 'acceptée':
                                        $statusClass = 'status-acceptee';
                                        break;
                                    case 'rejetée':
                                        $statusClass = 'status-rejetee';
                                        break;
                                    case 'en cours d\'examen':
                                        $statusClass = 'status-en-cours';
                                        break;
                                    case 'justifiée':
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
                <a href="historique.php" class="btn-history">Consulter l'historique</a>
            </div>
        </div>

        <!-- Justificatifs Récents Section -->
        <div class="absences-section">
            <h2 class="section-title">Justificatifs Récents</h2>
            <p class="section-subtitle">Derniers justificatifs soumis dans le système</p>

            <table class="table">
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Groupe</th>
                        <th>Date de début</th>
                        <th>Date de fin</th>
                        <th>Motif</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentProofs)): ?>
                        <tr>
                            <td colspan="7" class="empty-message">
                                Aucun justificatif récent
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentProofs as $proof): ?>
                            <tr>
                                <td data-label="Étudiant"><?php echo htmlspecialchars(($proof['last_name'] ?? '') . ' ' . ($proof['first_name'] ?? '')); ?>
                                </td>
                                <td data-label="Groupe"><?php echo htmlspecialchars($proof['group_label'] ?? 'N/A'); ?></td>
                                <td data-label="Début"><?php echo htmlspecialchars(date('d/m/Y', strtotime($proof['absence_start_date']))); ?></td>
                                <td data-label="Fin"><?php echo htmlspecialchars(date('d/m/Y', strtotime($proof['absence_end_date']))); ?></td>
                                <td data-label="Motif"><?php echo htmlspecialchars($donnes->translateProof('reason', $proof['main_reason'])); ?>
                                </td>
                                <td data-label="Statut" class="status-cell">
                                    <?php
                                    $statusText = $donnes->translateProof('status', $proof['status']);
                                    $statusClass = '';
                                    switch ($proof['status']) {
                                        case 'pending':
                                            $statusClass = 'status-en-attente';
                                            break;
                                        case 'accepted':
                                            $statusClass = 'status-acceptee';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'status-rejetee';
                                            break;
                                        case 'under_review':
                                            $statusClass = 'status-en-cours';
                                            break;
                                    }
                                    ?>
                                    <span class="status-text <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($statusText); ?>
                                    </span>
                                </td>
                                <td data-label="Action">
                                    <a href="view_proof.php?proof_id=<?php echo urlencode($proof['proof_id']); ?>"
                                        class="btn btn-sm">
                                        Voir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="history-section">
                <a href="historique_proof.php" class="btn-history">Consulter l'historique des justificatifs</a>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    <?php renderThemeScript(); ?>
</body>

</html>