<?php

/**
 * Migration: ad_clicks (ad_id, occurred_at) index
 *
 * Section 5.2.e. Same rationale as `ad_impressions` (5.2.d) — backs
 * per-ad click lookups over a date range, for auditing and for the
 * 5.3 rollup script.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `ad_clicks`
            ADD INDEX `ad_clicks_ad_id_occurred_at_index` (`ad_id`, `occurred_at`);
    SQL,

    'down' => <<<SQL
        ALTER TABLE `ad_clicks`
            DROP INDEX `ad_clicks_ad_id_occurred_at_index`;
    SQL,
];
