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

    public function findByCode(string $code): ?array
    {
        return Database::fetchOne('SELECT * FROM apps WHERE code = :code', ['code' => $code]);
    }

    /**
     * Creates a placement (a named ad slot) for an app, or returns the
     * existing one's id if $code is already taken for that app —
     * `code` only has to be unique within its own app (5.2.g). Used by
     * MockDataSeeder to build the same placements the UI prototype's
     * mock-data.php hardcodes, and by any future "add placement" admin action.
     */
    public function findOrCreatePlacement(int $appId, string $code, string $label): int
    {
        $existing = Database::fetchOne(
            'SELECT id FROM placements WHERE app_id = :app_id AND code = :code',
            ['app_id' => $appId, 'code' => $code]
        );

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        Database::query(
            'INSERT INTO placements (app_id, code, label) VALUES (:app_id, :code, :label)',
            ['app_id' => $appId, 'code' => $code, 'label' => $label]
        );

        return (int) Database::connection()->lastInsertId();
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
