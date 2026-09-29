# Skoolyst Email API — Integration Guide

Skoolyst Ads hosts a shared email service. Any Skoolyst app (skoolyst-store, skoolyst-mcqs, skoolyst-blogs, skoolyst-docs, ...) sends mail by calling one HTTP endpoint. The call itself only **queues** the email — a background worker sends them one at a time (see "How sending actually happens" below), which is what keeps several apps sending at once from racing each other or overloading a sender account.

Base URL: `https://ads.skoolyst.com/api/v1` (same host as the Ads API).

## 1. Get an API key

An admin opens **Admin → Email Accounts → API Clients → Add Client**, enters the app name (e.g. `skoolyst-blogs`), and copies the key shown **once** (`sk_mail_...`). Only a hash is stored; lost keys must be regenerated.

Store the key in the calling app's server-side config (e.g. `.env`). **Never put it in browser/client-side code.**

## 2. Send an email

`POST /api/v1/email/send` — `Content-Type: application/json`

| Field        | Required | Notes |
|--------------|----------|-------|
| `api_key`    | yes      | Or send `Authorization: Bearer <key>` instead. |
| `source_app` | recommended | Must equal the app name the key was issued for, otherwise `403`. |
| `to`         | yes      | Single valid email address. |
| `subject`    | yes      | Max 255 chars. |
| `body`       | yes      | Plain text, max 100,000 chars. |

```bash
curl -X POST https://ads.skoolyst.com/api/v1/email/send \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "sk_mail_xxxxxxxx",
    "source_app": "skoolyst-blogs",
    "to": "reader@example.com",
    "subject": "Welcome to Skoolyst Blogs",
    "body": "Thanks for subscribing!"
  }'
```

Success `202` — accepted and queued, **not yet sent**:

```json
{ "success": true, "data": { "queue_id": 42, "status": "queued" } }
```

There's no `sent_via`/success confirmation in this response, because sending hasn't happened yet — the background worker does that afterward. If you need to know whether a specific email actually went out, check **Admin → Email Inbox** (it appears there once sent, or under "Send Queue" with an error if every attempt failed).

### Error responses

These only cover the request itself (bad key, bad input) — a failure *after* the email is queued (every account exhausted, SMTP down) happens later in the worker and never comes back as an API response; see "How sending actually happens" below.

All errors: `{ "success": false, "error": { "code": "...", "message": "..." } }`

| HTTP | code                | Meaning / what to do |
|------|---------------------|----------------------|
| 401  | `unauthorized`      | Missing, invalid, or disabled `api_key`. |
| 403  | `forbidden`         | `source_app` doesn't match the key. |
| 422  | `validation_error`  | Bad/missing `to`, `subject`, `body`, or too long. Fix the request; don't retry. |
| 429  | —                   | Rate limited (60 req/min per key). Back off. |

## 3. Log an inbound email (optional)

`POST /api/v1/email/receive` records an email that arrived at one of our accounts (used by a forwarder/webhook). This service does **not** read mailboxes itself.

```json
{
  "api_key": "sk_mail_xxxxxxxx",
  "source_app": "skoolyst-blogs",
  "account_email": "sender1@gmail.com",
  "from": "reader@example.com",
  "subject": "Question",
  "body": "Hello..."
}
```

Returns `201 {"success":true,"data":{"message_id":43}}`; `404` if `account_email` isn't one of our accounts. It shows up unread in **Admin → Email Inbox**.

## 4. Example: skoolyst-blogs (PHP)

```php
function sendSkoolystEmail(string $to, string $subject, string $body): bool
{
    $ch = curl_init('https://ads.skoolyst.com/api/v1/email/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'api_key'    => getenv('SKOOLYST_EMAIL_API_KEY'),
            'source_app' => 'skoolyst-blogs',
            'to'         => $to,
            'subject'    => $subject,
            'body'       => $body,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = json_decode((string) curl_exec($ch), true);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 202) {
        return true; // queued — not a send confirmation, see the guide above
    }
    error_log('Email API error: ' . json_encode($response));
    return false;
}
```

The call itself is fast (just a database insert), so it's fine to call inline in a user request — you're not waiting on SMTP.

## How sending actually happens

`POST /api/v1/email/send` never opens an SMTP connection itself — it inserts a row into a queue table. A background worker (`database/scripts/process-email-queue.php`, run every minute by cron on the Ads server — see `cron/README.md`) claims and sends **one queued email at a time**:

- Each send uses the first `active` account (by id) still under its `daily_limit`.
- On success its `sent_count` increments; reaching the limit marks it `exhausted` and the next send uses the next account.
- If an account's SMTP login fails, that request falls through to the next account.
- `sent_count`/`received_count` reset and `exhausted` accounts become `active` again automatically on the first request of each new day (no cron needed for that part).
- Gmail accounts need 2-Step Verification and an **App Password**; default SMTP is `smtp.gmail.com:587` (override with `SMTP_HOST` / `SMTP_PORT`).
- A failed send retries automatically a few minutes later (up to 5 attempts, with increasing delay) before being parked as permanently failed — visible, with a manual retry button, on **Admin → Email Inbox**.
- Processing one at a time, never concurrently, is deliberate: it's what stops two apps sending at the same instant from over-sending past an account's `daily_limit`, or opening two SMTP sessions on the same Gmail account at once (which Gmail itself rate-limits).

There's currently no webhook/callback back to the calling app when a queued email actually sends or permanently fails — if you need that, ask before building against its absence.

## Server setup

1. Set `EMAIL_ENCRYPTION_KEY` in `.env` (`php -r "echo base64_encode(random_bytes(32));"`). Losing or changing it makes stored app passwords undecryptable.
2. `php database/scripts/migrate.php`
3. Add sender accounts and API clients in the admin panel.
4. Schedule the worker cron job from `cron/README.md` — without it, queued emails are never sent.
