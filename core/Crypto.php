<?php

namespace Core;

/**
 * Crypto
 *
 * Reversible AES-256-GCM encryption for secrets that must be recovered
 * later (e.g. SMTP app passwords) — unlike API keys, which are only
 * ever hashed. The key comes from EMAIL_ENCRYPTION_KEY (base64 of 32
 * random bytes; generate with `php -r "echo base64_encode(random_bytes(32));"`).
 */
class Crypto
{
    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('Malformed encrypted value.');
        }

        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed (wrong EMAIL_ENCRYPTION_KEY?).');
        }

        return $plain;
    }

    private static function key(): string
    {
        $key = base64_decode((string) getenv('EMAIL_ENCRYPTION_KEY'), true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('EMAIL_ENCRYPTION_KEY must be set to a base64-encoded 32-byte key.');
        }

        return $key;
    }
}
