<?php

/**
 * Migration: ad_stats_daily
 *
 * Section 5.3.a. Daily per-ad rollup of `ad_impressions`/`ad_clicks`.
 * Dashboards and reports read this table; the raw event tables stay
 * write-once and are only queried by the rollup script itself (see
 * 5.3.b) and for auditing (5.3.e).
 *
 * One row per (ad_id, date) — the unique index also makes the
 * rollup's upsert (`INSERT ... ON DUPLICATE KEY UPDATE`) safe to
 * re-run for the same day without creating duplicates.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ad_stats_daily` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ad_id` BIGINT UNSIGNED NOT NULL,
            `date` DATE NOT NULL,
            `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
            `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `ad_stats_daily_ad_id_date_unique` (`ad_id`, `date`),
            CONSTRAINT `ad_stats_daily_ad_id_fk`
                FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ad_stats_daily`;',
];
