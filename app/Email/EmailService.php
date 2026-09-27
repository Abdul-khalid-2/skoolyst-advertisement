<?php

namespace App\Email;

use Core\Crypto;
use Core\SmtpMailer;

/**
 * EmailService
 *
 * The rotation engine. send() tries each active, under-limit account in
 * id order; the first successful SMTP send is recorded (sent_count++,
 * auto-`exhausted` at the limit, message logged). An account whose SMTP
 * attempt fails (bad password, blocked, etc.) is skipped for this
 * request only. If nothing can send, returns a failure the API maps to
 * a 503 so the calling app can retry later.
 */
class EmailService
{
    private EmailAccountRepository $accounts;
    private EmailMessageRepository $messages;

    public function __construct()
    {
        $this->accounts = new EmailAccountRepository();
        $this->messages = new EmailMessageRepository();
    }

    /**
     * @return array{ok: true, message_id: int, account_email: string}|array{ok: false, code: string, message: string}
     */
    public function send(string $sourceApp, string $to, string $subject, string $body): array
    {
        $this->accounts->resetIfNewDay();

        $candidates = $this->accounts->sendCandidates();
        if ($candidates === []) {
            error_log("[email] all accounts exhausted/disabled - send from '{$sourceApp}' to '{$to}' rejected");

            return ['ok' => false, 'code' => 'all_accounts_exhausted', 'message' => 'All email accounts have reached their daily limit. Retry later.'];
        }

        $config = require __DIR__ . '/../../config/mail.php';

        foreach ($candidates as $account) {
            try {
                SmtpMailer::send(
                    $config['smtp_host'],
                    $config['smtp_port'],
                    $account['email'],
                    Crypto::decrypt($account['app_password']),
                    $to,
                    $subject,
                    $body
                );
            } catch (\Throwable $e) {
                error_log("[email] account {$account['email']} failed: " . $e->getMessage());
                continue;
            }

            $accountId = (int) $account['id'];
            $this->accounts->recordSent($accountId);
            $messageId = $this->messages->create($sourceApp, $accountId, $subject, 'To: ' . $to, $body, 'sent');

            return ['ok' => true, 'message_id' => $messageId, 'account_email' => $account['email']];
        }

        error_log("[email] every candidate account failed to send for '{$sourceApp}' to '{$to}'");

        return ['ok' => false, 'code' => 'send_failed', 'message' => 'Could not send the email through any available account. Retry later.'];
    }
}
