<?php

namespace Tests\Ads;

use App\Ads\AdRepository;
use Core\Database;
use Tests\Support\DatabaseTestCase;

/**
 * Section 13.d — one unit test for a repository query-building
 * method: AdRepository::findByStatus()'s conditional WHERE clause
 * (10.h's "All tab accepts null status" fix) and its LIMIT/OFFSET
 * pagination. Runs against the real disposable test database — this
 * project's own rule is no mocking (CONTRIBUTING.md/README's testing
 * philosophy), so "unit" here means "one repository method in
 * isolation", not "no database".
 */
class AdRepositoryTest extends DatabaseTestCase
{
    public function testFindByStatusFiltersByGivenStatus(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);

        $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Pending One', 'status' => 'pending']);
        $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Active One', 'status' => 'active']);
        $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Pending Two', 'status' => 'pending']);

        $repository = new AdRepository();
        $pending = $repository->findByStatus('pending');

        $this->assertCount(2, $pending);
        foreach ($pending as $ad) {
            $this->assertSame('pending', $ad['status']);
        }
    }

    public function testFindByStatusWithNullReturnsEveryStatus(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);

        $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['status' => 'pending']);
        $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['status' => 'active']);
        $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['status' => 'rejected']);

        $all = (new AdRepository())->findByStatus(null);

        $this->assertCount(3, $all);
    }

    public function testFindByStatusPaginatesWithoutOverlap(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);

        for ($i = 0; $i < 25; $i++) {
            $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => "Ad {$i}", 'status' => 'active']);
        }

        $repository = new AdRepository();
        $page1 = $repository->findByStatus('active', page: 1, perPage: 20);
        $page2 = $repository->findByStatus('active', page: 2, perPage: 20);

        $this->assertCount(20, $page1);
        $this->assertCount(5, $page2);

        $page1Ids = array_column($page1, 'id');
        $page2Ids = array_column($page2, 'id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids), 'Page 1 and page 2 must not share any rows.');
    }

    public function testDeleteForUserSoftDeletesInsteadOfRemovingTheRow(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);
        $ad = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['status' => 'active']);

        $repository = new AdRepository();
        $result = $repository->deleteForUser((int) $ad['id'], $user['id']);

        $this->assertTrue($result);

        // Row still exists in the database, just flipped to 'deleted' —
        // not actually removed.
        $row = Database::fetchOne('SELECT status FROM ads WHERE id = :id', ['id' => $ad['id']]);
        $this->assertNotNull($row);
        $this->assertSame('deleted', $row['status']);

        // But it's gone from every normal view: the advertiser's own
        // list/count, and the admin "All" tab / global counts.
        $this->assertSame([], $repository->findAllForUser($user['id']));
        $this->assertSame(0, $repository->countForUser($user['id']));
        $this->assertSame([], $repository->findByStatus(null));
        $this->assertSame(0, $repository->countsByStatus()['all']);
    }

    public function testDeleteForUserReturnsFalseForAnAdTheUserDoesNotOwn(): void
    {
        $owner = $this->seedUser();
        $someoneElse = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);
        $ad = $this->seedAd($owner['id'], $app['app']['id'], $placement['id']);

        $result = (new AdRepository())->deleteForUser((int) $ad['id'], $someoneElse['id']);

        $this->assertFalse($result);

        $row = Database::fetchOne('SELECT status FROM ads WHERE id = :id', ['id' => $ad['id']]);
        $this->assertNotSame('deleted', $row['status']);
    }

    /**
     * Admin overview's "Top Performing Ads" widget (public/admin/index.php)
     * — verifies findTopByClicks() ranks by lifetime clicks (summed from
     * ad_stats_daily) regardless of which advertiser owns each ad, and
     * excludes soft-deleted ads.
     */
    public function testFindTopByClicksOrdersByLifetimeClicksDescending(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);

        $low = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Low Clicks']);
        $high = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'High Clicks']);
        $deleted = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Deleted Ad', 'status' => 'deleted']);

        Database::query(
            'INSERT INTO ad_stats_daily (ad_id, `date`, impressions, clicks) VALUES (:ad_id, :date, :impressions, :clicks)',
            ['ad_id' => $low['id'], 'date' => date('Y-m-d'), 'impressions' => 100, 'clicks' => 2]
        );
        Database::query(
            'INSERT INTO ad_stats_daily (ad_id, `date`, impressions, clicks) VALUES (:ad_id, :date, :impressions, :clicks)',
            ['ad_id' => $high['id'], 'date' => date('Y-m-d'), 'impressions' => 100, 'clicks' => 9]
        );
        Database::query(
            'INSERT INTO ad_stats_daily (ad_id, `date`, impressions, clicks) VALUES (:ad_id, :date, :impressions, :clicks)',
            ['ad_id' => $deleted['id'], 'date' => date('Y-m-d'), 'impressions' => 100, 'clicks' => 50]
        );

        $top = (new AdRepository())->findTopByClicks(5);

        $this->assertCount(2, $top, 'Soft-deleted ads must never appear in the top-ads widget.');
        $this->assertSame('High Clicks', $top[0]['title']);
        $this->assertSame('Low Clicks', $top[1]['title']);
    }

    /**
     * "Needs Attention" widget — only pending/rejected ads, oldest
     * first, and never leaks another status (e.g. active) into the list.
     */
    public function testFindNeedsAttentionReturnsOnlyPendingAndRejectedOldestFirst(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id']);

        $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Active Ad', 'status' => 'active']);
        $newerPending = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Newer Pending', 'status' => 'pending']);
        $olderRejected = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], ['title' => 'Older Rejected', 'status' => 'rejected']);

        Database::query('UPDATE ads SET created_at = :created_at WHERE id = :id', ['created_at' => '2026-01-01 00:00:00', 'id' => $olderRejected['id']]);
        Database::query('UPDATE ads SET created_at = :created_at WHERE id = :id', ['created_at' => '2026-06-01 00:00:00', 'id' => $newerPending['id']]);

        $attention = (new AdRepository())->findNeedsAttention(5);

        $this->assertCount(2, $attention);
        $this->assertSame('Older Rejected', $attention[0]['title']);
        $this->assertSame('Newer Pending', $attention[1]['title']);
    }

    public function testOldestPendingCreatedAtReturnsNullWhenNothingIsPending(): void
    {
        $this->assertNull((new AdRepository())->oldestPendingCreatedAt());
    }
}
