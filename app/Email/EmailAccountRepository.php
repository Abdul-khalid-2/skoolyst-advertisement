<?php

namespace App\Email;

use Core\Crypto;
use Core\Database;

/**
 * Owns all query logic for `ad_email_accounts`, including the daily reset
 * and the send-rotation queries. app_password is only ever read back
 * (encrypted) by sendCandidates() for EmailService; every list/find
 * method here excludes it.
 */
class EmailAccountRepository
{
    private const PUBLIC_COLUMNS = 'id, email, daily_limit, sent_count, received_count, last_reset_date, status, created_at, updated_at';

    /**
     * Lazy daily reset: any account last reset before today gets its
     * counters zeroed and `exhausted` flipped back to `active`
     * (`disabled` stays disabled). Cheap and idempotent — called at the
     * start of every send/list instead of needing a cron job.
     */
    public function resetIfNewDay(): void
    {
        Database::query(
            "UPDATE ad_email_accounts
                SET sent_count = 0, received_count = 0,
                    status = IF(status = 'exhausted', 'active', status),
                    last_reset_date = CURDATE()
              WHERE last_reset_date IS NULL OR last_reset_date < CURDATE()"
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return Database::query('SELECT ' . self::PUBLIC_COLUMNS . ' FROM ad_email_accounts ORDER BY id ASC')->fetchAll();
    }

    public function emailTakenByOther(string $email, ?int $excludeId = null): bool
    {
        return Database::fetchOne(
            'SELECT id FROM ad_email_accounts WHERE email = :email AND id != :id',
            ['email' => $email, 'id' => $excludeId ?? 0]
        ) !== null;
    }

    public function create(string $email, string $appPassword, int $dailyLimit): int
    {
        Database::query(
            'INSERT INTO ad_email_accounts (email, app_password, daily_limit, last_reset_date) VALUES (:email, :pw, :limit, CURDATE())',
            ['email' => $email, 'pw' => Crypto::encrypt($appPassword), 'limit' => $dailyLimit]
        );

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Updates email/limit/status, and the password only when a new one
     * is supplied. Raising daily_limit above sent_count re-activates an
     * exhausted account; lowering it to/below sent_count exhausts it.
     */
    public function update(int $id, string $email, int $dailyLimit, string $status, ?string $appPassword): bool
    {
        if (Database::fetchOne('SELECT id FROM ad_email_accounts WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }

        $params = ['id' => $id, 'email' => $email, 'limit' => $dailyLimit, 'status' => $status];
        $pwSql = '';
        if ($appPassword !== null) {
            $pwSql = ', app_password = :pw';
            $params['pw'] = Crypto::encrypt($appPassword);
        }

        Database::query("UPDATE ad_email_accounts SET email = :email, daily_limit = :limit, status = :status{$pwSql} WHERE id = :id", $params);
        Database::query(
            "UPDATE ad_email_accounts
                SET status = CASE
                    WHEN status = 'active' AND sent_count >= daily_limit THEN 'exhausted'
                    WHEN status = 'exhausted' AND sent_count < daily_limit THEN 'active'
                    ELSE status END
              WHERE id = :id",
            ['id' => $id]
        );

        return true;
    }

    public function delete(int $id): bool
    {
        if (Database::fetchOne('SELECT id FROM ad_email_accounts WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }
        Database::query('DELETE FROM ad_email_accounts WHERE id = :id', ['id' => $id]);

        return true;
    }

    /**
     * Rotation: active accounts still under their daily limit, in id
     * order. EmailService tries them one at a time until a send works.
     *
     * @return array<int, array<string, mixed>> includes the ENCRYPTED app_password
     */
    public function sendCandidates(): array
    {
        return Database::query(
            "SELECT id, email, app_password FROM ad_email_accounts WHERE status = 'active' AND sent_count < daily_limit ORDER BY id ASC"
        )->fetchAll();
    }

    /**
     * Records one successful send. Status flips to `exhausted` in the
     * same statement once the count reaches the limit (MySQL/MariaDB
     * evaluate SET assignments left to right, so sent_count is already
     * the new value when status is computed).
     */
    public function recordSent(int $id): void
    {
        Database::query(
            "UPDATE ad_email_accounts SET sent_count = sent_count + 1,
                    status = IF(status = 'active' AND sent_count >= daily_limit, 'exhausted', status)
              WHERE id = :id",
            ['id' => $id]
        );
    }

    public function recordReceived(int $id): void
    {
        Database::query('UPDATE ad_email_accounts SET received_count = received_count + 1 WHERE id = :id', ['id' => $id]);
    }

    public function findIdByEmail(string $email): ?int
    {
        $row = Database::fetchOne('SELECT id FROM ad_email_accounts WHERE email = :email', ['email' => $email]);

        return $row ? (int) $row['id'] : null;
    }
}
