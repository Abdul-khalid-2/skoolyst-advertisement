<?php

/**
 * Migration runner (Section 10.a).
 *
 * No such runner existed yet — database/migrations/*.php were plain
 * up/down arrays with nothing to apply them in order or track what's
 * already run. This is that missing piece: a `migrations` bookkeeping
 * table (created on first run) records which migration files have
 * been applied, so re-running this script only applies new ones —
 * safe to run repeatedly, including against a database that already
 * has some migrations applied.
 *
 * Usage:
 *   php database/migrate.php            # apply all pending migrations
 *   php database/migrate.php --fresh    # drop all tables, then apply every migration from zero
 *   php database/migrate.php --status   # list applied vs pending, don't run anything
 */

require __DIR__ . '/../core/Database.php';

use Core\Database;

$args = array_slice($argv, 1);
$fresh = in_array('--fresh', $args, true);
$statusOnly = in_array('--status', $args, true);

$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.php');
sort($files); // filenames are zero-padded (0001_, 0002_, ...), so lexical sort is chronological order.

if (empty($files)) {
    fwrite(STDERR, "No migration files found in {$migrationsDir}\n");
    exit(1);
}

$pdo = Database::connection();

// Bookkeeping table — created here rather than as migration 0000 so
// this script works even against a database that predates it.
$pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(191) NOT NULL UNIQUE,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);

if ($fresh) {
    echo "==> --fresh: dropping all tables first\n";

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        echo "    dropped {$table}\n";
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Recreate the bookkeeping table itself — it was just dropped too.
    $pdo->exec(<<<SQL
        CREATE TABLE `migrations` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(191) NOT NULL UNIQUE,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL);
}

$applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$appliedSet = array_flip($applied);

if ($statusOnly) {
    foreach ($files as $file) {
        $name = basename($file);
        echo (isset($appliedSet[$name]) ? '[applied] ' : '[pending] ') . $name . "\n";
    }
    exit(0);
}

$ranCount = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (isset($appliedSet[$name])) {
        continue;
    }

    $migration = require $file;

    if (!isset($migration['up'])) {
        fwrite(STDERR, "Skipping {$name}: no 'up' key.\n");
        continue;
    }

    echo "==> Applying {$name}\n";

    try {
        $pdo->exec($migration['up']);
        $pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)')
            ->execute(['migration' => $name]);
        $ranCount++;
    } catch (\PDOException $e) {
        fwrite(STDERR, "Migration {$name} failed: {$e->getMessage()}\n");
        exit(1);
    }
}

echo $ranCount > 0
    ? "==> Done. {$ranCount} migration(s) applied.\n"
    : "==> Nothing to do — already up to date.\n";
