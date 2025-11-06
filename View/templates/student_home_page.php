<!DOCTYPE html>
<html lang="fr">
<?php
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('student');

// Use the authenticated user's ID
if (!isset($_SESSION['id_student'])) {
    $_SESSION['id_student'] = $user['id'];
}
?>

<head>
    <title>Accueil Étudiant</title>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../assets/css/student_home_page.css">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
</head>

<body>
    <?php
    include __DIR__ . '/navbar.php';
    require_once __DIR__ . '/../../Presenter/session_cache.php';
    require_once __DIR__ . '/../../Presenter/student_get_info.php';

    // Forcer le rafraîchissement du cache si demandé via ?refresh=1
    $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] == '1';

    // Utiliser les données en session si disponibles et récentes
    if ($forceRefresh || !isset($_SESSION['stats']) || !isset($_SESSION['proofsByCategory']) || !isset($_SESSION['recentAbsences']) || !isset($_SESSION['stats']['total_absences_count']) || shouldRefreshCache(15)) {
        $_SESSION['stats'] = getAbsenceStatistics($_SESSION['id_student']);
        $_SESSION['proofsByCategory'] = getProofsByCategory($_SESSION['id_student']);
        $_SESSION['recentAbsences'] = getRecentAbsences($_SESSION['id_student'], 5);
        updateCacheTimestamp();
    }

    $stats = $_SESSION['stats'];
    $proofsByCategory = $_SESSION['proofsByCategory'];
    $recentAbsences = $_SESSION['recentAbsences'];

    // Calculer le pourcentage de justification
    $justification_percentage = $stats['total_hours_absences'] > 0
        ? round(($stats['hour_total_justified'] / $stats['total_hours_absences']) * 100, 1)
        : 0;
    ?>

    <div class="dashboard-container">
        <h1 class="dashboard-title">Tableau de Bord - Suivi des Absences</h1>

        <!-- Vue d'ensemble principale -->
        <div class="overview-section">
            <div class="overview-card primary">
                <div class="card-icon">📅</div>
                <div class="card-content">
                    <div class="card-label">Total d'absences</div>
                    <div class="card-value"><?php echo $stats['total_absences_count']; ?></div>
                    <div class="card-description">cours manqués au total</div>
                </div>
            </div>

            <div class="overview-card success">
                <div class="card-icon">✅</div>
                <div class="card-content">
                    <div class="card-label">Heures justifiées</div>
                    <div class="card-value"><?php echo $stats['hour_total_justified']; ?>h</div>
                    <div class="card-description">sur <?php echo $stats['total_hours_absences']; ?>h d'absence</div>
                </div>
            </div>

            <div class="overview-card warning">
                <div class="card-icon">⚠️</div>
                <div class="card-content">
                    <div class="card-label">Heures non justifiées</div>
                    <div class="card-value"><?php echo $stats['hour_total_unjustified']; ?>h</div>
                    <div class="card-description">
                        <?php echo $stats['hour_total_unjustified'] > 0 ? 'À justifier rapidement !' : 'Aucune heure à justifier'; ?>
                    </div>
                </div>
            </div>

            <div class="overview-card info">
                <div class="card-icon">📆</div>
                <div class="card-content">
                    <div class="card-label">Ce mois-ci</div>
                    <div class="card-value"><?php echo $stats['hour_month']; ?>h</div>
                    <div class="card-description">heures manquées en <?php echo date('F Y'); ?></div>
                </div>
            </div>
        </div>

        <!-- Barre de progression de justification -->
        <div class="justification-progress-section">
            <h2 class="section-heading">
                <span class="heading-icon">📊</span>
                Taux de justification des absences
            </h2>
            <div class="progress-container">
                <div class="progress-info">
                    <span class="progress-label">
                        <strong><?php echo $stats['hour_total_justified']; ?>h justifiées</strong>
                        sur <?php echo $stats['total_hours_absences']; ?>h d'absence totales
                    </span>
                    <span class="progress-percentage"><?php echo $justification_percentage; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo $justification_percentage >= 80 ? 'good' : ($justification_percentage >= 50 ? 'medium' : 'low'); ?>"
                        style="width: <?php echo $justification_percentage; ?>%">
                    </div>
                </div>
                <div class="progress-legend">
                    <span class="legend-item">
                        <span class="legend-color good"></span>
                        Bon (≥80%)
                    </span>
                    <span class="legend-item">
                        <span class="legend-color medium"></span>
                        Moyen (50-79%)
                    </span>
                    <span class="legend-item">
                        <span class="legend-color low"></span>
                        Faible (<50%) </span>
                </div>
            </div>
        </div>

        <!-- Statut des justificatifs -->
        <div class="proofs-status-section">
            <h2 class="section-heading">
                <span class="heading-icon">📄</span>
                État de vos justificatifs
            </h2>
            <div class="proofs-grid">
                <a href="student_proofs.php?status=accepted" class="proof-card proof-accepted">
                    <div class="proof-icon">✅</div>
                    <div class="proof-content">
                        <div class="proof-count"><?php echo $stats['accepted_proofs']; ?></div>
                        <div class="proof-label">Acceptés</div>
                        <div class="proof-description">Justificatifs validés</div>
                    </div>
                </a>

                <a href="student_proofs.php?status=pending" class="proof-card proof-pending">
                    <div class="proof-icon">🕐</div>
                    <div class="proof-content">
                        <div class="proof-count"><?php echo $stats['pending_proofs']; ?></div>
                        <div class="proof-label">En attente</div>
                        <div class="proof-description">En cours d'examen</div>
                    </div>
                </a>

                <a href="student_proofs.php?status=under_review" class="proof-card proof-review">
                    <div class="proof-icon">⚠️</div>
                    <div class="proof-content">
                        <div class="proof-count"><?php echo $stats['under_review_proofs']; ?></div>
                        <div class="proof-label">En révision</div>
                        <div class="proof-description">Infos complémentaires demandées</div>
                    </div>
                </a>

                <a href="student_proofs.php?status=rejected" class="proof-card proof-rejected">
                    <div class="proof-icon">❌</div>
                    <div class="proof-content">
                        <div class="proof-count"><?php echo $stats['rejected_proofs']; ?></div>
                        <div class="proof-label">Refusés</div>
                        <div class="proof-description">Non acceptés</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Alerte si heures non justifiées -->
        <?php if ($stats['hour_no_proof'] > 0): ?>
            <div class="alert-box alert-warning">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <div class="alert-title">Action requise : Absences non justifiées</div>
                    <div class="alert-message">
                        Vous avez <strong><?php echo $stats['hour_no_proof']; ?> heures d'absence non
                            justifiées</strong>.
                        Pensez à soumettre vos justificatifs dans les 48h suivant votre retour en cours pour éviter des
                        pénalités.
                    </div>
                    <a href="student_proof_submit.php" class="alert-action">
                        <span>➕</span> Soumettre un justificatif
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alerte si justificatifs en révision -->
        <?php if ($stats['under_review_proofs'] > 0): ?>
            <div class="alert-box alert-info">
                <div class="alert-icon">💬</div>
                <div class="alert-content">
                    <div class="alert-title">Informations complémentaires requises</div>
                    <div class="alert-message">
                        Vous avez <strong><?php echo $stats['under_review_proofs']; ?> justificatif(s) en révision</strong>.
                        L'équipe pédagogique a besoin d'informations supplémentaires.
                    </div>
                    <a href="student_proofs.php?status=under_review" class="alert-action">
                        Consulter mes justificatifs
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Absences Section -->
        <?php if (count($recentAbsences) > 0): ?>
            <div class="absences-section">
                <h2 class="section-title">
                    <span class="status-badge" style="background-color: #e0e7ff; color: #4338ca;">📚 Dernières
                        absences</span>
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
                                <th>Évaluation</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($recentAbsences, 0, 5) as $absence): ?>
                                <?php
                                // Déterminer le statut en fonction du proof_status ou justified
                                $proofStatus = $absence['proof_status'] ?? null;
                                $modalStatus = 'none';
                                $statusText = 'Non justifiée';
                                $statusIcon = '✗';
                                $statusClass = 'status-unjustified';

                                if ($proofStatus === 'accepted') {
                                    $modalStatus = 'accepted';
                                    $statusText = 'Justifiée';
                                    $statusIcon = '✅';
                                    $statusClass = 'status-justified';
                                } elseif ($proofStatus === 'under_review') {
                                    $modalStatus = 'under_review';
                                    $statusText = 'En révision';
                                    $statusIcon = '⚠️';
                                    $statusClass = 'status-under-review';
                                } elseif ($proofStatus === 'pending') {
                                    $modalStatus = 'pending';
                                    $statusText = 'En attente';
                                    $statusIcon = '🕐';
                                    $statusClass = 'status-pending';
                                } elseif ($proofStatus === 'rejected') {
                                    $modalStatus = 'rejected';
                                    $statusText = 'Rejeté';
                                    $statusIcon = '🚫';
                                    $statusClass = 'status-unjustified';
                                }

                                $teacher = ($absence['teacher_first_name'] && $absence['teacher_last_name'])
                                    ? htmlspecialchars($absence['teacher_first_name'] . ' ' . $absence['teacher_last_name'])
                                    : '-';

                                $courseType = strtoupper($absence['course_type'] ?? 'Autre');
                                $badge_class = '';

                                switch ($courseType) {
                                    case 'CM':
                                        $badge_class = 'badge-cm';
                                        break;
                                    case 'TD':
                                        $badge_class = 'badge-td';
                                        break;
                                    case 'TP':
                                        $badge_class = 'badge-tp';
                                        break;
                                    default:
                                        $badge_class = 'badge-other';
                                }
                                ?>
                                <tr class="clickable-row absence-row" style="cursor: pointer;"
                                    data-modal-status="<?php echo $modalStatus; ?>"
                                    data-date="<?php echo date('d/m/Y', strtotime($absence['course_date'])); ?>"
                                    data-time="<?php echo date('H\hi', strtotime($absence['start_time'])) . ' - ' . date('H\hi', strtotime($absence['end_time'])); ?>"
                                    data-course="<?php echo htmlspecialchars($absence['course_name'] ?? 'N/A'); ?>"
                                    data-course-code="<?php echo htmlspecialchars($absence['course_code'] ?? ''); ?>"
                                    data-teacher="<?php echo $teacher; ?>"
                                    data-room="<?php echo htmlspecialchars($absence['room_name'] ?? '-'); ?>"
                                    data-duration="<?php echo number_format($absence['duration_minutes'] / 60, 1); ?>"
                                    data-type="<?php echo $courseType; ?>" data-type-badge="<?php echo $badge_class; ?>"
                                    data-evaluation="<?php echo $absence['is_evaluation'] ? 'Oui' : 'Non'; ?>"
                                    data-motif="Aucun motif spécifié" data-status-text="<?php echo $statusText; ?>"
                                    data-status-icon="<?php echo $statusIcon; ?>"
                                    data-status-class="<?php echo $statusClass; ?>">
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
                                            <br><small
                                                class="course-code"><?php echo htmlspecialchars($absence['course_name']); ?></small>
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
                                    <td><strong><?php echo number_format($absence['duration_minutes'] / 60, 1); ?>h</strong>
                                    </td>
                                    <td>
                                        <span class="course-type-badge <?php echo $badge_class; ?>">
                                            <?php echo $courseType; ?>
                                        </span>
                                    <td>
                                        <?php if ($absence['is_evaluation']): ?>
                                            <span class="eval-badge">⚠️ Oui</span>
                                        <?php else: ?>
                                            <span class="no-eval">Non</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span
                                            class="status-badge <?php echo $statusClass; ?>"><?php echo $statusIcon . ' ' . $statusText; ?></span>
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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($proofsByCategory['under_review'], 0, 5) as $proof): ?>
                                <?php
                                $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                                $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                                $period = $start === $end ? $start : "$start - $end";

                                $reasons = [
                                    'illness' => 'Maladie',
                                    'death' => 'Décès',
                                    'family_obligations' => 'Obligations familiales',
                                    'other' => 'Autre'
                                ];
                                $reasonText = $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                                ?>
                                <tr class="clickable-row proof-row" style="cursor: pointer;" data-status="under_review"
                                    data-proof-id="<?php echo $proof['proof_id']; ?>"
                                    data-period="<?php echo htmlspecialchars($period); ?>"
                                    data-reason="<?php echo htmlspecialchars($reasonText); ?>"
                                    data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                    data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                    data-absences="<?php echo $proof['absence_count'] ?? 0; ?>"
                                    data-submission="<?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>"
                                    data-status-text="En révision" data-status-icon="⚠️" data-status-class="badge-warning"
                                    data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>"
                                    data-comment="<?php echo htmlspecialchars($proof['manager_comment'] ?? ''); ?>">
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
                                            <span
                                                class="comment-preview"><?php echo htmlspecialchars(substr($proof['manager_comment'], 0, 50)); ?><?php echo strlen($proof['manager_comment']) > 50 ? '...' : ''; ?></span>
                                        <?php else: ?>
                                            <span class="course-code">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../../Presenter/get_proof_for_edit.php?proof_id=<?php echo $proof['proof_id']; ?>"
                                            class="btn-add-info" onclick="event.stopPropagation();"
                                            title="Ajouter des informations">
                                            📝 Compléter
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($proofsByCategory['under_review']) > 5): ?>
                    <div class="section-footer">
                        <a href="student_proofs.php?status=under_review" class="btn-more">Plus</a>
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
                                <?php
                                $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                                $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                                $period = $start === $end ? $start : "$start - $end";

                                $reasons = [
                                    'illness' => 'Maladie',
                                    'death' => 'Décès',
                                    'family_obligations' => 'Obligations familiales',
                                    'other' => 'Autre'
                                ];
                                $reasonText = $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                                ?>
                                <tr class="clickable-row proof-row" style="cursor: pointer;" data-status="pending"
                                    data-period="<?php echo htmlspecialchars($period); ?>"
                                    data-reason="<?php echo htmlspecialchars($reasonText); ?>"
                                    data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                    data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                    data-absences="<?php echo $proof['absence_count'] ?? 0; ?>"
                                    data-submission="<?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>"
                                    data-processing="-" data-status-text="En attente" data-status-icon="🕐"
                                    data-status-class="badge-info" data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>"
                                    data-comment="">
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
                        <a href="student_proofs.php?status=pending" class="btn-more">Plus</a>
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
                                <?php
                                $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                                $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                                $period = $start === $end ? $start : "$start - $end";

                                $reasons = [
                                    'illness' => 'Maladie',
                                    'death' => 'Décès',
                                    'family_obligations' => 'Obligations familiales',
                                    'other' => 'Autre'
                                ];
                                $reasonText = $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                                ?>
                                <tr class="clickable-row proof-row" style="cursor: pointer;" data-status="accepted"
                                    data-period="<?php echo htmlspecialchars($period); ?>"
                                    data-reason="<?php echo htmlspecialchars($reasonText); ?>"
                                    data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                    data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                    data-absences="<?php echo $proof['absence_count'] ?? 0; ?>"
                                    data-submission="<?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>"
                                    data-processing="<?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : '-'; ?>"
                                    data-status-text="Accepté" data-status-icon="✅" data-status-class="badge-success"
                                    data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>" data-comment="">
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
                                    <td><?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : 'N/A'; ?>
                                    </td>
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
                        <a href="student_proofs.php?status=accepted" class="btn-more">Plus</a>
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
                                <?php
                                $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                                $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                                $period = $start === $end ? $start : "$start - $end";

                                $reasons = [
                                    'illness' => 'Maladie',
                                    'death' => 'Décès',
                                    'family_obligations' => 'Obligations familiales',
                                    'other' => 'Autre'
                                ];
                                $reasonText = $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                                ?>
                                <tr class="clickable-row proof-row" style="cursor: pointer;" data-status="rejected"
                                    data-period="<?php echo htmlspecialchars($period); ?>"
                                    data-reason="<?php echo htmlspecialchars($reasonText); ?>"
                                    data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                    data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                    data-absences="<?php echo $proof['absence_count'] ?? 0; ?>"
                                    data-submission="<?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>"
                                    data-processing="<?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : '-'; ?>"
                                    data-status-text="Refusé" data-status-icon="❌" data-status-class="badge-danger"
                                    data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>"
                                    data-comment="<?php echo htmlspecialchars($proof['manager_comment'] ?? ''); ?>">
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
                                    <td><?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <?php if ($proof['has_exam']): ?>
                                            <span class="eval-badge">⚠️ Éval</span>
                                        <?php else: ?>
                                            <span class="no-eval">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($proof['manager_comment']): ?>
                                            <span
                                                class="comment-preview"><?php echo htmlspecialchars(substr($proof['manager_comment'], 0, 50)); ?><?php echo strlen($proof['manager_comment']) > 50 ? '...' : ''; ?></span>
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
                        <a href="student_proofs.php?status=rejected" class="btn-more">Plus</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal pour afficher les détails de l'absence -->
    <div id="absenceModal" class="modal">
        <div class="modal-overlay"></div>
        <div id="absenceModalContent" class="modal-content">
            <button class="modal-close" id="closeAbsenceModal">&times;</button>
            <h2 class="modal-title">Détails de l'Absence</h2>
            <div class="modal-body">
                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📅 Date :</span>
                        <span class="modal-value" id="absenceModalDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">🕐 Horaire :</span>
                        <span class="modal-value" id="absenceModalTime"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">⏱️ Durée :</span>
                        <span class="modal-value" id="absenceModalDuration"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📚 Cours :</span>
                        <span class="modal-value" id="absenceModalCourse"></span>
                    </div>
                    <div class="modal-info-item" id="absenceCourseCodeItem" style="display: none;">
                        <span class="modal-label">🔖 Code :</span>
                        <span class="modal-value" id="absenceModalCourseCode"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">👨‍🏫 Enseignant :</span>
                        <span class="modal-value" id="absenceModalTeacher"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">🚪 Salle :</span>
                        <span class="modal-value" id="absenceModalRoom"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📝 Type :</span>
                        <span class="modal-value">
                            <span id="absenceModalType" class="badge"></span>
                        </span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📝 Évaluation :</span>
                        <span class="modal-value" id="absenceModalEvaluation"></span>
                    </div>
                </div>

                <div class="modal-status-section">
                    <span class="modal-label">🏷️ Statut :</span>
                    <span id="absenceModalStatus" class="badge"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour afficher les détails du justificatif -->
    <div id="proofModal" class="modal">
        <div class="modal-overlay"></div>
        <div id="proofModalContent" class="modal-content">
            <button class="modal-close" id="closeProofModal">&times;</button>
            <h2 class="modal-title">Détails du Justificatif</h2>
            <div class="modal-body">
                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📅 Période d'absence :</span>
                        <span class="modal-value" id="proofModalPeriod"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📝 Motif :</span>
                        <span class="modal-value" id="proofModalReason"></span>
                    </div>
                    <div class="modal-info-item" id="proofCustomReasonItem" style="display: none;">
                        <span class="modal-label">ℹ️ Précision :</span>
                        <span class="modal-value" id="proofModalCustomReason"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">⏱️ Heures ratées :</span>
                        <span class="modal-value" id="proofModalHours"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📊 Absences concernées :</span>
                        <span class="modal-value" id="proofModalAbsences"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📝 Évaluation manquée :</span>
                        <span class="modal-value" id="proofModalExam"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📤 Date de soumission :</span>
                        <span class="modal-value" id="proofModalSubmission"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">✅ Date de traitement :</span>
                        <span class="modal-value" id="proofModalProcessing"></span>
                    </div>
                </div>

                <div class="modal-status-section">
                    <span class="modal-label">🏷️ Statut :</span>
                    <span id="proofModalStatus" class="badge"></span>
                </div>

                <div class="modal-comment-section" id="proofCommentSection" style="display: none;">
                    <span class="modal-label">💬 Commentaire du responsable :</span>
                    <div class="modal-comment-box" id="proofModalComment"></div>
                </div>

                <!-- Bouton Compléter (visible uniquement pour les justificatifs en révision) -->
                <div class="modal-action-section" id="proofActionSection"
                    style="display: none; margin-top: 20px; text-align: center;">
                    <a href="#" id="proofModalCompleteBtn" class="btn-add-info"
                        style="display: inline-block; padding: 12px 24px; text-decoration: none;">
                        📝 Compléter le justificatif
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/home_page_modals.js"></script>
    <?php renderThemeScript(); ?>

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