<?php

namespace App\Email;

use Core\Database;

/** Owns all query logic for `ad_email_messages` (the admin inbox log). */
class EmailMessageRepository
{
    public function create(string $sourceApp, int $accountId, string $title, ?string $subtitle, string $description, string $direction): int
    {
        Database::query(
            'INSERT INTO ad_email_messages (source_app, email_account_id, title, subtitle, description, direction, status)
             VALUES (:app, :account, :title, :subtitle, :description, :direction, :status)',
            [
                'app' => $sourceApp,
                'account' => $accountId,
                'title' => $title,
                'subtitle' => $subtitle,
                'description' => $description,
                'direction' => $direction,
                'status' => $direction === 'received' ? 'unread' : 'read',
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $sourceApp, ?string $status, int $limit = 100): array
    {
        $where = [];
        $params = [];
        if ($sourceApp !== null && $sourceApp !== '') {
            $where[] = 'm.source_app = :app';
            $params['app'] = $sourceApp;
        }
        if (in_array($status, ['read', 'unread'], true)) {
            $where[] = 'm.status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT m.id, m.source_app, m.title, m.subtitle, m.description, m.direction, m.status, m.pinned, m.created_at, a.email AS account_email
                FROM ad_email_messages m JOIN ad_email_accounts a ON a.id = m.email_account_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY m.pinned DESC, m.id DESC LIMIT ' . (int) $limit;

        return Database::query($sql, $params)->fetchAll();
    }

    /** @return string[] */
    public function sourceApps(): array
    {
        return array_column(Database::query('SELECT DISTINCT source_app FROM ad_email_messages ORDER BY source_app')->fetchAll(), 'source_app');
    }

    public function markRead(int $id): bool
    {
        return $this->setStatus($id, 'read');
    }

    public function markUnread(int $id): bool
    {
        return $this->setStatus($id, 'unread');
    }

    public function setPinned(int $id, bool $pinned): bool
    {
        if (Database::fetchOne('SELECT id FROM ad_email_messages WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }
        Database::query('UPDATE ad_email_messages SET pinned = :pinned WHERE id = :id', ['pinned' => $pinned ? 1 : 0, 'id' => $id]);

        return true;
    }

    public function delete(int $id): bool
    {
        if (Database::fetchOne('SELECT id FROM ad_email_messages WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }
        Database::query('DELETE FROM ad_email_messages WHERE id = :id', ['id' => $id]);

        return true;
    }

    private function setStatus(int $id, string $status): bool
    {
        if (Database::fetchOne('SELECT id FROM ad_email_messages WHERE id = :id', ['id' => $id]) === null) {
            return false;
        }
        Database::query('UPDATE ad_email_messages SET status = :status WHERE id = :id', ['status' => $status, 'id' => $id]);

        return true;
    }
}
