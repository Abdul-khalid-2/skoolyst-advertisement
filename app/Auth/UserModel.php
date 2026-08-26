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
