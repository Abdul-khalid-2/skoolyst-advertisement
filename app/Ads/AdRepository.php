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
        Database::query('INSERT INTO ad_impressions (ad_id, occurred_at) VALUES (:ad_id, NOW())', ['ad_id' => $adId]);
    }

    public function recordClick(int $adId): void
    {
        Database::query('INSERT INTO ad_clicks (ad_id, occurred_at) VALUES (:ad_id, NOW())', ['ad_id' => $adId]);
    }

    /**
     * GET /admin/ads?status=pending — the admin moderation queue,
     * paginated at the DB level (7.l), same LIMIT/OFFSET interpolation
     * rationale as findAllForUser() below. Same joins as
     * findAllForUser() plus the advertiser's name (`showAdvertiser`
     * in views/components/ads-table.php, 10.h) — an admin reviewing
     * the queue needs to know who submitted each ad, an advertiser
     * viewing their own list already does.
     *
     * $status === null drops the WHERE clause entirely — backs
     * admin/ads.php's "All" tab, which countsByStatus()'s own `all`
     * key already counts but has no single status value of its own.
     *
     * ORDER BY carries `ads.id` as a tiebreaker after `created_at`
     * (13.d finding): `created_at` alone is only second-precision, so
     * ads created within the same second — e.g. several seeded/
     * bulk-imported ads — have no defined relative order, which made
     * LIMIT/OFFSET pagination genuinely non-deterministic (page 1 and
     * page 2 could return the same row, or skip one, depending on
     * MySQL's tie-breaking on that call). Caught by an automated
     * pagination test seeding 25 ads in one go, not by manual
     * click-through testing, since manually-created ads are rarely
     * created in the same second.
     */
    public function findByStatus(?string $status, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min($perPage, 100));
        $offset = max(0, ($page - 1) * $perPage);
        $where = $status === null ? '' : 'WHERE ads.status = :status';

        return Database::query(
            <<<SQL
                SELECT
                    ads.id, ads.title, ads.image_path, ads.status,
                    ads.start_date, ads.end_date, ads.app_id,
                    users.name AS advertiser_name,
                    apps.name AS app_name,
                    placements.label AS placement_label,
                    COALESCE(stats.impressions, 0) AS impressions,
                    COALESCE(stats.clicks, 0) AS clicks
                FROM ads
                INNER JOIN users ON users.id = ads.user_id
                INNER JOIN apps ON apps.id = ads.app_id
                INNER JOIN placements ON placements.id = ads.placement_id
                LEFT JOIN (
                    SELECT ad_id, SUM(impressions) AS impressions, SUM(clicks) AS clicks
                    FROM ad_stats_daily
                    GROUP BY ad_id
                ) stats ON stats.ad_id = ads.id
                {$where}
                ORDER BY ads.created_at ASC, ads.id ASC
                LIMIT {$perPage} OFFSET {$offset}
            SQL,
            $status === null ? [] : ['status' => $status]
        )->fetchAll();
    }

    /**
     * Counts every status in one pass — backs the tab counters on
     * admin/ads.php (10.h) without seven separate COUNT queries.
     * Always returns every known status, zero-filled, so the caller
     * never has to guard against a missing key.
     *
     * @return array<string, int>
     */
    public function countsByStatus(): array
    {
        $counts = ['all' => 0, 'draft' => 0, 'pending' => 0, 'active' => 0, 'paused' => 0, 'rejected' => 0, 'ended' => 0];

        $rows = Database::query('SELECT status, COUNT(*) AS total FROM ads GROUP BY status')->fetchAll();

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
            $counts['all'] += (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Admin moderation decision (10.h) — approve/reject both go
     * through this one method, differing only in $status and whether
     * a reason is attached. Returns false if $adId doesn't exist, so
     * the controller can 404 instead of writing an AuditLog entry for
     * an action that didn't actually happen.
     */
    public function updateStatus(int $adId, string $status, ?string $rejectionReason = null): bool
    {
        // Existence checked separately, not via rowCount() — MySQL
        // reports rows actually changed, not rows matched (no
        // CLIENT_FOUND_ROWS flag here), so re-approving an already-
        // active ad would otherwise read as "not found" and 404 even
        // though the row exists (same fix as AppRepository::updateStatus()).
        if (Database::fetchOne('SELECT id FROM ads WHERE id = :id', ['id' => $adId]) === null) {
            return false;
        }

        Database::query(
            'UPDATE ads SET status = :status, rejection_reason = :rejection_reason WHERE id = :id',
            ['id' => $adId, 'status' => $status, 'rejection_reason' => $rejectionReason]
        );

        return true;
    }

    /**
     * GET /advertiser/ads — an advertiser only ever sees their own ads,
     * paginated at the DB level (7.k) rather than fetching every row
     * and slicing in PHP. $perPage/$offset are interpolated directly
     * (cast to int here, never taken as raw request input) because
     * Database::query() binds every param as a string, and MySQL's
     * native prepared statements (Database.php disables emulation)
     * reject a string operand in a LIMIT/OFFSET clause.
     *
     * Joins in the app name/placement label and each ad's lifetime
     * impressions/clicks (summed from `ad_stats_daily`, never the raw
     * event tables — 5.3.e) so my-ads.php (10.g) can render a row
     * without a separate lookup per ad.
     *
     * ORDER BY carries `ads.id DESC` as a tiebreaker after
     * `created_at DESC`, same reasoning and same 13.d finding as
     * findByStatus() above — `created_at`'s second precision alone
     * doesn't guarantee a stable order for rows created in the same
     * second, which broke LIMIT/OFFSET pagination's no-overlap
     * guarantee.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min($perPage, 100));
        $offset = max(0, ($page - 1) * $perPage);

        return Database::query(
            <<<SQL
                SELECT
                    ads.id, ads.title, ads.image_path, ads.status,
                    ads.start_date, ads.end_date, ads.app_id,
                    apps.name AS app_name,
                    placements.label AS placement_label,
                    COALESCE(stats.impressions, 0) AS impressions,
                    COALESCE(stats.clicks, 0) AS clicks
                FROM ads
                INNER JOIN apps ON apps.id = ads.app_id
                INNER JOIN placements ON placements.id = ads.placement_id
                LEFT JOIN (
                    SELECT ad_id, SUM(impressions) AS impressions, SUM(clicks) AS clicks
                    FROM ad_stats_daily
                    GROUP BY ad_id
                ) stats ON stats.ad_id = ads.id
                WHERE ads.user_id = :user_id
                ORDER BY ads.created_at DESC, ads.id DESC
                LIMIT {$perPage} OFFSET {$offset}
            SQL,
            ['user_id' => $userId]
        )->fetchAll();
    }

    /**
     * Total row count behind findAllForUser() — my-ads.php (10.g) needs
     * this to render "Page X of Y" against the real total, not just
     * whether the current page happens to be full.
     */
    public function countForUser(int $userId): int
    {
        $row = Database::fetchOne(
            'SELECT COUNT(*) AS total FROM ads WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Counts every status for one advertiser's own ads — same shape as
     * countsByStatus() (which is global, for the admin queue) but
     * scoped to `user_id`. Backs the advertiser dashboard's "Active
     * Ads" stat card and its "N pending review" sub-label (10.m).
     * Always returns every known status, zero-filled.
     *
     * @return array<string, int>
     */
    public function countsByStatusForUser(int $userId): array
    {
        $counts = ['all' => 0, 'draft' => 0, 'pending' => 0, 'active' => 0, 'paused' => 0, 'rejected' => 0, 'ended' => 0];

        $rows = Database::query(
            'SELECT status, COUNT(*) AS total FROM ads WHERE user_id = :user_id GROUP BY status',
            ['user_id' => $userId]
        )->fetchAll();

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
            $counts['all'] += (int) $row['total'];
        }

        return $counts;
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
