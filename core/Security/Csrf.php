<?php

namespace Core\Security;

/**
 * Csrf
 *
 * Generates and verifies the CSRF token used by every state-changing
 * dashboard form (Section 6.i/6.j). One token per session, regenerated
 * on login (see AuthController::login) so a stale token from a previous
 * session can never be replayed.
 */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Returns the current session's CSRF token, generating one on
     * first use. Called by the `csrf_field()` view helper to render
     * the hidden input on every protected form.
     */
    public static function token(): string
    {
        self::ensureSessionStarted();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Forces a fresh token, discarding any previous one. Called on
     * login so a session fixation attempt can't reuse a pre-login token.
     */
    public static function regenerate(): string
    {
        self::ensureSessionStarted();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Verifies a submitted token against the session's token using a
     * timing-safe comparison. Used by CsrfMiddleware on every
     * state-changing route (POST/PATCH/DELETE).
     */
    public static function verify(?string $submitted): bool
    {
        self::ensureSessionStarted();

        if (!$submitted || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $submitted);
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
