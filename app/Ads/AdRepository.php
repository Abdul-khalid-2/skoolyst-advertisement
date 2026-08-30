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
     * $status === null drops the status filter down to "not deleted"
     * — backs admin/ads.php's "All" tab, which countsByStatus()'s own
     * `all` key already counts (also excluding 'deleted') but has no
     * single status value of its own. An explicit ?status=deleted
     * still works via the exact-match branch below, if ever needed.
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
        $where = $status === null ? "WHERE ads.status != 'deleted'" : 'WHERE ads.status = :status';

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

        $rows = Database::query("SELECT status, COUNT(*) AS total FROM ads WHERE status != 'deleted' GROUP BY status")->fetchAll();

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
                WHERE ads.user_id = :user_id AND ads.status != 'deleted'
                ORDER BY ads.created_at DESC, ads.id DESC
                LIMIT {$perPage} OFFSET {$offset}
            SQL,
            ['user_id' => $userId]
        )->fetchAll();
    }

    /**
     * Total row count behind findAllForUser() — my-ads.php (10.g) needs
     * this to render "Page X of Y" against the real total, not just
     * whether the current page happens to be full. Excludes
     * soft-deleted ads, same as findAllForUser() itself, so the page
     * count never accounts for rows the advertiser can no longer see.
     */
    public function countForUser(int $userId): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM ads WHERE user_id = :user_id AND status != 'deleted'",
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
            "SELECT status, COUNT(*) AS total FROM ads WHERE user_id = :user_id AND status != 'deleted' GROUP BY status",
            ['user_id' => $userId]
        )->fetchAll();

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
            $counts['all'] += (int) $row['total'];
        }

        return $counts;
    }

    /**
     * GET /api/v1/advertiser/ads/{id} — feeds the "Edit Ad" form's
     * prefill (my-ads.php's edit button). Scoped to `user_id` in the
     * WHERE clause, same as updateForUser() below, so one advertiser
     * can never load another advertiser's ad by guessing its id — a
     * mismatched id/user_id simply returns null, same "not found"
     * shape the controller already uses for updateForUser()'s own
     * ownership check.
     *
     * Returns every field the edit form needs, including
     * `placement_id` (not selectable from findAllForUser()'s joined
     * row, which only has the human-readable `placement_label`) so
     * the form can pre-select the right placement in its dropdown.
     *
     * @return array<string, mixed>|null
     */
    public function findForUser(int $adId, int $userId): ?array
    {
        return Database::fetchOne(
            <<<SQL
                SELECT id, app_id, placement_id, advertiser_name, title, description, image_path, cta_text, click_url, status, start_date, end_date
                FROM ads
                WHERE id = :id AND user_id = :user_id AND status != 'deleted'
                LIMIT 1
            SQL,
            ['id' => $adId, 'user_id' => $userId]
        );
    }

    /**
     * @param array<string, mixed> $data Validated fields from AdValidator.
     */
    public function create(int $userId, array $data): array
    {
        Database::query(
            <<<SQL
                INSERT INTO ads (user_id, app_id, placement_id, advertiser_name, title, description, image_path, cta_text, click_url, status, start_date, end_date)
                VALUES (:user_id, :app_id, :placement_id, :advertiser_name, :title, :description, :image_path, :cta_text, :click_url, 'pending', :start_date, :end_date)
            SQL,
            [
                'user_id' => $userId,
                'app_id' => $data['app_id'],
                'placement_id' => $data['placement_id'],
                'advertiser_name' => $data['advertiser_name'],
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
                    advertiser_name = :advertiser_name,
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
                'advertiser_name' => $data['advertiser_name'],
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
     * Replaces just an ad's image — kept as its own method (and its
     * own endpoint, AdController::updateImage()) rather than folded
     * into updateForUser()'s field list, because a new image file can
     * only ever arrive as a multipart/form-data body, and PHP only
     * populates $_FILES for that content type on a POST request, never
     * on PATCH/PUT — so the rest of an edit (title, description, etc.)
     * goes through updateForUser() as a plain PATCH+JSON request,
     * while an image change (if any) is a separate POST call.
     *
     * Same ownership guarantee as updateForUser(): the WHERE clause
     * carries the `user_id` check, not a separate lookup a caller
     * could forget to make.
     */
    public function updateImageForUser(int $adId, int $userId, string $imagePath): bool
    {
        $statement = Database::query(
            'UPDATE ads SET image_path = :image_path WHERE id = :id AND user_id = :user_id',
            ['id' => $adId, 'user_id' => $userId, 'image_path' => $imagePath]
        );

        return $statement->rowCount() > 0;
    }

    /**
     * Admin-side counterpart to findForUser() — same field list (the
     * edit form needs it regardless of who's loading it), but no
     * `user_id` filter: an admin edits any advertiser's ad, not just
     * their own. Ownership scoping isn't relevant here because the
     * caller (AdController::adminShow()) already gated on the admin
     * role, not on owning the row.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $adId): ?array
    {
        return Database::fetchOne(
            <<<SQL
                SELECT id, app_id, placement_id, advertiser_name, title, description, image_path, cta_text, click_url, status, start_date, end_date
                FROM ads
                WHERE id = :id
                LIMIT 1
            SQL,
            ['id' => $adId]
        );
    }

    /**
     * Admin-side counterpart to updateForUser() — no `user_id` filter,
     * and deliberately does NOT reset `status` to 'pending' the way
     * updateForUser() does. That reset exists to send an advertiser's
     * edit back through moderation; an admin editing an ad *is* the
     * moderator, so their edit shouldn't pull an already-live ad out
     * of rotation or bump a paused/rejected ad's status on its own.
     *
     * @param array<string, mixed> $data
     */
    public function updateById(int $adId, array $data): bool
    {
        $statement = Database::query(
            <<<SQL
                UPDATE ads SET
                    advertiser_name = :advertiser_name,
                    title = :title,
                    description = :description,
                    cta_text = :cta_text,
                    click_url = :click_url,
                    start_date = :start_date,
                    end_date = :end_date
                WHERE id = :id
            SQL,
            [
                'id' => $adId,
                'advertiser_name' => $data['advertiser_name'],
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
     * Admin-side counterpart to updateImageForUser() — no `user_id`
     * filter, same reasoning as updateById() above.
     */
    public function updateImageById(int $adId, string $imagePath): bool
    {
        $statement = Database::query(
            'UPDATE ads SET image_path = :image_path WHERE id = :id',
            ['id' => $adId, 'image_path' => $imagePath]
        );

        return $statement->rowCount() > 0;
    }

    /**
     * Advertiser-side Pause/Activate (10.n follow-up) — same idea as
     * updateStatus() (admin approve/reject), but scoped to a row the
     * requesting advertiser actually owns, same ownership guarantee
     * as updateForUser()/updateImageForUser() above. Only ever called
     * with 'paused' or 'active' from AdController — an advertiser
     * can't set 'pending'/'rejected'/etc. through this path.
     */
    public function updateStatusForUser(int $adId, int $userId, string $status): bool
    {
        $statement = Database::query(
            'UPDATE ads SET status = :status WHERE id = :id AND user_id = :user_id',
            ['id' => $adId, 'user_id' => $userId, 'status' => $status]
        );

        return $statement->rowCount() > 0;
    }

    /**
     * Advertiser-side Delete (10.n follow-up, later switched to soft
     * delete). Ownership guarantee via the WHERE clause, same pattern
     * as every other *ForUser() method here. A status flip to
     * 'deleted' rather than a hard DELETE — preserves ad_stats_daily/
     * ad_impressions/ad_clicks history instead of losing it to their
     * ON DELETE CASCADE, and means a mistaken delete isn't
     * unrecoverable. Every list/count query in this class excludes
     * 'deleted' explicitly, so the ad still disappears from the
     * advertiser's and admin's normal views exactly as it did under
     * the old hard delete.
     *
     * Existence is checked separately rather than trusting
     * rowCount() > 0 on the UPDATE itself — same reasoning as
     * updateStatus() above: re-deleting an already-'deleted' ad would
     * change no columns, so MySQL reports 0 rows affected even though
     * the row (still) matches, which would otherwise read as "not
     * found" for an ad that's actually just already deleted.
     */
    public function deleteForUser(int $adId, int $userId): bool
    {
        if (Database::fetchOne('SELECT id FROM ads WHERE id = :id AND user_id = :user_id', ['id' => $adId, 'user_id' => $userId]) === null) {
            return false;
        }

        Database::query(
            "UPDATE ads SET status = 'deleted' WHERE id = :id AND user_id = :user_id",
            ['id' => $adId, 'user_id' => $userId]
        );

        return true;
    }
}
