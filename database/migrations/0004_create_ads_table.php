<?php

/**
 * Migration: ads
 *
 * The core record: an advertiser's ad, its moderation status, and
 * its schedule. `status` drives both the admin moderation queue
 * (Section 4: GET /admin/ads?status=pending) and what `/ads/serve`
 * is allowed to return (only 'active', within start/end date).
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ads` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `app_id` BIGINT UNSIGNED NOT NULL,
            `placement_id` BIGINT UNSIGNED NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `description` TEXT NULL,
            `image_path` VARCHAR(255) NULL,
            `cta_text` VARCHAR(50) NULL,
            `click_url` VARCHAR(255) NOT NULL,
            `status` ENUM('draft', 'pending', 'active', 'paused', 'rejected', 'ended')
                NOT NULL DEFAULT 'draft',
            `rejection_reason` VARCHAR(255) NULL,
            `start_date` DATE NULL,
            `end_date` DATE NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `ads_user_id_fk`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                ON DELETE CASCADE,
            CONSTRAINT `ads_app_id_fk`
                FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`)
                ON DELETE CASCADE,
            CONSTRAINT `ads_placement_id_fk`
                FOREIGN KEY (`placement_id`) REFERENCES `placements` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ads`;',
];
