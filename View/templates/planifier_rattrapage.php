<?php
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../Presenter/planificationRattrapage.php';

// ID du professeur from session
$teacherId = $user['id'];
$planif = new planificationRattrapage($teacherId);

// Récupérer les DS à rattraper
$lesDs = $planif->getLesDs();


$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dsId = $_POST['matiere'] ?? null;
    $date = $_POST['date'] ?? null;
    $heure = $_POST['heure'] ?? null;
    $duree = $_POST['duree'] ?? null;
    $salle = $_POST['salle'] ?? null;

    if ($dsId && $date && $heure && $duree) {
        $lesEleves = $planif->getLesEleves($dsId);

        $count = 0;
        foreach ($lesEleves as $eleve) {
            $planif->insererRattrapage(
                $eleve['id'],
                $dsId,
                $eleve['identifier'],
                $date,
                $salle,
                intval($duree)
            );
            $count++;
        }

        $message = "Rattrapage planifié avec succès pour {$count} étudiant(s) !";
        $messageType = 'success';

        $lesDs = $planif->getLesDs();
    } else {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../assets/css/planifier rattrapage.css">
    <style>
        <?php include __DIR__ . '/../assets/css/planifier rattrapage.css'; ?>
    </style>
    <title>Planifier un rattrapage</title>
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="main-content">
        <h1 class="page-title">Planifier un rattrapage</h1>

        <div class="section">
            <h2 class="section-title">Informations du rattrapage</h2>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <div class="form-group">
                    <label for="matiere">DS à rattraper <span class="required">*</span></label>
                    <select id="matiere" name="matiere" required
                        onchange="this.form.querySelector('.form-help').textContent = this.options[this.selectedIndex].dataset.info || '';">
                        <option value="">Sélectionnez un DS</option>
                        <?php if (empty($lesDs)): ?>
                            <option value="" disabled>Aucun DS à rattraper</option>
                        <?php else: ?>
                            <?php foreach ($lesDs as $ds):
                                $eleves = $planif->getLesEleves($ds['id']);
                                $nbEleves = count($eleves);
                                ?>
                                <option value="<?php echo htmlspecialchars($ds['id']); ?>"
                                    data-info="<?php echo $nbEleves; ?> étudiant(s) à rattraper">
                                    DS du <?php echo htmlspecialchars($ds['course_date']); ?> - ID:
                                    <?php echo htmlspecialchars($ds['id']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="form-help">Sélectionnez le DS concerné</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Date du rattrapage <span class="required">*</span></label>
                        <input type="date" id="date" name="date" required>
                        <div class="form-help">Sélectionnez une date future</div>
                    </div>

                    <div class="form-group">
                        <label for="salle">Salle <span class="required">*</span></label>
                        <input type="text" id="salle" name="salle" placeholder="Ex: PUM110" required>
                        <div class="form-help">Entrez le code de la salle</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="duree">Durée <span class="required">*</span></label>
                        <select id="duree" name="duree" required>
                            <option value="">Sélectionnez une durée</option>
                            <option value="30">30 minutes</option>
                            <option value="60">1 heure</option>
                            <option value="90">1h30</option>
                            <option value="120">2 heures</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Planifier le rattrapage</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="team-section">
                <h3 class="team-title">Équipe de développement</h3>
                <div class="team-names">
                    <p>CIPOLAT Matteo • BOLTZ Louis • NAVREZ Louis • COLLARD Yony • BISIAUX Ambroise • FOURNIER
                        Alexandre</p>
                </div>
            </div>
            <div class="footer-info">
                <p>&copy; 2025 UPHF - Système de gestion des absences</p>
            </div>
        </div>
    </footer>
    <?php renderThemeScript(); ?>
</body>

</html>