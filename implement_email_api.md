# Skoolyst Email API — Integration Guide

Skoolyst Ads hosts a shared email service. Any Skoolyst app (skoolyst-store, skoolyst-mcqs, skoolyst-blogs, skoolyst-docs, ...) sends mail by calling one HTTP endpoint; this service picks a sender account, sends via SMTP, and logs the message.

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

Success `201`:

```json
{ "success": true, "data": { "message_id": 42, "sent_via": "sender1@gmail.com" } }
```

### Error responses

All errors: `{ "success": false, "error": { "code": "...", "message": "..." } }`

| HTTP | code                     | Meaning / what to do |
|------|--------------------------|----------------------|
| 401  | `unauthorized`           | Missing, invalid, or disabled `api_key`. |
| 403  | `forbidden`              | `source_app` doesn't match the key. |
| 422  | `validation_error`       | Bad/missing `to`, `subject`, `body`, or too long. Fix the request; don't retry. |
| 429  | —                        | Rate limited (60 req/min per key). Back off. |
| 503  | `all_accounts_exhausted` | Every sender account hit its daily limit. **Retry later** (limits reset daily). |
| 503  | `send_failed`            | Accounts exist but SMTP failed on all of them. Retry later; tell an admin if persistent. |

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

    if ($status === 201) {
        return true;
    }
    if ($status === 503) {
        // Out of sending capacity right now — queue and retry later.
    }
    error_log('Email API error: ' . json_encode($response));
    return false;
}
```

Recommended: call it from a queue/cron job rather than inline in a user request, since a send can take a few seconds and a `503` should be retried.

## How rotation works (for reference)

- Each send uses the first `active` account (by id) still under its `daily_limit`.
- On success its `sent_count` increments; reaching the limit marks it `exhausted` and the next send uses the next account.
- If an account's SMTP login fails, that request falls through to the next account.
- `sent_count`/`received_count` reset and `exhausted` accounts become `active` again automatically on the first request of each new day (no cron needed).
- Gmail accounts need 2-Step Verification and an **App Password**; default SMTP is `smtp.gmail.com:587` (override with `SMTP_HOST` / `SMTP_PORT`).

## Server setup

1. Set `EMAIL_ENCRYPTION_KEY` in `.env` (`php -r "echo base64_encode(random_bytes(32));"`). Losing or changing it makes stored app passwords undecryptable.
2. `php database/scripts/migrate.php`
3. Add sender accounts and API clients in the admin panel.
