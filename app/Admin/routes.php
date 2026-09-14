<?php

/**
 * Admin module routes.
 *
 * Merged into the main router at boot (see router boot code, 3.1.j).
 * All paths are prefixed `/api/v1/` (Section 4 rule). `auth => true`
 * means the request pipeline requires a valid admin session before
 * dispatch (role check itself lands in Section 6).
 */

use App\Admin\ModerationController;
use App\Admin\UserController;

return [
    [
        'method' => 'GET',
        'path' => '/api/v1/admin/ads',
        'auth' => true,
        'handler' => [ModerationController::class, 'pendingAds'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/admin/ads/{id}/approve',
        'auth' => true,
        'handler' => [ModerationController::class, 'approve'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/admin/ads/{id}/reject',
        'auth' => true,
        'handler' => [ModerationController::class, 'reject'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/admin/ads/{id}/pause',
        'auth' => true,
        'handler' => [ModerationController::class, 'pause'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/admin/ads/{id}/activate',
        'auth' => true,
        'handler' => [ModerationController::class, 'activate'],
    ],

    // User management (Advertisers screen) — full CRUD over the `users`
    // table, both roles. `{id}` is a cosmetic path segment, same
    // convention as the Apps module routes — the real id is read from
    // the body (see UserController).
    [
        'method' => 'GET',
        'path' => '/api/v1/admin/users',
        'auth' => true,
        'handler' => [UserController::class, 'index'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/admin/users',
        'auth' => true,
        'handler' => [UserController::class, 'store'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/admin/users/{id}',
        'auth' => true,
        'handler' => [UserController::class, 'update'],
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/v1/admin/users/{id}',
        'auth' => true,
        'handler' => [UserController::class, 'destroy'],
    ],
];
