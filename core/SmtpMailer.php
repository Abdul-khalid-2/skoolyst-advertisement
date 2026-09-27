<?php

namespace Core;

/**
 * SmtpMailer
 *
 * Minimal SMTP client (no Composer/PHPMailer in this repo). Supports
 * implicit TLS (port 465) and STARTTLS (587), AUTH LOGIN, plain-text
 * UTF-8 bodies. Throws \RuntimeException on any failure so the caller
 * (EmailService) can fall through to the next account.
 */
class SmtpMailer
{
    /** @var resource */
    private $socket;

    public static function send(string $host, int $port, string $username, string $password, string $to, string $subject, string $body): void
    {
        $mailer = new self();
        try {
            $mailer->connect($host, $port);
            $mailer->authenticate($username, $password);
            $mailer->deliver($username, $to, $subject, $body);
        } finally {
            $mailer->close();
        }
    }

    private function connect(string $host, int $port): void
    {
        $scheme = $port === 465 ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client($scheme . $host . ':' . $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($socket, 15);
        $this->socket = $socket;

        $this->expect(220);
        $this->command('EHLO skoolyst-ads', 250);

        if ($port !== 465) {
            $this->command('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS negotiation failed.');
            }
            $this->command('EHLO skoolyst-ads', 250);
        }
    }

    private function authenticate(string $username, string $password): void
    {
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($username), 334);
        $this->command(base64_encode($password), 235);
    }

    private function deliver(string $from, string $to, string $subject, string $body): void
    {
        $clean = static fn (string $v): string => str_replace(["\r", "\n"], '', $v);
        $from = $clean($from);
        $to = $clean($to);

        $this->command("MAIL FROM:<{$from}>", 250);
        $this->command("RCPT TO:<{$to}>", 250);
        $this->command('DATA', 354);

        $headers = [
            'Date: ' . date('r'),
            "From: {$from}",
            "To: {$to}",
            'Subject: ' . mb_encode_mimeheader($clean($subject), 'UTF-8', 'B', "\r\n"),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@skoolyst-ads>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");

        fwrite($this->socket, $message . "\r\n.\r\n");
        $this->expect(250);
    }

    private function close(): void
    {
        if (is_resource($this->socket ?? null)) {
            @fwrite($this->socket, "QUIT\r\n");
            fclose($this->socket);
        }
    }

    private function command(string $line, int $expectedCode): string
    {
        fwrite($this->socket, $line . "\r\n");

        return $this->expect($expectedCode);
    }

    private function expect(int $code): string
    {
        $response = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if ((int) substr($response, 0, 3) !== $code) {
            throw new \RuntimeException('SMTP error, expected ' . $code . ': ' . trim($response));
        }

        return $response;
    }
}
