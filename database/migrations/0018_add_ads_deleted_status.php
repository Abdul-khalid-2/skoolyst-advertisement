<?php

/**
 * Migration: ads.status — add 'deleted'
 *
 * AdController::destroy() (10.n) used to hard-DELETE the row. Moved
 * to a soft delete instead: the advertiser's Delete button now flips
 * status to 'deleted' rather than removing the row, so ad_stats_daily
 * history and impression/click history survive a delete (previously
 * lost via those tables' ON DELETE CASCADE), and a deleted ad can
 * still be recovered by direct DB action if an advertiser deletes one
 * by mistake.
 *
 * Every query that lists/counts ads for the advertiser or admin
 * excludes 'deleted' explicitly (see AdRepository) so a soft-deleted
 * ad simply disappears from normal views, same as it did under the
 * old hard delete.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `ads`
            MODIFY COLUMN `status` ENUM('draft', 'pending', 'active', 'paused', 'rejected', 'ended', 'deleted')
                NOT NULL DEFAULT 'draft';
    SQL,

    'down' => <<<SQL
        ALTER TABLE `ads`
            MODIFY COLUMN `status` ENUM('draft', 'pending', 'active', 'paused', 'rejected', 'ended')
                NOT NULL DEFAULT 'draft';
    SQL,
];
