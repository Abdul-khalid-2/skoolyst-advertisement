<?php

/**
 * Ads module routes.
 *
 * Merged into the main router at boot (see router boot code, 3.1.j).
 * `auth` marks whether the request pipeline (public/index.php) must
 * see a valid session or API key before dispatch. `{id}` in a path
 * is a placeholder — param extraction is wired up when the router
 * gains dynamic-segment matching.
 */

use App\Ads\AdController;
use App\Ads\ImageController;

return [
    [
        'method' => 'GET',
        'path' => '/api/v1/ads/serve',
        'auth' => false,
        'handler' => [AdController::class, 'serve'],
    ],
    [
        'method' => 'GET',
        'path' => '/images/ads/{filename}',
        'auth' => false,
        'handler' => [ImageController::class, 'show'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/ads/{id}/impression',
        'auth' => false,
        'handler' => [AdController::class, 'impression'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/ads/{id}/click',
        'auth' => false,
        'handler' => [AdController::class, 'click'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/advertiser/ads',
        'auth' => true,
        'handler' => [AdController::class, 'store'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/advertiser/ads/{id}',
        'auth' => true,
        'handler' => [AdController::class, 'update'],
    ],
];
