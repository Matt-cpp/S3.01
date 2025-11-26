<meta charset="UTF-8">
<?php
/**
 * Fichier: teachertable.php
 * 
 * Présentateur du tableau enseignant - Gère l'affichage des absences pour un enseignant spécifique.
 * Fournit des méthodes pour:
 * - Récupérer les absences aux cours d'un enseignant avec pagination
 * - Filtrer par ressource/matière
 * - Générer un tableau HTML avec les informations des étudiants absents
 * - Gérer la navigation entre les pages (5 entrées par page)
 * Permet aux enseignants de suivre les absences dans leurs cours.
 */

class teacherTable
{
    private $page;
    private $db;
    private $userId;
    private $nombrepages;
    private $filtreBool;
    private $filtre;
    //constructeur
    public function __construct(int $id)
    {
        $this->page = 0;
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $this->linkTeacherUser($id);
        $this->nombrepages = $this->getTotalPages();
        $this->filtreBool = false;
        $this->filtre = "";
    }
        private function linkTeacherUser(int $id)
    {
        $query = "SELECT teachers.id as id
        FROM users LEFT JOIN teachers ON teachers.email = users.email
        WHERE users.id = " . $id; 
        $result = $this->db->select($query);
        return $result[0]['id'];
    }
    // calcule le nombre de pages totales du tableau
    public function getTotalPages()
    {
        try {
            if ($this->filtreBool == false) {
                $query = "SELECT COUNT(*) as count 
                FROM absences LEFT JOIN course_slots 
                ON absences.course_slot_id = course_slots.id
                WHERE course_slots.teacher_id = " . intval($this->userId);
            } else {
                $query = "SELECT COUNT(*) as count 
                FROM absences LEFT JOIN course_slots 
                ON absences.course_slot_id = course_slots.id
                LEFT JOIN resources ON course_slots.resource_id = resources.id
                WHERE course_slots.teacher_id = " . intval($this->userId) . "
                AND resources.label = '" . addslashes($this->filtre) . "'";
            }

            $result = $this->db->select($query);
            if (empty($result)) {
                return 1;
            }
            return ceil($result[0]['count'] / 5);
        } catch (Exception $e) {
            echo "ERREUR dans getTotalPages: " . $e->getMessage();
            return 1;
        }
    }
    // renvoie le nombre de pages totales sans refaire de requete
    public function getNombrePages()
    {
        return $this->nombrepages;
    }
    // renvoie le numéro de la page actuelle
    public function getPage()
    {
        return $this->page;
    }
    //reqeuete principale du tableau
    public function getData($page)
    {
        $offset = (int) ($page * 5);
        $userId = intval($this->userId);
        if ($this->filtreBool == true) {
            $query = "SELECT users.first_name, users.last_name, COALESCE(users.degrees,'N/A') as degrees, course_slots.course_date, absences.status, resources.label
            FROM absences 
            LEFT JOIN users ON absences.student_identifier = users.identifier
            LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
            LEFT JOIN resources ON course_slots.resource_id = resources.id
            WHERE course_slots.teacher_id = " . $userId . "
            AND resources.label = '" . addslashes($this->filtre) . "'
            ORDER BY course_slots.course_date DESC
            LIMIT 5 OFFSET " . $offset;
        } else {
            $query = "SELECT users.first_name, users.last_name, COALESCE(users.degrees,'N/A') as degrees, course_slots.course_date, absences.status, resources.label
            FROM absences 
            LEFT JOIN users ON absences.student_identifier = users.identifier
            LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
            LEFT JOIN resources ON course_slots.resource_id = resources.id
            WHERE course_slots.teacher_id = " . $userId . "
            ORDER BY course_slots.course_date DESC
            LIMIT 5 OFFSET " . $offset;
        }

        return $this->db->select($query);
    }
    public function setPage($page)
    {
        if ($page >= 0 && $page < $this->nombrepages) {
            $this->page = $page;
        }
    }
    // fait avancer la page de 1 si possible
    public function nextPage()
    {
        if ($this->page < $this->nombrepages - 1) {
            $this->page++;
        }
    }
    // fait reculer la page de 1 si possible
    public function previousPage()
    {
        if ($this->page > 0) {
            $this->page--;
        }
    }
    //renvoie le numéro de page actuel
    public function getCurrentPage()
    {
        return $this->page;
    }
    //permet l'accès a la page suivante et précédente en posant des limites
    public function getNextPage()
    {
        return min($this->page + 1, $this->nombrepages - 1);
    }
    public function getPreviousPage()
    {
        return max($this->page - 1, 0);
    }

    // Tableau
    public function laTable()
    {
        // Récupération des données brutes
        $donnees = $this->getData($this->getCurrentPage());
        $tableau = [];
        // Construction du tableau HTML
        foreach ($donnees as $ligne) {
            $tableau[] = "<tr>
            <td>" . htmlspecialchars($ligne['first_name']) . "</td>
            <td>" . htmlspecialchars($ligne['last_name']) . "</td>
            <td>" . htmlspecialchars($ligne['degrees']) . "</td>
            <td>" . htmlspecialchars($ligne['label']) . "</td>
            <td>" . htmlspecialchars($ligne['course_date']) . "</td>
            <td>" . htmlspecialchars($ligne['status']) . "</td>
            </tr>";
        }
        return "<table border='1'>  
        <tr>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Degrees</th>
        <th>Resource Label</th>
        <th>Course Date</th>
        <th>Status</th>
        </tr>" . implode("", $tableau) . "</table>";
    }
    public function activerUnFiltre($nom)
    {
        $this->filtreBool = true;
        $this->filtre = $nom;
        $this->nombrepages = $this->getTotalPages();
        $this->page = 0;
    }
    public function desactiverUnFiltre()
    {
        $this->filtreBool = false;
        $this->filtre = "";
        $this->nombrepages = $this->getTotalPages();
        $this->page = 0;
    }
    public function getRessourcesLabels()
    {
        $query = "SELECT DISTINCT resources.label
        From course_slots 
        Left Join resources ON course_slots.resource_id = resources.id
        WHERE course_slots.teacher_id=" . $this->userId;
        $result = $this->db->select($query);
        $labels = [];
        foreach ($result as $ligne) {
            $labels[] = $ligne['label'];
        }
        return $labels;
    }
}

