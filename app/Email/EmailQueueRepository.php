<?php

namespace App\Email;

use Core\Database;

/**
 * EmailQueueRepository
 *
 * Owns `ad_email_queue`. enqueue() is called from the request thread
 * (EmailApiController::send, fast, no SMTP); claimNextPending() and the
 * mark*() methods are called from the worker (database/scripts/
 * process-email-queue.php), one row at a time.
 */
class EmailQueueRepository
{
    private const MAX_ATTEMPTS = 5;

    public function enqueue(string $sourceApp, string $toEmail, string $subject, string $body): int
    {
        Database::query(
            'INSERT INTO ad_email_queue (source_app, to_email, subject, body) VALUES (:app, :to, :subject, :body)',
            ['app' => $sourceApp, 'to' => $toEmail, 'subject' => $subject, 'body' => $body]
        );

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Atomically claims the oldest pending, due-for-retry row by
     * flipping it to `processing` in one UPDATE ... WHERE status =
     * 'pending'. MySQL/MariaDB executes that as a single atomic
     * statement, so even two worker processes started at the same
     * instant (e.g. an overlapping cron tick) can never both claim the
     * same row — whichever's UPDATE commits first is the only one whose
     * WHERE clause still matches for the second. `next_attempt_at` is
     * what actually spaces retries across separate worker runs, not
     * this claim itself — see markFailed(). Returns null once nothing
     * is left to claim right now.
     *
     * @return array{id: int, source_app: string, to_email: string, subject: string, body: string, attempts: int}|null
     */
    public function claimNextPending(): ?array
    {
        $next = Database::fetchOne(
            "SELECT id FROM ad_email_queue
              WHERE status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
              ORDER BY id ASC LIMIT 1"
        );
        if ($next === null) {
            return null;
        }

        $id = (int) $next['id'];
        $claimed = Database::query(
            "UPDATE ad_email_queue SET status = 'processing' WHERE id = :id AND status = 'pending'",
            ['id' => $id]
        )->rowCount();

        if ($claimed === 0) {
            // Another worker claimed it between the SELECT and the
            // UPDATE above — not an error, just try the next one.
            return null;
        }

        return Database::fetchOne(
            'SELECT id, source_app, to_email, subject, body, attempts FROM ad_email_queue WHERE id = :id',
            ['id' => $id]
        );
    }

    public function markSent(int $id, int $emailMessageId): void
    {
        Database::query(
            "UPDATE ad_email_queue SET status = 'sent', email_message_id = :message_id WHERE id = :id",
            ['id' => $id, 'message_id' => $emailMessageId]
        );
    }

    /**
     * Failed attempt: retries (back to `pending`) until MAX_ATTEMPTS,
     * then parks it as permanently `failed` rather than looping forever
     * on something like a bad `to` address that every account will
     * reject the same way. `next_attempt_at` is pushed a few minutes
     * into the future (5 per attempt so far, capped at 30) so a retry
     * waits for a later cron tick instead of claimNextPending() just
     * picking this same row straight back up later in the same run —
     * the bug this backoff exists to prevent.
     */
    public function markFailed(int $id, int $attempts, string $error): void
    {
        $status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
        $delayMinutes = min(30, $attempts * 5);
        Database::query(
            "UPDATE ad_email_queue
                SET status = :status, attempts = :attempts, last_error = :error,
                    next_attempt_at = DATE_ADD(NOW(), INTERVAL :delay MINUTE)
              WHERE id = :id",
            ['id' => $id, 'status' => $status, 'attempts' => $attempts, 'error' => mb_substr($error, 0, 500), 'delay' => $delayMinutes]
        );
    }

    /** @return array<int, array<string, mixed>> newest first, for the admin queue view */
    public function recent(int $limit = 100): array
    {
        return Database::query(
            'SELECT id, source_app, to_email, subject, status, attempts, last_error, created_at, updated_at
             FROM ad_email_queue ORDER BY id DESC LIMIT ' . (int) $limit
        )->fetchAll();
    }

    /** @return array<string, int> counts keyed by status, always including every status even at 0 */
    public function countsByStatus(): array
    {
        $counts = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0];
        $rows = Database::query('SELECT status, COUNT(*) AS total FROM ad_email_queue GROUP BY status')->fetchAll();
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Re-queues a permanently failed row so the worker picks it up
     * again. next_attempt_at is cleared (not just left at whatever
     * backoff the last failed attempt set) so it's available on the
     * very next worker run, not stuck waiting out that old delay.
     */
    public function retry(int $id): bool
    {
        $affected = Database::query(
            "UPDATE ad_email_queue SET status = 'pending', attempts = 0, last_error = NULL, next_attempt_at = NULL WHERE id = :id AND status = 'failed'",
            ['id' => $id]
        )->rowCount();

        return $affected > 0;
    }
}
