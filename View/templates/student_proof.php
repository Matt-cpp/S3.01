<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
    <title>Soumission Justificatif Élève</title>

    <link rel="stylesheet" href="../assets/css/student-proof.css">
    <script src="../assets/js/student-proof.js"></script>

</head>
<body>
    <div class="logos-container">
        <img src="../img/UPHF.png" alt="Logo UPHF" class="logo" width="220" height="80">
        <img src="../img/logoIUT.png" alt="Logo IUT" class="logo" width="100" height="90">
    </div>

    <h1>Absence</h1>

    <form action="../templates/validation_student_proof.php" method="post" enctype="multipart/form-data">
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
            <p> à compléter </p>
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
            <input type="text" id="other_reason" name="other_reason" placeholder="Veuillez préciser votre motif d'absence">
        </div>
        
        <div class="form-group">
            <label for="proof_reason">Fichier justificatif :</label>
            <input type="file" id="proof_reason" name="proof_reason" 
                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
            <p class="help-text">Formats acceptés : PDF, images (JPG, PNG), documents Word. Taille maximale : 5MB</p>
        </div>
        
        <div class="form-group">
            <label for="comments">comments (facultatif) :</label>
            <textarea id="comments" name="comments" rows="4" cols="50" 
                      placeholder="Ajoutez des informations complémentaires si nécessaire..."></textarea>
        </div>
        
        <div class="form-group">
            <button type="submit" class="submit-btn">Soumettre le justificatif</button>
        </div>
        
        <!-- Note : ne pas oublié de rajouter la classe de l'élève et sa formation-->

    </form>
</body>
</html>