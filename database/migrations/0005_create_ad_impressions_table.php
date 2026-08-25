<?php

/**
 * Migration: ad_impressions
 *
 * Raw impression events, logged by POST /ads/{id}/impression. Kept
 * for auditing/fraud checks only once the daily rollup (5.3) exists —
 * dashboards read `ad_stats_daily`, not this table, once that lands.
 * `ip_hash`/`user_agent_hash` are stored hashed, never raw, per the
 * privacy note in Section 6.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ad_impressions` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ad_id` BIGINT UNSIGNED NOT NULL,
            `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ip_hash` VARCHAR(64) NULL,
            `user_agent_hash` VARCHAR(64) NULL,
            CONSTRAINT `ad_impressions_ad_id_fk`
                FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ad_impressions`;',
];
