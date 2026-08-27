<?php

/**
 * MockDataSeeder
 *
 * Section 10.b. Loads data/mock-data.php — the single source of truth
 * every dashboard/admin page and js/dashboard.js already read from
 * (see that file's own doc-block) — and inserts the same apps,
 * placements, advertisers, and ads into the real tables, so the DB
 * and the UI prototype agree instead of drifting apart.
 *
 * Separate from DatabaseSeeder.php (9.k), which seeds generic
 * admin/dev bootstrap accounts unrelated to the UI mock data.
 * Run this one after that one (or on its own — it doesn't depend on
 * DatabaseSeeder's rows).
 *
 * What this does NOT seed: individual ad_impressions/ad_clicks rows.
 * `data/mock-data.php` only carries aggregate totals per ad (e.g.
 * `'impressions' => 48210`), not individual events — fabricating that
 * many fake event rows to back into the same total isn't "the same
 * mock data", it's invented data the prototype never had. The ads
 * table itself has no impressions/clicks columns (Section 5) — those
 * numbers only ever exist via ad_stats_daily (5.3), which the rollup
 * script (database/scripts/rollup-ad-stats-daily.php) builds from
 * real tracked events, not seed data.
 *
 * Idempotent — matches existing rows by their natural key (apps by
 * `code`, placements by `app_id`+`code`, users by `email`, ads by
 * `app_id`+`title`) before inserting, so re-running this is safe.
 *
 * Usage:
 *   php database/seeders/MockDataSeeder.php
 */

require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../core/Database.php';
require __DIR__ . '/../../app/Auth/UserModel.php';
require __DIR__ . '/../../app/Auth/UserRepository.php';
require __DIR__ . '/../../app/Apps/AppRepository.php';

\Core\Env::load(__DIR__ . '/../../.env');

use App\Auth\UserRepository;
use App\Apps\AppRepository;
use Core\Database;

$mockData = require __DIR__ . '/../../data/mock-data.php';

// Same dev-only default as DatabaseSeeder.php (9.k) — overridable via
// env, never used outside local/dev seeding.
$seedPassword = getenv('SEED_PASSWORD') ?: 'ChangeMe123!';

$users = new UserRepository();
$apps = new AppRepository();

/** @var array<string, int> Maps mock app id (e.g. 'sk') to the real apps.id. */
$appIdByMockId = [];

/** @var array<string, int> Maps "mockAppId:placementCode" to the real placements.id. */
$placementIdByKey = [];

/** @var array<string, int> Maps advertiser email to the real users.id. */
$userIdByEmail = [];

echo "==> Seeding apps + placements\n";

foreach ($mockData['apps'] as $mockApp) {
    $existing = Database::fetchOne('SELECT id, status FROM apps WHERE code = :code', ['code' => $mockApp['id']]);

    if ($existing === null) {
        $created = $apps->createWithApiKey($mockApp['name'], $mockApp['id'], $mockApp['domain']);
        $appId = (int) $created['app']['id'];

        if ($mockApp['status'] !== 'active') {
            Database::query('UPDATE apps SET status = :status WHERE id = :id', ['status' => $mockApp['status'], 'id' => $appId]);
        }

        echo "    Created app '{$mockApp['id']}' ({$mockApp['name']}) — API key (shown once): {$created['api_key']}\n";
    } else {
        $appId = (int) $existing['id'];
        echo "    Skipped app '{$mockApp['id']}' — already exists\n";
    }

    $appIdByMockId[$mockApp['id']] = $appId;

    foreach ($mockData['placementsByApp'][$mockApp['id']] ?? [] as $mockPlacement) {
        $existingPlacement = Database::fetchOne(
            'SELECT id FROM placements WHERE app_id = :app_id AND code = :code',
            ['app_id' => $appId, 'code' => $mockPlacement['value']]
        );

        if ($existingPlacement === null) {
            Database::query(
                'INSERT INTO placements (app_id, code, label) VALUES (:app_id, :code, :label)',
                ['app_id' => $appId, 'code' => $mockPlacement['value'], 'label' => $mockPlacement['label']]
            );
            $placementId = (int) Database::connection()->lastInsertId();
            echo "    Created placement '{$mockPlacement['value']}' for '{$mockApp['id']}'\n";
        } else {
            $placementId = (int) $existingPlacement['id'];
        }

        $placementIdByKey["{$mockApp['id']}:{$mockPlacement['value']}"] = $placementId;
    }
}

echo "==> Seeding advertiser users\n";

foreach ($mockData['ads'] as $mockAd) {
    $advertiserName = $mockAd['advertiser'];
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($advertiserName)));
    $email = "{$slug}@example.com";

    if (isset($userIdByEmail[$email])) {
        continue;
    }

    $existingUser = $users->findByEmail($email);

    if ($existingUser === null) {
        $user = $users->create($advertiserName, $email, password_hash($seedPassword, PASSWORD_DEFAULT), 'advertiser');
        echo "    Created advertiser user for '{$advertiserName}' ({$email})\n";
    } else {
        $user = $existingUser;
        echo "    Skipped advertiser user for '{$advertiserName}' — already exists\n";
    }

    $userIdByEmail[$email] = $user->id;
}

echo "==> Seeding ads\n";

foreach ($mockData['ads'] as $mockAd) {
    $appId = $appIdByMockId[$mockAd['app']] ?? null;
    $placementId = $placementIdByKey["{$mockAd['app']}:{$mockAd['placement']}"] ?? null;

    if ($appId === null || $placementId === null) {
        fwrite(STDERR, "    Skipped ad '{$mockAd['id']}' — unknown app/placement '{$mockAd['app']}/{$mockAd['placement']}'\n");
        continue;
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($mockAd['advertiser'])));
    $userId = $userIdByEmail["{$slug}@example.com"];

    $existingAd = Database::fetchOne(
        'SELECT id FROM ads WHERE app_id = :app_id AND title = :title',
        ['app_id' => $appId, 'title' => $mockAd['title']]
    );

    if ($existingAd !== null) {
        echo "    Skipped ad '{$mockAd['id']}' — already exists\n";
        continue;
    }

    Database::query(
        <<<SQL
            INSERT INTO ads (
                user_id, app_id, placement_id, title, description, image_path,
                cta_text, click_url, status, rejection_reason, start_date, end_date
            ) VALUES (
                :user_id, :app_id, :placement_id, :title, :description, :image_path,
                :cta_text, :click_url, :status, :rejection_reason, :start_date, :end_date
            )
        SQL,
        [
            'user_id' => $userId,
            'app_id' => $appId,
            'placement_id' => $placementId,
            'title' => $mockAd['title'],
            'description' => $mockAd['description'],
            'image_path' => $mockAd['image'],
            'cta_text' => $mockAd['cta'],
            'click_url' => $mockAd['url'],
            'status' => $mockAd['status'],
            'rejection_reason' => $mockAd['rejectionReason'] ?? null,
            'start_date' => $mockAd['startDate'] !== '' ? $mockAd['startDate'] : null,
            'end_date' => $mockAd['endDate'] !== '' ? $mockAd['endDate'] : null,
        ]
    );

    echo "    Created ad '{$mockAd['id']}' ({$mockAd['title']})\n";
}

echo "==> Done\n";
