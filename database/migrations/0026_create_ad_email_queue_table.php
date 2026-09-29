<?php

/**
 * Migration: ad_email_queue
 *
 * POST /api/v1/email/send now enqueues here instead of sending SMTP
 * inline (10.s). Without a queue, two apps sending at the same instant
 * could both read the same sender account as "under its daily limit"
 * before either one's increment landed, over-sending past daily_limit,
 * and two SMTP sessions opening on the same Gmail account at once is
 * also exactly what trips Gmail's own abuse/rate limits. A single
 * worker (database/scripts/process-email-queue.php) claims and sends
 * one row at a time, so every send is fully serialized.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `ad_email_queue` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `source_app` VARCHAR(100) NOT NULL,
            `to_email` VARCHAR(191) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `body` MEDIUMTEXT NOT NULL,
            `status` ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
            `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `last_error` VARCHAR(500) NULL,
            `email_message_id` BIGINT UNSIGNED NULL,
            -- NULL = available immediately. On a failed attempt this is
            -- pushed into the future (see EmailQueueRepository::markFailed)
            -- so a retry waits for a later cron tick instead of being
            -- re-claimed again in the very same worker run.
            `next_attempt_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `ad_email_queue_status_idx` (`status`, `next_attempt_at`, `id`),
            CONSTRAINT `ad_email_queue_message_fk`
                FOREIGN KEY (`email_message_id`) REFERENCES `ad_email_messages` (`id`)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `ad_email_queue`;',
];
