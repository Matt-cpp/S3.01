<?php
// secretary_home.php (formerly dashboard-secretary.php)
require_once __DIR__ . '/../../../controllers/auth_guard.php';
$user = requireRole('secretary');

require_once __DIR__ . '/../../../Presenter/secretary/dashboard-presenter.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Secrétariat</title>
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="/View/assets/css/secretary/dashboard.css">
</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="dashboard-container">
        <!-- Left Section: Main Dashboard -->
        <div class="main-section">
            <!-- Import Section with Tabs -->
            <div class="card import-section">
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button class="tab-btn active" data-tab="absences">Importer les absences</button>
                        <button class="tab-btn" data-tab="students">Importer les étudiants</button>
                    </div>

                    <!-- Tab Content: Absences Import -->
                    <div id="absences-tab" class="tab-content active">
                        <div class="import-controls">
                            <label for="csv-file" class="file-label">
                                <span class="file-button">Choisir un fichier CSV</span>
                                <span id="file-name">Aucun fichier sélectionné</span>
                            </label>
                            <input type="file" id="csv-file" accept=".csv" style="display: none;">
                            <button id="import-btn" class="btn-primary" disabled>Importer</button>
                        </div>

                        <!-- Progress Bar -->
                        <div id="progress-container" class="progress-container" style="display: none;">
                            <div class="progress-info">
                                <span id="progress-status">Importation en cours...</span>
                                <span id="progress-percentage">0%</span>
                            </div>
                            <div class="progress-bar">
                                <div id="progress-fill" class="progress-fill"></div>
                            </div>
                            <div id="progress-details" class="progress-details"></div>
                        </div>

                        <!-- Import Result -->
                        <div id="import-result" class="import-result" style="display: none;"></div>
                    </div>

                    <!-- Tab Content: Students Import -->
                    <div id="students-tab" class="tab-content">

                        <div class="import-controls">
                            <label for="students-csv-file" class="file-label">
                                <span class="file-button">Choisir un fichier CSV</span>
                                <span id="students-file-name">Aucun fichier sélectionné</span>
                            </label>
                            <input type="file" id="students-csv-file" accept=".csv" style="display: none;">
                            <button id="students-import-btn" class="btn-primary" disabled>Importer</button>
                        </div>

                        <!-- Progress Bar for Students -->
                        <div id="students-progress-container" class="progress-container" style="display: none;">
                            <div class="progress-info">
                                <span id="students-progress-status">Importation en cours...</span>
                                <span id="students-progress-percentage">0%</span>
                            </div>
                            <div class="progress-bar">
                                <div id="students-progress-fill" class="progress-fill"></div>
                            </div>
                            <div id="students-progress-details" class="progress-details"></div>
                        </div>

                        <!-- Import Result for Students -->
                        <div id="students-import-result" class="import-result" style="display: none;"></div>
                    </div>
                </div>
            </div>

            <!-- Manual Absence Entry Section -->
            <div class="card manual-entry-section">
                <h2>Saisie manuelle d'absence</h2>
                <form id="manual-absence-form">
                    <!-- Student Selection -->
                    <div class="form-group">
                        <label for="student-search">Étudiant *</label>
                        <div class="search-container">
                            <input type="text" id="student-search" placeholder="Rechercher un étudiant..."
                                autocomplete="off">
                            <input type="hidden" id="selected-student-id" name="student_id">
                            <div id="student-results" class="search-results"></div>
                        </div>
                        <div id="selected-student-info" class="selected-info"></div>
                    </div>

                    <!-- Date Selection -->
                    <div class="form-group">
                        <label for="absence-date">Date *</label>
                        <input type="date" id="absence-date" name="absence_date" required>
                    </div>

                    <!-- Time Selection -->
                    <div class="form-group">
                        <label for="start-time">Heure de début *</label>
                        <input type="time" id="start-time" name="start_time" required min="08:00" max="20:00">
                    </div>

                    <div class="form-group">
                        <label>Durée du cours *</label>
                        <div class="duration-presets">
                            <button type="button" class="duration-btn" data-duration="30">+30min</button>
                            <button type="button" class="duration-btn" data-duration="60">+1h</button>
                            <button type="button" class="duration-btn" data-duration="90">+1h30</button>
                            <button type="button" class="duration-btn" data-duration="120">+2h</button>
                            <button type="button" class="duration-btn" data-duration="150">+2h30</button>
                            <button type="button" class="duration-btn" data-duration="180">+3h</button>
                            <button type="button" class="duration-btn custom-duration"
                                data-duration="custom">Personnalisé</button>
                        </div>

                        <div id="custom-end-time-container" class="custom-end-time-container" style="display: none;">
                            <label for="end-time">Heure de fin personnalisée *</label>
                            <input type="time" id="end-time" name="end_time_custom" min="08:00" max="20:00">
                        </div>

                        <input type="hidden" id="end-time-value" name="end_time">
                        <div id="selected-time-info" class="selected-time-info"></div>
                    </div>

                    <!-- Resource Selection -->
                    <div class="form-group">
                        <label for="resource-search">Matière *</label>
                        <div class="search-container">
                            <input type="text" id="resource-search" placeholder="Rechercher une matière..."
                                autocomplete="off">
                            <input type="hidden" id="selected-resource-id" name="resource_id">
                            <div id="resource-results" class="search-results"></div>
                        </div>
                        <div id="selected-resource-info" class="selected-info"></div>
                        <button type="button" id="create-resource-btn" class="btn-secondary">+ Créer une nouvelle
                            matière</button>
                    </div>

                    <!-- Room Selection -->
                    <div class="form-group">
                        <label for="room-search">Salle *</label>
                        <div class="search-container">
                            <input type="text" id="room-search" placeholder="Rechercher une salle..."
                                autocomplete="off">
                            <input type="hidden" id="selected-room-id" name="room_id">
                            <div id="room-results" class="search-results"></div>
                        </div>
                        <div id="selected-room-info" class="selected-info"></div>
                        <button type="button" id="create-room-btn" class="btn-secondary">+ Créer une nouvelle
                            salle</button>
                    </div>

                    <!-- Course Type -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course-type">Type de cours *</label>
                            <select id="course-type" name="course_type" required>
                                <option value="">Sélectionner</option>
                                <option value="CM">CM - Cours Magistral</option>
                                <option value="TD">TD - Travaux Dirigés</option>
                                <option value="TP">TP - Travaux Pratiques</option>
                                <option value="BEN">BEN - Autonomie</option>
                                <option value="TPC">TPC - TP Contrôle</option>
                                <option value="DS">DS - Devoir Surveillé</option>
                                <option value="TDC">TDC - TD Contrôle</option>
                            </select>
                        </div>

                        <!-- Is Evaluation -->
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" id="is-evaluation" name="is_evaluation">
                                <span>Est une évaluation</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Enregistrer l'absence</button>
                </form>
            </div>
        </div>

        <!-- Right Section: History -->
        <div class="history-section">
            <div class="card history-card">
                <h2>Historique des imports</h2>
                <div id="import-history" class="history-list">
                    <!-- History items will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for creating new resource -->
    <div id="create-resource-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Créer une nouvelle matière</h3>
            <form id="create-resource-form">
                <div class="form-group">
                    <label for="new-resource-code">Code de la matière *</label>
                    <input type="text" id="new-resource-code" name="code" required placeholder="Ex: R301, SAE15...">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary cancel-modal">Annuler</button>
                    <button type="submit" class="btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal for creating new room -->
    <div id="create-room-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Créer une nouvelle salle</h3>
            <form id="create-room-form">
                <div class="form-group">
                    <label for="new-room-code">Code de la salle *</label>
                    <input type="text" id="new-room-code" name="code" required placeholder="Ex: A101, B204...">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary cancel-modal">Annuler</button>
                    <button type="submit" class="btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/View/assets/js/secretary/dashboard.js"></script>
    <?php renderThemeScript(); ?>
</body>

</html>