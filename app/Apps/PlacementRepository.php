<?php

namespace App\Apps;

use Core\Database;

/**
 * PlacementRepository
 *
 * Owns all query logic for the `placements` table — the named ad
 * slots (e.g. "homepage-banner", "sidebar-unit") each connected app
 * defines for itself under Admin → Connected Apps
 * (https://ads.skoolyst.com/admin/apps.php). A placement always
 * belongs to exactly one app (`app_id`, FK `ON DELETE CASCADE` —
 * migration 0003) and its `code` only has to be unique within that
 * app, not globally (the composite unique index from migration
 * 0014, enforced again here with an app-scoped pre-check so a
 * duplicate reports as a clean `validation_error` rather than a raw
 * DB constraint failure bubbling up as a 500).
 */
class PlacementRepository
{
    /**
     * Every placement for one app, most recently added first — what
     * the "Manage Placements" panel on admin/apps.php lists.
     *
     * @return array<int, array{id: int, app_id: int, code: string, label: string, created_at: string}>
     */
    public function allForApp(int $appId): array
    {
        return Database::query(
            'SELECT id, app_id, code, label, created_at FROM placements WHERE app_id = :app_id ORDER BY created_at DESC',
            ['app_id' => $appId]
        )->fetchAll();
    }

    /**
     * One placement by id, or null if it doesn't exist. Callers that
     * need to confirm a placement belongs to a specific app (e.g.
     * before editing/deleting it via a URL a client could tamper
     * with) should compare the returned row's `app_id` themselves,
     * the same ownership-check shape `AppRepository::resolveAppId()`
     * expects of its own callers.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $placementId): ?array
    {
        return Database::fetchOne(
            'SELECT id, app_id, code, label, created_at FROM placements WHERE id = :id',
            ['id' => $placementId]
        );
    }

    /**
     * True if another placement on this app already uses this code.
     * Checked before insert/update so a duplicate fails with a plain
     * `validation_error` instead of the DB's unique-constraint
     * exception (migration 0014) surfacing as an uncaught 500.
     *
     * $excludePlacementId lets update() check "any placement but this
     * one" instead of always tripping on the row's own current code.
     */
    public function codeExistsForApp(int $appId, string $code, ?int $excludePlacementId = null): bool
    {
        $sql = 'SELECT id FROM placements WHERE app_id = :app_id AND code = :code';
        $params = ['app_id' => $appId, 'code' => $code];

        if ($excludePlacementId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludePlacementId;
        }

        return Database::fetchOne($sql, $params) !== null;
    }

    /**
     * Creates a new placement under an app. Uniqueness of (app_id,
     * code) is the caller's (PlacementController::store's)
     * responsibility to check first via codeExistsForApp() — kept
     * out of this method so it stays a plain insert, same split as
     * AppRepository::createWithApiKey() vs. its controller's own
     * required-field check.
     *
     * @return array<string, mixed>
     */
    public function create(int $appId, string $code, string $label): array
    {
        Database::query(
            'INSERT INTO placements (app_id, code, label) VALUES (:app_id, :code, :label)',
            ['app_id' => $appId, 'code' => $code, 'label' => $label]
        );

        $placement = Database::fetchOne(
            'SELECT id, app_id, code, label, created_at FROM placements WHERE app_id = :app_id AND code = :code',
            ['app_id' => $appId, 'code' => $code]
        );

        // Never null in practice (the insert above just succeeded),
        // but keep the return type honest for callers rather than
        // silently returning an empty array on some future change.
        return $placement ?? [];
    }

    /**
     * Updates a placement's code/label. Returns false if the id
     * doesn't exist, the same "existence checked separately from
     * rowCount()" shape as AppRepository::updateStatus() uses —
     * re-saving a placement with its own unchanged code/label would
     * otherwise report 0 rows changed and misread as "not found".
     */
    public function update(int $placementId, string $code, string $label): bool
    {
        if ($this->find($placementId) === null) {
            return false;
        }

        Database::query(
            'UPDATE placements SET code = :code, label = :label WHERE id = :id',
            ['id' => $placementId, 'code' => $code, 'label' => $label]
        );

        return true;
    }

    /**
     * True if any ad (any status, including ended/rejected ones —
     * moderation history shouldn't silently disappear) still
     * references this placement. `ads.placement_id` has a real
     * `ON DELETE CASCADE` FK back to `placements` (migration 0004) —
     * deleting a placement an ad still points to would silently wipe
     * that ad out at the DB layer, not just stop it being servable.
     * PlacementController::destroy() checks this first and refuses
     * the delete with a clean `conflict` error instead of ever
     * letting that cascade fire.
     */
    public function hasAds(int $placementId): bool
    {
        return Database::fetchOne(
            'SELECT id FROM ads WHERE placement_id = :placement_id LIMIT 1',
            ['placement_id' => $placementId]
        ) !== null;
    }

    /**
     * Deletes a placement outright — not a soft delete, since a
     * placement is just a slot definition, not user-authored content
     * like an ad. Returns false if the id doesn't exist. Callers MUST
     * check hasAds() first (see its doc-block): this method itself
     * doesn't guard against the FK's `ON DELETE CASCADE`, so calling
     * it on a placement with ads still attached would take those ads
     * down with it.
     */
    public function delete(int $placementId): bool
    {
        if ($this->find($placementId) === null) {
            return false;
        }

        Database::query('DELETE FROM placements WHERE id = :id', ['id' => $placementId]);

        return true;
    }
}
