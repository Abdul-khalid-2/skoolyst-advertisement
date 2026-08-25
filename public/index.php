<?php

/**
 * Front controller — single entry point for the application
 * (Section 9: public/ is the only publicly reachable folder).
 *
 * Pipeline: Router → Auth middleware → Rate-limit middleware →
 * Controller (dispatch wired up once modules define real routes,
 * Section 4).
 */

require __DIR__ . '/../core/Auth/Middleware.php';
require __DIR__ . '/../core/RateLimiter.php';

use Core\Auth\Middleware;
use Core\RateLimiter;

$routes = require __DIR__ . '/../routes/api.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$matched = null;
foreach ($routes as $route) {
    if ($route['method'] === $method && $route['path'] === $path) {
        $matched = $route;
        break;
    }
}

if ($matched === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => ['message' => 'Not found']]);
    return;
}

// Auth middleware — routes marked 'auth' => true need a valid session
// or API key before reaching the controller.
$userId = Middleware::checkSession();
$apiKey = Middleware::checkApiKey();

if (($matched['auth'] ?? false) === true) {
    if ($userId === null && $apiKey === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => ['message' => 'Unauthorized']]);
        return;
    }
}

// Rate-limit middleware — applied to every request; per-route limits
// (e.g. stricter on /ads/serve) are tuned once Section 4 routes exist.
$rateLimiter = new RateLimiter();
$rateLimitKey = $apiKey ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (!$rateLimiter->hit($rateLimitKey)) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => ['message' => 'Too many requests']]);
    return;
}

// Handler dispatch is wired up once modules define real routes (Section 4).
echo 'ok';
