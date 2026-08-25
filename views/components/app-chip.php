<?php
/**
 * Small helpers for rendering/looking up a connected app. Any page that
 * shows "which app is this ad on" (dashboard, my-ads, admin ads, admin
 * apps, api-docs) uses these instead of re-writing the same foreach lookup.
 */

function app_chip(array $app): string
{
    return '<span class="app-chip"><span class="app-chip__dot">'
        . htmlspecialchars($app['code']) . '</span>'
        . htmlspecialchars($app['name']) . '</span>';
}

function app_by_id(array $apps, string $id): ?array
{
    foreach ($apps as $app) {
        if ($app['id'] === $id) return $app;
    }
    return null;
}

function app_name_by_id(array $apps, string $id): string
{
    $app = app_by_id($apps, $id);
    return $app['name'] ?? $id;
}
