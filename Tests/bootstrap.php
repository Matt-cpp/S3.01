<?php
/**
 * PHPUnit Bootstrap File
 * Initializes the test environment
 */

// Set timezone
date_default_timezone_set('Europe/Paris');

// Require Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Override environment variables for testing BEFORE loading env.php
// This ensures the Database class uses test database settings
$_ENV['DB_HOST'] = getenv('DB_HOST') ?: 'localhost';
$_ENV['DB_PORT'] = getenv('DB_PORT') ?: '5432';
$_ENV['DB_NAME'] = getenv('DB_NAME') ?: 'test_absence_db';
$_ENV['DB_USER'] = getenv('DB_USER') ?: 'postgres';
$_ENV['DB_PASSWORD'] = getenv('DB_PASSWORD') ?: 'postgres';
$_ENV['TESTING'] = 'true';
$_ENV['DISABLE_EMAILS'] = 'true';

// Set environment variables globally
foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
}

// Load env.php to initialize EnvLoader with test values
require_once __DIR__ . '/../Model/env.php';

// Override EnvLoader to use test environment variables
if (class_exists('EnvLoader')) {
    // Force reload with test environment
    $reflection = new ReflectionClass('EnvLoader');
    $loadedProperty = $reflection->getProperty('loaded');
    $loadedProperty->setAccessible(true);
    $loadedProperty->setValue(null, false);
    
    $variablesProperty = $reflection->getProperty('variables');
    $variablesProperty->setAccessible(true);
    $variablesProperty->setValue(null, $_ENV);
    $loadedProperty->setValue(null, true);
}

// Disable error display (log instead)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Function to clean test database before running tests
function resetTestDatabase(): void
{
    try {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_PORT') ?: '5432',
            getenv('DB_NAME') ?: 'test_absence_db'
        );

        $pdo = new PDO(
            $dsn,
            getenv('DB_USER') ?: 'postgres',
            getenv('DB_PASSWORD') ?: 'postgres',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        echo "✓ Test database connection successful\n";

        // Check if schema exists
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'");
        $result = $stmt->fetch();

        if ($result['cnt'] == 0) {
            echo "⚠ Warning: Test database schema not found. Please run the schema setup first.\n";
            echo "  You can create the test database using changelog-db.sql\n";
        } else {
            echo "✓ Test database schema found\n";
        }

    } catch (PDOException $e) {
        echo "✗ Test database connection failed: " . $e->getMessage() . "\n";
        echo "  Make sure your test database is set up correctly.\n";
        echo "  Connection details: " . getenv('DB_HOST') . ':' . getenv('DB_PORT') . '/' . getenv('DB_NAME') . "\n";
    }
}

// Display test environment information
echo "\n";
echo "========================================\n";
echo "  PHPUnit Test Environment\n";
echo "========================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Database: " . getenv('DB_NAME') . "\n";
echo "Host: " . getenv('DB_HOST') . ':' . getenv('DB_PORT') . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "========================================\n";
echo "\n";

// Verify test database connection
resetTestDatabase();

echo "\n";
echo "Starting tests...\n";
echo "\n";
