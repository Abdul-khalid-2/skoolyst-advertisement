<?php

namespace App\Email;

use Core\Database;

/** Owns `ad_api_clients` — external apps allowed to use /api/v1/email/*. */
class ApiClientRepository
{
    /** @return array<int, array<string, mixed>> never includes the key hash */
    public function all(): array
    {
        return Database::query('SELECT id, app_name, active, created_at, last_used_at FROM ad_api_clients ORDER BY id ASC')->fetchAll();
    }

    public function appNameTaken(string $appName): bool
    {
        return Database::fetchOne('SELECT id FROM ad_api_clients WHERE app_name = :n', ['n' => $appName]) !== null;
    }

    /** @return string plaintext key — returned once, only its hash is stored */
    public function create(string $appName): string
    {
        $key = self::generateKey();
        Database::query(
            'INSERT INTO ad_api_clients (app_name, api_key_hash) VALUES (:n, :h)',
            ['n' => $appName, 'h' => self::hash($key)]
        );

        return $key;
    }

    public function regenerateKey(int $id): ?string
    {
        if (Database::fetchOne('SELECT id FROM ad_api_clients WHERE id = :id', ['id' => $id]) === null) {
            return null;
        }
        $key = self::generateKey();
        Database::query('UPDATE ad_api_clients SET api_key_hash = :h WHERE id = :id', ['h' => self::hash($key), 'id' => $id]);

        return $key;
    }

    public function setActive(int $id, bool $active): bool
    {
        if (Database::fetchOne('SELECT id FROM ad_api_clients WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }
        Database::query('UPDATE ad_api_clients SET active = :a WHERE id = :id', ['a' => $active ? 1 : 0, 'id' => $id]);

        return true;
    }

    public function delete(int $id): bool
    {
        if (Database::fetchOne('SELECT id FROM ad_api_clients WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }
        Database::query('DELETE FROM ad_api_clients WHERE id = :id', ['id' => $id]);

        return true;
    }

    /** @return array{id: int, app_name: string}|null active client for this plaintext key */
    public function authenticate(string $plaintextKey): ?array
    {
        $row = Database::fetchOne(
            'SELECT id, app_name FROM ad_api_clients WHERE api_key_hash = :h AND active = 1',
            ['h' => self::hash($plaintextKey)]
        );
        if ($row === null) {
            return null;
        }
        Database::query('UPDATE ad_api_clients SET last_used_at = NOW() WHERE id = :id', ['id' => $row['id']]);

        return ['id' => (int) $row['id'], 'app_name' => $row['app_name']];
    }

    private static function generateKey(): string
    {
        return 'sk_mail_' . bin2hex(random_bytes(24));
    }

    private static function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
