<?php

namespace Core\Auth;

/**
 * Middleware
 *
 * Reused by every protected route: dashboard requests are checked
 * against the signed session cookie (Section 6). Token/API-key
 * checks for connected-app requests are added next.
 */
class Middleware
{
    /**
     * Returns the logged-in user's id from the session, or null if
     * there is no valid session.
     */
    public static function checkSession(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Returns the raw bearer token from the Authorization header sent
     * by a connected app, or null if missing. The token itself is
     * looked up/hashed-compared against api_keys by AppRepository —
     * this only extracts it from the request.
     */
    public static function checkApiKey(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return null;
    }
}
