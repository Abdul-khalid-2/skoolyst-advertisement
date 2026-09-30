<?php

/**
 * "Login with Skoolyst" — steps 3+4 of the flow. This exact URL is what
 * must be registered as the redirect_uri on skoolyst.com's Connected
 * Apps admin panel (one entry per environment — local + production);
 * an unregistered/mismatched redirect_uri is rejected by skoolyst.com
 * before it ever redirects back here.
 *
 * Never redirects a logged-in-looking state anywhere without verifying
 * `state` first (CSRF protection — see the guide's §2/§9 checklist).
 */

require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';

use App\Auth\SkoolystAuthService;
use Core\Auth\Middleware;
use Core\Security\Csrf;

Core\Env::load(__DIR__ . '/../../.env');

function skoolyst_login_failed(string $reason): void
{
    error_log("[skoolyst-auth] {$reason}");
    header('Location: ../login?sso_error=1');
    exit;
}

// Ensures the session started in skoolyst.php (where `state` was
// stashed) is the same one being read back here.
Middleware::checkSession();

// skoolyst.com itself can redirect back with ?error=... for things
// that happen on ITS side (e.g. the user denied/cancelled) — no code
// to exchange in that case.
if (isset($_GET['error'])) {
    skoolyst_login_failed('skoolyst.com returned error=' . $_GET['error']);
}

$code = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');
$expectedState = $_SESSION['skoolyst_oauth_state'] ?? null;
unset($_SESSION['skoolyst_oauth_state']); // one-time use either way

if ($code === '' || $state === '' || $expectedState === null || !hash_equals($expectedState, $state)) {
    skoolyst_login_failed('missing/mismatched state or code — possible CSRF, aborting');
}

$service = new SkoolystAuthService();
if (!$service->isConfigured()) {
    skoolyst_login_failed('SKOOLYST_AUTH_* not configured');
}

$result = $service->exchangeCodeForUser($code);
if (!$result['ok']) {
    skoolyst_login_failed('token exchange failed: ' . $result['message']);
}

if ($result['user']['email'] === '') {
    // Local accounts require a real, unique email (users.email is NOT
    // NULL + UNIQUE) — can't create/match a local record without one.
    skoolyst_login_failed('skoolyst.com did not return an email for this user');
}

$localUser = $service->findOrCreateLocalUser($result['user']);

Middleware::startSession($localUser->id);
Csrf::regenerate();

header('Location: ' . ($localUser->isAdmin() ? '../admin/index' : '../dashboard/my-ads'));
exit;
