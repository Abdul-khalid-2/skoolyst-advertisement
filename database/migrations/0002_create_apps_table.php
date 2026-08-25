<?php

/**
 * Migration: apps
 *
 * Connected apps (skoolyst.com, social.skoolyst.com, teachers.skoolyst.com,
 * or outside apps) that call the AdEngine API. `api_key_hash` here is
 * kept for quick lookups; the full key lifecycle (issuing, revoking,
 * multiple keys per app) lives in `api_keys` (5.1.g).
 */

return [
    'up' => <<<SQL
        CREATE TABLE `apps` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `code` VARCHAR(100) NOT NULL,
            `domain` VARCHAR(255) NOT NULL,
            `api_key_hash` VARCHAR(255) NOT NULL,
            `status` ENUM('active', 'paused') NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `apps_code_unique` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL,

    'down' => 'DROP TABLE IF EXISTS `apps`;',
];
