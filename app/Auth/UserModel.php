<?php

namespace App\Auth;

/**
 * UserModel
 *
 * Represents a single user record (maps to the `users` table defined
 * in Section 5), covering both advertiser and admin roles. Holds
 * data/shape only — no query logic here (see UserRepository).
 */
class UserModel
{
    public int $id;
    public string $name;
    public string $email;
    public string $passwordHash;
    public string $role;
    public ?int $skoolystId;
    public string $createdAt;

    /**
     * @param array<string, mixed> $row Raw row from UserRepository.
     */
    public static function fromRow(array $row): self
    {
        $user = new self();
        $user->id = (int) $row['id'];
        $user->name = (string) $row['name'];
        $user->email = (string) $row['email'];
        $user->passwordHash = (string) $row['password_hash'];
        $user->role = (string) $row['role'];
        // isset() would treat a NULL column value as "not set" and skip
        // the cast either way, but array_key_exists() makes the intent
        // explicit: skoolyst_id really can be legitimately NULL here.
        $user->skoolystId = array_key_exists('skoolyst_id', $row) && $row['skoolyst_id'] !== null
            ? (int) $row['skoolyst_id']
            : null;
        $user->createdAt = (string) $row['created_at'];

        return $user;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAdvertiser(): bool
    {
        return $this->role === 'advertiser';
    }
}
