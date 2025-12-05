<?php
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../Presenter/planificationRattrapage.php';
require_once __DIR__ . '/../../Model/database.php';

// ID du professeur from session
$teacherId = $user['id'];
$planif = new planificationRattrapage($teacherId);
$db = Database::getInstance();

// Récupérer les DS à rattraper
$lesDs = $planif->getLesDs();

// Récupérer toutes les salles existantes
$rooms = $db->select("SELECT id, code FROM rooms ORDER BY code ASC");

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dsId = $_POST['matiere'] ?? null;
    $date = $_POST['date'] ?? null;
    $duree = $_POST['duree'] ?? null;
    $salleId = $_POST['salle_id'] ?? null;
    $newSalleCode = trim($_POST['new_salle'] ?? '');
    $comment = $_POST['comment'] ?? null;

    // Determine room ID
    $roomId = null;
    if ($salleId && $salleId !== 'new') {
        // Use existing room
        $roomId = intval($salleId);
    } elseif ($newSalleCode) {
        // Check if room already exists
        $existingRoom = $db->selectOne("SELECT id FROM rooms WHERE code = :code", [':code' => $newSalleCode]);
        if ($existingRoom) {
            $roomId = $existingRoom['id'];
        } else {
            // Create new room
            $db->execute("INSERT INTO rooms (code) VALUES (:code)", [':code' => $newSalleCode]);
            $roomId = $db->lastInsertId();
            // Refresh rooms list
            $rooms = $db->select("SELECT id, code FROM rooms ORDER BY code ASC");
        }
    }

    if ($dsId && $date && $duree) {
        $lesEleves = $planif->getLesEleves($dsId);

        $count = 0;
        foreach ($lesEleves as $eleve) {
            $planif->insererRattrapage(
                $eleve['id'],
                $dsId,
                $eleve['identifier'],
                $date,
                $roomId,
                intval($duree),
                $comment
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
                                // Format: Groupe — Matière — Date — Heure
                                $group = $ds['group_code'] ?? '';
                                $resource = $ds['resource_label'] ?? '';
                                $date = $ds['course_date'];
                                $time = $ds['start_time'];
                                $displayParts = [];
                                if ($group) $displayParts[] = htmlspecialchars($group);
                                if ($resource) $displayParts[] = htmlspecialchars($resource);
                                if ($date) $displayParts[] = htmlspecialchars($date);
                                if ($time) $displayParts[] = substr($time, 0, 5); // HH:MM
                                $displayLabel = implode(' — ', $displayParts) ?: ('DS #' . $ds['id']);
                                // Add student names
                                $studentNames = [];
                                foreach ($eleves as $eleve) {
                                    $studentNames[] = htmlspecialchars($eleve['first_name'] . ' ' . $eleve['last_name']);
                                }
                                $studentsList = implode(', ', $studentNames);
                                $fullDisplay = $displayLabel . ($studentsList ? ' (' . $studentsList . ')' : '');
                                ?>
                                <option value="<?php echo htmlspecialchars($ds['id']); ?>"
                                    data-info="<?php echo $nbEleves; ?> étudiant(s) à rattraper: <?php echo $studentsList; ?>">
                                    <?php echo $fullDisplay; ?>
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
                        <label for="salle_id">Salle</label>
                        <select id="salle_id" name="salle_id" onchange="toggleNewRoomInput(this)">
                            <option value="">-- Aucune salle --</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo htmlspecialchars($room['id']); ?>">
                                    <?php echo htmlspecialchars($room['code']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new">➕ Ajouter une nouvelle salle...</option>
                        </select>
                        <div class="form-help">Sélectionnez une salle existante ou ajoutez-en une nouvelle</div>
                    </div>
                </div>

                <div class="form-group" id="new-room-group" style="display: none;">
                    <label for="new_salle">Nouvelle salle</label>
                    <input type="text" id="new_salle" name="new_salle" placeholder="Ex: PUM110">
                    <div class="form-help">Entrez le code de la nouvelle salle</div>
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
                </div>

                <div class="form-group">
                    <label for="comment">Commentaire</label>
                    <textarea id="comment" name="comment" rows="3"
                        placeholder="Informations complémentaires pour l'étudiant (optionnel)"></textarea>
                    <div class="form-help">Ce commentaire sera inclus dans l'email envoyé à l'étudiant</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Planifier le rattrapage</button>
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
    <script>
        function toggleNewRoomInput(select) {
            const newRoomGroup = document.getElementById('new-room-group');
            const newRoomInput = document.getElementById('new_salle');

            if (select.value === 'new') {
                newRoomGroup.style.display = 'block';
                newRoomInput.focus();
            } else {
                newRoomGroup.style.display = 'none';
                newRoomInput.value = '';
            }
        }
    </script>
</body>

</html>