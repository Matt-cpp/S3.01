<?php

/**
 * Allows easy retrieval of environment variables from a .env file
 */
class EnvLoader
{
    private static $loaded = false;
    private static $variables = [];

    // Loads the .env file
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return; // Already loaded
        }

        $envFile = $path ?? __DIR__ . '/../.env';

        if (!file_exists($envFile)) {
            throw new Exception(".env file not found in: " . dirname($envFile));
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignore comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse variables KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                $value = trim($value, '"\'');

                // Store in $_ENV, $_SERVER and our local array
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                self::$variables[$key] = $value;

                // Define as constant if not already defined
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }

        self::$loaded = true;
    }

    // Retrieves an environment variable
    public static function get(string $key, $default = null)
    {
        self::load(); // Ensure .env is loaded

        // Search in different sources
        return $_ENV[$key] ?? $_SERVER[$key] ?? self::$variables[$key] ?? $default;
    }

    // Checks if a variable exists
    public static function has(string $key): bool
    {
        self::load();
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || isset(self::$variables[$key]);
    }

    // Retrieves all loaded variables
    public static function all(): array
    {
        self::load();
        return self::$variables;
    }
}

// Function to quickly get an environment variable
function env(string $key, $default = null)
{
    return EnvLoader::get($key, $default);
}

// Automatically load .env on first call
EnvLoader::load();

?>