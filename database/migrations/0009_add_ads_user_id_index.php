<?php

/**
 * Migration: ads (user_id) index
 *
 * Section 5.2.b. Backs the advertiser's "My Ads" list (dashboard),
 * which always scopes `ads` to the logged-in user's `user_id`.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `ads`
            ADD INDEX `ads_user_id_index` (`user_id`);
    SQL,

    'down' => <<<SQL
        ALTER TABLE `ads`
            DROP INDEX `ads_user_id_index`;
    SQL,
];
