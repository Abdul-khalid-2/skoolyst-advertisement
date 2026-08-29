<?php

/**
 * Reset test database (Section 13.g).
 *
 * Standalone script — same pattern as migrate.php and
 * rollup-ad-stats-daily.php — but deliberately separate from
 * migrate.php rather than adding a --env flag to it: this one is
 * destructive by design (drops every table first) and only ever
 * loads .env.testing, never .env, so there's no way to point it at
 * the dev database by passing the wrong argument.
 *
 * Drops and recreates every table by running each migration's `up`
 * SQL against the test database fresh — not migrate.php's
 * already-applied/pending tracking, since a disposable test DB has no
 * history to track between runs, only a clean slate every time.
 *
 * Usage:
 *   php database/scripts/reset-test-db.php
 */

require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../core/Database.php';

use Core\Database;
use Core\Env;

$envTestingPath = __DIR__ . '/../../.env.testing';

if (!is_file($envTestingPath)) {
    fwrite(STDERR, "Missing .env.testing — copy .env.testing.example to .env.testing first.\n");
    exit(1);
}

Env::load($envTestingPath);

$dbName = getenv('DB_NAME') ?: '';
if (!str_contains(strtolower($dbName), 'test')) {
    fwrite(STDERR, "DB_NAME=\"{$dbName}\" doesn't contain \"test\" — refusing to run against what might be a real database.\n");
    exit(1);
}

$connection = Database::connection();

// Drop in reverse-dependency order so foreign keys never block a
// DROP TABLE — simplest to just disable the checks for this one
// destructive script rather than hand-maintain the exact order.
$connection->exec('SET FOREIGN_KEY_CHECKS = 0');

// Database::connection() sets PDO::ATTR_DEFAULT_FETCH_MODE to
// FETCH_ASSOC connection-wide (core/Database.php), so a plain
// fetchAll() here would key each row by its column name
// ("Tables_in_skoolyst_ads_test"), not index 0 — array_column() would
// then silently return nothing to drop. FETCH_COLUMN explicitly
// avoids that.
$tables = $connection->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $connection->exec("DROP TABLE IF EXISTS `{$table}`");
}

$connection->exec('SET FOREIGN_KEY_CHECKS = 1');

$files = glob(__DIR__ . '/../migrations/*.php') ?: [];
$names = array_map('basename', $files);
sort($names, SORT_STRING);

foreach ($names as $filename) {
    $migration = require __DIR__ . '/../migrations/' . $filename;
    $connection->exec($migration['up']);
    echo "  Created tables from {$filename}\n";
}

echo "Test database \"{$dbName}\" reset — " . count($names) . " migrations applied fresh.\n";
