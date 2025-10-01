<?php 
require_once 'Model/database.php';

function getName() {
    $pdo = getDatabase();
    $result = $pdo->select("SELECT first_name, last_name FROM users");
    $name = !empty($result) ? $result[0] : null;
    return $name ? $name['first_name'] . ' ' . $name['last_name'] : '';
}

function translateMotif($motif) {
    $translations = [
        'illness' => 'Maladie',
        'death' => 'Décès',
        'family' => 'Famille',
        'medical' => 'Médical',
        'transport' => 'Transport',
        'personal' => 'Personnel'
    ];
    
    return isset($translations[$motif]) ? $translations[$motif] : ($motif ?: '');
}

function translateStatus($justified) {
    return $justified ? 'Justifiée' : 'Non justifiée';
}

    class AbsenceHistoryManager {
    private $db;
        
    public function __construct() {
        $this->db = getDatabase();
    }
    

    public function getAllAbsences($filters = []) {
        $query = "
            SELECT DISTINCT ON (a.id)
                a.id as absence_id,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.identifier as student_identifier,
                COALESCE(r.label, 'Non spécifié') as course,
                cs.course_date as date,
                cs.start_time::text as start_time,
                cs.end_time::text as end_time,
                cs.course_type,
                a.justified as status,
                p.main_reason as motif,
                p.file_path as file_path
            FROM absences a
            JOIN users u ON a.student_identifier = u.identifier
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            WHERE 1=1
        ";
        
        $params = [];
        $conditions = [];
            
            if (!empty($filters['name'])) {
                $conditions[] = "(u.first_name ILIKE :name OR u.last_name ILIKE :name)";
                $params[':name'] = '%' . $filters['name'] . '%';
            }
            
            if (!empty($filters['start_date'])) {
                $conditions[] = "cs.course_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $conditions[] = "cs.course_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'justifiée') {
                $conditions[] = "a.justified = true";
            } elseif ($filters['status'] === 'non_justifiée') {
                $conditions[] = "a.justified = false";
            }
        }
        
        if (!empty($filters['course_type'])) {
            $conditions[] = "cs.course_type = :course_type";
            $params[':course_type'] = $filters['course_type'];
        }
        
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }
        $query .= " ORDER BY a.id, cs.course_date DESC, cs.start_time DESC";
        
        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des absences: " . $e->getMessage());
            return [];
        }
    }
    

    public function getCourseTypes() {
        $query = "SELECT DISTINCT course_type FROM course_slots WHERE course_type IS NOT NULL ORDER BY course_type";
        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des types de cours: " . $e->getMessage());
            return [];
        }
    }
}

$historyManager = new AbsenceHistoryManager();
$filters = [];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['firstDateFilter']) && !empty($_POST['lastDateFilter'])) {
        if ($_POST['firstDateFilter'] > $_POST['lastDateFilter']) {
            $errorMessage = "La première date doit être antérieure à la deuxième date.";
        }
    }
    
    if (empty($errorMessage)) {
        $filters = [
            'name' => $_POST['nameFilter'] ?? '',
            'start_date' => $_POST['firstDateFilter'] ?? '',
            'end_date' => $_POST['lastDateFilter'] ?? '',
            'status' => $_POST['statusFilter'] ?? '',
            'course_type' => $_POST['courseTypeFilter'] ?? ''
        ];
    }
}

$absences = $historyManager->getAllAbsences($filters);
$courseTypes = $historyManager->getCourseTypes();

function is_there_proof($absence) {
    if ($absence['motif'] != null) {
        return true;
    } else {
        return false;
    }
}

function get_proof($absence) {
    if (is_there_proof($absence) && isset($absence['file_path'])){
        return $absence['file_path'] ?? '';
    }
    else {
        return '';
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style_historique.css">
    <title>Historique des absences</title>
</head>
<body>
    <header class="header">
        <div class="logo">
            <img id="logo" src="View/img/logoIUT.ico" alt="Logo IUT"/>
        </div>
        <h1>Historique des absences</h1>
        <div class="header-icons">
            <button class="btn">
                <img src="View/img/bell.png" alt="Notifications" class="btn-icon">
            </button>
            <button class="btn">
                <img src="View/img/settings.png" alt="Paramètres" class="btn-icon">
            </button>
            <button class="btn">
                <img src="View/img/profil.png" alt="Profil" class="btn-icon">
            </button>
        </div>
    </header>
    <main>
        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="historique.php">
            <div class="filter-grid">
                <input type="text" name="nameFilter" id="nameFilter" placeholder="Rechercher par nom..." 
                    value="<?php echo htmlspecialchars($filters['name'] ?? ''); ?>">
                <input type="date" name="firstDateFilter" id="firstDateFilter" 
                    value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                <input type="date" name="lastDateFilter" id="lastDateFilter" 
                    value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                <select name="statusFilter" id="statusFilter">
                    <option value="">Tous les statuts</option>
                    <option value="justifiée" <?php echo (($filters['status'] ?? '') === 'justifiée') ? 'selected' : ''; ?>>Justifiée</option>
                    <option value="non_justifiée" <?php echo (($filters['status'] ?? '') === 'non_justifiée') ? 'selected' : ''; ?>>Non Justifiée</option>
                </select>
                <select name="courseTypeFilter" id="courseTypeFilter">
                    <option value="">Tous les types</option>
                    <?php foreach ($courseTypes as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['course_type']); ?>" 
                                <?php echo (($filters['course_type'] ?? '') === $type['course_type']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['course_type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="button-container">
                <button type="submit" id="filterButton">
                    Filtrer
                </button>
                <a href="historique.php" class="reset-link">
                    Réinitialiser
                </a>
            </div>
        </form>

        <div class="results-counter">
            <strong>Nombre d'absences trouvées: <?php echo count($absences); ?></strong>
        </div>

        <table id="absenceTable">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Cours</th>
                    <th>Date</th>
                    <th>Horaire</th>
                    <th>Type</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th>Preuve</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($absences)): ?>
                    <tr>
                        <td colspan="7" class="no-results">
                            Aucune absence trouvée avec les critères sélectionnés.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($absences as $absence): ?>
                        <tr class="<?php echo $absence['status'] ? 'status-justified' : 'status-unjustified'; ?>">
                            <td><?php echo htmlspecialchars($absence['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($absence['course']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($absence['date']))); ?></td>
                            <td><?php echo htmlspecialchars($absence['start_time'] . ' - ' . $absence['end_time']); ?></td>
                            <td><?php echo htmlspecialchars($absence['course_type'] ?? 'Non spécifié'); ?></td>
                            <td><?php echo htmlspecialchars(translateMotif($absence['motif'])); ?></td>
                            <td>
                                <?php if ($absence['status']): ?>
                                    <span class="badge badge-success">Justifiée</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Non justifiée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (is_there_proof($absence)): ?>
                                    <button onclick="window.open('<?php echo htmlspecialchars(get_proof($absence)); ?>', '_blank')" class="btn_export               ">
                                        <img src="View/img/export.png" alt="export-icon" class="export">
                                    </button>
                                <?php else: ?>
                                    <span class="no-proof"></span>
                                    <?php endif; ?>
                                </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>