<?php
require_once __DIR__ . '/../../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../../Presenter/teacher/makeup_presenter.php';
require_once __DIR__ . '/../../../Model/database.php';

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
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../../assets/css/teacher/planifier_rattrapage.css">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <style>
        <?php include __DIR__ . '/../../assets/css/teacher/planifier_rattrapage.css'; ?>
    </style>
    <title>Planifier un rattrapage</title>
</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>

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
                    <select id="matiere" name="matiere" required onchange="updateDsInfo(this)">
                        <option value="">Sélectionnez un DS</option>
                        <?php if (empty($lesDs)): ?>
                            <option value="" disabled>Aucun DS à rattraper</option>
                        <?php else: ?>
                            <?php foreach ($lesDs as $ds):
                                $eleves = $planif->getLesEleves($ds['id']);
                                $nbEleves = count($eleves);
                                $elevesNames = array_map(function ($e) {
                                    return $e['first_name'] . ' ' . $e['last_name'];
                                }, $eleves);
                                $elevesJson = htmlspecialchars(json_encode($elevesNames), ENT_QUOTES, 'UTF-8');
                                ?>
                                <option value="<?php echo htmlspecialchars($ds['id']); ?>"
                                    data-date="<?php echo htmlspecialchars($ds['course_date']); ?>"
                                    data-time="<?php echo htmlspecialchars($ds['start_time']); ?>"
                                    data-resource="<?php echo htmlspecialchars($ds['resource_label'] ?? $ds['resource_code'] ?? 'Non défini'); ?>"
                                    data-group="<?php echo htmlspecialchars($ds['group_code'] ?? ''); ?>"
                                    data-students="<?php echo $elevesJson; ?>" data-count="<?php echo $nbEleves; ?>">
                                    <?php echo htmlspecialchars($ds['resource_label'] ?? $ds['resource_code'] ?? 'DS'); ?>
                                    - <?php echo htmlspecialchars($ds['course_date']); ?>
                                    à <?php echo htmlspecialchars(substr($ds['start_time'], 0, 5)); ?>
                                    (<?php echo $nbEleves; ?> étudiant<?php echo $nbEleves > 1 ? 's' : ''; ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="form-help" id="ds-help">Sélectionnez le DS concerné</div>
                </div>

                <!-- DS Info Panel -->
                <div id="ds-info-panel" class="ds-info-panel" style="display: none;">
                    <h4>Informations du DS sélectionné</h4>
                    <div class="ds-info-grid">
                        <div class="ds-info-item">
                            <span class="ds-info-label">Ressource:</span>
                            <span id="ds-resource" class="ds-info-value">-</span>
                        </div>
                        <div class="ds-info-item">
                            <span class="ds-info-label">Date:</span>
                            <span id="ds-date" class="ds-info-value">-</span>
                        </div>
                        <div class="ds-info-item">
                            <span class="ds-info-label">Heure:</span>
                            <span id="ds-time" class="ds-info-value">-</span>
                        </div>
                        <div class="ds-info-item">
                            <span class="ds-info-label">Groupe:</span>
                            <span id="ds-group" class="ds-info-value">-</span>
                        </div>
                    </div>
                    <div class="ds-students-section">
                        <span class="ds-info-label">Étudiants à rattraper:</span>
                        <ul id="ds-students-list" class="ds-students-list"></ul>
                    </div>
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

        function updateDsInfo(select) {
            const panel = document.getElementById('ds-info-panel');
            const option = select.options[select.selectedIndex];

            if (!option.value) {
                panel.style.display = 'none';
                document.getElementById('ds-help').textContent = 'Sélectionnez le DS concerné';
                return;
            }

            // Update info panel
            document.getElementById('ds-resource').textContent = option.dataset.resource || '-';
            document.getElementById('ds-date').textContent = option.dataset.date || '-';
            document.getElementById('ds-time').textContent = option.dataset.time ? option.dataset.time.substring(0, 5) : '-';
            document.getElementById('ds-group').textContent = option.dataset.group || 'Non défini';

            // Update students list
            const studentsList = document.getElementById('ds-students-list');
            studentsList.innerHTML = '';

            try {
                const students = JSON.parse(option.dataset.students || '[]');
                if (students.length > 0) {
                    students.forEach(student => {
                        const li = document.createElement('li');
                        li.textContent = student;
                        studentsList.appendChild(li);
                    });
                } else {
                    const li = document.createElement('li');
                    li.textContent = 'Aucun étudiant';
                    studentsList.appendChild(li);
                }
            } catch (e) {
                const li = document.createElement('li');
                li.textContent = 'Erreur de chargement';
                studentsList.appendChild(li);
            }

            // Update help text
            document.getElementById('ds-help').textContent = option.dataset.count + ' étudiant(s) à rattraper';

            panel.style.display = 'block';
        }
    </script>
</body>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

</html>