<meta charset="UTF-8">
<?php
class tableRatrapage{
    private $page;
    private $db;
    private $userId;
    private $nombrepages;
    //constructeur
    public function __construct(int $id) {
        $this->page = 0;
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $id;
        $this->nombrepages = $this->getTotalPages();
    }
    
}
