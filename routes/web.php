<?php

/**
 * Web routes — the page-serving side of the app, as opposed to
 * routes/api.php (JSON endpoints, Section 4).
 *
 * Every dashboard/admin page currently lives directly under public/
 * (public/dashboard/*.php, public/admin/*.php) and is requested by
 * its real file path — the web server resolves it straight off disk,
 * same as index.html/api-docs.php. This table is the single map of
 * every page that exists, kept in sync as new pages are added, so
 * that:
 *   - a future page router (once one exists) has one file to require
 *     instead of a filesystem scan, matching how api.php centralizes
 *     the API side instead of hand-listing endpoints elsewhere;
 *   - 'role' documents, per page, which of Section 6's role checks
 *     (advertiser/admin) applies — checked by each page itself today
 *     (views/bootstrap.php + the page's own $role assignment), and by
 *     a shared middleware once page routing is wired up.
 *
 * Paths are relative to the public/ webroot (Section 9.a/b).
 */

return [
    [
        'path' => '/',
        'file' => 'index.html',
        'role' => null,
    ],
    [
        'path' => '/api-docs.php',
        'file' => 'api-docs.php',
        'role' => null,
    ],
    [
        'path' => '/dashboard/',
        'file' => 'dashboard/index.php',
        'role' => 'advertiser',
    ],
    [
        'path' => '/dashboard/create-ad.php',
        'file' => 'dashboard/create-ad.php',
        'role' => 'advertiser',
    ],
    [
        'path' => '/dashboard/my-ads.php',
        'file' => 'dashboard/my-ads.php',
        'role' => 'advertiser',
    ],
    [
        'path' => '/admin/',
        'file' => 'admin/index.php',
        'role' => 'admin',
    ],
    [
        'path' => '/admin/ads.php',
        'file' => 'admin/ads.php',
        'role' => 'admin',
    ],
    [
        'path' => '/admin/apps.php',
        'file' => 'admin/apps.php',
        'role' => 'admin',
    ],
];
