<?php

/**
 * Migration: apps (api_key_hash) unique index
 *
 * Section 5.2.f. Backs the lookup every authenticated API request
 * does to resolve a connected app from its key hash, and guarantees
 * no two apps can collide on the same hash.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `apps`
            ADD UNIQUE INDEX `apps_api_key_hash_unique` (`api_key_hash`);
    SQL,

    'down' => <<<SQL
        ALTER TABLE `apps`
            DROP INDEX `apps_api_key_hash_unique`;
    SQL,
];
