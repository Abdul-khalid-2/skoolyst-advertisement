<?php

namespace App\Ads;

use Core\Database;

/**
 * AdStatsRepository
 *
 * Owns all query logic for `ad_stats_daily` (Section 5.3), plus the
 * one aggregate query allowed to touch the raw `ad_impressions` /
 * `ad_clicks` tables: the rollup itself. Everything else — dashboard
 * charts, reports — reads from `ad_stats_daily` through this class,
 * never from the raw event tables (5.3.e).
 */
class AdStatsRepository
{
    /**
     * Roll up one day's raw impression/click events into
     * `ad_stats_daily`, per ad. Safe to re-run for the same date —
     * the unique (`ad_id`, `date`) index makes this an upsert, so a
     * missed cron run can just be re-triggered for that date.
     *
     * This is the only place allowed to run an aggregate query
     * against the raw `ad_impressions` / `ad_clicks` tables; every
     * other read goes through `dailyImpressions()` below instead.
     *
     * @param string $date Y-m-d
     */
    public function rollupForDate(string $date): void
    {
        Database::query(
            <<<SQL
                INSERT INTO `ad_stats_daily` (`ad_id`, `date`, `impressions`, `clicks`)
                SELECT
                    `a`.`id`,
                    :date_out,
                    COALESCE(`i`.`cnt`, 0),
                    COALESCE(`c`.`cnt`, 0)
                FROM `ads` `a`
                LEFT JOIN (
                    SELECT `ad_id`, COUNT(*) AS `cnt`
                    FROM `ad_impressions`
                    WHERE DATE(`occurred_at`) = :date_impressions
                    GROUP BY `ad_id`
                ) `i` ON `i`.`ad_id` = `a`.`id`
                LEFT JOIN (
                    SELECT `ad_id`, COUNT(*) AS `cnt`
                    FROM `ad_clicks`
                    WHERE DATE(`occurred_at`) = :date_clicks
                    GROUP BY `ad_id`
                ) `c` ON `c`.`ad_id` = `a`.`id`
                WHERE `i`.`cnt` IS NOT NULL OR `c`.`cnt` IS NOT NULL
                ON DUPLICATE KEY UPDATE
                    `impressions` = VALUES(`impressions`),
                    `clicks` = VALUES(`clicks`),
                    `updated_at` = CURRENT_TIMESTAMP
            SQL,
            [
                // MySQL's native prepared statements (Database.php runs with
                // EMULATE_PREPARES => false) reject the same named parameter
                // used more than once, so the same date is bound three times
                // under three names instead of reusing :date.
                'date_out' => $date,
                'date_impressions' => $date,
                'date_clicks' => $date,
            ]
        );
    }

    /**
     * Total impressions per day, across every ad, for the last
     * `$days` days — backs the "Impressions, Last N Days" dashboard
     * chart (5.3.d). Reads `ad_stats_daily` only.
     *
     * @return list<array{date: string, impressions: int}>
     */
    public function dailyImpressions(int $days = 7): array
    {
        $rows = Database::query(
            <<<SQL
                SELECT `date`, SUM(`impressions`) AS `impressions`
                FROM `ad_stats_daily`
                WHERE `date` >= (CURDATE() - INTERVAL :days DAY)
                GROUP BY `date`
                ORDER BY `date` ASC
            SQL,
            ['days' => $days]
        )->fetchAll();

        return array_map(
            static fn (array $row): array => [
                'date' => $row['date'],
                'impressions' => (int) $row['impressions'],
            ],
            $rows
        );
    }

    /**
     * Same as dailyImpressions() but scoped to one advertiser's own
     * ads — backs the advertiser dashboard's "Impressions, Last N
     * Days" chart (10.m), which must never include other advertisers'
     * traffic. Still reads `ad_stats_daily` only, joined to `ads` just
     * to filter by `user_id` (same join shape AdRepository's own
     * queries already use).
     *
     * @return list<array{date: string, impressions: int}>
     */
    public function dailyImpressionsForUser(int $userId, int $days = 7): array
    {
        $rows = Database::query(
            <<<SQL
                SELECT ad_stats_daily.date, SUM(ad_stats_daily.impressions) AS impressions
                FROM ad_stats_daily
                INNER JOIN ads ON ads.id = ad_stats_daily.ad_id
                WHERE ads.user_id = :user_id
                  AND ad_stats_daily.date >= (CURDATE() - INTERVAL :days DAY)
                GROUP BY ad_stats_daily.date
                ORDER BY ad_stats_daily.date ASC
            SQL,
            ['user_id' => $userId, 'days' => $days]
        )->fetchAll();

        return array_map(
            static fn (array $row): array => [
                'date' => $row['date'],
                'impressions' => (int) $row['impressions'],
            ],
            $rows
        );
    }

    /**
     * Platform-wide counterpart to performanceSummaryForUser() below —
     * same current-30d-vs-previous-30d shape, but with no `ads.user_id`
     * filter, since the admin overview (public/admin/index.php) needs
     * totals across every advertiser, not just one. Reads
     * `ad_stats_daily` directly (5.3.e) with no join to `ads` needed,
     * since nothing here is scoped by owner.
     *
     * @return array{impressions_current: int, clicks_current: int, impressions_previous: int, clicks_previous: int}
     */
    public function performanceSummary(): array
    {
        $row = Database::fetchOne(
            <<<SQL
                SELECT
                    COALESCE(SUM(CASE WHEN date >= CURDATE() - INTERVAL 30 DAY THEN impressions ELSE 0 END), 0) AS impressions_current,
                    COALESCE(SUM(CASE WHEN date >= CURDATE() - INTERVAL 30 DAY THEN clicks ELSE 0 END), 0) AS clicks_current,
                    COALESCE(SUM(CASE WHEN date < CURDATE() - INTERVAL 30 DAY AND date >= CURDATE() - INTERVAL 60 DAY THEN impressions ELSE 0 END), 0) AS impressions_previous,
                    COALESCE(SUM(CASE WHEN date < CURDATE() - INTERVAL 30 DAY AND date >= CURDATE() - INTERVAL 60 DAY THEN clicks ELSE 0 END), 0) AS clicks_previous
                FROM ad_stats_daily
            SQL
        );

        return [
            'impressions_current' => (int) ($row['impressions_current'] ?? 0),
            'clicks_current' => (int) ($row['clicks_current'] ?? 0),
            'impressions_previous' => (int) ($row['impressions_previous'] ?? 0),
            'clicks_previous' => (int) ($row['clicks_previous'] ?? 0),
        ];
    }

    /**
     * One-query summary behind the dashboard's "Impressions (30d)",
     * "Clicks (30d)" and "Avg. CTR" stat cards, plus each card's
     * "vs last month" trend (10.m) — the current 30-day window against
     * the 30 days immediately before it, for one advertiser's ads
     * only. A single conditional-SUM query rather than two separate
     * round trips for the two windows.
     *
     * @return array{impressions_current: int, clicks_current: int, impressions_previous: int, clicks_previous: int}
     */
    public function performanceSummaryForUser(int $userId): array
    {
        $row = Database::fetchOne(
            <<<SQL
                SELECT
                    COALESCE(SUM(CASE WHEN date >= CURDATE() - INTERVAL 30 DAY THEN impressions ELSE 0 END), 0) AS impressions_current,
                    COALESCE(SUM(CASE WHEN date >= CURDATE() - INTERVAL 30 DAY THEN clicks ELSE 0 END), 0) AS clicks_current,
                    COALESCE(SUM(CASE WHEN date < CURDATE() - INTERVAL 30 DAY AND date >= CURDATE() - INTERVAL 60 DAY THEN impressions ELSE 0 END), 0) AS impressions_previous,
                    COALESCE(SUM(CASE WHEN date < CURDATE() - INTERVAL 30 DAY AND date >= CURDATE() - INTERVAL 60 DAY THEN clicks ELSE 0 END), 0) AS clicks_previous
                FROM ad_stats_daily
                INNER JOIN ads ON ads.id = ad_stats_daily.ad_id
                WHERE ads.user_id = :user_id
            SQL,
            ['user_id' => $userId]
        );

        return [
            'impressions_current' => (int) ($row['impressions_current'] ?? 0),
            'clicks_current' => (int) ($row['clicks_current'] ?? 0),
            'impressions_previous' => (int) ($row['impressions_previous'] ?? 0),
            'clicks_previous' => (int) ($row['clicks_previous'] ?? 0),
        ];
    }
}
