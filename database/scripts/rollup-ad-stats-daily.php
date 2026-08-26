<?php

/**
 * Rollup: ad_stats_daily
 *
 * Section 5.3.b. Standalone script — reads raw `ad_impressions` /
 * `ad_clicks` events for one day and upserts the per-ad totals into
 * `ad_stats_daily` via `AdStatsRepository::rollupForDate()`. All the
 * actual SQL lives in that repository (Code Review Checklist: query
 * logic only in Repositories) — this script is just the CLI entry
 * point a cron job calls (wired up in 5.3.c, see cron/README.md).
 *
 * Usage:
 *   php database/scripts/rollup-ad-stats-daily.php [Y-m-d]
 *
 * With no argument, rolls up *yesterday* — the intended daily cron
 * use (run once, shortly after midnight, for the day that just
 * finished). Pass an explicit date to backfill or re-run a day.
 */

require __DIR__ . '/../../core/Database.php';
require __DIR__ . '/../../app/Ads/AdStatsRepository.php';

use App\Ads\AdStatsRepository;

$date = $argv[1] ?? date('Y-m-d', strtotime('-1 day'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "Invalid date \"{$date}\" — expected Y-m-d.\n");
    exit(1);
}

try {
    (new AdStatsRepository())->rollupForDate($date);
    echo "Rolled up ad_stats_daily for {$date}.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Rollup failed for {$date}: {$e->getMessage()}\n");
    exit(1);
}
