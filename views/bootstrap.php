<?php
/**
 * Shared bootstrap for every dashboard/admin page. Loads the mock data
 * (until Section 3/4 replace this with real DB-backed repositories),
 * loads tooltip copy, and pulls in every reusable component function.
 *
 * Every page starts with:
 *   require __DIR__ . '/../views/bootstrap.php';
 */

$mockData = require __DIR__ . '/../data/mock-data.php';
$helpText = require __DIR__ . '/../config/help-text.php';

/** Returns ' active' when $key matches the page's active nav item, else ''. */
function nav_active(string $key, string $activeNav): string
{
    return $key === $activeNav ? ' active' : '';
}

require __DIR__ . '/components/status-badge.php';
require __DIR__ . '/components/stat-card.php';
require __DIR__ . '/components/app-chip.php';
require __DIR__ . '/components/help-icon.php';
require __DIR__ . '/components/ads-table.php';
require __DIR__ . '/components/modal-confirm.php';
