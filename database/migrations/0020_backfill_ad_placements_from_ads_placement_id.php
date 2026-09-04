<?php

/**
 * Migration: backfill ad_placements
 *
 * Every ad that existed before ad_placements (migration 0019) only
 * has its single `ads.placement_id` recorded. This copies that into
 * the new junction table so every pre-existing ad keeps serving
 * exactly where it already was — a one-time data migration, not a
 * schema change, run once and never re-applied (the migration runner
 * tracks it the same as any other file here).
 *
 * `ads.placement_id` is NOT NULL (migration 0004), so every row
 * qualifies — no WHERE clause needed. Safe to run more than once by
 * hand if it ever had to be: the composite primary key on
 * (ad_id, placement_id) makes a repeat INSERT of the same pair fail
 * loudly instead of silently duplicating it, which is exactly what
 * should happen if this were ever run twice by mistake.
 */

return [
    'up' => <<<SQL
        INSERT INTO `ad_placements` (`ad_id`, `placement_id`)
        SELECT `id`, `placement_id` FROM `ads`;
    SQL,

    'down' => 'DELETE FROM `ad_placements`;',
];
