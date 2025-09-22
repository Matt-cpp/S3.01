<?php

// Charger les variables d'environnement
require_once __DIR__ . '/env.php';

class Database
{
    private static $instance = null;
    private $pdo;

    // Paramètres de connexion depuis le fichier .env
    private function getDSN(): string
    {
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '5432');
        $dbname = env('DB_NAME', 'database');

        return "pgsql:host={$host};port={$port};dbname={$dbname};options='--client_encoding=UTF8'";
    }
    private function getUser(): string
    {
        return env('DB_USER', 'user');
    }

    private function getPassword(): string
    {
        return env('DB_PASSWORD', '');
    }

    // Options PDO pour une meilleure sécurité et performance
    private const OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ];

    // Constructeur privé pour le pattern Singleton
    private function __construct()
    {
        try {
            $this->pdo = new PDO($this->getDSN(), $this->getUser(), $this->getPassword(), self::OPTIONS);
        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            throw new Exception("Impossible de se connecter à la base de données");
        }
    }

    //Empêche le clonage de l'instance afin d'éviter plusieurs connexions
    private function __clone()
    {
    }

    //Empêche la désérialisation de l'instance Singleton afin d'éviter plusieurs connexions
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    // Retourne l'instance unique de la classe Database (Singleton)
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Retourne l'objet PDO pour les requêtes
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    //Prépare et exécute une requête SELECT
    public function select(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur lors de l'exécution de SELECT: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des données");
        }
    }

    //Prépare et exécute une requête SELECT pour un seul résultat
    public function selectOne(string $sql, array $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erreur lors de l'exécution de SELECT: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des données");
        }
    }

    // Prépare et exécute une requête INSERT, UPDATE ou DELETE

    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Erreur lors de l'exécution de la requête: " . $e->getMessage());
            throw new Exception("Erreur lors de l'exécution de la requête");
        }
    }

    // Retourne l'ID du dernier élément inséré

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    // Démarre une transaction

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    //Confirme une transaction

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    //Annule une transaction
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    //Vérifie si une transaction est active

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    // Teste la connexion à la base de données
    public function testConnection(): bool
    {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            error_log("Test de connexion échoué: " . $e->getMessage());
            return false;
        }
    }

    // Ferme la connexion à la base de données
    public function closeConnection(): void
    {
        $this->pdo = null;
    }
}

// Fonction utilitaire pour obtenir rapidement l'instance de la base de données

function getDatabase(): Database
{
    return Database::getInstance();
}

// Fonction utilitaire pour obtenir rapidement la connexion PDO

function getConnection(): PDO
{
    return Database::getInstance()->getConnection();
}
