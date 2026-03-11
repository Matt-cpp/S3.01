<?php

declare(strict_types=1);

/**
 * Environment variable manager - Loads and manages variables from the .env file.
 * Allows easy retrieval of configuration parameters (database, email, etc.)
 * without hardcoding them in the application.
 * Implements a Singleton pattern to load the .env file only once.
 * Provides the env() function for easy variable access.
 */

/**
 * Allows easy retrieval of environment variables from a .env file
 */
class EnvLoader
{
    private static bool $loaded = false;
    private static array $variables = [];

    // Load the .env file
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

    // Retrieve an environment variable
    public static function get(string $key, mixed $default = null): mixed
    {
        self::load(); // Ensure .env is loaded

        // Search in different sources
        return $_ENV[$key] ?? $_SERVER[$key] ?? self::$variables[$key] ?? $default;
    }

    // Check if a variable exists
    public static function has(string $key): bool
    {
        self::load();
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || isset(self::$variables[$key]);
    }

    // Retrieve all loaded variables
    public static function all(): array
    {
        self::load();
        return self::$variables;
    }
}

// Quickly get an environment variable
function env(string $key, mixed $default = null): mixed
{
    return EnvLoader::get($key, $default);
}

// Automatically load .env on first inclusion
EnvLoader::load();
