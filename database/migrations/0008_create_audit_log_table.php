<?php

/**
 * Migration: audit_log
 *
 * One row per sensitive admin action — ad approve/reject (6.v) and
 * API-key regeneration (6.w) so far, more added as new admin actions
 * ship. `subject_type` + `subject_id` point at the affected record
 * generically, so this one table covers every future action type
 * without a schema change.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `audit_log` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `admin_id` BIGINT UNSIGNED NOT NULL,
            `action` VARCHAR(100) NOT NULL,
            `subject_type` VARCHAR(50) NOT NULL,
            `subject_id` BIGINT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `audit_log_admin_id_fk`
                FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
                ON DELETE CASCADE,
            KEY `audit_log_subject_idx` (`subject_type`, `subject_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `audit_log`;',
];
