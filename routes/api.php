<?php

/**
 * Router boot.
 *
 * Merges every module's routes.php into a single route table at boot.
 * Adding a route to one module (e.g. Ads) never touches another
 * module's routes file — each module owns and edits only its own file.
 *
 * This merged table is the shared source both `routes/api-public.php`
 * and `routes/api-auth.php` filter from — the public API entry point
 * (`public/index.php`) requires those two files, not this one directly.
 */

$routes = [];

$moduleRouteFiles = [
    __DIR__ . '/../app/Ads/routes.php',
    __DIR__ . '/../app/Apps/routes.php',
    __DIR__ . '/../app/Auth/routes.php',
    __DIR__ . '/../app/Admin/routes.php',
];

foreach ($moduleRouteFiles as $routeFile) {
    $moduleRoutes = require $routeFile;
    $routes = array_merge($routes, $moduleRoutes);
}

return $routes;
