<?php

/**
 * Migration: ads (start_date, end_date) index
 *
 * Section 5.2.c. Backs the schedule check in `/ads/serve` — an ad
 * can only be served while today falls within its `start_date` and
 * `end_date` window.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `ads`
            ADD INDEX `ads_start_date_end_date_index` (`start_date`, `end_date`);
    SQL,

    'down' => <<<SQL
        ALTER TABLE `ads`
            DROP INDEX `ads_start_date_end_date_index`;
    SQL,
];
