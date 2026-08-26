<?php

/**
 * Migration: ads (status, placement_id) index
 *
 * Section 5.2.a. Composite index backing `/ads/serve`, which filters
 * `ads` by `status = 'active'` scoped to a given `placement_id` (and
 * the admin moderation queue's `status` filter, Section 4).
 */

return [
    'up' => <<<SQL
        ALTER TABLE `ads`
            ADD INDEX `ads_status_placement_id_index` (`status`, `placement_id`);
    SQL,

    'down' => <<<SQL
        ALTER TABLE `ads`
            DROP INDEX `ads_status_placement_id_index`;
    SQL,
];
