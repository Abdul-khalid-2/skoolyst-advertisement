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

/**
 * Turn a route path with `{param}` placeholders (e.g.
 * `/api/v1/admin/ads/{id}/approve`) into a regex so a real request
 * path like `.../ads/42/approve` matches. Every current handler that
 * needs an id reads it from the request body (Request::int('ad_id')
 * in ModerationController etc.), not the URL, so the matched segment
 * is only used to confirm the route, never extracted or bound.
 * Segment-by-segment (not preg_quote on the whole string first) so
 * `{id}`'s own braces don't get escaped before they're detected.
 */
function routePathToRegex(string $routePath): string
{
    $segments = array_map(
        static function (string $segment): string {
            return preg_match('/^\{[a-zA-Z_][a-zA-Z0-9_]*\}$/', $segment) === 1
                ? '[^/]+'
                : preg_quote($segment, '#');
        },
        explode('/', $routePath)
    );

    return implode('/', $segments);
}

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
    if ($route['method'] !== $method) {
        continue;
    }
    if (preg_match('#^' . routePathToRegex($route['path']) . '$#', $path)) {
        $matched = $route;
        break;
    }
}

if ($matched === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    $body = ['success' => false, 'error' => ['message' => 'Not found']];
    // Temporary: surface exactly what the subdirectory-stripping logic
    // above saw, so a 404 that "should" match can actually be diagnosed
    // instead of guessed at. Debug-mode only (APP_DEBUG=true, the local
    // default) — never reaches a real deploy where APP_DEBUG=false.
    if ((require __DIR__ . '/../config/app.php')['debug']) {
        $body['debug'] = [
            'method' => $method,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
            'computed_scriptDir' => $scriptDir,
            'computed_path' => $path,
        ];
    }
    echo json_encode($body);
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

if (!CsrfMiddleware::passes($method, $submittedCsrf, $apiKey !== null, ($matched['auth'] ?? false) === true)) {
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

// Handler dispatch.
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
