<?php

declare(strict_types=1);
require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../../Presenter/teacher/makeup_presenter.php';
require_once __DIR__ . '/../../../Model/format_ressource.php';

// Teacher ID from session
$teacherId = $user['id'];
$planif = new MakeupSchedulingPresenter($teacherId);

// Retrieve exams to make up
$exams = $planif->getExams();

// Retrieve all existing rooms
$rooms = $planif->getAllRooms();

$message = '';
$messageType = '';

$isAjaxRequest = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $planif->scheduleMakeups(
        (int) ($_POST['matiere'] ?? 0),
        $_POST['date'] ?? null,
        $_POST['duree'] ?? null,
        $_POST['salle_id'] ?? null,
        trim($_POST['new_salle'] ?? ''),
        $_POST['comment'] ?? null
    );

    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';

    if ($result['success']) {
        $exams = $planif->getExams();
        $rooms = $planif->getAllRooms();
    }

    if ($isAjaxRequest) {
        http_response_code($result['success'] ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
            'count' => $result['count'] ?? 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
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
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
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
                        <?php if (empty($exams)): ?>
                            <option value="" disabled>Aucun DS à rattraper</option>
                        <?php else: ?>
                            <?php foreach ($exams as $ds):
                                $students = $planif->getStudents($ds['id']);
                                $nbEleves = count($students);
                                $elevesNames = array_map(function ($e) {
                                    return $e['first_name'] . ' ' . $e['last_name'];
                                }, $students);
                                $elevesJson = htmlspecialchars(json_encode($elevesNames), ENT_QUOTES, 'UTF-8');
                                ?>
                                <option value="<?php echo htmlspecialchars((string) $ds['id']); ?>"
                                    data-date="<?php echo htmlspecialchars($ds['course_date']); ?>"
                                    data-time="<?php echo htmlspecialchars(substr((string)$ds['start_time'], 0, 5)); ?>"
                                    data-resource="<?php echo htmlspecialchars(formatResourceLabel($ds['resource_label'] ?? $ds['resource_code'] ?? 'Non défini')); ?>"
                                    data-group="<?php echo htmlspecialchars($ds['group_code'] ?? ''); ?>"
                                    data-students="<?php echo $elevesJson; ?>" data-count="<?php echo $nbEleves; ?>">
                                    <?php echo htmlspecialchars(formatResourceLabel($ds['resource_label'] ?? $ds['resource_code'] ?? 'DS')); ?>
                                    - <?php echo htmlspecialchars($ds['course_date']); ?>
                                    &agrave; <?php echo htmlspecialchars(substr($ds['start_time'], 0, 5)); ?>
                                    (<?php echo $nbEleves; ?> &eacute;tudiant<?php echo $nbEleves > 1 ? 's' : ''; ?>)
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
                                <option value="<?php echo htmlspecialchars((string) $room['id']); ?>">
                                    <?php echo htmlspecialchars($room['code']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new">Ajouter une nouvelle salle...</option>
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

        // Confirmation avant validation du rattrapage
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', async function (e) {
                    const matiere = document.getElementById('matiere').value;
                    const date = document.getElementById('date').value;
                    const duree = document.getElementById('duree').value;
                    const submitButton = form.querySelector('button[type="submit"]');
                    const existingMessage = document.querySelector('.message');

                    if (!matiere || !date || !duree) {
                        return;
                    }

                    if (!confirm('Êtes-vous sûr de vouloir planifier ce rattrapage ?')) {
                        e.preventDefault();
                        return;
                    }

                    e.preventDefault();
                    submitButton.disabled = true;
                    const originalLabel = submitButton.textContent;
                    submitButton.textContent = 'Planification en cours...';

                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: new FormData(form),
                            credentials: 'same-origin'
                        });

                        const payload = await response.json();

                        if (existingMessage) {
                            existingMessage.remove();
                        }

                        const messageNode = document.createElement('div');
                        messageNode.className = 'message ' + (payload.success ? 'success' : 'error');
                        messageNode.textContent = payload.message || 'Une erreur est survenue.';
                        form.parentNode.insertBefore(messageNode, form);

                        if (payload.success) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 700);
                        }
                    } catch (error) {
                        if (existingMessage) {
                            existingMessage.remove();
                        }
                        const messageNode = document.createElement('div');
                        messageNode.className = 'message error';
                        messageNode.textContent = 'Erreur reseau. Veuillez reessayer.';
                        form.parentNode.insertBefore(messageNode, form);
                    } finally {
                        submitButton.disabled = false;
                        submitButton.textContent = originalLabel;
                    }
                });
            }
        });
    </script>
</body>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

</html>