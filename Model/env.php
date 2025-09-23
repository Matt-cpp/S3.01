<?php

/**
 * Permet de récupérer les variables d'environnement depuis un fichier .env facilement
 */
class EnvLoader
{
    private static $loaded = false;
    private static $variables = [];

    // Charge le fichier .env
    public static function load(string $path = null): void
    {
        if (self::$loaded) {
            return; // Déjà chargé
        }

        $envFile = $path ?? __DIR__ . '/../.env';

        if (!file_exists($envFile)) {
            throw new Exception("Fichier .env non trouvé dans : " . dirname($envFile));
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parser les variables KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Supprimer les guillemets si présents
                $value = trim($value, '"\'');

                // Stocker dans $_ENV, $_SERVER et notre tableau local
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                self::$variables[$key] = $value;

                // Définir comme constante si pas déjà définie
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }

        self::$loaded = true;
    }

    // Récupère une variable d'environnement
    public static function get(string $key, $default = null)
    {
        self::load(); // S'assurer que le .env est chargé

        // Chercher dans différentes sources
        return $_ENV[$key] ?? $_SERVER[$key] ?? self::$variables[$key] ?? $default;
    }

    // Vérifie si une variable existe
    public static function has(string $key): bool
    {
        self::load();
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || isset(self::$variables[$key]);
    }

    //Récupère toutes les variables chargées
    public static function all(): array
    {
        self::load();
        return self::$variables;
    }
}

// Fonction pour récupérer rapidement une variable d'environnement
function env(string $key, $default = null)
{
    return EnvLoader::get($key, $default);
}

// Charger automatiquement le .env au premier appel
EnvLoader::load();

?>