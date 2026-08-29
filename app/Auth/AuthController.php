<?php

namespace App\Auth;

/**
 * AuthController
 *
 * Handles login/session for advertisers and admins, and API-key
 * issuing for connected apps (issuing itself lives in Apps module —
 * see AppController::store). Kept thin: validate input, call a
 * repository method, return a response.
 */
use Core\Request;
use Core\Response;
use Core\Validator;
use Core\Auth\Middleware;
use Core\Security\Csrf;

class AuthController
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    /**
     * POST /api/v1/auth/register
     * Advertiser self-signup. Admin accounts are seeded/created
     * directly, never through this public endpoint.
     */
    public function register(): void
    {
        $name = Request::string('name');
        $email = Request::string('email');
        $password = (string) (Request::input()['password'] ?? '');

        if (!Validator::required($name) || !Validator::required($email) || !Validator::required($password)) {
            Response::error(['code' => 'validation_error', 'message' => 'Name, email, and password are required.']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error(['code' => 'validation_error', 'message' => 'Enter a valid email address.']);
            return;
        }

        if (mb_strlen($password) < 8) {
            Response::error(['code' => 'validation_error', 'message' => 'Password must be at least 8 characters.']);
            return;
        }

        if ($this->users->findByEmail($email) !== null) {
            Response::error(['code' => 'email_taken', 'message' => 'An account with that email already exists.'], 409);
            return;
        }

        // 6.a — never store the raw password, only its hash.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $user = $this->users->create($name, $email, $passwordHash, 'advertiser');

        Response::success(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role], 201);
    }

    /**
     * POST /api/v1/auth/login
     * Verifies credentials and issues the session cookie.
     */
    public function login(): void
    {
        $email = Request::string('email');
        $password = (string) (Request::input()['password'] ?? '');

        $user = $this->users->findByEmail($email);

        // 6.b — password_verify() against the stored hash. Same error
        // message whether the email or the password was wrong, so a
        // request can't be used to enumerate registered emails.
        if ($user === null || !password_verify($password, $user->passwordHash)) {
            Response::error(['code' => 'invalid_credentials', 'message' => 'Incorrect email or password.'], 401);
            return;
        }

        Middleware::startSession($user->id);

        // A fresh CSRF token per login — a token issued before login
        // (e.g. on the public login page) can never be replayed after.
        Csrf::regenerate();

        Response::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'csrf_token' => Csrf::token(),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Destroys the session server-side and expires the cookie.
     */
    public function logout(): void
    {
        Middleware::destroySession();
        Response::success([]);
    }

    /**
     * GET /api/v1/auth/session
     * Lets a static/public page (index.html's navbar) ask "is anyone
     * logged in?" without exposing anything sensitive — just enough
     * to decide which buttons to show. Public route (auth => false)
     * on purpose: a guest calling this must not get an error, just
     * {loggedIn: false}.
     */
    public function session(): void
    {
        $userId = Middleware::checkSession();
        if ($userId === null) {
            Response::success(['loggedIn' => false]);
            return;
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            Response::success(['loggedIn' => false]);
            return;
        }

        Response::success(['loggedIn' => true, 'role' => $user->role]);
    }
}
