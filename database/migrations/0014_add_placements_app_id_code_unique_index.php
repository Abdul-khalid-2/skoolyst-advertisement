<?php

/**
 * Migration: placements (app_id, code) unique composite index
 *
 * Section 5.2.g. `code` only needs to be unique within its own app
 * (see the note on `placements.code` in migration 0003) — this is
 * the constraint that actually enforces that, and backs placement
 * lookups by `app_id` + `code`.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `placements`
            ADD UNIQUE INDEX `placements_app_id_code_unique` (`app_id`, `code`);
    SQL,

    'down' => <<<SQL
        ALTER TABLE `placements`
            DROP INDEX `placements_app_id_code_unique`;
    SQL,
];
