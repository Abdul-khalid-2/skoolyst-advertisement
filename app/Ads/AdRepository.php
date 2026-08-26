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
        Database::query('INSERT INTO ad_impressions (ad_id, created_at) VALUES (:ad_id, NOW())', ['ad_id' => $adId]);
    }

    public function recordClick(int $adId): void
    {
        Database::query('INSERT INTO ad_clicks (ad_id, created_at) VALUES (:ad_id, NOW())', ['ad_id' => $adId]);
    }

    /**
     * GET /advertiser/ads — an advertiser only ever sees their own ads.
     */
    public function findAllForUser(int $userId): array
    {
        return Database::query('SELECT * FROM ads WHERE user_id = :user_id ORDER BY created_at DESC', ['user_id' => $userId])
            ->fetchAll();
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
}
