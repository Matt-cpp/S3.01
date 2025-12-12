<?php

/**
 * Fichier: database.php
 * 
 * Modèle de connexion à la base de données - Gère la connexion PostgreSQL.
 * Implémente le pattern Singleton pour assurer une seule instance de connexion.
 * Fournit des méthodes pour exécuter des requêtes SQL (SELECT, INSERT, UPDATE, DELETE).
 * Gère les transactions, les requêtes préparées et la gestion des erreurs.
 * Configure la connexion avec les paramètres du fichier .env.
 */

// Load environment variables
require_once __DIR__ . '/env.php';

class Database
{
    private static $instance = null;
    private $pdo;
    private static $testPdo = null; // For testing: inject PDO connection
    private $transactionDepth = 0; // Track transaction depth for nested transaction support
    private static $inTestMode = false; // Prevent commits/rollbacks in test mode

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
        // If test PDO is set, use it instead of creating new connection
        if (self::$testPdo !== null) {
            $this->pdo = self::$testPdo;
            return;
        }

        // Set timezone for PHP operations to Europe/Paris
        date_default_timezone_set('Europe/Paris');

        try {
            $this->pdo = new PDO($this->getDSN(), $this->getUser(), $this->getPassword(), self::OPTIONS);

            // Set timezone for PostgreSQL to Europe/Paris
            $this->pdo->exec("SET TIME ZONE 'Europe/Paris'");

            // Force UTF-8 encoding for PostgreSQL client connection
            $this->pdo->exec("SET CLIENT_ENCODING TO 'UTF8'");
            $this->pdo->exec("SET NAMES 'UTF8'");
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

    /**
     * Set a test PDO connection for testing purposes
     * This allows tests to inject their transactional PDO
     */
    public static function setTestConnection(?\PDO $pdo): void
    {
        self::$testPdo = $pdo;
        self::$inTestMode = ($pdo !== null);
        self::$instance = null; // Reset instance to use new connection
    }

    /**
     * Reset transaction depth (for testing)
     */
    public function resetTransactionDepth(): void
    {
        $this->transactionDepth = 0;
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
            $result = $stmt->fetch();
            return $result === false ? null : $result;
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
        // If already in a transaction, just increment depth counter
        if ($this->pdo->inTransaction()) {
            $this->transactionDepth++;
            return true;
        }
        $result = $this->pdo->beginTransaction();
        if ($result) {
            $this->transactionDepth = 1;
        }
        return $result;
    }

    // Commits a transaction

    public function commit(): bool
    {
        // In test mode, never actually commit - just decrement depth
        if (self::$inTestMode) {
            if ($this->transactionDepth > 0) {
                $this->transactionDepth--;
            }
            return true;
        }

        // Only commit if we're at the outermost transaction level
        if ($this->transactionDepth > 1) {
            $this->transactionDepth--;
            return true;
        }
        if (!$this->pdo->inTransaction()) {
            $this->transactionDepth = 0;
            return true;
        }
        $result = $this->pdo->commit();
        if ($result) {
            $this->transactionDepth = 0;
        }
        return $result;
    }

    // Rolls back a transaction
    public function rollBack(): bool
    {
        // In test mode, never actually rollback - just decrement depth
        if (self::$inTestMode) {
            if ($this->transactionDepth > 0) {
                $this->transactionDepth--;
            }
            return true;
        }

        // Only rollback if we're at the outermost transaction level
        if ($this->transactionDepth > 1) {
            $this->transactionDepth--;
            return true;
        }
        if (!$this->pdo->inTransaction()) {
            $this->transactionDepth = 0;
            return true;
        }
        $result = $this->pdo->rollBack();
        if ($result) {
            $this->transactionDepth = 0;
        }
        return $result;
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
