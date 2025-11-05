<!DOCTYPE html>
<html lang="fr">
<?php
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('student');

// Use the authenticated user's ID
if (!isset($_SESSION['id_student'])) {
    $_SESSION['id_student'] = $user['id'];
}
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
    <title>Mes Statistiques</title>

    <link rel="stylesheet" href="../assets/css/student_proof_submit.css">
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <h1>Mes Statistiques</h1>
</body>

</html>