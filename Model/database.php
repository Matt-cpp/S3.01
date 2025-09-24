<?php

// Load environment variables
require_once __DIR__ . '/env.php';

class Database
{
    private static $instance = null;
    private $pdo;

    // Connection parameters from .env file
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

    // PDO options for better security and performance
    private const OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ];

    // Private constructor for Singleton pattern
    private function __construct()
    {
        try {
            $this->pdo = new PDO($this->getDSN(), $this->getUser(), $this->getPassword(), self::OPTIONS);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Unable to connect to the database");
        }
    }

    // Prevent cloning the instance to avoid multiple connections
    private function __clone()
    {
    }

    // Prevent unserializing the Singleton instance to avoid multiple connections
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    // Returns the unique instance of the Database class (Singleton)
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Returns the PDO object for queries
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    // Prepares and executes a SELECT query
    public function select(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error executing SELECT: " . $e->getMessage());
            throw new Exception("Error retrieving data");
        }
    }

    // Prepares and executes a SELECT query for a single result
    public function selectOne(string $sql, array $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error executing SELECT: " . $e->getMessage());
            throw new Exception("Error retrieving data");
        }
    }

    // Prepares and executes an INSERT, UPDATE or DELETE query

    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Error executing query: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw new Exception("Error executing query: " . $e->getMessage());
        }
    }

    // Returns the ID of the last inserted item

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    // Starts a transaction

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    // Commits a transaction

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    // Rolls back a transaction
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    // Checks if a transaction is active

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    // Tests the database connection
    public function testConnection(): bool
    {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            error_log("Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    // Closes the database connection
    public function closeConnection(): void
    {
        $this->pdo = null;
    }
}

// Utility function to quickly get the database instance

function getDatabase(): Database
{
    return Database::getInstance();
}

// Utility function to quickly get the PDO connection

function getConnection(): PDO
{
    return Database::getInstance()->getConnection();
}
