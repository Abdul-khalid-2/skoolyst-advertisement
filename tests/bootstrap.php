<?php

/**
 * PHPUnit bootstrap (Section 13.b/13.g).
 *
 * Loads the same runtime the app itself uses (core/Autoload.php,
 * core/Env.php) plus a tiny Tests\ autoloader for this folder — no
 * Composer autoloader required, same dependency-free reasoning as
 * core/Autoload.php/core/Env.php themselves (Section 11.g).
 *
 * Deliberately loads .env.testing, never .env — the whole point of a
 * disposable test database is that nothing here can ever touch the
 * local dev database or its data. If .env.testing doesn't exist yet,
 * fail loudly with the fix instead of silently falling back to .env.
 */

require __DIR__ . '/../core/Autoload.php';
require __DIR__ . '/../core/Env.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

$envTestingPath = __DIR__ . '/../.env.testing';

if (!is_file($envTestingPath)) {
    fwrite(STDERR, "Missing .env.testing — copy .env.testing.example to .env.testing and fill in a disposable test database first (see README's \"Running Tests\" section).\n");
    exit(1);
}

Core\Env::load($envTestingPath);

// Safety net: refuse to run against anything that isn't obviously a
// test database. A typo'd .env.testing pointing at the real dev DB
// would otherwise get truncated by tests/Support/DatabaseTestCase's
// setUp() — this check exists purely to make that mistake loud
// instead of silently destructive.
$dbName = getenv('DB_NAME') ?: '';
if (!str_contains(strtolower($dbName), 'test')) {
    fwrite(STDERR, "DB_NAME=\"{$dbName}\" in .env.testing doesn't contain \"test\" — refusing to run, to avoid ever truncating a non-test database by mistake. Point .env.testing at a dedicated test database (e.g. skoolyst_ads_test).\n");
    exit(1);
}
