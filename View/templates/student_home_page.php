<!DOCTYPE html>
<html lang="fr">
<?php
session_start();
// FIXME Force student ID to 1 POUR LINSTANT
$_SESSION['id_student'] = 1;
?>

<head>
    <title>Accueil</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/student_home_page.css">
</head>

<body>
    <?php 
    include __DIR__ . '/student_navbar.php';
    require_once __DIR__ . '/../../Presenter/session_cache.php';
    require_once __DIR__ . '/../../Presenter/student_get_info.php';

    // Utiliser les données en session si disponibles et récentes (défini dans session_cache.php), par défaut 20 minutes
    // sinon les récupérer de la BD
    if (!isset($_SESSION['stats']) || !isset($_SESSION['proofsByCategory']) || !isset($_SESSION['recentAbsences']) || shouldRefreshCache(1200)) {
        $_SESSION['stats'] = getAbsenceStatistics($_SESSION['id_student']);
        $_SESSION['proofsByCategory'] = getProofsByCategory($_SESSION['id_student']);
        $_SESSION['recentAbsences'] = getRecentAbsences($_SESSION['id_student'], 5);
        updateCacheTimestamp();
    }
    
    $stats = $_SESSION['stats'];
    $proofsByCategory = $_SESSION['proofsByCategory'];
    $recentAbsences = $_SESSION['recentAbsences'];
    ?>

    // FIXME stats pas bonne
    <div class="dashboard-container">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Heures manquées ce mois</h3>
            <div class="stat-number"><?php echo $stats['hour_month']; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Total heures manquées justifiées</h3>
            <div class="stat-number"><?php echo $stats['hour_total_justified']; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Total heures manquées non justifiées</h3>
            <div class="stat-number"><?php echo $stats['hour_total_unjustified']; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Total heures absences</h3>
            <div class="stat-number"><?php echo $stats['total_hours_absences']; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Justificatifs en révision</h3>
            <div class="stat-number"><?php echo $stats['under_review_proofs']; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Justificatifs en attente</h3>
            <div class="stat-number"><?php echo $stats['pending_proofs']; ?></div>
        </div>

        <div class="stat-card">
            <h3>Justificatifs acceptés</h3>
            <div class="stat-number"><?php echo $stats['accepted_proofs']; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Justificatifs refusés</h3>
            <div class="stat-number"><?php echo $stats['rejected_proofs']; ?></div>
        </div>

    </div>

    <!-- Recent Absences Section -->
    <?php if (count($recentAbsences) > 0): ?>
    <div class="absences-section">
        <h2 class="section-title">
            <span class="status-badge" style="background-color: #e0e7ff; color: #4338ca;">📚 Dernières absences</span>
        </h2>
        <div class="absences-subtitle">Derniers cours manqués</div>
        <div class="absences-table-container">
            <table class="absences-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Horaire</th>
                        <th>Cours</th>
                        <th>Enseignant</th>
                        <th>Salle</th>
                        <th>Durée</th>
                        <th>Type</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recentAbsences, 0, 5) as $absence): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($absence['course_date'])); ?></td>
                        <td>
                            <?php 
                            echo date('H\hi', strtotime($absence['start_time'])) . ' - ' . 
                                 date('H\hi', strtotime($absence['end_time'])); 
                            ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($absence['course_code'] ?? 'N/A'); ?></strong>
                            <?php if ($absence['course_name']): ?>
                                <br><small class="course-code"><?php echo htmlspecialchars($absence['course_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($absence['teacher_first_name'] && $absence['teacher_last_name']) {
                                echo htmlspecialchars($absence['teacher_first_name'] . ' ' . $absence['teacher_last_name']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($absence['room_name'] ?? '-'); ?></td>
                        <td><strong><?php echo number_format($absence['duration_minutes'] / 60, 1); ?>h</strong></td>
                        <td>
                            <?php if ($absence['is_evaluation']): ?>
                                <span class="eval-badge">⚠️ Éval</span>
                            <?php else: ?>
                                <span class="course-type-badge">
                                    <?php 
                                    $types = [
                                        'cm' => 'CM',
                                        'td' => 'TD',
                                        'tp' => 'TP',
                                        'exam' => 'Examen',
                                        'other' => 'Autre'
                                    ];
                                    echo $types[$absence['course_type']] ?? strtoupper($absence['course_type']);
                                    ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($absence['justified']): ?>
                                <span class="status-badge status-justified">✓ Justifié</span>
                            <?php else: ?>
                                <span class="status-badge status-unjustified">✗ Non justifié</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($recentAbsences) > 5): ?>
        <div class="section-footer">
            <a href="student_absences.php" class="btn-more">Plus</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Justificatifs by Category -->
    <?php if (count($proofsByCategory['under_review']) > 0): ?>
    <div class="absences-section">
        <h2 class="section-title">
            <span class="status-badge status-under-review">⚠️ Justificatifs en révision</span>
        </h2>
        <div class="absences-subtitle">Justificatifs nécessitant des informations supplémentaires</div>
        <div class="absences-table-container">
            <table class="absences-table">
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Motif</th>
                        <th>Heures ratées</th>
                        <th>Date soumission</th>
                        <th>Évaluation</th>
                        <th>Commentaire</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($proofsByCategory['under_review'], 0, 5) as $proof): ?>
                    <tr>
                        <td>
                            <?php 
                            $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                            $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                            echo $start === $end ? $start : "$start - $end";
                            ?>
                        </td>
                        <td>
                            <?php 
                            $reasons = [
                                'illness' => 'Maladie',
                                'death' => 'Décès',
                                'family_obligations' => 'Obligations familiales',
                                'other' => 'Autre'
                            ];
                            echo $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                            if ($proof['custom_reason']) {
                                echo '<br><small class="course-code">' . htmlspecialchars($proof['custom_reason']) . '</small>';
                            }
                            ?>
                        </td>
                        <td><strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong></td>
                        <td><?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?></td>
                        <td>
                            <?php if ($proof['has_exam']): ?>
                                <span class="eval-badge">⚠️ Éval</span>
                            <?php else: ?>
                                <span class="no-eval">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($proof['manager_comment']): ?>
                                <span class="comment-preview"><?php echo htmlspecialchars(substr($proof['manager_comment'], 0, 50)); ?><?php echo strlen($proof['manager_comment']) > 50 ? '...' : ''; ?></span>
                            <?php else: ?>
                                <span class="course-code">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($proofsByCategory['under_review']) > 5): ?>
        <div class="section-footer">
            <a href="student_absences.php" class="btn-more">Plus</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (count($proofsByCategory['pending']) > 0): ?>
    <div class="absences-section">
        <h2 class="section-title">
            <span class="status-badge status-pending">🕐 Justificatifs en attente de validation</span>
        </h2>
        <div class="absences-subtitle">En attente de vérification par le responsable pédagogique</div>
        <div class="absences-table-container">
            <table class="absences-table">
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Motif</th>
                        <th>Heures ratées</th>
                        <th>Date soumission</th>
                        <th>Évaluation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($proofsByCategory['pending'], 0, 5) as $proof): ?>
                    <tr>
                        <td>
                            <?php 
                            $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                            $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                            echo $start === $end ? $start : "$start - $end";
                            ?>
                        </td>
                        <td>
                            <?php 
                            $reasons = [
                                'illness' => 'Maladie',
                                'death' => 'Décès',
                                'family_obligations' => 'Obligations familiales',
                                'other' => 'Autre'
                            ];
                            echo $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                            if ($proof['custom_reason']) {
                                echo '<br><small class="course-code">' . htmlspecialchars($proof['custom_reason']) . '</small>';
                            }
                            ?>
                        </td>
                        <td><strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong></td>
                        <td><?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?></td>
                        <td>
                            <?php if ($proof['has_exam']): ?>
                                <span class="eval-badge">⚠️ Éval</span>
                            <?php else: ?>
                                <span class="no-eval">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($proofsByCategory['pending']) > 5): ?>
        <div class="section-footer">
            <a href="student_absences.php" class="btn-more">Plus</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (count($proofsByCategory['accepted']) > 0): ?>
    <div class="absences-section">
        <h2 class="section-title">
            <span class="status-badge status-justified">✅ Justificatifs validés</span>
        </h2>
        <div class="absences-subtitle">Justificatifs acceptés par le responsable pédagogique</div>
        <div class="absences-table-container">
            <table class="absences-table">
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Motif</th>
                        <th>Heures ratées</th>
                        <th>Date soumission</th>
                        <th>Date validation</th>
                        <th>Évaluation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($proofsByCategory['accepted'], 0, 5) as $proof): ?>
                    <tr>
                        <td>
                            <?php 
                            $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                            $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                            echo $start === $end ? $start : "$start - $end";
                            ?>
                        </td>
                        <td>
                            <?php 
                            $reasons = [
                                'illness' => 'Maladie',
                                'death' => 'Décès',
                                'family_obligations' => 'Obligations familiales',
                                'other' => 'Autre'
                            ];
                            echo $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                            if ($proof['custom_reason']) {
                                echo '<br><small class="course-code">' . htmlspecialchars($proof['custom_reason']) . '</small>';
                            }
                            ?>
                        </td>
                        <td><strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong></td>
                        <td><?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?></td>
                        <td><?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : 'N/A'; ?></td>
                        <td>
                            <?php if ($proof['has_exam']): ?>
                                <span class="eval-badge">⚠️ Éval</span>
                            <?php else: ?>
                                <span class="no-eval">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($proofsByCategory['accepted']) > 5): ?>
        <div class="section-footer">
            <a href="student_absences.php" class="btn-more">Plus</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (count($proofsByCategory['rejected']) > 0): ?>
    <div class="absences-section">
        <h2 class="section-title">
            <span class="status-badge status-unjustified">❌ Justificatifs refusés</span>
        </h2>
        <div class="absences-subtitle">Justificatifs refusés par le responsable pédagogique</div>
        <div class="absences-table-container">
            <table class="absences-table">
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Motif</th>
                        <th>Heures ratées</th>
                        <th>Date soumission</th>
                        <th>Date refus</th>
                        <th>Évaluation</th>
                        <th>Commentaire</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($proofsByCategory['rejected'], 0, 5) as $proof): ?>
                    <tr>
                        <td>
                            <?php 
                            $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                            $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                            echo $start === $end ? $start : "$start - $end";
                            ?>
                        </td>
                        <td>
                            <?php 
                            $reasons = [
                                'illness' => 'Maladie',
                                'death' => 'Décès',
                                'family_obligations' => 'Obligations familiales',
                                'other' => 'Autre'
                            ];
                            echo $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                            if ($proof['custom_reason']) {
                                echo '<br><small class="course-code">' . htmlspecialchars($proof['custom_reason']) . '</small>';
                            }
                            ?>
                        </td>
                        <td><strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong></td>
                        <td><?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?></td>
                        <td><?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : 'N/A'; ?></td>
                        <td>
                            <?php if ($proof['has_exam']): ?>
                                <span class="eval-badge">⚠️ Éval</span>
                            <?php else: ?>
                                <span class="no-eval">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($proof['manager_comment']): ?>
                                <span class="comment-preview"><?php echo htmlspecialchars(substr($proof['manager_comment'], 0, 50)); ?><?php echo strlen($proof['manager_comment']) > 50 ? '...' : ''; ?></span>
                            <?php else: ?>
                                <span class="course-code">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($proofsByCategory['rejected']) > 5): ?>
        <div class="section-footer">
            <a href="student_absences.php" class="btn-more">Plus</a>
        </div>
        <?php endif; ?>
    </div>
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
</body>

</html>