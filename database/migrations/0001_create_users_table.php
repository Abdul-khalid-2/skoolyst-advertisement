<?php

/**
 * Migration: users
 *
 * Advertisers and platform admins both live in this one table,
 * distinguished by `role` — see Section 6 for password hashing and
 * session auth built on top of it.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `users` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `email` VARCHAR(191) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('advertiser', 'admin') NOT NULL DEFAULT 'advertiser',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `users_email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `users`;',
];
