<?php

/**
 * DatabaseSeeder
 *
 * Section 9.k. Standalone CLI script — same pattern as
 * database/scripts/rollup-ad-stats-daily.php: all the actual query
 * logic stays in each module's Repository (Code Review Checklist:
 * query logic only in Repositories), this script is just the entry
 * point that calls them in the right order.
 *
 * Seeds the local/dev bootstrap data every environment needs before
 * it's usable:
 *   - one admin user — AuthController::register only ever creates
 *     'advertiser' accounts (see its doc-block), so the first admin
 *     has to come from somewhere else. This is that somewhere else.
 *   - one sample advertiser user, for exercising the dashboard
 *     without registering by hand.
 *   - one sample connected app + its API key, for exercising
 *     `/ads/serve` and friends without registering a real app first.
 *
 * Idempotent — safe to re-run. Each step checks for an existing row
 * before inserting, so running this twice never duplicates data or
 * throws on the unique-key constraints from Section 5.
 *
 * Usage:
 *   php database/seeders/DatabaseSeeder.php
 */

require __DIR__ . '/../../core/Database.php';
require __DIR__ . '/../../app/Auth/UserModel.php';
require __DIR__ . '/../../app/Auth/UserRepository.php';
require __DIR__ . '/../../app/Apps/AppRepository.php';

use App\Auth\UserRepository;
use App\Apps\AppRepository;

/**
 * Dev-only default password for both seeded users. Never used outside
 * local/dev seeding — a real deployment changes these immediately
 * after first login. Overridable via env so a shared dev/staging box
 * doesn't have to use the literal default.
 */
$seedPassword = getenv('SEED_PASSWORD') ?: 'ChangeMe123!';

$users = new UserRepository();
$apps = new AppRepository();

echo "==> Seeding admin user\n";

$admin = $users->findByEmail('admin@skoolyst.com');

if ($admin === null) {
    // 6.a — hashed here, exactly like a real signup; the seeder never
    // stores a plaintext password either.
    $admin = $users->create(
        'Skoolyst Admin',
        'admin@skoolyst.com',
        password_hash($seedPassword, PASSWORD_DEFAULT),
        'admin'
    );
    echo "    Created admin@skoolyst.com (password: {$seedPassword})\n";
} else {
    echo "    Skipped — admin@skoolyst.com already exists\n";
}

echo "==> Seeding sample advertiser user\n";

$advertiser = $users->findByEmail('advertiser@skoolyst.com');

if ($advertiser === null) {
    $advertiser = $users->create(
        'Sample Advertiser',
        'advertiser@skoolyst.com',
        password_hash($seedPassword, PASSWORD_DEFAULT),
        'advertiser'
    );
    echo "    Created advertiser@skoolyst.com (password: {$seedPassword})\n";
} else {
    echo "    Skipped — advertiser@skoolyst.com already exists\n";
}

echo "==> Seeding sample connected app\n";

$existingApps = $apps->all();
$sampleApp = null;

foreach ($existingApps as $row) {
    if ($row['code'] === 'skoolyst-main') {
        $sampleApp = $row;
        break;
    }
}

if ($sampleApp === null) {
    $created = $apps->createWithApiKey('Skoolyst Main Site', 'skoolyst-main', 'skoolyst.com');
    echo "    Created app 'skoolyst-main' — API key (shown once): {$created['api_key']}\n";
} else {
    echo "    Skipped — app 'skoolyst-main' already exists\n";
}

echo "==> Done\n";
