<?php

/**
 * Migration: ad_placements
 *
 * An ad used to be tied to exactly one placement (`ads.placement_id`,
 * migration 0004) — Admin's own Connected Apps page can define
 * several placements per app (e.g. Header, Footer, Sidebar on
 * teachers.skoolyst.com), but there was no way to make one ad show on
 * more than one of them. This junction table is the actual fix
 * (10.p): every placement an ad should serve on now gets a row here,
 * so a single ad can target 1, 2, or all of an app's placements.
 *
 * `ads.placement_id` itself is left in place rather than dropped —
 * it now just records the first placement an ad was submitted with
 * (kept for cheap "which placement" lookups and to avoid a wider,
 * riskier schema change), but this table is the actual source of
 * truth for which placements an ad serves on
 * (AdRepository::findServableForPlacement()).
 *
 * Composite primary key instead of a surrogate `id`: (ad_id,
 * placement_id) is exactly the fact being recorded, and the pair
 * already has to be unique, so a separate `id` column would only add
 * an index nothing here needs. Both foreign keys cascade on delete,
 * same behavior `ads.placement_id`'s own FK already has — deleting an
 * ad or a placement removes just its link rows, not any other ad or
 * placement (PlacementRepository::hasAds() still exists specifically
 * to warn an admin *before* that placement-side cascade fires, per
 * 10.o).
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ad_placements` (
            `ad_id` BIGINT UNSIGNED NOT NULL,
            `placement_id` BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (`ad_id`, `placement_id`),
            CONSTRAINT `ad_placements_ad_id_fk`
                FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`)
                ON DELETE CASCADE,
            CONSTRAINT `ad_placements_placement_id_fk`
                FOREIGN KEY (`placement_id`) REFERENCES `placements` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ad_placements`;',
];
