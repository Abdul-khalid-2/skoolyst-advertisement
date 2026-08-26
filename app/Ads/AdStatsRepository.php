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
}
