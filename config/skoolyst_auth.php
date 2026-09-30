<?php

/**
 * "Login with Skoolyst" OAuth2 client config — this app is a client of
 * skoolyst.com's identity provider (see implement_email_api.md's sibling
 * doc, the "Login with Skoolyst — Integration Guide" the client_id/
 * secret below came from). No fallbacks for the client credentials —
 * an empty client_secret must fail loudly, not silently try requests
 * that can only ever come back invalid_client.
 */

return [
    'base_url' => rtrim(getenv('SKOOLYST_AUTH_BASE') ?: 'https://skoolyst.com', '/'),
    'client_id' => getenv('SKOOLYST_AUTH_CLIENT_ID') ?: '',
    'client_secret' => getenv('SKOOLYST_AUTH_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('SKOOLYST_AUTH_REDIRECT_URI') ?: '',
];
