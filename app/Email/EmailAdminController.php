<?php

namespace App\Email;

/**
 * EmailAdminController
 *
 * Admin-only management for the email service: sender accounts, API
 * clients (external apps + their keys), and the message inbox. Thin —
 * validate, call a repository, respond — like AppController. Ids come
 * from the request body (account_id / client_id / message_id), the
 * same convention as every other module; `{id}` in the path is cosmetic.
 */
use Core\AuditLog;
use Core\Request;
use Core\Response;
use Core\Auth\Middleware;

class EmailAdminController
{
    private EmailAccountRepository $accounts;
    private ApiClientRepository $clients;
    private EmailMessageRepository $messages;
    private EmailQueueRepository $queue;

    public function __construct()
    {
        $this->accounts = new EmailAccountRepository();
        $this->clients = new ApiClientRepository();
        $this->messages = new EmailMessageRepository();
        $this->queue = new EmailQueueRepository();
    }

    // ---- Accounts ----------------------------------------------------

    /** GET /api/v1/admin/email-accounts (no app_password, ever) */
    public function accountsIndex(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }
        $this->accounts->resetIfNewDay();
        Response::success(['accounts' => $this->accounts->all()]);
    }

    /** POST /api/v1/admin/email-accounts */
    public function accountsStore(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $email = Request::string('email');
        $password = self::normalizeAppPassword((string) (Request::input()['app_password'] ?? ''));
        $limit = Request::int('daily_limit') ?? 40;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || $limit < 1 || $limit > 100000) {
            Response::error(['code' => 'validation_error', 'message' => 'A valid email, an app password, and a daily limit of at least 1 are required.'], 422);
            return;
        }
        if ($this->accounts->emailTakenByOther($email)) {
            Response::error(['code' => 'email_taken', 'message' => 'That email account already exists.'], 409);
            return;
        }

        try {
            $id = $this->accounts->create($email, $password, $limit);
        } catch (\RuntimeException $e) {
            Response::error(['code' => 'encryption_error', 'message' => $e->getMessage()], 500);
            return;
        }

        AuditLog::write($adminId, 'email_account.create', 'email_account', $id);
        Response::success(['id' => $id], 201);
    }

    /** PATCH /api/v1/admin/email-accounts/{id} — blank app_password keeps the current one */
    public function accountsUpdate(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $id = Request::int('account_id');
        $email = Request::string('email');
        $password = self::normalizeAppPassword((string) (Request::input()['app_password'] ?? ''));
        $limit = Request::int('daily_limit');
        $status = Request::string('status');

        if ($id === null || !filter_var($email, FILTER_VALIDATE_EMAIL) || $limit === null || $limit < 1 || $limit > 100000
            || !in_array($status, ['active', 'disabled', 'exhausted'], true)) {
            Response::error(['code' => 'validation_error', 'message' => 'account_id, a valid email, daily_limit >= 1 and a valid status are required.'], 422);
            return;
        }
        if ($this->accounts->emailTakenByOther($email, $id)) {
            Response::error(['code' => 'email_taken', 'message' => 'Another account already uses that email.'], 409);
            return;
        }

        try {
            $found = $this->accounts->update($id, $email, $limit, $status, $password === '' ? null : $password);
        } catch (\RuntimeException $e) {
            Response::error(['code' => 'encryption_error', 'message' => $e->getMessage()], 500);
            return;
        }
        if (!$found) {
            Response::error(['code' => 'not_found', 'message' => 'Account not found.'], 404);
            return;
        }

        AuditLog::write($adminId, 'email_account.update', 'email_account', $id);
        Response::success([]);
    }

    /** DELETE /api/v1/admin/email-accounts/{id} — also deletes that account's logged messages (FK cascade) */
    public function accountsDestroy(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $id = Request::int('account_id');
        if ($id === null || !$this->accounts->delete($id)) {
            Response::error(['code' => 'not_found', 'message' => 'Account not found.'], 404);
            return;
        }

        AuditLog::write($adminId, 'email_account.delete', 'email_account', $id);
        Response::success([]);
    }

    // ---- API clients -------------------------------------------------

    /** GET /api/v1/admin/email-clients (never includes keys) */
    public function clientsIndex(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }
        Response::success(['clients' => $this->clients->all()]);
    }

    /** POST /api/v1/admin/email-clients — the plaintext key is returned once, here only */
    public function clientsStore(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $appName = Request::string('app_name');
        if ($appName === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/i', $appName)) {
            Response::error(['code' => 'validation_error', 'message' => 'app_name is required (letters, numbers, dot, dash, underscore), e.g. skoolyst-blogs.'], 422);
            return;
        }
        if ($this->clients->appNameTaken($appName)) {
            Response::error(['code' => 'app_taken', 'message' => 'A client with that app name already exists.'], 409);
            return;
        }

        $key = $this->clients->create($appName);
        AuditLog::write($adminId, 'email_client.create', 'email_client', 0);
        Response::success(['api_key' => $key], 201);
    }

    /** PATCH /api/v1/admin/email-clients/{id} — body: {client_id, active} or {client_id, regenerate: true} */
    public function clientsUpdate(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $id = Request::int('client_id');
        if ($id === null) {
            Response::error(['code' => 'validation_error', 'message' => 'client_id is required.'], 422);
            return;
        }

        if (filter_var(Request::input()['regenerate'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $key = $this->clients->regenerateKey($id);
            if ($key === null) {
                Response::error(['code' => 'not_found', 'message' => 'Client not found.'], 404);
                return;
            }
            AuditLog::write($adminId, 'email_client.regenerate_key', 'email_client', $id);
            Response::success(['api_key' => $key]);
            return;
        }

        $active = filter_var(Request::input()['active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null || !$this->clients->setActive($id, $active)) {
            Response::error(['code' => 'not_found', 'message' => 'Client not found or "active" missing.'], 404);
            return;
        }
        AuditLog::write($adminId, $active ? 'email_client.enable' : 'email_client.disable', 'email_client', $id);
        Response::success([]);
    }

    /** DELETE /api/v1/admin/email-clients/{id} */
    public function clientsDestroy(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $id = Request::int('client_id');
        if ($id === null || !$this->clients->delete($id)) {
            Response::error(['code' => 'not_found', 'message' => 'Client not found.'], 404);
            return;
        }
        AuditLog::write($adminId, 'email_client.delete', 'email_client', $id);
        Response::success([]);
    }

    // ---- Messages ----------------------------------------------------

    /** GET /api/v1/admin/email-messages?source_app=&status= */
    public function messagesIndex(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }
        Response::success(['messages' => $this->messages->list(Request::string('source_app') ?: null, Request::string('status') ?: null)]);
    }

    /** PATCH /api/v1/admin/email-messages/{id}/read */
    public function messagesMarkRead(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        $id = Request::int('message_id');
        if ($id === null || !$this->messages->markRead($id)) {
            Response::error(['code' => 'not_found', 'message' => 'Message not found.'], 404);
            return;
        }
        Response::success([]);
    }

    /** PATCH /api/v1/admin/email-messages/{id}/unread */
    public function messagesMarkUnread(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        $id = Request::int('message_id');
        if ($id === null || !$this->messages->markUnread($id)) {
            Response::error(['code' => 'not_found', 'message' => 'Message not found.'], 404);
            return;
        }
        Response::success([]);
    }

    /** PATCH /api/v1/admin/email-messages/{id}/pin — body: {message_id, pinned} */
    public function messagesSetPinned(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        $id = Request::int('message_id');
        $pinned = filter_var(Request::input()['pinned'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($id === null || $pinned === null || !$this->messages->setPinned($id, $pinned)) {
            Response::error(['code' => 'not_found', 'message' => 'Message not found or "pinned" missing.'], 404);
            return;
        }
        Response::success([]);
    }

    /** DELETE /api/v1/admin/email-messages/{id} */
    public function messagesDestroy(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $id = Request::int('message_id');
        if ($id === null || !$this->messages->delete($id)) {
            Response::error(['code' => 'not_found', 'message' => 'Message not found.'], 404);
            return;
        }
        AuditLog::write($adminId, 'email_message.delete', 'email_message', $id);
        Response::success([]);
    }

    // ---- Queue (10.s) --------------------------------------------------

    /** GET /api/v1/admin/email-queue — counts plus the most recent rows */
    public function queueIndex(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }
        Response::success(['counts' => $this->queue->countsByStatus(), 'items' => $this->queue->recent()]);
    }

    /** PATCH /api/v1/admin/email-queue/{id}/retry — re-queues a permanently failed row */
    public function queueRetry(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        $id = Request::int('queue_id');
        if ($id === null || !$this->queue->retry($id)) {
            Response::error(['code' => 'not_found', 'message' => 'Queued email not found, or it is not currently failed.'], 404);
            return;
        }
        Response::success([]);
    }

    /**
     * Google's UI shows an app password as 4 space-separated groups
     * (e.g. "abcd efgh ijkl mnop") purely for readability — the actual
     * credential has no spaces, and Gmail's SMTP AUTH rejects it if
     * they're sent as-is. Stripping all whitespace here means it works
     * whether an admin pastes it with or without the spaces.
     */
    private static function normalizeAppPassword(string $password): string
    {
        return preg_replace('/\s+/', '', $password) ?? '';
    }
}
