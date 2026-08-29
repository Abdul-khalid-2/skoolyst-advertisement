<?php

namespace Tests\Ads;

use App\Ads\AdStatsRepository;
use Core\Database;
use Tests\Support\DatabaseTestCase;

/**
 * Section 13.e — one unit test for the stats rollup calculation
 * (AdStatsRepository::rollupForDate()). Automates the same procedure
 * 10.k's manual test already did by hand against a real MySQL
 * instance (5 impressions + 2 clicks on one ad, 1 impression on
 * another) — this test is that verification, now repeatable on every
 * run instead of a one-off manual pass.
 */
class AdStatsRepositoryTest extends DatabaseTestCase
{
    public function testRollupAggregatesRawEventsPerAdForTheGivenDate(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);

        $adOne = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Ad One']);
        $adTwo = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Ad Two']);

        $date = '2026-08-15';

        $this->recordEvents('ad_impressions', (int) $adOne['id'], $date, 5);
        $this->recordEvents('ad_clicks', (int) $adOne['id'], $date, 2);
        $this->recordEvents('ad_impressions', (int) $adTwo['id'], $date, 1);

        // An event on a different day must never bleed into this
        // day's rollup.
        $this->recordEvents('ad_impressions', (int) $adOne['id'], '2026-08-14', 3);

        (new AdStatsRepository())->rollupForDate($date);

        $rowOne = Database::fetchOne(
            'SELECT impressions, clicks FROM ad_stats_daily WHERE ad_id = :ad_id AND `date` = :date',
            ['ad_id' => $adOne['id'], 'date' => $date]
        );
        $rowTwo = Database::fetchOne(
            'SELECT impressions, clicks FROM ad_stats_daily WHERE ad_id = :ad_id AND `date` = :date',
            ['ad_id' => $adTwo['id'], 'date' => $date]
        );

        $this->assertSame(5, (int) $rowOne['impressions']);
        $this->assertSame(2, (int) $rowOne['clicks']);
        $this->assertSame(1, (int) $rowTwo['impressions']);
        $this->assertSame(0, (int) $rowTwo['clicks']);
    }

    public function testRollupIsIdempotentWhenReRunForTheSameDate(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);
        $ad = $this->seedAd($user['id'], $app['app']['id'], $placement['id']);

        $date = '2026-08-15';
        $this->recordEvents('ad_impressions', (int) $ad['id'], $date, 4);

        $repository = new AdStatsRepository();
        $repository->rollupForDate($date);
        $repository->rollupForDate($date);

        $rows = Database::query(
            'SELECT impressions FROM ad_stats_daily WHERE ad_id = :ad_id AND `date` = :date',
            ['ad_id' => $ad['id'], 'date' => $date]
        )->fetchAll();

        $this->assertCount(1, $rows, 'Re-running the rollup for the same date must not create a duplicate row.');
        $this->assertSame(4, (int) $rows[0]['impressions']);
    }

    private function recordEvents(string $table, int $adId, string $date, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Database::query(
                "INSERT INTO `{$table}` (ad_id, occurred_at) VALUES (:ad_id, :occurred_at)",
                ['ad_id' => $adId, 'occurred_at' => $date . ' 12:00:00']
            );
        }
    }
}
