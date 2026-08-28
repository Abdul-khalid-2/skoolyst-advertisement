<?php

namespace App\Apps;

use Core\Database;

/**
 * AppRepository
 *
 * Owns all query logic for the `apps` and `api_keys` tables. Handles
 * API-key generation/hashing (6.d/6.e) and the lookup connected-app
 * requests go through to resolve their `app_id` (6.u — every other
 * query is then scoped to that app_id, never to the raw key).
 */
class AppRepository
{
    public function all(): array
    {
        return Database::query('SELECT id, name, code, domain, status, created_at FROM apps ORDER BY created_at DESC')
            ->fetchAll();
    }

    /**
     * Same as all(), plus each app's placement count and total ad
     * count — what admin/apps.php's connected-apps grid shows (10.i)
     * instead of data/mock-data.php's hardcoded numbers. Ad count is
     * every ad regardless of status, matching the mock UI's own
     * `ads.filter(a => a.app === app.id).length`.
     */
    public function allWithCounts(): array
    {
        $apps = Database::query(
            <<<SQL
                SELECT
                    apps.id, apps.name, apps.code, apps.domain, apps.status, apps.created_at,
                    COALESCE(placement_counts.total, 0) AS placements_count,
                    COALESCE(ad_counts.total, 0) AS ads_count
                FROM apps
                LEFT JOIN (
                    SELECT app_id, COUNT(*) AS total FROM placements GROUP BY app_id
                ) placement_counts ON placement_counts.app_id = apps.id
                LEFT JOIN (
                    SELECT app_id, COUNT(*) AS total FROM ads GROUP BY app_id
                ) ad_counts ON ad_counts.app_id = apps.id
                ORDER BY apps.created_at DESC
            SQL
        )->fetchAll();

        foreach ($apps as &$app) {
            $app['placements_count'] = (int) $app['placements_count'];
            $app['ads_count'] = (int) $app['ads_count'];
        }
        unset($app);

        return $apps;
    }

    /**
     * Every active app plus its own placements, nested — what the
     * advertiser-facing "new ad" form (create-ad.php, 10.f) needs to
     * populate its app/placement pickers with real ids instead of
     * data/mock-data.php's string codes, which can't satisfy the
     * `ads.app_id`/`ads.placement_id` foreign keys. A paused app can't
     * receive new ads, so it's excluded here rather than filtered
     * client-side.
     *
     * @return array<int, array{id: int, name: string, code: string, domain: string, placements: array<int, array{id: int, code: string, label: string}>}>
     */
    public function allActiveWithPlacements(): array
    {
        $apps = Database::query(
            "SELECT id, name, code, domain FROM apps WHERE status = 'active' ORDER BY name ASC"
        )->fetchAll();

        foreach ($apps as &$app) {
            $app['placements'] = Database::query(
                'SELECT id, code, label FROM placements WHERE app_id = :app_id ORDER BY label ASC',
                ['app_id' => $app['id']]
            )->fetchAll();
        }
        unset($app);

        return $apps;
    }

    /**
     * Registers a new connected app and issues its first API key.
     * Returns the plaintext key alongside the app row — the ONLY time
     * the plaintext ever exists outside the admin's clipboard; only
     * the hash is persisted (6.e).
     *
     * @return array{app: array<string, mixed>, api_key: string}
     */
    public function createWithApiKey(string $name, string $code, string $domain): array
    {
        $apiKey = self::generateApiKey();
        $keyHash = self::hashApiKey($apiKey);

        Database::query(
            'INSERT INTO apps (name, code, domain, api_key_hash, status) VALUES (:name, :code, :domain, :hash, :status)',
            ['name' => $name, 'code' => $code, 'domain' => $domain, 'hash' => $keyHash, 'status' => 'active']
        );

        $app = Database::fetchOne('SELECT id, name, code, domain, status, created_at FROM apps WHERE code = :code', ['code' => $code]);
        $appId = (int) $app['id'];

        Database::query(
            'INSERT INTO api_keys (app_id, key_hash) VALUES (:app_id, :hash)',
            ['app_id' => $appId, 'hash' => $keyHash]
        );

        return ['app' => $app, 'api_key' => $apiKey];
    }

    /**
     * Issues a new key for an existing app, revokes every previous key
     * for that app, and updates the app's own api_key_hash lookup
     * column so it stays in sync (6.d, plus the audit-log write for
     * this action happens in AppController::regenerateKey, 6.w).
     *
     * @return string The new plaintext key.
     */
    public function regenerateApiKey(int $appId): string
    {
        $apiKey = self::generateApiKey();
        $keyHash = self::hashApiKey($apiKey);

        Database::query('UPDATE api_keys SET revoked_at = NOW() WHERE app_id = :app_id AND revoked_at IS NULL', ['app_id' => $appId]);
        Database::query('INSERT INTO api_keys (app_id, key_hash) VALUES (:app_id, :hash)', ['app_id' => $appId, 'hash' => $keyHash]);
        Database::query('UPDATE apps SET api_key_hash = :hash WHERE id = :app_id', ['hash' => $keyHash, 'app_id' => $appId]);

        return $apiKey;
    }

    /**
     * Toggles an app between active/paused (10.i, admin/apps.php's
     * connect-switch) — kept generic here; the audit-log write for
     * which direction happened lives in AppController::update(), the
     * same split as AdRepository::updateStatus()/ModerationController.
     */
    public function updateStatus(int $appId, string $status): bool
    {
        // rowCount() on an UPDATE reports rows actually changed, not
        // rows matched (MySQL's default, no CLIENT_FOUND_ROWS flag) —
        // toggling to a status the app is already in would otherwise
        // read as "not found" and 404 even though the app exists.
        // Existence is confirmed separately instead.
        if (Database::fetchOne('SELECT id FROM apps WHERE id = :id', ['id' => $appId]) === null) {
            return false;
        }

        Database::query(
            'UPDATE apps SET status = :status WHERE id = :id',
            ['id' => $appId, 'status' => $status]
        );

        return true;
    }

    /**
     * Resolves a bearer token (as extracted by Middleware::checkApiKey)
     * to the app_id it belongs to, or null if it's invalid/revoked.
     * Every subsequent query for that request must filter by this
     * app_id — never trust an app_id supplied in the request body
     * (6.u: a key can only ever see/affect its own app's placements).
     */
    public function resolveAppId(string $plaintextKey): ?int
    {
        $hash = self::hashApiKey($plaintextKey);

        $row = Database::fetchOne(
            'SELECT app_id FROM api_keys WHERE key_hash = :hash AND revoked_at IS NULL LIMIT 1',
            ['hash' => $hash]
        );

        if ($row === null) {
            return null;
        }

        Database::query('UPDATE api_keys SET last_used_at = NOW() WHERE key_hash = :hash', ['hash' => $hash]);

        return (int) $row['app_id'];
    }

    /**
     * Generates a high-entropy plaintext key. Prefixed so it's visibly
     * distinguishable from a session token or password in logs.
     */
    private static function generateApiKey(): string
    {
        return 'sk_ad_' . bin2hex(random_bytes(32));
    }

    /**
     * Hashed with SHA-256, not password_hash(): this is a
     * high-entropy machine credential looked up by exact value on
     * every API request, not a low-entropy human password that needs
     * a slow, salted algorithm to resist guessing (6.e).
     */
    private static function hashApiKey(string $plaintextKey): string
    {
        return hash('sha256', $plaintextKey);
    }
}
