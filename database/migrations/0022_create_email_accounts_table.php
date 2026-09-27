<?php

/**
 * Migration: ad_email_accounts
 *
 * Pool of SMTP sender accounts for the centralized email service
 * (app/Email). `app_password` holds Core\Crypto ciphertext, never the
 * plaintext. `sent_count`/`received_count` reset daily (see
 * EmailAccountRepository::resetIfNewDay); `status` is `exhausted` once
 * sent_count reaches `daily_limit` and flips back to `active` on reset.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ad_email_accounts` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(191) NOT NULL,
            `app_password` TEXT NOT NULL,
            `daily_limit` INT UNSIGNED NOT NULL DEFAULT 40,
            `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `received_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_reset_date` DATE NULL,
            `status` ENUM('active', 'exhausted', 'disabled') NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `ad_email_accounts_email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ad_email_accounts`;',
];
