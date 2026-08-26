# Scheduled Jobs

Cron entries for jobs this app needs running in production. Not
executed automatically by anything in this repo — the host's
crontab (or equivalent scheduler) is configured to match this file
during deploy (see Section 14).

## ad_stats_daily rollup (Section 5.3)

Rolls up the previous day's raw `ad_impressions` / `ad_clicks` rows
into `ad_stats_daily` (`database/scripts/rollup-ad-stats-daily.php`).
Runs once, shortly after midnight, so "yesterday" is a complete day
by the time it runs.

```cron
# Run daily at 00:15 server time
15 0 * * * php /path/to/skoolyst-advertisement/database/scripts/rollup-ad-stats-daily.php >> /var/log/skoolyst-ads/rollup.log 2>&1
```

Notes:
- `>> ... 2>&1` keeps a log per run — the script writes a one-line
  success message to stdout, or an error to stderr with a non-zero
  exit code (see `CODE_REVIEW_CHECKLIST.md`-style expectations: fail
  loudly, don't swallow errors).
- The script is idempotent (`AdStatsRepository::rollupForDate()`
  upserts on the `(ad_id, date)` unique index), so re-running the
  same day after a missed or failed run is always safe:
  `php database/scripts/rollup-ad-stats-daily.php 2026-08-25`
