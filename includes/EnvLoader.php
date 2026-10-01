<?php
// includes/EnvLoader.php
// Chargeur de variables d'environnement depuis un fichier .env

class EnvLoader
{
    private static array $variables = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($path)) {
            throw new RuntimeException("Fichier .env introuvable : {$path}");
        }

        if (!is_readable($path)) {
            throw new RuntimeException("Fichier .env non lisible : {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            throw new RuntimeException("Impossible de lire le fichier .env : {$path}");
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Supprimer les guillemets
            if (strlen($value) >= 2) {
                $firstChar = $value[0];
                $lastChar = $value[strlen($value) - 1];
                if (($firstChar === '"' && $lastChar === '"') || 
                    ($firstChar === "'" && $lastChar === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Convertir les booléens et null
            $lowerValue = strtolower($value);
            if ($lowerValue === 'true') $value = true;
            elseif ($lowerValue === 'false') $value = false;
            elseif ($lowerValue === 'null') $value = null;

            self::$variables[$key] = $value;

            if (!getenv($key)) {
                putenv("{$key}={$value}");
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false) {
            $lowerValue = strtolower($value);
            if ($lowerValue === 'true') return true;
            if ($lowerValue === 'false') return false;
            if ($lowerValue === 'null') return null;
            return $value;
        }

        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        return $default;
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'production') === 'production';
    }

    public static function isDevelopment(): bool
    {
        return self::get('APP_ENV', 'production') === 'development';
    }

    public static function validateRequired(array $requiredKeys): array
    {
        $missing = [];
        foreach ($requiredKeys as $key) {
            $value = self::get($key);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }
        return $missing;
    }
}