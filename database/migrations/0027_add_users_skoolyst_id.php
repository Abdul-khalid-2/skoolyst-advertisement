<?php

/**
 * Migration: users.skoolyst_id
 *
 * Backs "Login with Skoolyst" (10.t) — the stable key (skoolyst.com's
 * own user id) a local account is found-or-created by, per the
 * integration guide's "key local users by user.id, not by email" rule
 * (a Skoolyst user can change their email; their id never changes).
 * NULL for every account created the normal way (register/admin-created)
 * — only SSO-created accounts ever have this set.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `users`
            ADD COLUMN `skoolyst_id` BIGINT UNSIGNED NULL AFTER `role`,
            ADD UNIQUE KEY `users_skoolyst_id_unique` (`skoolyst_id`);
    SQL,

    'down' => <<<SQL
        ALTER TABLE `users`
            DROP INDEX `users_skoolyst_id_unique`,
            DROP COLUMN `skoolyst_id`;
    SQL,
];
