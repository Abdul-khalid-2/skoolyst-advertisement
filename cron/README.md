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

## Email queue worker (10.s)

`POST /api/v1/email/send` (the shared email API other Skoolyst apps
call) only enqueues a row in `ad_email_queue` — this worker
(`database/scripts/process-email-queue.php`) is what actually opens
an SMTP connection and sends, one queued email at a time. Without it,
nothing is ever sent.

```cron
# Every minute — each run drains up to 50 pending emails, then exits;
# cron just re-invokes it. flock()'d internally, so an overlapping
# tick from a slow previous run is a safe no-op, not a double-send.
* * * * * php /path/to/skoolyst-advertisement/database/scripts/process-email-queue.php >> /var/log/skoolyst-ads/email-queue.log 2>&1
```

Notes:
- One email is sent at a time, never concurrently — that's the whole
  point (see the script's own doc-block): it's what keeps two apps
  sending at the same instant from racing the account-rotation logic
  or opening two SMTP sessions on the same Gmail account at once.
- A send that fails retries automatically on the next few runs (up to
  5 attempts) before being parked as `failed` — visible, with a retry
  button, on **Admin → Email Inbox**.
- If every sender account is exhausted/disabled, the run stops early
  rather than burning retry attempts on the rest of that batch; the
  next cron tick picks up where it left off once an account resets.
