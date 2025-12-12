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
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../../assets/css/shared/accueil.css">
</head>

<body>
    <?php
    require_once __DIR__ . '/../../../controllers/auth_guard.php';
    $user = requireRole('academic_manager');

    require_once __DIR__ . '/../../../Presenter/academic_manager/dashboard.php';
    require_once __DIR__ . '/../../../Model/ProofModel.php';
    require_once __DIR__ . '/../../../Model/database.php';
    $donnes = new AcademicManagerDashboardPresenter();
    $proofModel = new ProofModel();
    $recentProofs = $proofModel->getRecentProofs(5); // Get 5 most recent proofs
    
    // Récupérer le nombre de justificatifs en attente
    $db = Database::getInstance();
    $pendingProofsCount = $db->select("SELECT COUNT(*) as count FROM proof WHERE status = 'pending'")[0]['count'];
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
                    <!-- Add your table headers here if needed -->
                </thead>
                <tbody>
                    <?php
                    $f = $donnes->laTable();
                    $tabel = json_decode(json_encode($f), true);

                    foreach ($tabel as $row) {
                        echo "<tr>";
                        foreach ($row as $cell) {
                            echo "<td>" . htmlspecialchars($cell) . "</td>";
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
                            <td colspan="7" style="text-align: center; padding: 20px; color: #6c757d;">
                                Aucun justificatif récent
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentProofs as $proof): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(($proof['last_name'] ?? '') . ' ' . ($proof['first_name'] ?? '')); ?>
                                </td>
                                <td><?php echo htmlspecialchars($proof['group_label'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($proof['absence_start_date']))); ?></td>
                                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($proof['absence_end_date']))); ?></td>
                                <td><?php echo htmlspecialchars($proofModel->translate('reason', $proof['main_reason'])); ?>
                                </td>
                                <td>
                                    <?php
                                    $statusText = $proofModel->translate('status', $proof['status']);
                                    $statusClass = '';
                                    switch ($proof['status']) {
                                        case 'pending':
                                            $statusClass = 'badge-warning';
                                            break;
                                        case 'accepted':
                                            $statusClass = 'badge-success';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'badge-danger';
                                            break;
                                        case 'under_review':
                                            $statusClass = 'badge-info';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"
                                        style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                        <?php echo htmlspecialchars($statusText); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_proof.php?proof_id=<?php echo urlencode($proof['proof_id']); ?>"
                                        class="btn btn-sm"
                                        style="padding: 6px 12px; background-color: #4338ca; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; display: inline-block;">
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
</body>

</html>