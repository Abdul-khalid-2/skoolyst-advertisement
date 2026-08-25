<?php

/**
 * Migration: placements
 *
 * A placement is a named ad slot within a connected app (e.g. a
 * homepage banner, a sidebar unit). `code` only has to be unique
 * within its own app — see the unique composite index in 5.2.g.
 */

return [
    'up' => <<<SQL
        CREATE TABLE `placements` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `app_id` BIGINT UNSIGNED NOT NULL,
            `code` VARCHAR(100) NOT NULL,
            `label` VARCHAR(150) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `placements_app_id_fk`
                FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `placements`;',
];
