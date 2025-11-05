<!DOCTYPE html>
<html lang="fr">
<?php
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('student');

// Use the authenticated user's ID
if (!isset($_SESSION['id_student'])) {
    $_SESSION['id_student'] = $user['id'];
}

// Vérifier si on a bien les données de modification
if (!isset($_SESSION['edit_proof'])) {
    $_SESSION['error_message'] = "Aucun justificatif à modifier.";
    header('Location: student_absences.php');
    exit();
}

$editData = $_SESSION['edit_proof'];
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
    <title>Modification Justificatif Élève</title>

    <link rel="stylesheet" href="../assets/css/student_proof_submit.css">
    <script>
        // Pass PHP session data to JavaScript
        window.studentId = <?php echo $_SESSION['id_student'] ?? 1; ?>;
        window.isEditing = true;
        window.editProofId = <?php echo $editData['proof_id'] ?? 'null'; ?>;
    </script>
    <script src="../assets/js/student_proof_submit.js"></script>

</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>

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

        <?php if (!empty($editData['manager_comment'])): ?>
            <div class="manager-comment-section"
                style="background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <h3 style="color: #856404; margin-top: 0;">💬 Commentaire du responsable :</h3>
                <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($editData['manager_comment'])); ?>
                </p>
            </div>
        <?php endif; ?>

        <form action="../../Presenter/student_proof_update.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="proof_id" value="<?php echo htmlspecialchars($editData['proof_id']); ?>">

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

            <div class="form-group">
                <label for="proof_file">Fichier justificatif :</label>
                <?php if (!empty($editData['existing_file_path'])): ?>
                    <div
                        style="margin-bottom: 10px; padding: 10px; background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px;">
                        <strong>📎 Fichier actuel :</strong>
                        <a href="../../<?php echo htmlspecialchars($editData['existing_file_path']); ?>" target="_blank"
                            style="color: #0056b3;">
                            <?php echo htmlspecialchars(basename($editData['existing_file_path'])); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <input type="file" id="proof_file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.gif">
                <p class="help-text">Laissez vide pour conserver le fichier actuel</p>
                <p class="help-text" style="color: #e36153ff; font-weight: bold; margin-top: 8px;">
                    <strong>ATTENTION :</strong> Taille maximale autorisée : <strong>5MB</strong><br>
                    Formats acceptés : PDF (recommandé), JPG, JPEG, PNG, DOC, DOCX
                </p>
                <div id="file_size_warning"
                    style="display: none; color: #e74c3c; font-weight: bold; margin-top: 5px; padding: 8px; background-color: #ffe6e6; border: 1px solid #e74c3c; border-radius: 4px;">
                    Fichier trop volumineux ! Veuillez sélectionner un fichier de moins de 5MB.
                </div>
            </div>

            <div class="form-group">
                <label for="comments">Commentaires (facultatif) :</label>
                <textarea id="comments" name="comments" rows="4" cols="50"
                    placeholder="Ajoutez des informations complémentaires si nécessaire..."><?php echo htmlspecialchars($editData['comments']); ?></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="submit-btn">Mettre à jour le justificatif</button>
                <a href="student_absences.php" class="submit-btn"
                    style="background-color: #6c757d; text-decoration: none; display: inline-block; text-align: center; margin-left: 10px;">Annuler</a>
            </div>
        </form>
    </main>
</body>

</html>