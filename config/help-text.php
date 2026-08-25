<?php
/**
 * Centralized copy for every "ⓘ" help tooltip across the dashboard and
 * admin UI. One place to edit wording instead of hunting it down inside
 * templates — see views/components/help-icon.php for how this is rendered.
 */

return [
    'ctr'              => 'Clicks divided by impressions, over the selected period.',
    'placement'        => 'Where on the app this ad appears. Each connected app defines its own set of placements.',
    'schedule'         => 'The ad stops showing automatically once the end date passes — no manual cleanup needed.',
    'api_key'          => 'Used by a connected app to authenticate every request to the AdEngine API. Treat it like a password — regenerate it if it leaks.',
    'ad_status_pending'=> 'New ads are reviewed before going live, usually within 24 hours.',
    'connected_apps'   => 'Any site or app that requests ads through the AdEngine API using its own API key.',
    'impressions'      => 'Counted once an ad has actually scrolled into view — not just when it was requested from the API.',
];
