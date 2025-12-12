<?php

namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base TestCase class for all tests
 * Provides common functionality like database transactions, helpers, and assertions
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected static ?\PDO $pdo = null;
    protected bool $inTransaction = false;

    /**
     * Set up test database connection (once for all tests)
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$pdo === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_PORT') ?: '5432',
                getenv('DB_NAME') ?: 'test_absence_db'
            );

            self::$pdo = new \PDO(
                $dsn,
                getenv('DB_USER') ?: 'postgres',
                getenv('DB_PASSWORD') ?: 'postgres',
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );

            // Set timezone for PostgreSQL connection
            self::$pdo->exec("SET timezone = 'Europe/Paris'");

            // Inject test PDO into Database singleton so models use the same connection
            require_once __DIR__ . '/../Model/database.php';
            \Database::setTestConnection(self::$pdo);
        }
    }

    /**
     * Begin transaction before each test to isolate changes
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (self::$pdo && !$this->inTransaction) {
            self::$pdo->beginTransaction();
            $this->inTransaction = true;

            // Initialize Database singleton's transaction depth to 1
            // since we started a transaction directly on PDO
            $db = \Database::getInstance();
            $db->resetTransactionDepth();
            // Use reflection to set transactionDepth to 1
            $reflection = new \ReflectionClass($db);
            $property = $reflection->getProperty('transactionDepth');
            $property->setAccessible(true);
            $property->setValue($db, 1);
        }
    }

    /**
     * Rollback transaction after each test to clean up
     */
    protected function tearDown(): void
    {
        if (self::$pdo && $this->inTransaction) {
            // Force rollback regardless of transaction depth
            try {
                if (self::$pdo->inTransaction()) {
                    self::$pdo->rollBack();
                }
            } catch (\PDOException $e) {
                // Ignore errors if transaction was already closed
            }
            $this->inTransaction = false;

            // Reset transaction depth in Database singleton for next test
            $db = \Database::getInstance();
            $db->resetTransactionDepth();
        }

        parent::tearDown();
    }

    /**
     * Get PDO connection for direct database queries in tests
     */
    protected function getConnection(): \PDO
    {
        return self::$pdo;
    }

    /**
     * Get a Database wrapper for the test PDO connection
     * This allows models to work with the same transactional connection
     */
    protected function getTestDatabase(): object
    {
        // Create an anonymous class that mimics Database but uses the test PDO
        return new class (self::$pdo) {
            private \PDO $pdo;

            public function __construct(\PDO $pdo)
            {
                $this->pdo = $pdo;
            }

            public function getConnection(): \PDO
            {
                return $this->pdo;
            }

            public function select(string $sql, array $params = []): array
            {
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                } catch (\PDOException $e) {
                    error_log("Error executing SELECT: " . $e->getMessage());
                    throw new \Exception("Error retrieving data");
                }
            }

            public function selectOne(string $sql, array $params = [])
            {
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetch();
                    return $result === false ? null : $result;
                } catch (\PDOException $e) {
                    error_log("Error executing SELECT: " . $e->getMessage());
                    throw new \Exception("Error retrieving data");
                }
            }

            public function execute(string $sql, array $params = []): int
            {
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    return $stmt->rowCount();
                } catch (\PDOException $e) {
                    error_log("Error executing query: " . $e->getMessage());
                    throw new \Exception("Error executing statement");
                }
            }

            public function insert(string $sql, array $params = []): ?int
            {
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    $lastId = $this->pdo->lastInsertId();
                    return $lastId !== false ? (int) $lastId : null;
                } catch (\PDOException $e) {
                    error_log("Error executing INSERT: " . $e->getMessage());
                    throw new \Exception("Error inserting data");
                }
            }

            public function beginTransaction(): bool
            {
                return $this->pdo->beginTransaction();
            }

            public function commit(): bool
            {
                return $this->pdo->commit();
            }

            public function rollback(): bool
            {
                return $this->pdo->rollBack();
            }

            public function inTransaction(): bool
            {
                return $this->pdo->inTransaction();
            }
        };
    }

    /**
     * Execute a SQL query and return results
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a SQL query and return single row
     */
    protected function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute a SQL query without returning results
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get the last inserted ID
     */
    protected function lastInsertId(string $sequence = null): int
    {
        return (int) self::$pdo->lastInsertId($sequence);
    }

    // =========================================================================
    // Custom Assertions
    // =========================================================================

    /**
     * Assert that a database table has a specific number of rows
     */
    protected function assertTableCount(string $table, int $expectedCount, string $message = ''): void
    {
        $result = $this->queryOne("SELECT COUNT(*) as cnt FROM {$table}");
        $actualCount = (int) $result['cnt'];

        $this->assertEquals(
            $expectedCount,
            $actualCount,
            $message ?: "Table '{$table}' should have {$expectedCount} rows, but has {$actualCount}"
        );
    }

    /**
     * Assert that a record exists in database
     */
    protected function assertRecordExists(string $table, array $conditions, string $message = ''): void
    {
        $where = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $where[] = "{$column} = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE " . implode(' AND ', $where);
        $result = $this->queryOne($sql, $params);

        $this->assertGreaterThan(
            0,
            (int) $result['cnt'],
            $message ?: "Record with conditions " . json_encode($conditions) . " should exist in table '{$table}'"
        );
    }

    /**
     * Assert that a record does not exist in database
     */
    protected function assertRecordNotExists(string $table, array $conditions, string $message = ''): void
    {
        $where = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $where[] = "{$column} = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE " . implode(' AND ', $where);
        $result = $this->queryOne($sql, $params);

        $this->assertEquals(
            0,
            (int) $result['cnt'],
            $message ?: "Record with conditions " . json_encode($conditions) . " should NOT exist in table '{$table}'"
        );
    }

    /**
     * Assert that an absence has a specific status
     */
    protected function assertAbsenceStatus(int $absenceId, string $expectedStatus, string $message = ''): void
    {
        $result = $this->queryOne('SELECT status FROM absences WHERE id = ?', [$absenceId]);

        $this->assertNotNull($result, "Absence with ID {$absenceId} should exist");
        $this->assertEquals(
            $expectedStatus,
            $result['status'],
            $message ?: "Absence {$absenceId} should have status '{$expectedStatus}'"
        );
    }

    /**
     * Assert that an absence is justified
     */
    protected function assertAbsenceJustified(int $absenceId, bool $shouldBeJustified = true, string $message = ''): void
    {
        $result = $this->queryOne('SELECT justified FROM absences WHERE id = ?', [$absenceId]);

        $this->assertNotNull($result, "Absence with ID {$absenceId} should exist");
        $this->assertEquals(
            $shouldBeJustified,
            (bool) $result['justified'],
            $message ?: "Absence {$absenceId} should " . ($shouldBeJustified ? 'be' : 'NOT be') . " justified"
        );
    }

    /**
     * Assert that a proof has a specific status
     */
    protected function assertProofStatus(int $proofId, string $expectedStatus, string $message = ''): void
    {
        $result = $this->queryOne('SELECT status FROM proof WHERE id = ?', [$proofId]);

        $this->assertNotNull($result, "Proof with ID {$proofId} should exist");
        $this->assertEquals(
            $expectedStatus,
            $result['status'],
            $message ?: "Proof {$proofId} should have status '{$expectedStatus}'"
        );
    }

    /**
     * Assert that a proof is locked
     */
    protected function assertProofLocked(int $proofId, bool $shouldBeLocked = true, string $message = ''): void
    {
        $result = $this->queryOne('SELECT locked FROM proof WHERE id = ?', [$proofId]);

        $this->assertNotNull($result, "Proof with ID {$proofId} should exist");
        $this->assertEquals(
            $shouldBeLocked,
            (bool) $result['locked'],
            $message ?: "Proof {$proofId} should " . ($shouldBeLocked ? 'be' : 'NOT be') . " locked"
        );
    }

    /**
     * Assert that a decision history record exists
     */
    protected function assertDecisionHistoryExists(int $proofId, string $action, string $message = ''): void
    {
        $result = $this->queryOne(
            'SELECT COUNT(*) as cnt FROM decision_history WHERE justification_id = ? AND action = ?',
            [$proofId, $action]
        );

        $this->assertGreaterThan(
            0,
            (int) $result['cnt'],
            $message ?: "Decision history for proof {$proofId} with action '{$action}' should exist"
        );
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Clean up all test data (useful for integration tests)
     */
    protected function cleanDatabase(): void
    {
        // Order matters due to foreign key constraints
        $tables = [
            'decision_history',
            'proof_absences',
            'proof',
            'makeups',
            'absences',
            'notifications',
            'absence_monitoring',
            'course_slots',
            'user_groups',
            'import_history',
            'import_jobs',
            'email_verifications',
            'rejection_validation_reasons',
            'resources',
            'rooms',
            'teachers',
            'groups',
            'users'
        ];

        foreach ($tables as $table) {
            $this->execute("DELETE FROM {$table}");
        }
    }

    /**
     * Truncate specific tables and reset sequences
     */
    protected function truncateTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->execute("TRUNCATE TABLE {$table} RESTART IDENTITY CASCADE");
        }
    }

    /**
     * Create a test date string in PostgreSQL format
     */
    protected function createDate(string $dateString): string
    {
        return date('Y-m-d', strtotime($dateString));
    }

    /**
     * Create a test datetime string in PostgreSQL format
     */
    protected function createDateTime(string $dateTimeString): string
    {
        return date('Y-m-d H:i:s', strtotime($dateTimeString));
    }

    /**
     * Get current timestamp in PostgreSQL format
     */
    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
