<?php

/**
 * "Login with Skoolyst" — step 2 of the flow (login.php's "Login with
 * Skoolyst" button points here). Generates and stores the CSRF `state`
 * value, then redirects the browser to skoolyst.com's own authorize
 * endpoint. See app/Auth/SkoolystAuthService.php and
 * public/auth/skoolyst-callback.php for the rest of the flow.
 */

require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';

use App\Auth\SkoolystAuthService;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

$service = new SkoolystAuthService();
if (!$service->isConfigured()) {
    http_response_code(500);
    echo 'Login with Skoolyst is not configured on this server (missing SKOOLYST_AUTH_* in .env).';
    exit;
}

// Just to get a session going before login — checkSession() starts one
// as a side effect even though nobody's logged in yet, same trick
// skoolyst-callback.php uses to read the state back.
Middleware::checkSession();

$state = bin2hex(random_bytes(16));
$_SESSION['skoolyst_oauth_state'] = $state;

header('Location: ' . $service->buildAuthorizeUrl($state));
exit;
