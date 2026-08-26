<?php

/**
 * Database connection config (Section 9.h).
 *
 * Single source of truth for connection settings — core/Database.php
 * reads this file instead of calling getenv() directly, so every
 * consumer (migrations, seeders, the app itself) resolves the same
 * values the same way.
 *
 * Values come from environment variables (set in the server's real
 * environment, or via .env.example once Section 11 adds it); the
 * fallbacks below are dev-only defaults, never production credentials.
 */

return [
    'driver'  => getenv('DB_DRIVER') ?: 'mysql',
    'host'    => getenv('DB_HOST') ?: '127.0.0.1',
    'port'    => getenv('DB_PORT') ?: '3306',
    'name'    => getenv('DB_NAME') ?: '',
    'user'    => getenv('DB_USER') ?: '',
    'pass'    => getenv('DB_PASS') ?: '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];
