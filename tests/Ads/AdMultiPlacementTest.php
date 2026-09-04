<?php

namespace Tests\Ads;

use App\Ads\AdRepository;
use App\Apps\PlacementRepository;
use Core\Database;
use Tests\Support\DatabaseTestCase;

/**
 * 10.p — an ad used to be tied to exactly one placement
 * (`ads.placement_id`); this covers the actual fix: an ad can now
 * serve on 1, several, or all of its app's placements at once via
 * the new `ad_placements` junction table (migration 0019).
 */
class AdMultiPlacementTest extends DatabaseTestCase
{
    /**
     * The core scenario from the bug report: an app with 3
     * placements (Header, Footer, Sidebar), one ad targeting 2 of
     * them — the ad must serve on both selected placements and never
     * on the one that wasn't selected.
     */
    public function testCreateWritesEveryPlacementAndTheAdServesOnEachOne(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $header = $this->seedPlacement($app['app']['id'], 'header');
        $footer = $this->seedPlacement($app['app']['id'], 'footer');
        $sidebar = $this->seedPlacement($app['app']['id'], 'sidebar');

        $repository = new AdRepository();
        $created = $repository->create($user['id'], [
            'app_id' => $app['app']['id'],
            'placement_ids' => [(int) $header['id'], (int) $sidebar['id']],
            'advertiser_name' => 'Test Advertiser',
            'title' => 'Multi-Placement Ad',
            'description' => 'Runs on two placements.',
            'image_path' => null,
            'cta_text' => 'Learn More',
            'click_url' => 'https://example.test/land',
            'start_date' => null,
            'end_date' => null,
        ]);
        $adId = (int) $created['id'];

        // Newly-created ads are 'pending' until approved — flip it to
        // 'active' directly (same as an admin's approve action would)
        // so findServableForPlacement()'s own status filter doesn't
        // mask what this test is actually checking.
        Database::query("UPDATE ads SET status = 'active' WHERE id = :id", ['id' => $adId]);

        $onHeader = $repository->findServableForPlacement((int) $app['app']['id'], $header['code']);
        $onSidebar = $repository->findServableForPlacement((int) $app['app']['id'], $sidebar['code']);
        $onFooter = $repository->findServableForPlacement((int) $app['app']['id'], $footer['code']);

        $this->assertNotNull($onHeader, 'The ad must serve on Header — it was selected.');
        $this->assertSame($adId, $onHeader['id']);
        $this->assertNotNull($onSidebar, 'The ad must serve on Sidebar — it was selected.');
        $this->assertSame($adId, $onSidebar['id']);
        $this->assertNull($onFooter, 'The ad must NOT serve on Footer — it was never selected.');
    }

    public function testCreateLinksOnlyTheSubmittedPlacementsInTheJunctionTable(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $one = $this->seedPlacement($app['app']['id'], 'one');
        $two = $this->seedPlacement($app['app']['id'], 'two');
        $three = $this->seedPlacement($app['app']['id'], 'three');

        $created = (new AdRepository())->create($user['id'], [
            'app_id' => $app['app']['id'],
            'placement_ids' => [(int) $one['id'], (int) $two['id']],
            'advertiser_name' => 'Test',
            'title' => 'Test',
            'description' => null,
            'image_path' => null,
            'cta_text' => null,
            'click_url' => 'https://example.test',
            'start_date' => null,
            'end_date' => null,
        ]);

        $rows = Database::query(
            'SELECT placement_id FROM ad_placements WHERE ad_id = :ad_id ORDER BY placement_id ASC',
            ['ad_id' => $created['id']]
        )->fetchAll();
        $linkedIds = array_map('intval', array_column($rows, 'placement_id'));

        sort($linkedIds);
        $expected = [(int) $one['id'], (int) $two['id']];
        sort($expected);

        $this->assertSame($expected, $linkedIds);
        $this->assertNotContains((int) $three['id'], $linkedIds, 'The unselected placement must not be linked.');
    }

    /**
     * create()'s legacy `ads.placement_id` column (kept for backward
     * compatibility, see AdRepository::create()'s doc-block) must
     * hold the first submitted placement — not be left null/wrong,
     * since it's a NOT NULL column with its own FK.
     */
    public function testCreateSetsLegacyPlacementIdToTheFirstSubmittedPlacement(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $first = $this->seedPlacement($app['app']['id'], 'first');
        $second = $this->seedPlacement($app['app']['id'], 'second');

        $created = (new AdRepository())->create($user['id'], [
            'app_id' => $app['app']['id'],
            'placement_ids' => [(int) $first['id'], (int) $second['id']],
            'advertiser_name' => 'Test',
            'title' => 'Test',
            'description' => null,
            'image_path' => null,
            'cta_text' => null,
            'click_url' => 'https://example.test',
            'start_date' => null,
            'end_date' => null,
        ]);

        $row = Database::fetchOne('SELECT placement_id FROM ads WHERE id = :id', ['id' => $created['id']]);

        $this->assertSame((int) $first['id'], (int) $row['placement_id']);
    }

    public function testPlacementIdsForAdReturnsEveryLinkedPlacement(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $header = $this->seedPlacement($app['app']['id'], 'header');
        $sidebar = $this->seedPlacement($app['app']['id'], 'sidebar');

        $ad = $this->seedAd($user['id'], $app['app']['id'], (int) $header['id'], [
            'placement_ids' => [(int) $header['id'], (int) $sidebar['id']],
        ]);

        $ids = (new AdRepository())->placementIdsForAd((int) $ad['id']);
        sort($ids);
        $expected = [(int) $header['id'], (int) $sidebar['id']];
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    /**
     * findByStatus()'s admin moderation queue must show every
     * placement an ad targets, not just the first one — this is what
     * an admin actually sees in the table (10.p's UI change).
     */
    public function testFindByStatusAggregatesAllPlacementLabels(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $header = $this->seedPlacement($app['app']['id'], 'header');
        $sidebar = $this->seedPlacement($app['app']['id'], 'sidebar');

        $this->seedAd($user['id'], $app['app']['id'], (int) $header['id'], [
            'status' => 'pending',
            'placement_ids' => [(int) $header['id'], (int) $sidebar['id']],
        ]);

        $rows = (new AdRepository())->findByStatus('pending');

        $this->assertCount(1, $rows);
        $this->assertStringContainsString($header['label'], $rows[0]['placement_label']);
        $this->assertStringContainsString($sidebar['label'], $rows[0]['placement_label']);
    }

    /**
     * The security fix that came with the feature: a submitted
     * placement id must actually belong to the app the ad claims to
     * be for, same cross-app scoping principle as 6.u.
     */
    public function testAllBelongToAppRejectsAnotherAppsPlacement(): void
    {
        $appA = $this->seedApp();
        $appB = $this->seedApp();
        $placementInA = $this->seedPlacement($appA['app']['id']);
        $placementInB = $this->seedPlacement($appB['app']['id']);

        $repository = new PlacementRepository();

        $this->assertTrue(
            $repository->allBelongToApp([(int) $placementInA['id']], $appA['app']['id'])
        );
        $this->assertFalse(
            $repository->allBelongToApp([(int) $placementInA['id'], (int) $placementInB['id']], $appA['app']['id']),
            'A placement from a different app must never pass this check.'
        );
    }

    public function testAllBelongToAppIsFalseForAnEmptyList(): void
    {
        $app = $this->seedApp();

        $this->assertFalse((new PlacementRepository())->allBelongToApp([], $app['app']['id']));
    }
}
