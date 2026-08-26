<?php

/**
 * Migration: ad_impressions (ad_id, occurred_at) index
 *
 * Section 5.2.d. Backs per-ad impression lookups over a date range —
 * used for auditing today, and by the 5.3 rollup script once it
 * exists (raw events → `ad_stats_daily` per ad, per day).
 */

return [
    'up' => <<<SQL
        ALTER TABLE `ad_impressions`
            ADD INDEX `ad_impressions_ad_id_occurred_at_index` (`ad_id`, `occurred_at`);
    SQL,

    'down' => <<<SQL
        ALTER TABLE `ad_impressions`
            DROP INDEX `ad_impressions_ad_id_occurred_at_index`;
    SQL,
];
