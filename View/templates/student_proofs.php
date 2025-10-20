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
    <title>Mes Justificatifs</title>

    <link rel="stylesheet" href="../assets/css/student_proof_submit.css">
</head>

<body>
    <?php 
    include __DIR__ . '/student_navbar.php';
    require_once __DIR__ . '/../../Presenter/session_cache.php';
    require_once __DIR__ . '/../../Presenter/student_get_info.php';

    // Utiliser les données en session si disponibles et récentes (défini dans session_cache.php), par défaut 20 minutes
    // sinon les récupérer de la BD
    if (!isset($_SESSION['stats']) || !isset($_SESSION['proofsByCategory']) || !isset($_SESSION['recentAbsences']) || shouldRefreshCache(1200)) {
        $_SESSION['stats'] = getAbsenceStatistics($_SESSION['id_student']);
        $_SESSION['proofsByCategory'] = getProofsByCategory($_SESSION['id_student']);
        $_SESSION['recentAbsences'] = getRecentAbsences($_SESSION['id_student'], 5);
        updateCacheTimestamp();
    }
    
    $stats = $_SESSION['stats'];
    $proofsByCategory = $_SESSION['proofsByCategory'];
    $recentAbsences = $_SESSION['recentAbsences'];
    ?>

    <h1>Mes Justificatifs</h1>
</body>

</html>