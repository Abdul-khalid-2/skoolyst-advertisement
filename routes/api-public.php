<?php

/**
 * Public API routes — no session/API-key required before dispatch.
 * Covers the ad-serving and tracking endpoints connected apps call
 * directly (e.g. GET /ads/serve, POST /impression, POST /click).
 *
 * Filtered from the full merged table in routes/api.php, keyed on
 * each route's `auth` flag, so a route only has to be marked
 * `auth => false` once, in its module's routes.php — never listed
 * twice.
 */

$allRoutes = require __DIR__ . '/api.php';

return array_values(array_filter(
    $allRoutes,
    fn (array $route): bool => ($route['auth'] ?? false) === false
));
