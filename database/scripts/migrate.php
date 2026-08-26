<?php

/**
 * Migration runner
 *
 * Section 10.a. Standalone CLI script — same pattern as
 * database/scripts/rollup-ad-stats-daily.php: this is the entry
 * point a human (or the deploy script, later) calls; the migration
 * files themselves (database/migrations/000N_*.php) hold the actual
 * `up`/`down` SQL, same as AdStatsRepository holds the rollup's SQL.
 *
 * Tracks which migrations have already run in a `migrations` table
 * (created on first use), so re-running this script only applies
 * whatever is new — safe to run again after adding a migration,
 * never re-runs one that already succeeded.
 *
 * Usage:
 *   php database/scripts/migrate.php            # apply every pending migration
 *   php database/scripts/migrate.php --status    # list applied vs pending, don't run anything
 *   php database/scripts/migrate.php --rollback  # undo only the most recently applied migration
 */

require __DIR__ . '/../../core/Database.php';

use Core\Database;

/**
 * Ensures the tracking table exists. Not itself a numbered migration —
 * bootstrapping the thing that tracks migrations can't depend on the
 * thing it tracks.
 */
function ensureMigrationsTableExists(): void
{
    Database::connection()->exec(
        <<<SQL
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(191) NOT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `migrations_migration_unique` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL
    );
}

/** @return string[] Migration filenames already recorded as applied, oldest first. */
function appliedMigrations(): array
{
    return array_column(
        Database::query('SELECT migration FROM migrations ORDER BY id ASC')->fetchAll(),
        'migration'
    );
}

/** @return string[] Every migration filename on disk, sorted — the numeric prefix (0001, 0002, ...) is the order. */
function allMigrationFiles(): array
{
    $files = glob(__DIR__ . '/../migrations/*.php') ?: [];
    $names = array_map('basename', $files);
    sort($names, SORT_STRING);

    return $names;
}

function runMigration(string $filename): void
{
    $migration = require __DIR__ . '/../migrations/' . $filename;

    Database::connection()->exec($migration['up']);

    Database::query(
        'INSERT INTO migrations (migration) VALUES (:migration)',
        ['migration' => $filename]
    );

    echo "  Applied {$filename}\n";
}

function rollbackMigration(string $filename): void
{
    $migration = require __DIR__ . '/../migrations/' . $filename;

    Database::connection()->exec($migration['down']);

    Database::query('DELETE FROM migrations WHERE migration = :migration', ['migration' => $filename]);

    echo "  Rolled back {$filename}\n";
}

$mode = $argv[1] ?? null;

ensureMigrationsTableExists();

$applied = appliedMigrations();
$onDisk = allMigrationFiles();
$pending = array_values(array_diff($onDisk, $applied));

if ($mode === '--status') {
    echo "Applied (" . count($applied) . "):\n";
    foreach ($applied as $name) {
        echo "  [x] {$name}\n";
    }
    echo "Pending (" . count($pending) . "):\n";
    foreach ($pending as $name) {
        echo "  [ ] {$name}\n";
    }
    exit(0);
}

if ($mode === '--rollback') {
    if ($applied === []) {
        echo "Nothing to roll back.\n";
        exit(0);
    }

    $last = end($applied);

    try {
        rollbackMigration($last);
    } catch (\Throwable $e) {
        fwrite(STDERR, "Rollback failed on {$last}: {$e->getMessage()}\n");
        exit(1);
    }

    exit(0);
}

if ($pending === []) {
    echo "Nothing to migrate — already up to date (" . count($applied) . " applied).\n";
    exit(0);
}

echo "Running " . count($pending) . " pending migration(s):\n";

foreach ($pending as $filename) {
    try {
        runMigration($filename);
    } catch (\Throwable $e) {
        fwrite(STDERR, "Migration failed on {$filename}: {$e->getMessage()}\n");
        fwrite(STDERR, "Stopped — fix the error above and re-run; already-applied migrations were not touched.\n");
        exit(1);
    }
}

echo "Done.\n";
