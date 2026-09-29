<?php

/**
 * Email queue worker (10.s)
 *
 * POST /api/v1/email/send only enqueues (EmailApiController::send ->
 * EmailQueueRepository::enqueue) — this script is what actually sends.
 * It claims and processes ONE queued email at a time, in a plain PHP
 * loop, never concurrently: that's what makes the account-rotation
 * logic in EmailService safe when several apps call the API at once,
 * and it keeps this process from ever opening more than one SMTP
 * session simultaneously (which is itself something Gmail rate-limits).
 *
 * flock() on its own PID-less lock file stops two overlapping cron
 * ticks (e.g. a slow run still going when the next one starts) from
 * both running this loop at the same time — belt-and-braces on top of
 * claimNextPending()'s own atomic UPDATE, which is what actually
 * prevents two workers from grabbing the same row.
 *
 * Usage (see cron/README.md for the schedule):
 *   php database/scripts/process-email-queue.php [max_per_run]
 *
 * Drains up to max_per_run (default 50) pending emails, then exits —
 * cron re-invokes it on its own schedule rather than this looping
 * forever, so a stuck run can't outlive its own process indefinitely.
 */

require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';

use App\Email\EmailAccountRepository;
use App\Email\EmailQueueRepository;
use App\Email\EmailService;
use Core\Crypto;

Core\Env::load(__DIR__ . '/../../.env');

$maxPerRun = isset($argv[1]) ? max(1, (int) $argv[1]) : 50;

$lockPath = sys_get_temp_dir() . '/skoolyst-ads-email-queue.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Another process-email-queue.php run is already in progress — exiting.\n";
    exit(0);
}

// EMAIL_ENCRYPTION_KEY must be set before any account's app_password
// can be decrypted — fail loudly up front rather than mid-batch.
try {
    Crypto::encrypt('startup-check');
} catch (\Throwable $e) {
    fwrite(STDERR, "Cannot start: {$e->getMessage()}\n");
    exit(1);
}

$queue = new EmailQueueRepository();
$service = new EmailService();
(new EmailAccountRepository())->resetIfNewDay();

$sent = 0;
$failed = 0;

for ($i = 0; $i < $maxPerRun; $i++) {
    $job = $queue->claimNextPending();
    if ($job === null) {
        break;
    }

    $result = $service->send($job['source_app'], $job['to_email'], $job['subject'], $job['body']);

    if ($result['ok']) {
        $queue->markSent((int) $job['id'], $result['message_id']);
        $sent++;
        echo "Sent queue #{$job['id']} ({$job['source_app']} -> {$job['to_email']}) via {$result['account_email']}.\n";
    } else {
        $attempts = (int) $job['attempts'] + 1;
        $queue->markFailed((int) $job['id'], $attempts, $result['message']);
        $failed++;
        echo "Failed queue #{$job['id']} (attempt {$attempts}): {$result['message']}\n";

        // Every account is exhausted/disabled right now — trying the
        // rest of this batch would just fail the same way and spend
        // their retry attempts for nothing, so stop the run here and
        // let the next cron tick pick up where this left off.
        if ($result['code'] === 'all_accounts_exhausted') {
            break;
        }
    }
}

echo "Done. Sent {$sent}, failed {$failed}.\n";

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
