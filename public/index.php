<?php

/**
 * Front controller — single entry point for the application
 * (Section 9: public/ is the only publicly reachable folder).
 *
 * Pipeline: Router → Auth middleware → CSRF middleware → Rate-limit
 * middleware → Controller dispatch.
 */

require __DIR__ . '/../core/Env.php';
require __DIR__ . '/../core/Autoload.php';

use Core\Auth\Middleware;
use Core\Security\CsrfMiddleware;
use Core\RateLimiter;
use Core\Response;

Core\Env::load(__DIR__ . '/../.env');

$routes = array_merge(
    require __DIR__ . '/../routes/api-public.php',
    require __DIR__ . '/../routes/api-auth.php'
);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Routes are defined root-relative (e.g. `/api/v1/ads/serve`), which
// only matches as-is when public/ is a vhost's document root. Local
// dev under XAMPP/`htdocs` instead reaches this file through a
// subdirectory (e.g. `/Projects/.../public/index.php`), so REQUEST_URI
// carries that whole prefix too. Strip it — based on where this
// script actually lives (SCRIPT_NAME), not a hardcoded guess — so the
// same route table matches in both setups without editing every path.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir)) ?: '/';
}

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

// Handler dispatch — exact-path routes only for now. Dynamic-segment
// paths (`{id}`, `{filename}`) still never match in the loop above
// (Section 10.f note in app/Ads/routes.php), so they simply won't
// reach here yet; that's tracked separately from this fix, which
// only makes an already-matched route actually call its controller
// instead of the `echo 'ok'` stub every route was silently hitting.
[$class, $methodName] = $matched['handler'];

try {
    $controller = new $class();
    $controller->$methodName();
} catch (\Throwable $e) {
    $isLocal = (require __DIR__ . '/../config/app.php')['debug'];
    Response::error([
        'code' => 'server_error',
        'message' => $isLocal ? $e->getMessage() : 'Something went wrong.',
    ], 500);
}
