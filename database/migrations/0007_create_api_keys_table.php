<?php

/**
 * Migration: api_keys
 *
 * The full lifecycle of a connected app's key(s): issued, hashed on
 * storage (never plaintext, Section 6), tracked by last use, and
 * revocable without deleting the app record itself.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `api_keys` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `app_id` BIGINT UNSIGNED NOT NULL,
            `key_hash` VARCHAR(255) NOT NULL,
            `last_used_at` DATETIME NULL,
            `revoked_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `api_keys_app_id_fk`
                FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `api_keys`;',
];
