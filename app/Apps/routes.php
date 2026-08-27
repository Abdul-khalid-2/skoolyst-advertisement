<?php

/**
 * Apps module routes.
 *
 * Merged into the main router at boot (see router boot code, 3.1.j).
 * All paths are prefixed `/api/v1/` (Section 4 rule). `auth => true`
 * means the request pipeline requires a valid admin session before
 * dispatch (role check itself lands in Section 6).
 */

use App\Apps\AppController;

return [
    [
        'method' => 'GET',
        'path' => '/api/v1/advertiser/apps',
        'auth' => true,
        'handler' => [AppController::class, 'forAdvertiser'],
    ],
    [
        'method' => 'GET',
        'path' => '/api/v1/admin/apps',
        'auth' => true,
        'handler' => [AppController::class, 'index'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/admin/apps',
        'auth' => true,
        'handler' => [AppController::class, 'store'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/admin/apps/{id}',
        'auth' => true,
        'handler' => [AppController::class, 'update'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/admin/apps/{id}/regenerate-key',
        'auth' => true,
        'handler' => [AppController::class, 'regenerateKey'],
    ],
];
