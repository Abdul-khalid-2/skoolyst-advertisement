<?php

/**
 * Migration: ad_email_messages
 *
 * Log of every email sent (or received) through the centralized email
 * service — backs the admin inbox view. `title` = subject, `subtitle` =
 * counterpart address, `description` = body.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ad_email_messages` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `source_app` VARCHAR(100) NOT NULL,
            `email_account_id` BIGINT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `subtitle` VARCHAR(255) NULL,
            `description` MEDIUMTEXT NOT NULL,
            `direction` ENUM('sent', 'received') NOT NULL,
            `status` ENUM('read', 'unread') NOT NULL DEFAULT 'unread',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `ad_email_messages_source_app_idx` (`source_app`),
            KEY `ad_email_messages_status_idx` (`status`),
            CONSTRAINT `ad_email_messages_account_fk`
                FOREIGN KEY (`email_account_id`) REFERENCES `ad_email_accounts` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ad_email_messages`;',
];
