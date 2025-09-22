<?php
session_start();
$_SESSION['justificatif_data'] = array(
    'datetime_debut' => $_POST['datetime_debut'] ?? '',
    'datetime_fin' => $_POST['datetime_fin'] ?? '',
    'cours_concernes' => $_POST['cours_concernes'] ?? array(),
    'motif_absence' => $_POST['motif_absence'] ?? '',
    'motif_autre' => $_POST['motif_autre'] ?? '',
    'fichier_justificatif' => $_FILES['fichier_justificatif']['name'] ?? '',
    'commentaires' => $_POST['commentaires'] ?? '',
    'date_soumission' => date('Y-m-d H:i:s')
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/logoIUT.ico">
    <title>Validé</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #27ae60;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #27ae60;
            margin-bottom: 20px;
        }
        
        ul {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        li {
            margin-bottom: 8px;
        }
        
        .pdf-download {
            text-align: center;
            margin: 30px 0;
        }
        
        .btn-pdf {
            background-color: #e74c3c;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn-pdf:hover {
            background-color: #c0392b;
            text-decoration: none;
            color: white;
        }

    </style>
</head>
<body>
    <div class="container">
        <h1>Votre justificatif a été validé</h1>
        
        <div class="success-message">
            <strong>Succès !</strong> Votre demande de justificatif d'absence a été enregistrée avec succès.
            Un email vous a été envoyé récapitulant les informations de votre justificatif.
        </div>
        
        <div class="pdf-download">
            <a href="generer_pdf.php" class="btn-pdf" target="_blank">
                Télécharger le récapitulatif PDF
            </a>
        </div>
        
        <h3>Récapitulatif de votre demande :</h3>
        <ul>
            <li><strong>Date et heure de début :</strong> 
                <?php 
                $datetime_debut = $_SESSION['justificatif_data']['datetime_debut'];
                echo date('d/m/Y à H:i', strtotime($datetime_debut));
                ?>
            </li>
            <li><strong>Date et heure de fin :</strong> 
                <?php 
                $datetime_fin = $_SESSION['justificatif_data']['datetime_fin'];
                echo date('d/m/Y à H:i', strtotime($datetime_fin));
                ?>
            </li>
            <li><strong>Cours concerné(s) :</strong> 
                <?php 
                $cours = $_SESSION['justificatif_data']['cours_concernes'];
                if (is_array($cours)) {
                    echo htmlspecialchars(implode(', ', $cours));
                } else {
                    echo htmlspecialchars($cours);
                }
                ?>
            </li>
            <li><strong>Motif de l'absence :</strong> <?php echo htmlspecialchars($_SESSION['justificatif_data']['motif_absence']); ?></li>
            <?php if (!empty($_SESSION['justificatif_data']['motif_autre'])): ?>
                <li><strong>Précision du motif :</strong> <?php echo htmlspecialchars($_SESSION['justificatif_data']['motif_autre']); ?></li>
            <?php endif; ?>
            <li><strong>Fichier justificatif :</strong> <?php echo htmlspecialchars($_SESSION['justificatif_data']['fichier_justificatif']); ?></li>
            <?php if (!empty($_SESSION['justificatif_data']['commentaires'])): ?>
                <li><strong>Commentaires :</strong> <?php echo nl2br(htmlspecialchars($_SESSION['justificatif_data']['commentaires'])); ?></li>
            <?php endif; ?>
            <li><strong>Date de soumission :</strong> <?php echo htmlspecialchars($_SESSION['justificatif_data']['date_soumission']); ?></li>
        </ul>
        
        <div style="margin-top: 30px; text-align: center; color: #6c757d;">
            <p><em>Conservez ce récapitulatif pour vos archives.</em></p>
        </div>
    </div>
</body>
</html>