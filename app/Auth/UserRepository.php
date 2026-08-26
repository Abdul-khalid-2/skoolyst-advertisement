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
}
