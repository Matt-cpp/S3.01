<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../Img/logoIUT.ico">
    <title>Soumission Justificatif Élève</title>

    <style>
        .logos-container {
            top: 5px;
            left: 10px;
            z-index: 1000;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 70px 20px 20px 20px
            background-color: #f5f5f5;
        }
        
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        
        form {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #34495e;
        }
        
        input[type="date"], input[type="text"], input[type="file"], select, textarea, input[type="datetime-local"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 18px;
            box-sizing: border-box;
        }
        
        input[type="datetime-local"] {
            font-size: 18px;
            padding: 12px;
            height: 30px;
            font-weight: 500;
        }
        
        select[multiple] {
            height: auto;
        }
        
        textarea {
            resize: vertical;
            font-family: inherit;
            font-size: 17px;
            line-height: 1.4;
            padding: 12px;
            min-height: 120px;
        }
        
        .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
            margin-bottom: 0;
        }
        
        .submit-btn {
            background-color: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        
        .submit-btn:hover {
            background-color: #2980b9;
        }
        
        #motif_personnalise {
            margin-top: 10px;
        }

    </style>

    <script>
        function toggleMotifPersonnalise() {
            var select = document.getElementById('motif_absence');
            var divPersonnalise = document.getElementById('motif_personnalise');
            var inputPersonnalise = document.getElementById('motif_autre');
            
            if (select.value === 'autre') {
                divPersonnalise.style.display = 'block';
                inputPersonnalise.required = true;
            } else {
                divPersonnalise.style.display = 'none';
                inputPersonnalise.required = false;
                inputPersonnalise.value = '';
            }
        }

        function validateDates() {
            var dateDebut = document.getElementById('datetime_debut').value;
            var dateFin = document.getElementById('datetime_fin').value;
            var currentDate = new Date();
            
            // Validation de la date de fin seule (48h)
            if (dateFin) {
                var fin = new Date(dateFin);
                var minDate = new Date(currentDate.getTime() - (48 * 60 * 60 * 1000));
                
                if (fin < minDate) {
                    alert('La date de fin ne peut pas être antérieure à plus de 48h.');
                    document.getElementById('datetime_fin').value = '';
                    return false;
                }
            }
            
            // Vérifier que la date de fin est après la date de début
            if (dateDebut && dateFin) {
                var debut = new Date(dateDebut);
                var fin = new Date(dateFin);
                if (fin <= debut) {
                    alert('La date/heure de fin doit être postérieure à la date/heure de début.');
                    document.getElementById('datetime_fin').value = '';
                    return false;
                }
            }
            
            return true;
        }

        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('datetime_debut').addEventListener('change', function() {
                var dateFin = document.getElementById('datetime_fin');
                if (dateFin.value) {
                    validateDates();
                }
                dateFin.min = this.value;
            });
            
            document.getElementById('datetime_fin').addEventListener('change', function() {
                var dateFin = document.getElementById('datetime_fin');
                if (dateFin.value) {
                    validateDates();
                }
            });
            
            // Validation avant soumission du formulaire
            document.querySelector('form').addEventListener('submit', function(e) {
                if (!validateDates()) {
                    e.preventDefault();
                }
            });
        });
    </script>

</head>
<body>
    <div class="logos-container">
        <img src="../Img/UPHF.png" alt="Logo UPHF" class="logo" width="220" height="80">
        <img src="../Img/logoIUT.png" alt="Logo IUT" class="logo" width="100" height="90">
    </div>

    <h1>Absence</h1>

    <form action="../Presenter/validation_justificatif_eleve.php" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="datetime_debut">Date et heure de début d'absence :</label>
            <input type="datetime-local" id="datetime_debut" name="datetime_debut" required>
        </div>
        
        <div class="form-group">
            <label for="datetime_fin">Date et heure de fin d'absence :</label>
            <input type="datetime-local" id="datetime_fin" name="datetime_fin" required>
            <p class="help-text">Sélectionnez la même date si l'absence ne dure qu'une journée</p>
        </div>
        
        <div class="form-group">
            <label for="cours_concernes">Cours concerné(s) :</label>
            <p> à compléter </p>
        </div>
        
        <div class="form-group">
            <label for="motif_absence">Motif de l'absence :</label>
            <select id="motif_absence" name="motif_absence" onchange="toggleMotifPersonnalise()" required>
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
        
        <div class="form-group" id="motif_personnalise" style="display: none;">
            <label for="motif_autre">Précisez le motif :</label>
            <input type="text" id="motif_autre" name="motif_autre" placeholder="Veuillez préciser votre motif d'absence">
        </div>
        
        <div class="form-group">
            <label for="fichier_justificatif">Fichier justificatif :</label>
            <input type="file" id="fichier_justificatif" name="fichier_justificatif" 
                   accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx" required>
            <p class="help-text">Formats acceptés : PDF, images (JPG, PNG, GIF), documents Word. Taille maximale : 5MB</p>
        </div>
        
        <div class="form-group">
            <label for="commentaires">Commentaires (facultatif) :</label>
            <textarea id="commentaires" name="commentaires" rows="4" cols="50" 
                      placeholder="Ajoutez des informations complémentaires si nécessaire..."></textarea>
        </div>
        
        <div class="form-group">
            <button type="submit" class="submit-btn">Soumettre le justificatif</button>
        </div>
        
        <!-- Note : ne pas oublié de rajouter la classe de l'élève et sa formation-->

    </form>
</body>
</html>