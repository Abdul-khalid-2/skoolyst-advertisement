<?php

/**
 * MockDataSeeder
 *
 * Section 10.b. Seeds the real database with the exact same apps,
 * placements, and ads that data/mock-data.php hardcodes for the UI
 * prototype — so the dashboard/admin pages produce identical-looking
 * output whether they're reading the mock array (today) or a real
 * repository (once Section 10's later "wire module X into page Y"
 * items land), without a fixture rewrite at that point.
 *
 * Not merged into DatabaseSeeder.php on purpose: that script is the
 * minimal "make the app usable at all" bootstrap (one admin, one
 * advertiser, one app) every environment needs, including production.
 * This script is prototype fixture data — local/dev and staging only.
 *
 * Idempotent — safe to re-run:
 *   - apps are keyed by their unique `code`
 *   - placements are keyed by (app_id, code) via AppRepository::findOrCreatePlacement()
 *   - ads have no natural unique key, so this checks by exact `title`
 *     (AdRepository::findByTitle()) before inserting
 *   - advertiser users are keyed by `email`, same as DatabaseSeeder.php
 *
 * Usage:
 *   php database/seeders/MockDataSeeder.php
 */

require __DIR__ . '/../../core/Database.php';
require __DIR__ . '/../../app/Auth/UserModel.php';
require __DIR__ . '/../../app/Auth/UserRepository.php';
require __DIR__ . '/../../app/Apps/AppRepository.php';
require __DIR__ . '/../../app/Ads/AdRepository.php';

use App\Auth\UserRepository;
use App\Apps\AppRepository;
use App\Ads\AdRepository;

$mockData = require __DIR__ . '/../../data/mock-data.php';
$seedPassword = getenv('SEED_PASSWORD') ?: 'ChangeMe123!';

$users = new UserRepository();
$apps = new AppRepository();
$ads = new AdRepository();

// ---------------------------------------------------------------------
// 1. Apps — data/mock-data.php's 'apps' array. The mock 'apiKey' values
//    (e.g. "sk_live_9c1c...4f2a") are already-redacted UI display
//    strings, not real keys, so a fresh real key is issued instead via
//    the normal 6.d/6.e path — never the mock string.
// ---------------------------------------------------------------------
echo "==> Seeding apps\n";

/** @var array<string, int> $appIdByMockId Maps mock-data.php's short 'id' (e.g. 'sk') to the real DB app_id. */
$appIdByMockId = [];

foreach ($mockData['apps'] as $mockApp) {
    $existing = $apps->findByCode($mockApp['code']);

    if ($existing !== null) {
        $appIdByMockId[$mockApp['id']] = (int) $existing['id'];
        echo "    Skipped — app '{$mockApp['code']}' already exists\n";
        continue;
    }

    $created = $apps->createWithApiKey($mockApp['name'], $mockApp['code'], $mockApp['domain']);
    $appIdByMockId[$mockApp['id']] = (int) $created['app']['id'];
    echo "    Created app '{$mockApp['code']}' ({$mockApp['name']})\n";
}

// ---------------------------------------------------------------------
// 2. Placements — data/mock-data.php's 'placementsByApp', keyed by the
//    same mock app id.
// ---------------------------------------------------------------------
echo "==> Seeding placements\n";

/** @var array<string, int> $placementIdByCode Maps "mockAppId:code" to the real DB placement_id. */
$placementIdByCode = [];

foreach ($mockData['placementsByApp'] as $mockAppId => $placements) {
    $appId = $appIdByMockId[$mockAppId];

    foreach ($placements as $placement) {
        $placementId = $apps->findOrCreatePlacement($appId, $placement['value'], $placement['label']);
        $placementIdByCode["{$mockAppId}:{$placement['value']}"] = $placementId;
    }
}

echo '    ' . count($placementIdByCode) . " placement(s) in place\n";

// ---------------------------------------------------------------------
// 3. One advertiser user per distinct advertiser name in mock-data.php's
//    'ads' array, so admin/ads.php's advertiser column has a real user
//    to attribute each ad to (the real `ads` table has no separate
//    "advertiser company name" field — ownership is by user_id, same as
//    a real advertiser signing up via AuthController::register).
// ---------------------------------------------------------------------
echo "==> Seeding advertiser users (one per mock advertiser)\n";

/** @var array<string, int> $userIdByAdvertiserName */
$userIdByAdvertiserName = [];

foreach ($mockData['ads'] as $mockAd) {
    $name = $mockAd['advertiser'];

    if (isset($userIdByAdvertiserName[$name])) {
        continue;
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $email = "{$slug}@advertisers.example";

    $user = $users->findByEmail($email);

    if ($user === null) {
        $user = $users->create($name, $email, password_hash($seedPassword, PASSWORD_DEFAULT), 'advertiser');
        echo "    Created advertiser user for '{$name}' ({$email})\n";
    } else {
        echo "    Skipped — advertiser user for '{$name}' already exists\n";
    }

    $userIdByAdvertiserName[$name] = $user->id;
}

// ---------------------------------------------------------------------
// 4. Ads — data/mock-data.php's 'ads' array, with the exact status
//    (active/pending/paused/rejected/draft/ended) the mock UI shows,
//    via AdRepository::seedRaw() rather than the normal create() path
//    (which always forces 'pending').
// ---------------------------------------------------------------------
echo "==> Seeding ads\n";

$adIdByMockId = [];

foreach ($mockData['ads'] as $mockAd) {
    $existing = $ads->findByTitle($mockAd['title']);

    if ($existing !== null) {
        $adIdByMockId[$mockAd['id']] = (int) $existing['id'];
        echo "    Skipped — ad '{$mockAd['title']}' already exists\n";
        continue;
    }

    $placementKey = "{$mockAd['app']}:{$mockAd['placement']}";

    if (!isset($placementIdByCode[$placementKey])) {
        fwrite(STDERR, "    Skipping '{$mockAd['title']}' — no placement '{$mockAd['placement']}' for app '{$mockAd['app']}'.\n");
        continue;
    }

    $adId = $ads->seedRaw([
        'user_id' => $userIdByAdvertiserName[$mockAd['advertiser']],
        'app_id' => $appIdByMockId[$mockAd['app']],
        'placement_id' => $placementIdByCode[$placementKey],
        'title' => $mockAd['title'],
        'description' => $mockAd['description'],
        'image_path' => $mockAd['image'],
        'cta_text' => $mockAd['cta'],
        'click_url' => $mockAd['url'],
        'status' => $mockAd['status'],
        'rejection_reason' => $mockAd['rejectionReason'] ?? null,
        'start_date' => $mockAd['startDate'] ?? null,
        'end_date' => $mockAd['endDate'] ?? null,
    ]);

    $adIdByMockId[$mockAd['id']] = $adId;
    echo "    Created ad '{$mockAd['title']}' (status: {$mockAd['status']})\n";
}

// ---------------------------------------------------------------------
// 5. Impressions/clicks — mock-data.php stores these as pre-aggregated
//    totals (e.g. 48210 impressions), not individual raw events. Since
//    5.3's whole design point is that dashboards read aggregated
//    ad_stats_daily rather than counting raw event rows, seeding
//    48,000+ individual INSERTs to reproduce that number isn't the
//    right fidelity target — the totals are written directly into
//    ad_stats_daily instead, dated to each ad's start_date (or today,
//    for the two ads with no dates), so the existing dashboard chart
//    (5.3.d) shows the same numbers the mock UI does.
// ---------------------------------------------------------------------
echo "==> Seeding ad_stats_daily totals\n";

foreach ($mockData['ads'] as $mockAd) {
    if (($mockAd['impressions'] ?? 0) === 0 && ($mockAd['clicks'] ?? 0) === 0) {
        continue;
    }

    $adId = $adIdByMockId[$mockAd['id']] ?? null;

    if ($adId === null) {
        continue; // The ad itself was skipped above (e.g. a missing placement).
    }

    $date = !empty($mockAd['startDate']) ? $mockAd['startDate'] : date('Y-m-d');

    \Core\Database::query(
        <<<SQL
            INSERT INTO ad_stats_daily (ad_id, date, impressions, clicks)
            VALUES (:ad_id, :date, :impressions, :clicks)
            ON DUPLICATE KEY UPDATE impressions = :impressions_update, clicks = :clicks_update
        SQL,
        [
            'ad_id' => $adId,
            'date' => $date,
            'impressions' => $mockAd['impressions'],
            'clicks' => $mockAd['clicks'],
            // MySQL's native prepared statements (Database.php runs with
            // EMULATE_PREPARES => false) reject reusing the same named
            // parameter twice in one query — same reason
            // AdStatsRepository::rollupForDate() binds its date three
            // times under three different names.
            'impressions_update' => $mockAd['impressions'],
            'clicks_update' => $mockAd['clicks'],
        ]
    );

    echo "    {$mockAd['title']}: {$mockAd['impressions']} impressions, {$mockAd['clicks']} clicks on {$date}\n";
}

echo "==> Done\n";
