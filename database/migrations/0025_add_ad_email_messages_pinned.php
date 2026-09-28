<?php

/**
 * Migration: ad_email_messages.pinned
 *
 * Backs the Email Inbox's pin action (admin/email-inbox.php) — a
 * pinned message sorts to the top of the list regardless of date.
 */

return [
    'up' => <<<SQL
        ALTER TABLE `ad_email_messages`
            ADD COLUMN `pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;
    SQL,

    'down' => <<<SQL
        ALTER TABLE `ad_email_messages`
            DROP COLUMN `pinned`;
    SQL,
];
