<?php

namespace App\Email;

/**
 * EmailApiController
 *
 * Public, api_key-authenticated endpoints other Skoolyst apps call:
 *   POST /api/v1/email/send
 *   POST /api/v1/email/receive
 * The key is validated on every request against `ad_api_clients` (hash
 * lookup, active only). It is read from the JSON/form body's `api_key`
 * field, or an `Authorization: Bearer` header. Neither the key nor any
 * account app_password is ever included in a response.
 */
use Core\Request;
use Core\Response;
use Core\Auth\Middleware;

class EmailApiController
{
    private const MAX_SUBJECT = 255;
    private const MAX_BODY = 100000;

    private ApiClientRepository $clients;

    public function __construct()
    {
        $this->clients = new ApiClientRepository();
    }

    /**
     * POST /api/v1/email/send
     * Body: { api_key, source_app, to, subject, body }
     *
     * Enqueues rather than sending inline (10.s) — when several apps
     * call this at the same instant, sending each one synchronously
     * inside the request meant every one of them read the account
     * rotation table at once, so two requests could both see the same
     * account as still under its daily_limit and both send before
     * either's counter update landed (over-sending past the limit), and
     * concurrent SMTP logins to the same Gmail account are exactly what
     * trips Gmail's own rate limiting. database/scripts/process-email-
     * queue.php claims and sends queued rows one at a time, so this
     * endpoint now only ever does a fast DB insert.
     */
    public function send(): void
    {
        $input = Request::input();
        $client = $this->authenticate($input);
        if ($client === null) {
            return;
        }

        $to = trim((string) ($input['to'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $body = (string) ($input['body'] ?? '');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '' || trim($body) === '') {
            Response::error(['code' => 'validation_error', 'message' => 'A valid "to" address, "subject" and "body" are required.'], 422);
            return;
        }
        if (mb_strlen($subject) > self::MAX_SUBJECT || mb_strlen($body) > self::MAX_BODY) {
            Response::error(['code' => 'validation_error', 'message' => 'Subject or body is too long.'], 422);
            return;
        }

        $queueId = (new EmailQueueRepository())->enqueue($client['app_name'], $to, $subject, $body);

        // 202: accepted for processing, not yet sent — distinct from the
        // old synchronous 201 so callers know not to expect sent_via
        // in the same response.
        Response::success(['queue_id' => $queueId, 'status' => 'queued'], 202);
    }

    /**
     * POST /api/v1/email/receive
     * Body: { api_key, source_app, account_email, from, subject, body }
     * Logs an inbound email against one of our accounts (a forwarder /
     * webhook / future IMAP poller calls this — nothing here reads a
     * mailbox itself).
     */
    public function receive(): void
    {
        $input = Request::input();
        $client = $this->authenticate($input);
        if ($client === null) {
            return;
        }

        $accountEmail = trim((string) ($input['account_email'] ?? ''));
        $from = trim((string) ($input['from'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $body = (string) ($input['body'] ?? '');

        if ($accountEmail === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $subject === '' || trim($body) === '') {
            Response::error(['code' => 'validation_error', 'message' => '"account_email", a valid "from" address, "subject" and "body" are required.'], 422);
            return;
        }
        if (mb_strlen($subject) > self::MAX_SUBJECT || mb_strlen($body) > self::MAX_BODY) {
            Response::error(['code' => 'validation_error', 'message' => 'Subject or body is too long.'], 422);
            return;
        }

        $accounts = new EmailAccountRepository();
        $accountId = $accounts->findIdByEmail($accountEmail);
        if ($accountId === null) {
            Response::error(['code' => 'not_found', 'message' => 'Unknown account_email.'], 404);
            return;
        }

        $accounts->resetIfNewDay();
        $accounts->recordReceived($accountId);
        $messageId = (new EmailMessageRepository())->create($client['app_name'], $accountId, $subject, 'From: ' . $from, $body, 'received');

        Response::success(['message_id' => $messageId], 201);
    }

    /**
     * Validates the api_key and that `source_app` (if sent) matches the
     * app the key belongs to, so one app can't log mail as another.
     * Sends the error response itself and returns null on failure.
     *
     * @param array<string, mixed> $input
     * @return array{id: int, app_name: string}|null
     */
    private function authenticate(array $input): ?array
    {
        $key = trim((string) ($input['api_key'] ?? ''));
        if ($key === '') {
            $key = (string) Middleware::checkApiKey();
        }

        $client = $key !== '' ? $this->clients->authenticate($key) : null;
        if ($client === null) {
            Response::error(['code' => 'unauthorized', 'message' => 'Missing, invalid, or disabled api_key.'], 401);
            return null;
        }

        $sourceApp = trim((string) ($input['source_app'] ?? ''));
        if ($sourceApp !== '' && $sourceApp !== $client['app_name']) {
            Response::error(['code' => 'forbidden', 'message' => 'source_app does not match this api_key.'], 403);
            return null;
        }

        return $client;
    }
}
