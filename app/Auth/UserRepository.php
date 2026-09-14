<?php

namespace App\Auth;

use Core\Database;

/**
 * UserRepository
 *
 * Owns all query logic for the `users` table. AuthController calls
 * this instead of running queries directly (same pattern as
 * AdRepository for the Ads module).
 */
class UserRepository
{
    public function findByEmail(string $email): ?UserModel
    {
        $row = Database::fetchOne(
            'SELECT * FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        return $row ? UserModel::fromRow($row) : null;
    }

    public function findById(int $id): ?UserModel
    {
        $row = Database::fetchOne(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return $row ? UserModel::fromRow($row) : null;
    }

    /**
     * Creates a new user. $passwordHash must already be the output of
     * password_hash() (Section 6.a) — this never hashes plaintext itself,
     * so it can't accidentally be called with a raw password.
     */
    public function create(string $name, string $email, string $passwordHash, string $role = 'advertiser'): UserModel
    {
        Database::query(
            'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)',
            [
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
                'role' => $role,
            ]
        );

        return $this->findByEmail($email);
    }

    /**
     * Every user (both roles) plus their own ad count — backs the admin
     * Advertisers screen's user-management table. Ad count uses the same
     * LEFT JOIN + COALESCE shape as AppRepository::allWithCounts().
     */
    public function allWithAdsCounts(): array
    {
        $rows = Database::query(
            <<<SQL
                SELECT
                    users.id, users.name, users.email, users.role, users.created_at,
                    COALESCE(ad_counts.total, 0) AS ads_count
                FROM users
                LEFT JOIN (
                    SELECT user_id, COUNT(*) AS total FROM ads GROUP BY user_id
                ) ad_counts ON ad_counts.user_id = users.id
                ORDER BY users.created_at DESC
            SQL
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['ads_count'] = (int) $row['ads_count'];
        }
        unset($row);

        return $rows;
    }

    /**
     * True if some OTHER user already owns this email — used to validate
     * both create (excludeId = null) and update (excludeId = the user
     * being edited, so keeping your own email doesn't trip the check).
     */
    public function emailTakenByOther(string $email, ?int $excludeId = null): bool
    {
        $row = Database::fetchOne(
            'SELECT id FROM users WHERE email = :email AND id != :exclude_id LIMIT 1',
            ['email' => $email, 'exclude_id' => $excludeId ?? 0]
        );

        return $row !== null;
    }

    /**
     * Updates name/email/role, and optionally the password hash when the
     * admin sets a new one. Returns false if the user doesn't exist.
     */
    public function update(int $id, string $name, string $email, string $role, ?string $passwordHash = null): bool
    {
        if (Database::fetchOne('SELECT id FROM users WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }

        if ($passwordHash !== null) {
            Database::query(
                'UPDATE users SET name = :name, email = :email, role = :role, password_hash = :password_hash WHERE id = :id',
                ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role, 'password_hash' => $passwordHash]
            );
        } else {
            Database::query(
                'UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id',
                ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role]
            );
        }

        return true;
    }

    /**
     * Deletes a user outright. `ads_user_id_fk` is ON DELETE CASCADE
     * (0004_create_ads_table.php), so this also removes every ad the
     * user owns — UserController warns for that before calling this.
     */
    public function delete(int $id): bool
    {
        if (Database::fetchOne('SELECT id FROM users WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }

        Database::query('DELETE FROM users WHERE id = :id', ['id' => $id]);

        return true;
    }
}
