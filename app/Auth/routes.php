<?php

/**
 * Auth module routes.
 *
 * Merged into the main router at boot (see router boot code, 3.1.j).
 * Login, session, and logout endpoints (Section 6). API-key issuing
 * for connected apps lives in the Apps module (app/Apps/routes.php),
 * not here — a "user" logging in and an "app" being issued a key are
 * different entities. Every path added must be prefixed `/api/v1/`
 * (Section 4 rule), same as Ads/Admin/Apps.
 */

use App\Auth\AuthController;

return [
    [
        'method' => 'POST',
        'path' => '/api/v1/auth/register',
        'auth' => false,
        'handler' => [AuthController::class, 'register'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/auth/login',
        'auth' => false,
        'handler' => [AuthController::class, 'login'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/auth/logout',
        'auth' => true,
        'handler' => [AuthController::class, 'logout'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/v1/auth/session',
        'auth' => false,
        'handler' => [AuthController::class, 'session'],
    ],
];
