<?php

/**
 * Migration: apps.sort_order
 *
 * Backs the Connected Apps grid's manual drag-and-drop reordering
 * (admin/apps.php) — cards are shown ordered by `sort_order` ASC,
 * `id` ASC. Every existing row defaults to 0, so until an admin
 * actually drags a card, that tiebreaker on `id` (assigned in
 * creation order) keeps the grid exactly where it was before this
 * column existed — no separate backfill statement needed.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `apps`
            ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`;
    SQL,

    'down' => <<<SQL
        ALTER TABLE `apps`
            DROP COLUMN `sort_order`;
    SQL,
];
