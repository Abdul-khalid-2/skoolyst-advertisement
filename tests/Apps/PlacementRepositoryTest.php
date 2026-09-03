<?php

namespace Tests\Apps;

use App\Apps\PlacementRepository;
use Tests\Support\DatabaseTestCase;

/**
 * Section 13.d-style unit test (one repository, real disposable test
 * DB, no mocking) for PlacementRepository — the CRUD 10.i's Connected
 * Apps page never actually got (10.o).
 *
 * The hasAds()/delete() pair gets the most attention here: `ads`
 * has a real `ON DELETE CASCADE` FK back to `placements` (migration
 * 0004), so a placement with ads still on it must never reach
 * delete() un-guarded — PlacementController::destroy() relies on
 * hasAds() to refuse that case with a 409 before it can happen.
 */
class PlacementRepositoryTest extends DatabaseTestCase
{
    public function testCreateAndAllForAppRoundTrip(): void
    {
        $app = $this->seedApp();
        $repository = new PlacementRepository();

        $repository->create($app['app']['id'], 'homepage-banner', 'Homepage Banner');
        $repository->create($app['app']['id'], 'sidebar-unit', 'Sidebar Unit');

        $placements = $repository->allForApp($app['app']['id']);

        $this->assertCount(2, $placements);
        $codes = array_column($placements, 'code');
        $this->assertContains('homepage-banner', $codes);
        $this->assertContains('sidebar-unit', $codes);
    }

    public function testAllForAppNeverReturnsAnotherAppsPlacements(): void
    {
        $appA = $this->seedApp();
        $appB = $this->seedApp();
        $repository = new PlacementRepository();

        $repository->create($appA['app']['id'], 'shared-code', 'App A Slot');
        $repository->create($appB['app']['id'], 'shared-code', 'App B Slot');

        $forA = $repository->allForApp($appA['app']['id']);

        $this->assertCount(1, $forA);
        $this->assertSame('App A Slot', $forA[0]['label']);
    }

    public function testCodeExistsForAppIsScopedPerApp(): void
    {
        $appA = $this->seedApp();
        $appB = $this->seedApp();
        $repository = new PlacementRepository();

        $repository->create($appA['app']['id'], 'homepage-banner', 'Homepage Banner');

        $this->assertTrue($repository->codeExistsForApp($appA['app']['id'], 'homepage-banner'));
        $this->assertFalse($repository->codeExistsForApp($appB['app']['id'], 'homepage-banner'));
    }

    public function testCodeExistsForAppExcludesGivenPlacementId(): void
    {
        $app = $this->seedApp();
        $repository = new PlacementRepository();

        $placement = $repository->create($app['app']['id'], 'homepage-banner', 'Homepage Banner');

        // Re-saving a placement with its own unchanged code must not
        // read as a collision with itself.
        $this->assertFalse(
            $repository->codeExistsForApp($app['app']['id'], 'homepage-banner', (int) $placement['id'])
        );

        $repository->create($app['app']['id'], 'sidebar-unit', 'Sidebar Unit');

        $this->assertTrue(
            $repository->codeExistsForApp($app['app']['id'], 'sidebar-unit', (int) $placement['id'])
        );
    }

    public function testUpdateChangesCodeAndLabel(): void
    {
        $app = $this->seedApp();
        $repository = new PlacementRepository();
        $placement = $repository->create($app['app']['id'], 'old-code', 'Old Label');

        $ok = $repository->update((int) $placement['id'], 'new-code', 'New Label');
        $updated = $repository->find((int) $placement['id']);

        $this->assertTrue($ok);
        $this->assertSame('new-code', $updated['code']);
        $this->assertSame('New Label', $updated['label']);
    }

    public function testUpdateReturnsFalseForUnknownId(): void
    {
        $repository = new PlacementRepository();

        $this->assertFalse($repository->update(999999, 'x', 'X'));
    }

    public function testHasAdsIsFalseForAFreshPlacement(): void
    {
        $app = $this->seedApp();
        $repository = new PlacementRepository();
        $placement = $repository->create($app['app']['id'], 'homepage-banner', 'Homepage Banner');

        $this->assertFalse($repository->hasAds((int) $placement['id']));
    }

    public function testHasAdsIsTrueOnceAnAdReferencesThePlacement(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $repository = new PlacementRepository();
        $placement = $repository->create($app['app']['id'], 'homepage-banner', 'Homepage Banner');

        $this->seedAd($user['id'], $app['app']['id'], (int) $placement['id']);

        $this->assertTrue($repository->hasAds((int) $placement['id']));
    }

    public function testDeleteRemovesAPlacementWithNoAds(): void
    {
        $app = $this->seedApp();
        $repository = new PlacementRepository();
        $placement = $repository->create($app['app']['id'], 'homepage-banner', 'Homepage Banner');

        $ok = $repository->delete((int) $placement['id']);

        $this->assertTrue($ok);
        $this->assertNull($repository->find((int) $placement['id']));
    }

    /**
     * The critical regression guard: PlacementRepository::delete()
     * itself does NOT check hasAds() (that's PlacementController's
     * job, per delete()'s own doc-block) — so this test proves the
     * FK really does cascade when delete() is called unguarded,
     * which is exactly why the controller must always call hasAds()
     * first. If a future refactor ever calls delete() directly
     * without that check, this test documents the real consequence:
     * the ad silently disappears too.
     */
    public function testDeleteWithoutTheHasAdsGuardCascadesAndDeletesTheAdToo(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $repository = new PlacementRepository();
        $placement = $repository->create($app['app']['id'], 'homepage-banner', 'Homepage Banner');
        $ad = $this->seedAd($user['id'], $app['app']['id'], (int) $placement['id']);

        $this->assertTrue($repository->hasAds((int) $placement['id']));

        $repository->delete((int) $placement['id']);

        $stillExists = \Core\Database::fetchOne('SELECT id FROM ads WHERE id = :id', ['id' => $ad['id']]);
        $this->assertNull($stillExists, 'Expected the FK cascade to have removed the ad along with its placement.');
    }
}
