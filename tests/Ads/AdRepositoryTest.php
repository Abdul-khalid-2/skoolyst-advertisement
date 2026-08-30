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
}
