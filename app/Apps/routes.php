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
use App\Apps\PlacementController;

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
        'method' => 'GET',
        'path' => '/api/v1/admin/apps/for-ad-form',
        'auth' => true,
        'handler' => [AppController::class, 'forAdminAdForm'],
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

    // Placement CRUD (10.o) — an app's own ad-slot codes, managed
    // from the same Admin → Connected Apps page. `{id}`/`{placementId}`
    // are cosmetic path segments, same convention as the app routes
    // above — see PlacementController::index()'s doc-block.
    [
        'method' => 'GET',
        'path' => '/api/v1/admin/apps/{id}/placements',
        'auth' => true,
        'handler' => [PlacementController::class, 'index'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/admin/apps/{id}/placements',
        'auth' => true,
        'handler' => [PlacementController::class, 'store'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/admin/apps/{id}/placements/{placementId}',
        'auth' => true,
        'handler' => [PlacementController::class, 'update'],
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/v1/admin/apps/{id}/placements/{placementId}',
        'auth' => true,
        'handler' => [PlacementController::class, 'destroy'],
    ],
];
