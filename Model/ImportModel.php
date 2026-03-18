<?php

declare(strict_types=1);

/**
 * ImportModel - Manages CSV import jobs and import action history.
 * Used by the secretary dashboard and CSV import API endpoints.
 */

require_once __DIR__ . '/database.php';

class ImportModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? getDatabase();
    }

    // Retrieve the current status of an import job by ID
    public function getJobStatus(string $jobId): ?array
    {
        return $this->db->selectOne(
            'SELECT id, status, total_rows, processed_rows, message, updated_at
             FROM import_jobs
             WHERE id = :id',
            [':id' => $jobId]
        );
    }

    // Update an import job's fields (e.g. status, processed_rows, message)
    public function updateJobProgress(string $jobId, array $data): void
    {
        $setParts = [];
        $params = [':id' => $jobId];
        foreach ($data as $key => $value) {
            $setParts[] = "$key = :$key";
            $params[":$key"] = $value;
        }
        $this->db->execute(
            'UPDATE import_jobs SET ' . implode(', ', $setParts) . ' WHERE id = :id',
            $params
        );
    }

    // Create a new import job row
    public function createJob(string $id, string $filename, string $filepath, int $totalRows): void
    {
        $this->db->execute(
            "INSERT INTO import_jobs (id, filename, filepath, status, total_rows)
             VALUES (:id, :filename, :filepath, 'pending', :total_rows)",
            [
                ':id' => $id,
                ':filename' => $filename,
                ':filepath' => $filepath,
                ':total_rows' => $totalRows,
            ]
        );
    }

    // Update total rows for a running import
    public function updateTotalRows(string $jobId, int $totalRows): void
    {
        $this->db->execute(
            "UPDATE import_jobs SET total_rows = :total WHERE id = :id",
            [':total' => $totalRows, ':id' => $jobId]
        );
    }

    // Update job status using a dedicated PDO connection so progress is visible during long transactions
    public function updateJobStatusImmediate(string $jobId, string $status, ?int $processedRows = null, ?string $message = null): void
    {
        $updates = ["status = :status", "updated_at = NOW()"];
        $params = [':id' => $jobId, ':status' => $status];

        if ($processedRows !== null) {
            $updates[] = "processed_rows = :processed_rows";
            $params[':processed_rows'] = $processedRows;
        }

        if ($message !== null) {
            $updates[] = "message = :message";
            $params[':message'] = $message;
        }

        $sql = "UPDATE import_jobs SET " . implode(', ', $updates) . " WHERE id = :id";

        static $progressPdo = null;
        if ($progressPdo === null) {
            require_once __DIR__ . '/env.php';
            $host = env('DB_HOST', 'localhost');
            $port = env('DB_PORT', '5432');
            $dbname = env('DB_NAME', 'database');
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};options='--client_encoding=UTF8'";
            $progressPdo = new PDO($dsn, env('DB_USER', 'user'), env('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        $stmt = $progressPdo->prepare($sql);
        $stmt->execute($params);
    }

    // Log an action to the import_history table
    public function logAction(string $action, string $details, string $status = 'success'): void
    {
        $sql = 'INSERT INTO import_history (action_type, description, status, created_at)
                VALUES (:action, :details, :status, NOW())';
        try {
            $this->db->execute($sql, [':action' => $action, ':details' => $details, ':status' => $status]);
        } catch (Exception $e) {
            $this->ensureTable();
            $this->db->execute($sql, [':action' => $action, ':details' => $details, ':status' => $status]);
        }
    }

    // Retrieve recent import history entries
    public function getHistory(int $limit = 50): array
    {
        try {
            return $this->db->select(
                'SELECT action_type as action, description as details, status, created_at
                 FROM import_history
                 ORDER BY created_at DESC
                 LIMIT :limit',
                [':limit' => $limit]
            );
        } catch (Exception $e) {
            $this->ensureTable();
            return [];
        }
    }

    // Create the import_history table if it does not exist yet
    public function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS import_history (
            id SERIAL PRIMARY KEY,
            action_type VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'success',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->getConnection()->exec($sql);
    }
}
