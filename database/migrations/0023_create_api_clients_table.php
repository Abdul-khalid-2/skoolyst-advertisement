<?php

/**
 * Migration: ad_api_clients
 *
 * One row per external Skoolyst app allowed to call /api/v1/email/*.
 * Only the SHA-256 hash of the key is stored (same approach as
 * apps.api_key_hash) — the plaintext is shown once, at creation.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ad_api_clients` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `app_name` VARCHAR(100) NOT NULL,
            `api_key_hash` CHAR(64) NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` DATETIME NULL,
            UNIQUE KEY `ad_api_clients_app_name_unique` (`app_name`),
            UNIQUE KEY `ad_api_clients_api_key_hash_unique` (`api_key_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ad_api_clients`;',
];
