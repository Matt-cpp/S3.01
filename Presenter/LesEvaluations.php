<meta charset="UTF-8">
<?php
class pageEvalProf
{
    private $db;
    private $userId;
    //constructeur
    public function __construct(int $id)
    {
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = this->linkTeacherUser($id);
        
    }
    public function linkTeacherUser(int $id)
    {
        $query = "SELECT teacher.id FROM teachers left JOIN users ON teachers.email=users.email
        WHERE users.id=" . $id . ""; 
        $result = $this->db->select($query);
        return $result[0]['identifier'];
    }
    

    

}