<?php

/**
 * Front controller — single entry point for the application
 * (Section 9: public/ is the only publicly reachable folder).
 *
 * Pipeline: Router → Auth middleware → CSRF middleware → Rate-limit
 * middleware → Controller dispatch.
 */

require __DIR__ . '/../core/Env.php';
require __DIR__ . '/../core/Auth/Middleware.php';
require __DIR__ . '/../core/Security/Csrf.php';
require __DIR__ . '/../core/Security/CsrfMiddleware.php';
require __DIR__ . '/../core/RateLimiter.php';

use Core\Auth\Middleware;
use Core\Security\CsrfMiddleware;
use Core\RateLimiter;

Core\Env::load(__DIR__ . '/../.env');

$routes = array_merge(
    require __DIR__ . '/../routes/api-public.php',
    require __DIR__ . '/../routes/api-auth.php'
);

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

// CSRF middleware (6.j) — only applies to session-based, state-changing
// requests. A connected app's API-key request is exempt (see
// CsrfMiddleware doc-block); it never has a session-rendered token.
$submittedCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);

if (!CsrfMiddleware::passes($method, $submittedCsrf, $apiKey !== null)) {
    http_response_code(419);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => ['message' => 'Invalid or missing CSRF token']]);
    return;
}

// Rate-limit middleware (6.s/6.t) — applied to every request, but
// tighter on the high-traffic public ad-serving/tracking routes than
// on ordinary dashboard/API calls.
$rateLimiter = new RateLimiter();
$rateLimitKey = $apiKey ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

$highTrafficPaths = ['/api/v1/ads/serve', '/api/v1/ads/{id}/impression', '/api/v1/ads/{id}/click'];
$isHighTraffic = in_array($matched['path'], $highTrafficPaths, true);

$allowed = $isHighTraffic
    ? $rateLimiter->hit($rateLimitKey, maxHits: 300, windowSeconds: 60)
    : $rateLimiter->hit($rateLimitKey, maxHits: 60, windowSeconds: 60);

if (!$allowed) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => ['message' => 'Too many requests']]);
    return;
}

// Handler dispatch is wired up once the router gains dynamic-segment
// matching for `{id}` paths (see note in app/Ads/routes.php).
echo 'ok';
