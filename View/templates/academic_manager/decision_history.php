<?php

declare(strict_types=1);

/**
 * Decision history template for the academic manager - Consultation of proof decisions.
 * Main features:
 * - Multi-criteria search and filtering:
 *   - Search by student name
 *   - Filtering by decision date range
 *   - Filtering by action type (accept, reject, request_info, unlock)
 *   - Filtering by resulting status
 * - Detailed display of all decisions with context
 * - Result count
 * - Complete table with:
 *   - Decision date and action taken
 *   - Student information and absence period
 *   - Manager who processed it
 *   - Reason and comment
 *   - Status before/after
 * Uses DecisionHistoryPresenter to manage filters and retrieve data.
 */

require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$user = requireRole('academic_manager');

// Build absolute path for presenter
$presenterPath = __DIR__ . '/../../../Presenter/academic_manager/decision_history_presenter.php';
require_once $presenterPath;

// Instantiate the presenter
$presenter = new DecisionHistoryPresenter();

$decisions = $presenter->getDecisions();
$filters = $presenter->getFilters();
$errorMessage = $presenter->getErrorMessage();

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/academic_manager/historique.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <title>Historique des décisions</title>
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <style>
        .badge {
            display: inline-block;
            padding: 0.35rem 0.65rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: black;
        }

        .badge-info {
            background-color: #17a2b8;
            color: white;
        }

        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }

        .decision-row {
            border-bottom: 1px solid #e0e0e0;
        }

        .decision-row:hover {
            background-color: #f9f9f9;
        }

        .manager-name {
            font-size: 0.9rem;
            color: #666;
            font-style: italic;
        }

        .decision-comment {
            max-width: 300px;
            word-break: break-word;
            white-space: pre-wrap;
            font-size: 0.9rem;
            color: #555;
        }

        .decision-reason {
            background-color: #f5f5f5;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
            margin: 0.25rem 0;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
        }

        .filter-input {
            display: flex;
            flex-direction: column;
        }

        .filter-input label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .filter-input input,
        .filter-input select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 0.25rem;
            font-size: 0.9rem;
        }

        .filter-input input:focus,
        .filter-input select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .filter-buttons {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        .results-info {
            background-color: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.25rem;
        }

        .results-info strong {
            color: #1976D2;
        }

        .error-message {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .table thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9rem;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .no-results {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .decision-details {
            font-size: 0.9rem;
        }

        .status-transition {
            display: inline-block;
            color: #666;
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 0.8rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
            }

            .decision-comment {
                max-width: 150px;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>
    <main>
        <h1 class="page-title">Historique des décisions</h1>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Multi-criteria decision filtering form -->
        <form method="POST" action="">
            <div class="filter-grid">
                <div class="filter-input">
                    <label for="nameFilter">Nom de l'étudiant</label>
                    <input type="text" name="nameFilter" id="nameFilter" placeholder="Rechercher par nom..." 
                        value="<?php echo htmlspecialchars($filters['name'] ?? ''); ?>">
                </div>
                <div class="filter-input">
                    <label for="startDateFilter">Date de début</label>
                    <input type="date" name="startDateFilter" id="startDateFilter" 
                        placeholder="Date de début"
                        value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                </div>
                <div class="filter-input">
                    <label for="endDateFilter">Date de fin</label>
                    <input type="date" name="endDateFilter" id="endDateFilter" 
                        placeholder="Date de fin"
                        value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                </div>
                <div class="filter-input">
                    <label for="actionFilter">Type d'action</label>
                    <select name="actionFilter" id="actionFilter">
                        <option value="">-- Tous les types --</option>
                        <option value="accept" <?php echo isset($filters['action']) && $filters['action'] === 'accept' ? 'selected' : ''; ?>>
                            Accepté
                        </option>
                        <option value="reject" <?php echo isset($filters['action']) && $filters['action'] === 'reject' ? 'selected' : ''; ?>>
                            Rejeté
                        </option>
                        <option value="request_info" <?php echo isset($filters['action']) && $filters['action'] === 'request_info' ? 'selected' : ''; ?>>
                            Demande d'infos
                        </option>
                        <option value="unlock" <?php echo isset($filters['action']) && $filters['action'] === 'unlock' ? 'selected' : ''; ?>>
                            Déverrouillé
                        </option>
                    </select>
                </div>
                <div class="filter-input">
                    <label for="statusFilter">Statut résultant</label>
                    <select name="statusFilter" id="statusFilter">
                        <option value="">-- Tous les statuts --</option>
                        <option value="pending" <?php echo isset($filters['status']) && $filters['status'] === 'pending' ? 'selected' : ''; ?>>
                            En attente
                        </option>
                        <option value="accepted" <?php echo isset($filters['status']) && $filters['status'] === 'accepted' ? 'selected' : ''; ?>>
                            Accepté
                        </option>
                        <option value="rejected" <?php echo isset($filters['status']) && $filters['status'] === 'rejected' ? 'selected' : ''; ?>>
                            Rejeté
                        </option>
                        <option value="under_review" <?php echo isset($filters['status']) && $filters['status'] === 'under_review' ? 'selected' : ''; ?>>
                            En révision
                        </option>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="decision_history.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </div>
        </form>

        <!-- Results summary -->
        <div class="results-info">
            <strong><?php echo $presenter->getDecisionCount(); ?></strong> décision(s) trouvée(s)
        </div>

        <?php if (empty($decisions)): ?>
            <div class="no-results">
                <p>Aucune décision trouvée selon les critères de recherche.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date de décision</th>
                        <th>Étudiant</th>
                        <th>Période d'absence</th>
                        <th>Action</th>
                        <th>Statut avant / après</th>
                        <th>Raison / Motif</th>
                        <th>Responsable</th>
                        <th>Commentaire</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($decisions as $decision): ?>
                        <tr class="decision-row">
                            <td>
                                <?php echo htmlspecialchars($presenter->formatDateFr($decision['decision_date'])); ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($decision['first_name'] . ' ' . $decision['last_name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($decision['student_identifier']); ?></small>
                            </td>
                            <td>
                                <small>
                                    Du <?php echo htmlspecialchars(substr($decision['absence_start_date'], 0, 10)); ?>
                                    <br>au <?php echo htmlspecialchars(substr($decision['absence_end_date'], 0, 10)); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge <?php echo htmlspecialchars($presenter->getActionBadgeClass($decision['action'])); ?>">
                                    <?php echo htmlspecialchars($presenter->translateAction($decision['action'])); ?>
                                </span>
                            </td>
                            <td>
                                <small class="status-transition">
                                    <span class="badge <?php echo htmlspecialchars($presenter->getStatusBadgeClass($decision['old_status'])); ?>">
                                        <?php echo htmlspecialchars($presenter->translateStatus($decision['old_status'])); ?>
                                    </span>
                                    <strong>→</strong>
                                    <span class="badge <?php echo htmlspecialchars($presenter->getStatusBadgeClass($decision['new_status'])); ?>">
                                        <?php echo htmlspecialchars($presenter->translateStatus($decision['new_status'])); ?>
                                    </span>
                                </small>
                            </td>
                            <td>
                                <?php if (!empty($decision['main_reason'])): ?>
                                    <div class="decision-reason">
                                        <?php echo htmlspecialchars($decision['main_reason']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($decision['rejection_reason'])): ?>
                                    <div class="decision-reason" style="background-color: #fff3cd; border-left: 3px solid #ffc107;">
                                        <strong>Motif:</strong> <?php echo htmlspecialchars($decision['rejection_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="manager-name">
                                    <?php echo htmlspecialchars($presenter->getManagerName($decision['manager_last_name'], $decision['manager_first_name'])); ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($decision['comment'])): ?>
                                    <div class="decision-comment">
                                        <?php echo htmlspecialchars($decision['comment']); ?>
                                    </div>
                                <?php else: ?>
                                    <em style="color: #999;">--</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>

</html>
