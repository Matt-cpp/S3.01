<?php
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../Presenter/LesEvaluations.php';
require_once __DIR__ . '/../../Presenter/tableRatrapage.php';
// ID du professeur from session
$teacherId = $user['id'];
$table = new LesEvaluations($teacherId);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Tableau des Evaluations</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="<?php echo __DIR__ . '/../assets/css/teacher_evals.css'; ?>">
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <main class="main-content">
</body>
</html>