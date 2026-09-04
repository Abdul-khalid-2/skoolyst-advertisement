<?php

namespace Tests\Support;

use App\Apps\AppRepository;
use App\Auth\UserRepository;
use Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * DatabaseTestCase
 *
 * Base class for any test that touches the database (Section 13.g's
 * "disposable/seeded test database"). Truncates every app table
 * before each test — not once per class — so tests never see another
 * test's leftover rows regardless of run order.
 *
 * Seed helpers here call the same repository methods the app itself
 * uses (AppRepository::createWithApiKey(), UserRepository::create()),
 * per this project's own "repository methods, not raw queries" rule
 * (see MockDataSeeder.php) — a test fixture is still app code.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->truncateAllTables();
    }

    private function truncateAllTables(): void
    {
        $connection = Database::connection();
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (['ad_stats_daily', 'ad_clicks', 'ad_impressions', 'audit_log', 'ad_placements', 'ads', 'api_keys', 'placements', 'apps', 'users'] as $table) {
            $connection->exec("TRUNCATE TABLE `{$table}`");
        }

        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * @return array{id: int, email: string, password: string, role: string}
     */
    protected function seedUser(string $role = 'advertiser', string $password = 'Password123!'): array
    {
        static $counter = 0;
        $counter++;

        $email = "test-user-{$counter}-" . bin2hex(random_bytes(4)) . '@example.test';
        $user = (new UserRepository())->create('Test User ' . $counter, $email, password_hash($password, PASSWORD_DEFAULT), $role);

        return ['id' => $user->id, 'email' => $email, 'password' => $password, 'role' => $role];
    }

    /**
     * @return array{app: array<string, mixed>, api_key: string}
     */
    protected function seedApp(): array
    {
        static $counter = 0;
        $counter++;

        $code = 'test_app_' . $counter . '_' . bin2hex(random_bytes(4));

        return (new AppRepository())->createWithApiKey("Test App {$counter}", $code, 'test.example.test');
    }

    protected function seedPlacement(int $appId, string $code = 'test_placement'): array
    {
        static $counter = 0;
        $counter++;

        $uniqueCode = $code . '_' . $counter;

        Database::query(
            'INSERT INTO placements (app_id, code, label) VALUES (:app_id, :code, :label)',
            ['app_id' => $appId, 'code' => $uniqueCode, 'label' => 'Test Placement ' . $counter]
        );

        return Database::fetchOne(
            'SELECT * FROM placements WHERE app_id = :app_id AND code = :code',
            ['app_id' => $appId, 'code' => $uniqueCode]
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function seedAd(int $userId, int $appId, int $placementId, array $overrides = []): array
    {
        // Pulled out before merging into $data below — 'placement_ids'
        // isn't a real `ads` column, it's this fixture's own way of
        // saying "seed the ad onto more than one placement" (10.p),
        // so it must never reach the ads INSERT's bound params.
        $placementIds = $overrides['placement_ids'] ?? [$placementId];
        unset($overrides['placement_ids']);

        $defaults = [
            'title' => 'Test Ad',
            'description' => 'A test ad.',
            'image_path' => null,
            'cta_text' => 'Learn More',
            'click_url' => 'https://example.test/land',
            'status' => 'active',
            'rejection_reason' => null,
            'start_date' => null,
            'end_date' => null,
        ];
        $data = array_merge($defaults, $overrides);

        Database::query(
            <<<SQL
                INSERT INTO ads (user_id, app_id, placement_id, title, description, image_path, cta_text, click_url, status, rejection_reason, start_date, end_date)
                VALUES (:user_id, :app_id, :placement_id, :title, :description, :image_path, :cta_text, :click_url, :status, :rejection_reason, :start_date, :end_date)
            SQL,
            [
                'user_id' => $userId,
                'app_id' => $appId,
                'placement_id' => $placementId,
                ...$data,
            ]
        );

        $ad = Database::fetchOne(
            'SELECT * FROM ads WHERE user_id = :user_id AND app_id = :app_id ORDER BY id DESC LIMIT 1',
            ['user_id' => $userId, 'app_id' => $appId]
        );

        // Mirrors the real AdRepository::create() (10.p): every ad now
        // needs a row in ad_placements to actually be servable — this
        // fixture inserts directly into `ads` rather than going
        // through the repository (see this class's own doc-block), so
        // it has to keep that part in sync by hand. $placementIds lets
        // a test seed an ad onto more than one placement at once
        // without a second raw INSERT of its own (pass
        // `['placement_ids' => [...]]` in $overrides).
        foreach ($placementIds as $id) {
            Database::query(
                'INSERT INTO ad_placements (ad_id, placement_id) VALUES (:ad_id, :placement_id)',
                ['ad_id' => $ad['id'], 'placement_id' => $id]
            );
        }

        return $ad;
    }
}
