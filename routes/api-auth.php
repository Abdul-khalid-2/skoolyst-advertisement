<?php

/**
 * Authenticated API routes — a valid session or API key is required
 * before dispatch. Covers advertiser (create/edit ads) and admin
 * (moderation, connected-app management) endpoints.
 *
 * Filtered from the full merged table in routes/api.php, keyed on
 * each route's `auth` flag, so a route only has to be marked
 * `auth => true` once, in its module's routes.php — never listed
 * twice.
 */

$allRoutes = require __DIR__ . '/api.php';

return array_values(array_filter(
    $allRoutes,
    fn (array $route): bool => ($route['auth'] ?? false) === true
));
