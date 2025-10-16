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
    <?php include __DIR__ . '/student_navbar.php'; ?>

    <h1>Mes Justificatifs</h1>
</body>

</html>