<!DOCTYPE html>
<html lang="fr">
<?php
/**
 * File: home.php
 *
 * Student home page template. Displays a dashboard with:
 * - Absence overview (half-days, total...)
 * - Justification progress bar
 * - Proof status
 * - Recent absences list
 * - Proofs by category
 */
require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$user = requireRole('student');
require_once __DIR__ . '/../../../Model/format_ressource.php';

// Use the authenticated user's ID
if (!isset($_SESSION['id_student'])) {
    $_SESSION['id_student'] = $user['id'];
}
?>

<head>
    <title data-translate="page_title">Accueil Étudiant</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../../assets/css/student/home.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
    <link rel="stylesheet" href="../../assets/css/shared/language-switcher.css">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
</head>

<body data-page="student_home">
    <?php
    include __DIR__ . '/../shared/navbar.php';
    require_once __DIR__ . '/../../../Presenter/student/dashboard_presenter.php';

    // Handle cache refresh
    $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] == '1';

    $dashboard = new StudentDashboardPresenter($_SESSION['id_student'], $forceRefresh);
    $stats = $dashboard->getStats();
    $proofsByCategory = $dashboard->getProofsByCategory();
    $recentAbsences = $dashboard->getRecentAbsences();
    $justificationPercentage = $dashboard->getJustificationPercentage();
    $halfPointsLost = $dashboard->getHalfPointsLost();
    ?>

    <div class="dashboard-container">
        <h1 class="dashboard-title" data-translate="dashboard_title">Tableau de Bord</h1>

        <!-- Bouton d'action principal - Soumettre un justificatif (Incitation) -->
        <div class="cta-section">
            <a href="proof_submit.php" class="btn-cta" data-translate="submit_proof">
                Soumettre un justificatif
            </a>
        </div>

        <!-- Overview with main statistics cards (3 cards: total, unjustified, justifiable) -->
        <div class="overview-section">
            <div class="overview-card primary">
                <div class="card-content">
                    <div class="card-label" data-translate="half_days_missed">Demi-journées manquées</div>
                    <div class="card-value"><?php echo $stats['total_half_days']; ?></div>
                    <div class="card-description" data-translate="total_half_days_desc">Total de demi-journées d'absence
                    </div>
                </div>
            </div>

            <div class="overview-card danger">
                <div class="card-content">
                    <div class="card-label" data-translate="half_days_unjustified">Demi-journées non justifiées</div>
                    <div class="card-value"><?php echo $stats['half_days_unjustified']; ?></div>
                    <div class="card-description">
                        <?php echo $stats['half_days_unjustified'] > 0 ? '<span data-translate="warning">ATTENTION !</span>' : '<span data-translate="none_to_justify">Aucune à justifier</span>'; ?>
                    </div>
                </div>
            </div>

            <div class="overview-card warning">
                <div class="card-content">
                    <div class="card-label" data-translate="half_days_justifiable">Demi-journées justifiables</div>
                    <div class="card-value"><?php echo $stats['half_days_justifiable']; ?></div>
                    <div class="card-description" data-translate="without_proof">
                        Sans justificatif ou en revue
                    </div>
                </div>
            </div>

            <div class="overview-card success">
                <div class="card-content">
                    <div class="card-label" data-translate="half_days_justified">Demi-journées justifiées</div>
                    <div class="card-value"><?php echo $stats['half_days_justified']; ?></div>
                    <div class="card-description"><span data-translate="on_half_days">Sur</span>
                        <?php echo $stats['total_half_days']; ?> <span>demi-journées</span></div>
                </div>
            </div>

            <div class="overview-card info">
                <div class="card-content">
                    <div class="card-label" data-translate="this_month">Ce mois-ci</div>
                    <div class="card-value"><?php echo $stats['half_days_this_month']; ?></div>
                    <div class="card-description"><span data-translate="half_days_in">Demi-journées en</span>
                        <span class="current-month-year" data-date="<?php echo date('Y-m-01'); ?>"></span>
                    </div>
                </div>
            </div>

            <div class="overview-card secondary">
                <div class="card-content">
                    <div class="card-label" data-translate="total_absences">Total absences</div>
                    <div class="card-value"><?php echo $stats['total_absences_count']; ?></div>
                    <div class="card-description" data-translate="courses_missed">Cours manqués au total</div>
                </div>
            </div>
        </div>

        <!-- Statut des justificatifs (groupé en haut pour visibilité) -->
        <div class="proofs-status-section">
            <h2 class="section-heading">
                <span class="heading-icon"></span>
                <span data-translate="proofs_status">État de vos justificatifs</span>
            </h2>
            <div class="proofs-grid">
                <a href="proofs.php?status=accepted" class="proof-card proof-accepted">
                    <div class="proof-icon"></div>
                    <div class="proof-content">
                        <div class="proof-count"><?php echo $stats['accepted_proofs']; ?></div>
                        <div class="proof-label" data-translate="accepted">Acceptés</div>
                        <div class="proof-description" data-translate="validated_proofs">Justificatifs validés</div>
                    </div>
                </a>

                <a href="proofs.php?status=pending" class="proof-card proof-pending">
                    <div class="proof-icon"></div>
                    <div class="proof-content">
                        <div class="proof-count"><?php echo $stats['pending_proofs']; ?></div>
                        <div class="proof-label" data-translate="pending">En attente</div>
                        <div class="proof-description" data-translate="under_review_desc">En cours d'examen</div>
                    </div>
                </a>

                <a href="proofs.php?status=under_review" class="proof-card proof-review">
                    <div class="proof-icon"></div>
                    <div class="proof-content">
                        <div class="proof-count"><?php echo $stats['under_review_proofs']; ?></div>
                        <div class="proof-label" data-translate="under_review">En révision</div>
                        <div class="proof-description" data-translate="additional_info_requested">Infos
                            complémentaires
                            demandées</div>
                    </div>
                </a>

                <a href="proofs.php?status=rejected" class="proof-card proof-rejected">
                    <div class="proof-icon"></div>
                    <div class="proof-content">
                        <div class="proof-count"><?php echo $stats['rejected_proofs']; ?></div>
                        <div class="proof-label" data-translate="rejected">Refusés</div>
                        <div class="proof-description" data-translate="not_accepted">Non acceptés</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Justification progress bar -->

        <div class="justification-progress-section">
            <h2 class="section-heading" data-translate="justification_rate">
                Taux de justification des demi-journées d'absence
            </h2>
            <div class="progress-container">
                <div class="progress-info">
                    <span class="progress-label">
                        <strong><?php echo $stats['half_days_justified']; ?> <span
                                data-translate="justified_half_days">demi-journées justifiées</span></strong>
                        <span data-translate="on_total">sur</span> <?php echo $stats['total_half_days']; ?> <span
                            data-translate="total_absence_half_days">demi-journées d'absence totales</span>
                    </span>
                    <span class="progress-percentage"><?php echo $justificationPercentage; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo $justificationPercentage >= 80 ? 'good' : ($justificationPercentage >= 50 ? 'medium' : 'low'); ?>"
                        style="width: <?php echo $justificationPercentage; ?>%">
                    </div>
                </div>
                <div class="progress-legend">
                    <span class="legend-item">
                        <span class="legend-color good"></span>
                        <span data-translate="good">Bon (≥80%)</span>
                    </span>
                    <span class="legend-item">
                        <span class="legend-color medium"></span>
                        <span data-translate="medium">Moyen (50-79%)</span>
                    </span>
                    <span class="legend-item">
                        <span class="legend-color low"></span>
                        <span data-translate="low">Faible (<50%)< /span>
                        </span>
                </div>
                <div class="points-penalty"
                    style="margin-top: 1.5rem; padding: 1rem; background: <?php echo $halfPointsLost > 0 ? '#fee2e2' : '#dcfce7'; ?>; border-radius: 8px; text-align: center;">
                    <span style="font-size: 1rem; color: #4b5563;">
                        <?php if ($halfPointsLost > 0): ?>
                            <strong style="color: #dc2626;"><?php echo $halfPointsLost; ?> <span
                                    data-translate="points_lost">point(s) perdu(s)</span></strong>
                            <span data-translate="in_average">dans la moyenne</span>
                            <span style="display: block; font-size: 0.875rem; margin-top: 0.25rem;"
                                data-translate="penalty_rule">(5 demi-journées non justifiées = 0,5 point perdu)</span>
                        <?php else: ?>
                            <strong style="color: #16a34a;" data-translate="no_points_lost">Aucun point perdu !</strong>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>


        <?php if ($stats['half_days_justifiable'] > 0 && $stats['under_review_proofs'] == 0): ?>
            <div class="alert-box alert-warning">
                <div class="alert-icon"></div>
                <div class="alert-content">
                    <div class="alert-title" data-translate="action_required">Action requise : Demi-journées non
                        justifiées
                    </div>
                    <div class="alert-message">
                        <span data-translate="you_have">Vous avez</span>
                        <strong><?php echo $stats['half_days_justifiable']; ?> <span
                                data-translate="unjustified_half_days_alert">demi-journée(s) d'absence non
                                justifiée(s)</span></strong>.
                        <span data-translate="submit_within_48h">Pensez à soumettre vos justificatifs dans les 48h
                            suivant
                            votre retour en cours pour éviter des pénalités.</span>
                    </div>
                    <a href="proof_submit.php" class="alert-action" data-translate="submit_proof">
                        Soumettre un justificatif
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alert if proofs under review -->
        <?php if ($stats['under_review_proofs'] > 0): ?>
            <div class="alert-box alert-info">
                <div class="alert-icon"></div>
                <div class="alert-content">
                    <div class="alert-title" data-translate="additional_info_title">Informations complémentaires
                        requises
                    </div>
                    <div class="alert-message">
                        <span data-translate="you_have">Vous avez</span>
                        <strong><?php echo $stats['under_review_proofs']; ?> <span
                                data-translate="proofs_under_review">justificatif(s) en révision</span></strong>.
                        <span data-translate="team_needs_info">L'équipe pédagogique a besoin d'informations
                            supplémentaires.</span>
                    </div>
                    <a href="proofs.php?status=under_review" class="alert-action" data-translate="view_my_proofs">
                        Consulter mes justificatifs
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Absences Section -->
        <?php if (count($recentAbsences) > 0): ?>
            <div class="absences-section">
                <h2 class="section-title">
                    <span class="status-badge" style="background-color: #e0e7ff; color: #4338ca;"
                        data-translate="recent_absences">Dernières absences</span>
                </h2>
                <div class="absences-subtitle" data-translate="recent_courses_missed">Derniers cours manqués</div>
                <div class="absences-table-container">
                    <table class="absences-table">
                        <thead>
                            <tr>
                                <th data-translate="date">Date</th>
                                <th data-translate="time">Horaire</th>
                                <th data-translate="course">Cours</th>
                                <th data-translate="teacher">Enseignant</th>
                                <th data-translate="room">Salle</th>
                                <th data-translate="duration">Durée</th>
                                <th data-translate="type">Type</th>
                                <th data-translate="evaluation">Évaluation</th>
                                <th data-translate="status">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($recentAbsences, 0, 5) as $absence): ?>
                                <?php
                                // Determine the status based on proof_status or justified
                                $proofStatus = $absence['proof_status'] ?? null;
                                $modalStatus = 'none';
                                $statusText = 'Non justifiée';
                                $statusIcon = '';
                                $statusClass = 'status-unjustified';

                                if ($proofStatus === 'accepted') {
                                    $modalStatus = 'accepted';
                                    $statusText = 'Justifiée';
                                    $statusIcon = '';
                                    $statusClass = 'status-justified';
                                } elseif ($proofStatus === 'under_review') {
                                    $modalStatus = 'under_review';
                                    $statusText = 'En révision';
                                    $statusIcon = '';
                                    $statusClass = 'status-under-review';
                                } elseif ($proofStatus === 'pending') {
                                    $modalStatus = 'pending';
                                    $statusText = 'En attente';
                                    $statusIcon = '';
                                    $statusClass = 'status-pending';
                                } elseif ($proofStatus === 'rejected') {
                                    $modalStatus = 'rejected';
                                    $statusText = 'Rejeté';
                                    $statusIcon = '';
                                    $statusClass = 'status-unjustified';
                                }

                                $teacher = ($absence['teacher_first_name'] && $absence['teacher_last_name'])
                                    ? htmlspecialchars($absence['teacher_first_name'] . ' ' . $absence['teacher_last_name'])
                                    : '-';

                                $courseType = strtoupper($absence['course_type'] ?? 'Autre');
                                $badgeClass = '';

                                switch ($courseType) {
                                    case 'CM':
                                        $badgeClass = 'badge-cm';
                                        break;
                                    case 'TD':
                                        $badgeClass = 'badge-td';
                                        break;
                                    case 'TP':
                                        $badgeClass = 'badge-tp';
                                        break;
                                    default:
                                        $badgeClass = 'badge-other';
                                }
                                ?>
                                <tr class="clickable-row absence-row" style="cursor: pointer;"
                                    data-modal-status="<?php echo $modalStatus; ?>"
                                    data-date="<?php echo date('d/m/Y', strtotime($absence['course_date'])); ?>"
                                    data-time="<?php echo date('H\hi', strtotime($absence['start_time'])) . ' - ' . date('H\hi', strtotime($absence['end_time'])); ?>"
                                    data-course="<?php echo htmlspecialchars(formatResourceLabel($absence['course_name'] ?? 'N/A')); ?>"
                                    data-teacher="<?php echo $teacher; ?>"
                                    data-room="<?php echo htmlspecialchars($absence['room_name'] ?? '-'); ?>"
                                    data-duration="<?php echo number_format($absence['duration_minutes'] / 60, 1); ?>"
                                    data-type="<?php echo $courseType; ?>" data-type-badge="<?php echo $badgeClass; ?>"
                                    data-evaluation="<?php echo $absence['is_evaluation'] ? 'Oui' : 'Non'; ?>"
                                    data-is-evaluation="<?php echo $absence['is_evaluation'] ? '1' : '0'; ?>"
                                    data-has-makeup="<?php echo !empty($absence['makeup_id']) ? '1' : '0'; ?>"
                                    data-makeup-scheduled="<?php echo !empty($absence['makeup_scheduled']) ? '1' : '0'; ?>"
                                    data-makeup-date="<?php echo !empty($absence['makeup_date']) ? date('d/m/Y', strtotime($absence['makeup_date'])) : ''; ?>"
                                    data-makeup-time="<?php echo !empty($absence['makeup_start_time']) && !empty($absence['makeup_end_time']) ? date('H\hi', strtotime($absence['makeup_start_time'])) . ' - ' . date('H\hi', strtotime($absence['makeup_end_time'])) : ''; ?>"
                                    data-makeup-duration="<?php echo !empty($absence['makeup_duration']) ? number_format($absence['makeup_duration'] / 60, 1) : ''; ?>"
                                    data-makeup-room="<?php echo htmlspecialchars($absence['makeup_room'] ?? ''); ?>"
                                    data-makeup-resource="<?php echo htmlspecialchars(formatResourceLabel($absence['makeup_resource_label'] ?? '')); ?>"
                                    data-makeup-comment="<?php echo htmlspecialchars($absence['makeup_comment'] ?? ''); ?>"
                                    data-motif="Aucun motif spécifié" data-status-text="<?php echo $statusText; ?>"
                                    data-status-icon="<?php echo $statusIcon; ?>"
                                    data-status-class="<?php echo $statusClass; ?>">
                                    <td data-label="Date">
                                        <?php echo date('d/m/Y', strtotime($absence['course_date'])); ?>
                                    </td>
                                    <td data-label="Horaire">
                                        <?php
                                        echo date('H\hi', strtotime($absence['start_time'])) . ' - ' .
                                            date('H\hi', strtotime($absence['end_time']));
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($absence['course_name']): ?>
                                            <p class="course-code"><?php echo htmlspecialchars(formatResourceLabel($absence['course_name'])); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Enseignant">
                                        <?php
                                        if ($absence['teacher_first_name'] && $absence['teacher_last_name']) {
                                            echo htmlspecialchars($absence['teacher_first_name'] . ' ' . $absence['teacher_last_name']);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Salle"><?php echo htmlspecialchars($absence['room_name'] ?? '-'); ?>
                                    </td>
                                    <td data-label="Durée">
                                        <strong><?php echo number_format($absence['duration_minutes'] / 60, 1); ?>h</strong>
                                    </td>
                                    <td data-label="Type">
                                        <span class="course-type-badge <?php echo $badgeClass; ?>">
                                            <?php echo $courseType; ?>
                                        </span>
                                    </td>
                                    <td data-label="Évaluation">
                                        <?php if ($absence['is_evaluation']): ?>
                                            <span class="eval-badge" data-translate="yes">Oui</span>
                                            <?php if (!empty($absence['makeup_id']) && !empty($absence['makeup_scheduled'])): ?>
                                                <br><span class="makeup-badge"
                                                    style="background-color: #17a2b8; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-top: 4px; display: inline-block;"
                                                    data-translate="makeup_scheduled">Rattrapage
                                                    prévu</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="no-eval" data-translate="no">Non</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Statut">
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
                        <a href="student_absences.php" class="btn-more" data-translate="more">Plus</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Justificatifs by Category -->
        <?php if (count($proofsByCategory['under_review']) > 0): ?>
            <div class="absences-section">
                <h2 class="section-title">
                    <span class="status-badge status-under-review" data-translate="proofs_under_review">Justificatifs en
                        révision</span>
                </h2>
                <div class="absences-subtitle" data-translate="proofs_needing_info">Justificatifs nécessitant des
                    informations supplémentaires</div>
                <div class="absences-table-container">
                    <table class="absences-table">
                        <thead>
                            <tr>
                                <th data-translate="period">Période</th>
                                <th data-translate="reason">Motif</th>
                                <th data-translate="hours_missed">Heures ratées</th>
                                <th data-translate="submission_date">Date soumission</th>
                                <th data-translate="evaluation">Évaluation</th>
                                <th data-translate="comment">Commentaire</th>
                                <th data-translate="action">Action</th>
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
                                    'official_summons' => 'Convocation officielle',
                                    'transport_issue' => 'Problème de transport',
                                    'other' => 'Autre'
                                ];
                                $reasonText = $reasons[$proof['main_reason']] ?? $proof['main_reason'];

                                $proofFiles = [];
                                if (!empty($proof['proof_files'])) {
                                    $proofFiles = is_array($proof['proof_files']) ? $proof['proof_files'] : json_decode($proof['proof_files'], true);
                                    $proofFiles = is_array($proofFiles) ? $proofFiles : [];
                                }
                                ?>
                                <tr class="clickable-row proof-row" style="cursor: pointer;" data-status="under_review"
                                    data-proof-id="<?php echo $proof['proof_id']; ?>"
                                    data-period="<?php echo htmlspecialchars($period); ?>"
                                    data-start-datetime="<?php echo htmlspecialchars($proof['absence_start_datetime'] ?? $proof['absence_start_date']); ?>"
                                    data-end-datetime="<?php echo htmlspecialchars($proof['absence_end_datetime'] ?? $proof['absence_end_date']); ?>"
                                    data-reason="<?php echo htmlspecialchars($reasonText); ?>"
                                    data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                    data-student-comment="<?php echo htmlspecialchars($proof['student_comment'] ?? ''); ?>"
                                    data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                    data-absences="<?php echo $proof['nb_absences'] ?? 0; ?>"
                                    data-half-days="<?php echo $proof['half_days_count'] ?? 0; ?>"
                                    data-submission="<?php echo date('d/m/Y \\à H\\hi', strtotime($proof['submission_date'])); ?>"
                                    data-status-text="En révision" data-status-icon="" data-status-class="badge-warning"
                                    data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>"
                                    data-comment="<?php echo htmlspecialchars($proof['manager_comment'] ?? ''); ?>"
                                    data-files="<?php echo htmlspecialchars(json_encode($proofFiles)); ?>">
                                    <td data-label="Période">
                                        <?php
                                        $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                                        $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                                        echo $start === $end ? $start : "$start - $end";
                                        ?>
                                    </td>
                                    <td data-label="Motif">
                                        <?php
                                        $reasons = [
                                            'illness' => 'Maladie',
                                            'death' => 'Décès',
                                            'family_obligations' => 'Obligations familiales',
                                            'official_summons' => 'Convocation officielle',
                                            'transport_issue' => 'Problème de transport',
                                            'other' => 'Autre'
                                        ];
                                        echo $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                                        if ($proof['custom_reason']) {
                                            echo '<br><small class="course-code">' . htmlspecialchars($proof['custom_reason']) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Heures">
                                        <strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong>
                                    </td>
                                    <td data-label="Soumis le">
                                        <?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>
                                    </td>
                                    <td data-label="Évaluation">
                                        <?php if ($proof['has_exam']): ?>
                                            <span class="eval-badge" data-translate="eval">Éval</span>
                                        <?php else: ?>
                                            <span class="no-eval">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Commentaire">
                                        <?php if ($proof['manager_comment']): ?>
                                            <span
                                                class="comment-preview"><?php echo htmlspecialchars(substr($proof['manager_comment'], 0, 50)); ?><?php echo strlen($proof['manager_comment']) > 50 ? '...' : ''; ?></span>
                                        <?php else: ?>
                                            <span class="course-code">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Action">
                                        <a href="../../../Presenter/Student/get_proof_for_edit.php?proof_id=<?php echo $proof['proof_id']; ?>"
                                            class="btn-add-info" onclick="event.stopPropagation();"
                                            title="Ajouter des informations" data-translate="complete">
                                            Compléter
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($proofsByCategory['under_review']) > 5): ?>
                    <div class="section-footer">
                        <a href="proofs.php?status=under_review" class="btn-more" data-translate="more">Plus</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (count($proofsByCategory['pending']) > 0): ?>
            <div class="absences-section">
                <h2 class="section-title">
                    <span class="status-badge status-pending" data-translate="proofs_pending_validation">Justificatifs
                        en
                        attente de validation</span>
                </h2>
                <div class="absences-subtitle" data-translate="awaiting_verification">En attente de vérification par
                    le
                    responsable pédagogique</div>
                <div class="absences-table-container">
                    <table class="absences-table">
                        <thead>
                            <tr>
                                <th data-translate="period">Période</th>
                                <th data-translate="reason">Motif</th>
                                <th data-translate="hours_missed">Heures ratées</th>
                                <th data-translate="submission_date">Date soumission</th>
                                <th data-translate="evaluation">Évaluation</th>
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
                                    'official_summons' => 'Convocation officielle',
                                    'transport_issue' => 'Problème de transport',
                                    'other' => 'Autre'
                                ];
                                $reasonText = $reasons[$proof['main_reason']] ?? $proof['main_reason'];

                                $proofFiles = [];
                                if (!empty($proof['proof_files'])) {
                                    $proofFiles = is_array($proof['proof_files']) ? $proof['proof_files'] : json_decode($proof['proof_files'], true);
                                    $proofFiles = is_array($proofFiles) ? $proofFiles : [];
                                }
                                ?>
                                <tr class="clickable-row proof-row" style="cursor: pointer;" data-status="pending"
                                    data-proof-id="<?php echo $proof['proof_id']; ?>"
                                    data-period="<?php echo htmlspecialchars($period); ?>"
                                    data-start-datetime="<?php echo htmlspecialchars($proof['absence_start_datetime'] ?? $proof['absence_start_date']); ?>"
                                    data-end-datetime="<?php echo htmlspecialchars($proof['absence_end_datetime'] ?? $proof['absence_end_date']); ?>"
                                    data-reason="<?php echo htmlspecialchars($reasonText); ?>"
                                    data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                    data-student-comment="<?php echo htmlspecialchars($proof['student_comment'] ?? ''); ?>"
                                    data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                    data-absences="<?php echo $proof['nb_absences'] ?? 0; ?>"
                                    data-half-days="<?php echo $proof['half_days_count'] ?? 0; ?>"
                                    data-submission="<?php echo date('d/m/Y \\à H\\hi', strtotime($proof['submission_date'])); ?>"
                                    data-processing="-" data-status-text="En attente" data-status-icon=""
                                    data-status-class="badge-info" data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>"
                                    data-comment="" data-files="<?php echo htmlspecialchars(json_encode($proofFiles)); ?>">
                                    <td data-label="Période">
                                        <?php
                                        $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                                        $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                                        echo $start === $end ? $start : "$start - $end";
                                        ?>
                                    </td>
                                    <td data-label="Motif">
                                        <?php
                                        $reasons = [
                                            'illness' => 'Maladie',
                                            'death' => 'Décès',
                                            'family_obligations' => 'Obligations familiales',
                                            'official_summons' => 'Convocation officielle',
                                            'transport_issue' => 'Problème de transport',
                                            'other' => 'Autre'
                                        ];
                                        echo $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                                        if ($proof['custom_reason']) {
                                            echo '<br><small class="course-code">' . htmlspecialchars($proof['custom_reason']) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Heures">
                                        <strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong>
                                    </td>
                                    <td data-label="Soumis le">
                                        <?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>
                                    </td>
                                    <td data-label="Évaluation">
                                        <?php if ($proof['has_exam']): ?>
                                            <span class="eval-badge" data-translate="eval">Éval</span>
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
                        <a href="proofs.php?status=pending" class="btn-more" data-translate="more">Plus</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (count($proofsByCategory['accepted']) > 0): ?>
            <div class="absences-section">
                <h2 class="section-title">
                    <span class="status-badge status-justified" data-translate="validated_proofs">Justificatifs
                        validés</span>
                </h2>
                <div class="absences-subtitle" data-translate="proofs_accepted_by_manager">Justificatifs acceptés
                    par le
                    responsable pédagogique</div>
                <div class="absences-table-container">
                    <table class="absences-table">
                        <thead>
                            <tr>
                                <th data-translate="period">Période</th>
                                <th data-translate="reason">Motif</th>
                                <th data-translate="hours_missed">Heures ratées</th>
                                <th data-translate="submission_date">Date soumission</th>
                                <th data-translate="validation_date">Date validation</th>
                                <th data-translate="evaluation">Évaluation</th>
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
                                    'official_summons' => 'Convocation officielle',
                                    'transport_issue' => 'Problème de transport',
                                    'other' => 'Autre'
                                ];
                                $reasonText = $reasons[$proof['main_reason']] ?? $proof['main_reason'];

                                $proofFiles = [];
                                if (!empty($proof['proof_files'])) {
                                    $proofFiles = is_array($proof['proof_files']) ? $proof['proof_files'] : json_decode($proof['proof_files'], true);
                                    $proofFiles = is_array($proofFiles) ? $proofFiles : [];
                                }
                                ?>
                                <tr class="clickable-row proof-row" style="cursor: pointer;" data-status="accepted"
                                    data-proof-id="<?php echo $proof['proof_id']; ?>"
                                    data-period="<?php echo htmlspecialchars($period); ?>"
                                    data-start-datetime="<?php echo htmlspecialchars($proof['absence_start_datetime'] ?? $proof['absence_start_date']); ?>"
                                    data-end-datetime="<?php echo htmlspecialchars($proof['absence_end_datetime'] ?? $proof['absence_end_date']); ?>"
                                    data-reason="<?php echo htmlspecialchars($reasonText); ?>"
                                    data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                    data-student-comment="<?php echo htmlspecialchars($proof['student_comment'] ?? ''); ?>"
                                    data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                    data-absences="<?php echo $proof['nb_absences'] ?? 0; ?>"
                                    data-half-days="<?php echo $proof['half_days_count'] ?? 0; ?>"
                                    data-submission="<?php echo date('d/m/Y \\à H\\hi', strtotime($proof['submission_date'])); ?>"
                                    data-processing="<?php echo $proof['processing_date'] ? date('d/m/Y \\à H\\hi', strtotime($proof['processing_date'])) : '-'; ?>"
                                    data-status-text="Accepté" data-status-icon="" data-status-class="badge-success"
                                    data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>" data-comment=""
                                    data-files="<?php echo htmlspecialchars(json_encode($proofFiles)); ?>">
                                    <td data-label="Période">
                                        <?php
                                        $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                                        $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                                        echo $start === $end ? $start : "$start - $end";
                                        ?>
                                    </td>
                                    <td data-label="Motif">
                                        <?php
                                        $reasons = [
                                            'illness' => 'Maladie',
                                            'death' => 'Décès',
                                            'family_obligations' => 'Obligations familiales',
                                            'official_summons' => 'Convocation officielle',
                                            'transport_issue' => 'Problème de transport',
                                            'other' => 'Autre'
                                        ];
                                        echo $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                                        if ($proof['custom_reason']) {
                                            echo '<br><small class="course-code">' . htmlspecialchars($proof['custom_reason']) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Heures">
                                        <strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong>
                                    </td>
                                    <td data-label="Soumis le">
                                        <?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>
                                    </td>
                                    <td data-label="Traité le">
                                        <?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : 'N/A'; ?>
                                    </td>
                                    <td data-label="Évaluation">
                                        <?php if ($proof['has_exam']): ?>
                                            <span class="eval-badge" data-translate="eval">Éval</span>
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
                        <a href="proofs.php?status=accepted" class="btn-more" data-translate="more">Plus</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (count($proofsByCategory['rejected']) > 0): ?>
            <div class="absences-section">
                <h2 class="section-title">
                    <span class="status-badge status-unjustified" data-translate="rejected_proofs">Justificatifs
                        refusés</span>
                </h2>
                <div class="absences-subtitle" data-translate="proofs_rejected_by_manager">Justificatifs refusés par
                    le
                    responsable pédagogique</div>
                <div class="absences-table-container">
                    <table class="absences-table">
                        <thead>
                            <tr>
                                <th data-translate="period">Période</th>
                                <th data-translate="reason">Motif</th>
                                <th data-translate="hours_missed">Heures ratées</th>
                                <th data-translate="submission_date">Date soumission</th>
                                <th data-translate="rejection_date">Date refus</th>
                                <th data-translate="evaluation">Évaluation</th>
                                <th data-translate="comment">Commentaire</th>
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
                                    'official_summons' => 'Convocation officielle',
                                    'transport_issue' => 'Problème de transport',
                                    'other' => 'Autre'
                                ];
                                $reasonText = $reasons[$proof['main_reason']] ?? $proof['main_reason'];

                                $proofFiles = [];
                                if (!empty($proof['proof_files'])) {
                                    $proofFiles = is_array($proof['proof_files']) ? $proof['proof_files'] : json_decode($proof['proof_files'], true);
                                    $proofFiles = is_array($proofFiles) ? $proofFiles : [];
                                }
                                ?>
                                <tr class="clickable-row proof-row" style="cursor: pointer;" data-status="rejected"
                                    data-proof-id="<?php echo $proof['proof_id']; ?>"
                                    data-period="<?php echo htmlspecialchars($period); ?>"
                                    data-start-datetime="<?php echo htmlspecialchars($proof['absence_start_datetime'] ?? $proof['absence_start_date']); ?>"
                                    data-end-datetime="<?php echo htmlspecialchars($proof['absence_end_datetime'] ?? $proof['absence_end_date']); ?>"
                                    data-reason="<?php echo htmlspecialchars($reasonText); ?>"
                                    data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                    data-student-comment="<?php echo htmlspecialchars($proof['student_comment'] ?? ''); ?>"
                                    data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                    data-absences="<?php echo $proof['absence_count'] ?? 0; ?>"
                                    data-submission="<?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>"
                                    data-processing="<?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : '-'; ?>"
                                    data-status-text="Refusé" data-status-icon="" data-status-class="badge-danger"
                                    data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>"
                                    data-comment="<?php echo htmlspecialchars($proof['manager_comment'] ?? ''); ?>"
                                    data-files="<?php echo htmlspecialchars(json_encode($proofFiles)); ?>">
                                    <td data-label="Période">
                                        <?php
                                        $start = date('d/m/Y', strtotime($proof['absence_start_date']));
                                        $end = date('d/m/Y', strtotime($proof['absence_end_date']));
                                        echo $start === $end ? $start : "$start - $end";
                                        ?>
                                    </td>
                                    <td data-label="Motif">
                                        <?php
                                        $reasons = [
                                            'illness' => 'Maladie',
                                            'death' => 'Décès',
                                            'family_obligations' => 'Obligations familiales',
                                            'official_summons' => 'Convocation officielle',
                                            'transport_issue' => 'Problème de transport',
                                            'other' => 'Autre'
                                        ];
                                        echo $reasons[$proof['main_reason']] ?? $proof['main_reason'];
                                        if ($proof['custom_reason']) {
                                            echo '<br><small class="course-code">' . htmlspecialchars($proof['custom_reason']) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Heures">
                                        <strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong>
                                    </td>
                                    <td data-label="Soumis le">
                                        <?php echo date('d/m/Y \à H\hi', strtotime($proof['submission_date'])); ?>
                                    </td>
                                    <td data-label="Refusé le">
                                        <?php echo $proof['processing_date'] ? date('d/m/Y \à H\hi', strtotime($proof['processing_date'])) : 'N/A'; ?>
                                    </td>
                                    <td data-label="Évaluation">
                                        <?php if ($proof['has_exam']): ?>
                                            <span class="eval-badge" data-translate="eval">Éval</span>
                                        <?php else: ?>
                                            <span class="no-eval">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Commentaire">
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
                        <a href="proofs.php?status=rejected" class="btn-more" data-translate="more">Plus</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal to display absence details -->
    <div id="absenceModal" class="modal">
        <div class="modal-overlay"></div>
        <div id="absenceModalContent" class="modal-content">
            <button class="modal-close" id="closeAbsenceModal">&times;</button>
            <h2 class="modal-title" data-translate="absence_details">Détails de l'Absence</h2>
            <div class="modal-body">
                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="date">Date :</span>
                        <span class="modal-value" id="absenceModalDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="time">Horaire :</span>
                        <span class="modal-value" id="absenceModalTime"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="duration">Durée :</span>
                        <span class="modal-value" id="absenceModalDuration"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="course">Cours :</span>
                        <span class="modal-value" id="absenceModalCourse"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="teacher">Enseignant :</span>
                        <span class="modal-value" id="absenceModalTeacher"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="room">Salle :</span>
                        <span class="modal-value" id="absenceModalRoom"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="type">Type :</span>
                        <span class="modal-value">
                            <span id="absenceModalType" class="badge"></span>
                        </span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="evaluation">Évaluation :</span>
                        <span class="modal-value" id="absenceModalEvaluation"></span>
                    </div>
                </div>

                <!-- Missed evaluation section (visible only if is_evaluation) -->
                <div id="evaluationSection" class="modal-info-group"
                    style="display: none; background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <h3 style="color: #856404; margin-bottom: 10px; font-size: 16px;"
                        data-translate="missed_evaluation">Évaluation ratée</h3>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="evaluation">Évaluation :</span>
                        <span class="modal-value" id="evaluationCourse"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="date">Date :</span>
                        <span class="modal-value" id="evaluationDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="time">Horaire :</span>
                        <span class="modal-value" id="evaluationTime"></span>
                    </div>
                </div>

                <!-- Makeup section (visible only if makeup exists) -->
                <div id="makeupSection" class="modal-info-group"
                    style="display: none; background-color: #d1ecf1; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <h3 style="color: #0c5460; margin-bottom: 10px; font-size: 16px;" data-translate="makeup_scheduled">
                        Rattrapage prévu</h3>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="makeup_date">Date du rattrapage :</span>
                        <span class="modal-value" id="makeupDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="time">Horaire :</span>
                        <span class="modal-value" id="makeupTime"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="duration">Durée :</span>
                        <span class="modal-value" id="makeupDuration"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="room">Salle :</span>
                        <span class="modal-value" id="makeupRoom"></span>
                    </div>
                    <div class="modal-info-item" id="makeupResourceItem" style="display: none;">
                        <span class="modal-label" data-translate="subject">Matière :</span>
                        <span class="modal-value" id="makeupResource"></span>
                    </div>
                    <div class="modal-info-item" id="makeupCommentItem" style="display: none;">
                        <span class="modal-label" data-translate="comment">Commentaire :</span>
                        <span class="modal-value" id="makeupComment"></span>
                    </div>
                </div>

                <div class="modal-status-section">
                    <span class="modal-label" data-translate="status">Statut :</span>
                    <span id="absenceModalStatus" class="badge"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal to display proof details -->
    <div id="proofModal" class="modal">
        <div class="modal-overlay"></div>
        <div id="proofModalContent" class="modal-content">
            <button class="modal-close" id="closeProofModal">&times;</button>
            <h2 class="modal-title" data-translate="proof_details">Détails du Justificatif</h2>
            <div class="modal-body">
                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="absence_start">Début d'absence :</span>
                        <span class="modal-value" id="proofModalStartDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="absence_end">Fin d'absence :</span>
                        <span class="modal-value" id="proofModalEndDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="reason">Motif :</span>
                        <span class="modal-value" id="proofModalReason"></span>
                    </div>
                    <div class="modal-info-item" id="proofCustomReasonItem" style="display: none;">
                        <span class="modal-label" data-translate="specification">Précision :</span>
                        <span class="modal-value" id="proofModalCustomReason"></span>
                    </div>
                    <div class="modal-info-item" id="proofStudentCommentItem" style="display: none;">
                        <span class="modal-label" data-translate="student_comment">Commentaire de l'étudiant :</span>
                        <span class="modal-value" id="proofModalStudentComment"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="hours_missed">Heures ratées :</span>
                        <span class="modal-value" id="proofModalHours"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="affected_absences">Absences concernées :</span>
                        <span class="modal-value" id="proofModalAbsences"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="affected_half_days">Demi-journées concernées :</span>
                        <span class="modal-value" id="proofModalHalfDays"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="missed_evaluation">Évaluation manquée :</span>
                        <span class="modal-value" id="proofModalExam"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="submission_date">Date de soumission :</span>
                        <span class="modal-value" id="proofModalSubmission"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label" data-translate="processing_date">Date de traitement :</span>
                        <span class="modal-value" id="proofModalProcessing"></span>
                    </div>
                </div>

                <div class="modal-status-section">
                    <span class="modal-label" data-translate="status">Statut :</span>
                    <span id="proofModalStatus" class="badge"></span>
                </div>

                <div class="modal-files-section" id="proofFilesSection" style="display: none; margin-top: 20px;">
                    <span class="modal-label" data-translate="proof_files">Fichiers justificatifs :</span>
                    <div id="proofModalFiles" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;"></div>
                </div>

                <div class="modal-comment-section" id="proofCommentSection" style="display: none;">
                    <span class="modal-label" data-translate="manager_comment">Commentaire du responsable :</span>
                    <div class="modal-comment-box" id="proofModalComment"></div>
                </div>

                <!-- Complete button (visible only for proofs under review) -->
                <div class="modal-action-section" id="proofActionSection"
                    style="display: none; margin-top: 20px; text-align: center;">
                    <a href="#" id="proofModalCompleteBtn" class="btn-add-info"
                        style="display: inline-block; padding: 12px 24px; text-decoration: none;"
                        data-translate="complete_proof">
                        Compléter le justificatif
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/translations.js"></script>
    <script src="../../assets/js/student/home_modals.js"></script>
    <?php renderThemeScript(); ?>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
