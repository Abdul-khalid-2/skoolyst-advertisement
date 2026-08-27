<?php

namespace Core;

/**
 * Env
 *
 * Minimal, dependency-free .env loader (Section 11).
 *
 * No composer.json/vendor exists yet (Section 11.e/f are still
 * unstarted), so this avoids pulling in vlucas/phpdotenv just to
 * unblock local setup. Reads KEY=VALUE lines from a .env file and
 * exposes them via getenv()/$_ENV/$_SERVER, the same functions
 * config/database.php and config/app.php already call — no changes
 * needed there. Safe to call more than once; only loads once.
 *
 * Usage (top of any entry point, before config/*.php is read):
 *   require __DIR__ . '/../core/Env.php';
 *   Core\Env::load(__DIR__ . '/../.env');
 */
class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            // No .env present (e.g. real env vars set another way,
            // such as production server config) — not fatal.
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and blank lines.
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip matching surrounding quotes, e.g. DB_PASS="p@ss word".
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[-1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Don't override a real environment variable that's already
            // set (e.g. by the OS/webserver) — .env is a local-dev
            // convenience, not the source of truth once one exists.
            if (getenv($key) !== false) {
                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
