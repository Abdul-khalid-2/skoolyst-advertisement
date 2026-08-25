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
];
