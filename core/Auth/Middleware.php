<?php

namespace Core\Auth;

use App\Auth\UserRepository;

/**
 * Middleware
 *
 * Reused by every protected route: dashboard requests are checked
 * against the signed session cookie (Section 6). Token/API-key
 * checks for connected-app requests are handled by checkApiKey()
 * below; role checks for advertiser-only / admin-only routes are
 * handled by requireRole().
 */
class Middleware
{
    /**
     * Returns the logged-in user's id from the session, or null if
     * there is no valid session.
     */
    public static function checkSession(): ?int
    {
        self::ensureSessionStarted();

        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Returns the raw bearer token from the Authorization header sent
     * by a connected app, or null if missing. The token itself is
     * looked up/hashed-compared against api_keys by AppRepository —
     * this only extracts it from the request (6.f).
     */
    public static function checkApiKey(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return null;
    }

    /**
     * Starts a fresh, signed, HttpOnly session for the given user id
     * (6.c). Called only after credentials are verified (see
     * AuthController::login). session_regenerate_id() prevents session
     * fixation — a session id issued before login can never be reused
     * to hijack the now-authenticated session.
     */
    public static function startSession(int $userId): void
    {
        self::configureCookieParams();
        self::ensureSessionStarted();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    /**
     * Destroys the session server-side and expires the cookie
     * client-side (used by AuthController::logout).
     */
    public static function destroySession(): void
    {
        self::ensureSessionStarted();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    /**
     * Enforces that the currently logged-in user has one of the given
     * roles (6.g advertiser-only, 6.h admin-only). Returns the user id
     * on success; sends a 403 and returns null otherwise, so callers
     * can `if (($id = Middleware::requireRole(['admin'])) === null) return;`.
     *
     * @param string[] $roles
     */
    public static function requireRole(array $roles): ?int
    {
        $userId = self::checkSession();

        if ($userId === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => ['message' => 'Unauthorized']]);
            return null;
        }

        $user = (new UserRepository())->findById($userId);

        if ($user === null || !in_array($user->role, $roles, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => ['message' => 'Forbidden']]);
            return null;
        }

        return $userId;
    }

    /**
     * Sets the session cookie flags before the session starts: HttpOnly
     * (no JS access), Secure (HTTPS only), and SameSite=Lax (sent on
     * normal navigation but not cross-site POSTs) — the "signed,
     * HttpOnly session cookie" required by 6.c.
     */
    private static function configureCookieParams(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // A distinct session cookie name — not PHP's PHPSESSID default —
        // so this project never shares a session with another PHP app
        // running on the same host/domain (e.g. another project also
        // served from localhost under XAMPP). Without this, two apps
        // both using the default name and the site-wide path='/' below
        // literally share one session file: logging into one silently
        // overwrites $_SESSION['user_id'] for the other, which then
        // reads back a user id that's wrong or doesn't exist in this
        // app's own `users` table — surfacing as an unexplained 403
        // from requireRole() rather than an obvious login failure.
        session_name('skoolyst_ads_session');

        // Secure cookies are dropped outright by browsers over plain
        // HTTP — fine in staging/production (always HTTPS), but it
        // would silently break every session-based login on local
        // XAMPP/`php -S` dev (Section 11), where APP_ENV=local and
        // there's no TLS. Only require Secure once the app itself
        // isn't running as local.
        $appConfig = require __DIR__ . '/../../config/app.php';
        $isLocal = ($appConfig['env'] ?? 'local') === 'local';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => !$isLocal,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::configureCookieParams();
            session_start();
        }
    }
}
