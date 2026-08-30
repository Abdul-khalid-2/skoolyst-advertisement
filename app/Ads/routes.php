<?php

/**
 * Ads module routes.
 *
 * Merged into the main router at boot (see router boot code, 3.1.j).
 * `auth` marks whether the request pipeline (public/index.php) must
 * see a valid session or API key before dispatch. `{id}` in a path
 * is a placeholder — the router matches it against any single path
 * segment, but doesn't extract or bind its value anywhere; every
 * handler that needs an id still reads it from the request body
 * (e.g. Request::int('ad_id')).
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
        'method' => 'GET',
        'path' => '/api/v1/advertiser/ads/{id}',
        'auth' => true,
        'handler' => [AdController::class, 'show'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/advertiser/ads/{id}',
        'auth' => true,
        'handler' => [AdController::class, 'update'],
    ],
    [
        'method' => 'POST',
        'path' => '/api/v1/advertiser/ads/{id}/image',
        'auth' => true,
        'handler' => [AdController::class, 'updateImage'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/advertiser/ads/{id}/pause',
        'auth' => true,
        'handler' => [AdController::class, 'pause'],
    ],
    [
        'method' => 'PATCH',
        'path' => '/api/v1/advertiser/ads/{id}/activate',
        'auth' => true,
        'handler' => [AdController::class, 'activate'],
    ],
    [
        'method' => 'DELETE',
        'path' => '/api/v1/advertiser/ads/{id}',
        'auth' => true,
        'handler' => [AdController::class, 'destroy'],
    ],
];
