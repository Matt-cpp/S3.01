<!DOCTYPE html>
<html lang="fr">
<?php
session_start();
// FIXME Force student ID to 1 POUR LINSTANT
$_SESSION['id_student'] = 1;
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
    <title>Soumission Justificatif Élève</title>

    <link rel="stylesheet" href="../assets/css/student_proof_submit.css">
    <script>
        // Pass PHP session data to JavaScript
        window.studentId = <?php echo $_SESSION['id_student'] ?? 1; ?>;
    </script>
    <script src="../assets/js/student_proof_submit.js"></script>

</head>

<body>
    <?php include __DIR__ . '/student_navbar.php'; ?>

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
            echo '<strong>✅ Succès:</strong> Votre justificatif a été soumis avec succès !';
            echo '</div>';
        }
        ?>

        <h1 class="page-title">Création de justificatif</h1>

        <form action="../../Presenter/student_proof_validation.php" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="datetime_start">Date et heure de début d'absence :</label>
            <input type="datetime-local" id="datetime_start" name="datetime_start" required>
        </div>


        <div class="form-group">
            <label for="datetime_end">Date et heure de fin d'absence :</label>
            <input type="datetime-local" id="datetime_end" name="datetime_end" required>
            <p class="help-text">Sélectionnez la même date si l'absence ne dure qu'une journée</p>
        </div>

        <div class="form-group">
            <label for="class_involved">Cours concerné(s) :</label>
            <div id="courses_loading" style="display: none; color: #666; font-style: italic;">
                Chargement des cours...
            </div>
            <div id="courses_container">
                <p id="courses_placeholder" style="color: #666; font-style: italic;">
                    Sélectionnez les dates de début et fin pour voir les cours concernés
                </p>
                <div id="courses_list" style="display: none;"></div>
            </div>
            <div id="absence_recap" style="display: none;"></div>
            <input type="hidden" name="class_involved" id="class_involved_hidden" value="">
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
                <option value="maladie">Maladie</option>
                <option value="deces">Décès dans la famille</option>
                <option value="obligations_familiales">Obligations familiales</option>
                <option value="rdv_medical">Rendez-vous médical</option>
                <option value="convocation_officielle">Convocation officielle (permis, TOIC, etc.)</option>
                <option value="transport">Problème de transport</option>
                <option value="autre">Autre (préciser)</option>
            </select>
        </div>

        <div class="form-group" id="custom_reason" style="display: none;">
            <label for="other_reason">Précisez le motif :</label>
            <input type="text" id="other_reason" name="other_reason"
                placeholder="Veuillez préciser votre motif d'absence">
        </div>

        <div class="form-group">
            <label for="proof_file">Fichier justificatif :</label>
            <input type="file" id="proof_file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
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
                placeholder="Ajoutez des informations complémentaires si nécessaire..."></textarea>
        </div>

        <div class="form-group">
            <button type="submit" class="submit-btn">Soumettre le justificatif</button>
        </div>
        </form>
    </main>
</body>

</html>