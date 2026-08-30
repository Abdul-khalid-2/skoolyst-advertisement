<?php

/**
 * Migration: ads.advertiser_name
 *
 * The "Advertiser / Business Name" field on create-ad.php's form has
 * existed since the form was built, but was never wired to any
 * column — AdController::store()/update() never read it, and the
 * table had nowhere to put it even if they had. It was silently
 * discarded on every submit; edit mode then had nothing to prefill it
 * from. This column, plus the AdController/AdRepository changes
 * alongside it, closes that gap.
 *
 * DEFAULT '' (not NULL) so this ALTER doesn't fail against a table
 * that already has rows — every ad created before this migration
 * simply reads back as an empty business name, same as if the
 * advertiser had left the field blank.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `ads`
            ADD COLUMN `advertiser_name` VARCHAR(150) NOT NULL DEFAULT '' AFTER `user_id`;
    SQL,

    'down' => <<<SQL
        ALTER TABLE `ads`
            DROP COLUMN `advertiser_name`;
    SQL,
];
