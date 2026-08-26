<?php

namespace Core\Security;

/**
 * CsrfMiddleware
 *
 * Verification step run by public/index.php for every session-based,
 * state-changing dashboard request (POST/PATCH/DELETE routes with
 * `auth => true` and no bearer API key — i.e. a human in the browser,
 * not a connected app). Connected-app API-key requests are exempt:
 * they never carry a session or a form-rendered token, and are already
 * authenticated by the key itself.
 */
class CsrfMiddleware
{
    private const PROTECTED_METHODS = ['POST', 'PATCH', 'PUT', 'DELETE'];

    /**
     * Returns true if the request is exempt or the token is valid,
     * false if it should be rejected with a 419-style error.
     */
    public static function passes(string $method, ?string $submittedToken, bool $hasApiKey): bool
    {
        if ($hasApiKey) {
            return true;
        }

        if (!in_array($method, self::PROTECTED_METHODS, true)) {
            return true;
        }

        return Csrf::verify($submittedToken);
    }
}
