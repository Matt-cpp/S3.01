<?php

namespace Tests\Fixtures;

/**
 * Helper functions for test fixtures
 */
class FixtureHelper
{
    /**
     * Sanitize data array to ensure proper types for PostgreSQL
     * Converts PHP boolean false/true to PostgreSQL-compatible values
     */
    public static function sanitizeDataForPostgres(array $data, array $booleanColumns = []): array
    {
        foreach ($booleanColumns as $column) {
            if (isset($data[$column])) {
                // Convert to boolean then to int (0 or 1) for PostgreSQL
                $value = $data[$column];
                if ($value === '' || $value === null) {
                    $data[$column] = false;
                } else {
                    $data[$column] = (bool) $value;
                }
            }
        }
        return $data;
    }

    /**
     * Execute prepared statement with proper type binding for booleans
     */
    public static function executeWithBooleans(\PDO $pdo, string $sql, array $data, array $booleanColumns = []): \PDOStatement
    {
        $stmt = $pdo->prepare($sql);

        foreach ($data as $key => $value) {
            $param = is_int($key) ? $key + 1 : ":{$key}";
            if (!str_starts_with($key, ':')) {
                $param = ":{$key}";
            }

            if (in_array($key, $booleanColumns)) {
                // Bind boolean parameters explicitly
                $stmt->bindValue($param, $value === true || $value === 't' || $value === 1, \PDO::PARAM_BOOL);
            } else {
                // Let PDO infer the type
                $stmt->bindValue($param, $value);
            }
        }

        $stmt->execute();
        return $stmt;
    }
}
