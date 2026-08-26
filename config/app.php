<?php

/**
 * General application config (Section 9.i).
 *
 * Non-database settings that apply across the whole app — separate
 * from config/database.php (9.h) so connection secrets and general
 * settings can be reasoned about (and, later, permissioned) on their
 * own. Env-driven with dev-safe fallbacks, same pattern as
 * config/database.php.
 */

return [
    'name'  => getenv('APP_NAME') ?: 'Skoolyst Ads — AdEngine',
    'env'   => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'url'   => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',

    // Default page size for the paginated advertiser "My Ads" list
    // (7.k) and the admin moderation queue (7.l) — a single place to
    // tune both instead of a magic number in each repository.
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
];
