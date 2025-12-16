<?php
/**
 * Fichier: proof_edit.php
 * 
 * Template de modification d'un justificatif d'absence existant pour les étudiants.
 * Fonctionnalités principales :
 * - Modification d'un justificatif en attente ou en révision
 * - Pré-remplissage automatique du formulaire avec les données existantes
 * - Gestion des fichiers justificatifs (conservation, suppression, ajout de nouveaux)
 * - Affichage du commentaire du responsable académique si disponible
 * - Restriction de la modification des dates (seuls le motif et les fichiers sont modifiables)
 * - Validation et chargement dynamique des cours concernés
 * Les données sont récupérées depuis la session et doivent être préparées par la page appelante.
 */
?>
<!DOCTYPE html>
<html lang="fr">
<?php
require_once __DIR__ . '/../../../controllers/auth_guard.php';
$user = requireRole('student');

// Use the authenticated user's ID
if (!isset($_SESSION['id_student'])) {
    $_SESSION['id_student'] = $user['id'];
}

// Vérification de la présence des données de modification en session
// Redirection vers la page des absences si aucune donnée n'est disponible
if (!isset($_SESSION['edit_proof'])) {
    $_SESSION['error_message'] = "Aucun justificatif à modifier.";
    header('Location: absences.php');
    exit();
}

$editData = $_SESSION['edit_proof'];
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <title>Modification Justificatif Élève</title>

    <link rel="stylesheet" href="../../assets/css/student/proof_submit.css">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <script>
        // Pass PHP session data to JavaScript
        window.studentId = <?php echo $_SESSION['id_student'] ?? 1; ?>;
        window.isEditing = true;
        window.editProofId = <?php echo $editData['proof_id'] ?? 'null'; ?>;
    </script>
    <!-- Load proof_submit.js for course loading and date validation -->
    <script src="../../assets/js/student/proof_submit.js"></script>
    <!-- Load proof_edit.js for file management in edit mode -->
    <script src="../../assets/js/student/proof_edit.js"></script>

</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>

    <main>
        <?php
        // Display error message if there's one in session
        if (isset($_SESSION['error_message'])) {
            echo '<div class="error-message">';
            echo '<strong>⚠️ Erreur:</strong> ' . htmlspecialchars($_SESSION['error_message']);
            echo '</div>';
            unset($_SESSION['error_message']); // Clear the message after displaying
        }

        // Display success message if redirected from successful submission
        if (isset($_GET['success'])) {
            echo '<div class="success-message">';
            echo '<strong>✅ Succès:</strong> Votre justificatif a été modifié avec succès !';
            echo '</div>';
        }
        ?>

        <h1 class="page-title">Modification de justificatif</h1>

        <!-- Affichage du commentaire du responsable académique s'il existe (cas de demande de révision) -->
        <?php if (!empty($editData['manager_comment'])): ?>
            <div class="manager-comment-section"
                style="background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <h3 style="color: #856404; margin-top: 0;">💬 Commentaire du responsable :</h3>
                <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($editData['manager_comment'])); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Formulaire de modification du justificatif (multipart pour upload de fichiers) -->
        <form action="../../../Presenter/student/proof_update.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="proof_id" value="<?php echo htmlspecialchars($editData['proof_id']); ?>">

            <!-- Dates d'absence (non modifiables, affichées en lecture seule) -->
            <div class="form-group">
                <label for="datetime_start">Date et heure de début d'absence :</label>
                <input type="datetime-local" id="datetime_start" name="datetime_start"
                    value="<?php echo htmlspecialchars($editData['datetime_start']); ?>" readonly
                    style="background-color: #e9ecef; cursor: not-allowed;" required>
                <p class="help-text" style="color: #6c757d;">Les dates ne peuvent pas être modifiées</p>
            </div>


            <div class="form-group">
                <label for="datetime_end">Date et heure de fin d'absence :</label>
                <input type="datetime-local" id="datetime_end" name="datetime_end"
                    value="<?php echo htmlspecialchars($editData['datetime_end']); ?>" readonly
                    style="background-color: #e9ecef; cursor: not-allowed;" required>
                <p class="help-text" style="color: #6c757d;">Les dates ne peuvent pas être modifiées</p>
            </div>

            <div class="form-group">
                <label for="class_involved">Cours concerné(s) :</label>
                <div id="courses_loading" style="display: none; color: #666; font-style: italic;">
                    Chargement des cours...
                </div>
                <div id="courses_container">
                    <p id="courses_placeholder" style="color: #666; font-style: italic;">
                        Chargement des cours concernés...
                    </p>
                    <div id="courses_list" style="display: none;"></div>
                </div>
                <div id="absence_recap" style="display: none;"></div>
                <input type="hidden" name="class_involved" id="class_involved_hidden"
                    value="<?php echo htmlspecialchars($editData['class_involved']); ?>">
                <!-- Hidden fields for statistics data -->
                <input type="hidden" name="absence_stats_hours" id="absence_stats_hours" value="0">
                <input type="hidden" name="absence_stats_halfdays" id="absence_stats_halfdays" value="0">
                <input type="hidden" name="absence_stats_evaluations" id="absence_stats_evaluations" value="0">
                <input type="hidden" name="absence_stats_course_types" id="absence_stats_course_types" value="{}">
                <input type="hidden" name="absence_stats_evaluation_details" id="absence_stats_evaluation_details"
                    value="[]">
            </div>


            <div class="form-group">
                <label for="absence_reason">Motif de l'absence :</label>
                <select id="absence_reason" name="absence_reason" onchange="toggleCustomReason()" required>
                    <option value="">-- Sélectionnez un motif --</option>
                    <option value="maladie" <?php echo ($editData['absence_reason'] === 'illness') ? 'selected' : ''; ?>>
                        Maladie</option>
                    <option value="deces" <?php echo ($editData['absence_reason'] === 'death') ? 'selected' : ''; ?>>Décès
                        dans la famille</option>
                    <option value="obligations_familiales" <?php echo ($editData['absence_reason'] === 'family_obligations') ? 'selected' : ''; ?>>Obligations
                        familiales</option>
                    <option value="rdv_medical" <?php echo ($editData['absence_reason'] === 'other' && strpos(strtolower($editData['other_reason'] ?? ''), 'médical') !== false) ? 'selected' : ''; ?>>
                        Rendez-vous médical</option>
                    <option value="convocation_officielle" <?php echo ($editData['absence_reason'] === 'other' && strpos(strtolower($editData['other_reason'] ?? ''), 'convocation') !== false) ? 'selected' : ''; ?>>Convocation officielle (permis, TOIC, etc.)</option>
                    <option value="transport" <?php echo ($editData['absence_reason'] === 'other' && strpos(strtolower($editData['other_reason'] ?? ''), 'transport') !== false) ? 'selected' : ''; ?>>Problème de transport</option>
                    <option value="autre" <?php echo ($editData['absence_reason'] === 'other' && empty($editData['other_reason'])) ? 'selected' : ''; ?>>Autre (préciser)</option>
                </select>
            </div>

            <div class="form-group" id="custom_reason"
                style="<?php echo ($editData['absence_reason'] === 'other' && !empty($editData['other_reason'])) ? 'display: block;' : 'display: none;'; ?>">
                <label for="other_reason">Précisez le motif :</label>
                <input type="text" id="other_reason" name="other_reason"
                    placeholder="Veuillez préciser votre motif d'absence"
                    value="<?php echo htmlspecialchars($editData['other_reason']); ?>">
            </div>

            <!-- Gestion des fichiers justificatifs (existants et nouveaux) -->
            <div class="form-group">
                <label>Fichiers justificatifs actuels :</label>
                <?php
                // Récupération et affichage des fichiers déjà téléchargés
                $existingFiles = $editData['existing_files'] ?? [];
                if (!empty($existingFiles)): ?>
                    <div id="existing-files-list" style="margin-bottom: 15px;">
                        <?php foreach ($existingFiles as $index => $file): ?>
                            <div class="existing-file-item" data-file-size="<?php echo $file['size'] ?? 0; ?>"
                                style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 8px;">
                                <span style="font-size: 24px;">
                                    <?php
                                    $ext = strtolower(pathinfo($file['original_name'] ?? '', PATHINFO_EXTENSION));
                                    $icons = ['pdf' => '📄', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'doc' => '📝', 'docx' => '📝'];
                                    echo $icons[$ext] ?? '📎';
                                    ?>
                                </span>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 500; word-break: break-all; font-size: 14px;">
                                        <?php echo htmlspecialchars($file['original_name'] ?? $file['saved_name'] ?? 'Fichier ' . ($index + 1)); ?>
                                    </div>
                                    <?php if (!empty($file['size'])): ?>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo number_format($file['size'] / 1024, 1); ?> Ko
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <a href="../../Presenter/view_upload_proof.php?proof_id=<?php echo $editData['proof_id']; ?>&file_index=<?php echo $index; ?>"
                                    target="_blank"
                                    style="padding: 6px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; white-space: nowrap;">
                                    Voir
                                </a>
                                <label
                                    style="display: flex; align-items: center; gap: 5px; cursor: pointer; margin: 0; padding: 6px 12px; background: #dc3545; color: white; border-radius: 4px; font-size: 13px; white-space: nowrap;">
                                    <input type="checkbox" name="delete_files[]" value="<?php echo $index; ?>"
                                        onchange="toggleDeleteExistingFile(this, <?php echo $index; ?>)"
                                        style="margin: 0; cursor: pointer;">
                                    <span>Supprimer</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666; font-style: italic; margin-bottom: 10px;">Aucun fichier actuellement</p>
                <?php endif; ?>

                <div
                    style="margin-top: 15px; padding: 12px; background: #e7f3ff; border: 1px solid #0066cc; border-radius: 5px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #0066cc;">
                        📎 Ajouter de nouveaux fichiers :
                    </label>
                    <button type="button" onclick="addNewFiles()"
                        style="padding: 8px 16px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                        ➕ Sélectionner des fichiers
                    </button>
                    <p class="help-text" style="margin-top: 8px; margin-bottom: 0; font-size: 13px;">
                        Max 5MB par fichier, 20MB au total - Formats : PDF, JPG, PNG, DOC, DOCX, GIF
                    </p>
                </div>

                <div id="new-files-container" style="display: none;"></div>

                <div id="file_size_warning"
                    style="display: none; color: #721c24; font-weight: bold; margin-top: 10px; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                </div>

                <div id="files-summary"></div>
            </div>

            <div class="form-group">
                <label for="comments">Commentaires (facultatif) :</label>
                <textarea id="comments" name="comments" rows="4" cols="50"
                    placeholder="Ajoutez des informations complémentaires si nécessaire..."><?php echo htmlspecialchars($editData['comments']); ?></textarea>
            </div>

            <div class="form-group">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="submit-btn">Mettre à jour le justificatif</button>
                    <a href="student_absences.php" class="submit-btn" style="background-color: #6c757d;">Annuler</a>
                </div>
            </div>
        </form>
    </main>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>