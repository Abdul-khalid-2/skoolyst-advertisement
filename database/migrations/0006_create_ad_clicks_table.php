<?php

/**
 * Migration: ad_clicks
 *
 * Raw click events, logged by POST /ads/{id}/click (also used as the
 * public redirect URL). Same shape and same rollup relationship as
 * `ad_impressions` — kept for auditing only once `ad_stats_daily` (5.3)
 * exists.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ad_clicks` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ad_id` BIGINT UNSIGNED NOT NULL,
            `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ip_hash` VARCHAR(64) NULL,
            `user_agent_hash` VARCHAR(64) NULL,
            CONSTRAINT `ad_clicks_ad_id_fk`
                FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ad_clicks`;',
];
