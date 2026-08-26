<?php

namespace App\Ads;

use Core\Database;

/**
 * AdRepository
 *
 * Owns all query logic for the `ads` table. AdController calls this
 * instead of running queries directly. Other modules (e.g. Admin)
 * must go through this repository's public methods rather than
 * querying the `ads` table themselves.
 *
 * Every method takes $appId explicitly and every query below filters
 * on it — the caller (AdController) resolves $appId from the
 * authenticated API key via AppRepository::resolveAppId(), never from
 * a value the client sent, so one connected app can never read or
 * touch another app's placements (6.u). All statements use bound
 * parameters (6.m) — no query in this class ever concatenates a
 * value into the SQL string.
 */
class AdRepository
{
    /**
     * GET /ads/serve — only ever returns ads that are active, within
     * their schedule window, and scoped to the requesting app.
     *
     * @return array<string, mixed>|null
     */
    public function findServableForPlacement(int $appId, string $placementCode): ?array
    {
        return Database::fetchOne(
            <<<SQL
                SELECT ads.id, ads.title, ads.description, ads.image_path, ads.cta_text, ads.click_url
                FROM ads
                INNER JOIN placements ON placements.id = ads.placement_id
                WHERE ads.app_id = :app_id
                  AND placements.code = :placement_code
                  AND ads.status = 'active'
                  AND (ads.start_date IS NULL OR ads.start_date <= CURDATE())
                  AND (ads.end_date IS NULL OR ads.end_date >= CURDATE())
                ORDER BY RAND()
                LIMIT 1
            SQL,
            ['app_id' => $appId, 'placement_code' => $placementCode]
        );
    }

    /**
     * Confirms an ad belongs to the given app before impression/click
     * tracking is recorded against it (6.u).
     */
    public function belongsToApp(int $adId, int $appId): bool
    {
        return Database::fetchOne(
            'SELECT id FROM ads WHERE id = :id AND app_id = :app_id LIMIT 1',
            ['id' => $adId, 'app_id' => $appId]
        ) !== null;
    }

    public function recordImpression(int $adId): void
    {
        // Column is `occurred_at` (see migration 0005), not `created_at` —
        // was `created_at` here until this was caught during the Section
        // 10.a/10.b real-DB verification pass, since nothing had actually
        // run this query against the real schema before then.
        Database::query('INSERT INTO ad_impressions (ad_id, occurred_at) VALUES (:ad_id, NOW())', ['ad_id' => $adId]);
    }

    public function recordClick(int $adId): void
    {
        Database::query('INSERT INTO ad_clicks (ad_id, occurred_at) VALUES (:ad_id, NOW())', ['ad_id' => $adId]);
    }

    /**
     * GET /admin/ads?status=pending — the admin moderation queue,
     * paginated at the DB level (7.l), same LIMIT/OFFSET interpolation
     * rationale as findAllForUser() below.
     */
    public function findByStatus(string $status, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min($perPage, 100));
        $offset = max(0, ($page - 1) * $perPage);

        return Database::query(
            "SELECT * FROM ads WHERE status = :status ORDER BY created_at ASC LIMIT {$perPage} OFFSET {$offset}",
            ['status' => $status]
        )->fetchAll();
    }

    /**
     * GET /advertiser/ads — an advertiser only ever sees their own ads,
     * paginated at the DB level (7.k) rather than fetching every row
     * and slicing in PHP. $perPage/$offset are interpolated directly
     * (cast to int here, never taken as raw request input) because
     * Database::query() binds every param as a string, and MySQL's
     * native prepared statements (Database.php disables emulation)
     * reject a string operand in a LIMIT/OFFSET clause.
     */
    public function findAllForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min($perPage, 100));
        $offset = max(0, ($page - 1) * $perPage);

        return Database::query(
            "SELECT * FROM ads WHERE user_id = :user_id ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            ['user_id' => $userId]
        )->fetchAll();
    }

    /**
     * @param array<string, mixed> $data Validated fields from AdValidator.
     */
    public function create(int $userId, array $data): array
    {
        Database::query(
            <<<SQL
                INSERT INTO ads (user_id, app_id, placement_id, title, description, image_path, cta_text, click_url, status, start_date, end_date)
                VALUES (:user_id, :app_id, :placement_id, :title, :description, :image_path, :cta_text, :click_url, 'pending', :start_date, :end_date)
            SQL,
            [
                'user_id' => $userId,
                'app_id' => $data['app_id'],
                'placement_id' => $data['placement_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'image_path' => $data['image_path'],
                'cta_text' => $data['cta_text'],
                'click_url' => $data['click_url'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]
        );

        return ['user_id' => $userId] + $data;
    }

    /**
     * Only updates a row the requesting user actually owns — the
     * WHERE clause carries the ownership check, not a separate lookup
     * a caller could forget to make.
     *
     * @param array<string, mixed> $data
     */
    public function updateForUser(int $adId, int $userId, array $data): bool
    {
        $statement = Database::query(
            <<<SQL
                UPDATE ads SET
                    title = :title,
                    description = :description,
                    cta_text = :cta_text,
                    click_url = :click_url,
                    start_date = :start_date,
                    end_date = :end_date,
                    status = 'pending'
                WHERE id = :id AND user_id = :user_id
            SQL,
            [
                'id' => $adId,
                'user_id' => $userId,
                'title' => $data['title'],
                'description' => $data['description'],
                'cta_text' => $data['cta_text'],
                'click_url' => $data['click_url'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]
        );

        return $statement->rowCount() > 0;
    }

    /**
     * Seed-only insert: unlike create() above, this accepts an explicit
     * $status and $rejectionReason instead of always forcing 'pending' —
     * MockDataSeeder needs ads that are already 'active', 'ended',
     * 'rejected', etc. to match data/mock-data.php exactly. Never called
     * from AdController; a real advertiser's ad always starts 'pending'
     * (see create()) and only moderation (ModerationController) can move
     * it from there.
     *
     * @param array<string, mixed> $data
     */
    public function seedRaw(array $data): int
    {
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
                'user_id' => $data['user_id'],
                'app_id' => $data['app_id'],
                'placement_id' => $data['placement_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'image_path' => $data['image_path'],
                'cta_text' => $data['cta_text'],
                'click_url' => $data['click_url'],
                'status' => $data['status'],
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'start_date' => $data['start_date'] ?: null,
                'end_date' => $data['end_date'] ?: null,
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Finds a previously-seeded ad by its exact title — MockDataSeeder's
     * only way to check "have I already inserted this one" since ads
     * has no natural unique key of its own to key off (unlike users.email
     * or apps.code), keeping the seeder idempotent like every other step.
     */
    public function findByTitle(string $title): ?array
    {
        return Database::fetchOne('SELECT * FROM ads WHERE title = :title', ['title' => $title]);
    }
}
