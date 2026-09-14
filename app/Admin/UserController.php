<?php

namespace App\Admin;

/**
 * UserController
 *
 * Admin-only CRUD over the `users` table (both advertiser and admin
 * accounts) — backs public/admin/advertisers.php. Kept thin: validate
 * input, call UserRepository, return a response, same shape as
 * App\Apps\AppController.
 */
use App\Auth\UserRepository;
use Core\Request;
use Core\Response;
use Core\Validator;
use Core\Auth\Middleware;
use Core\AuditLog;

class UserController
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    /**
     * GET /api/v1/admin/users
     */
    public function index(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        Response::success(['users' => $this->users->allWithAdsCounts()]);
    }

    /**
     * POST /api/v1/admin/users
     * Creates either role — unlike POST /api/v1/auth/register (advertiser
     * self-signup only), an admin can seed another admin account here.
     */
    public function store(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        $name = Request::string('name');
        $email = Request::string('email');
        $password = (string) (Request::input()['password'] ?? '');
        $role = Request::string('role', 'advertiser');

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

        if (!in_array($role, ['advertiser', 'admin'], true)) {
            Response::error(['code' => 'validation_error', 'message' => 'Role must be advertiser or admin.']);
            return;
        }

        if ($this->users->emailTakenByOther($email)) {
            Response::error(['code' => 'email_taken', 'message' => 'An account with that email already exists.'], 409);
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $user = $this->users->create($name, $email, $passwordHash, $role);

        $adminId = Middleware::checkSession();
        AuditLog::write((int) $adminId, 'user.create', 'user', $user->id);

        Response::success(['user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => $user->createdAt,
            'ads_count' => 0,
        ]], 201);
    }

    /**
     * PATCH /api/v1/admin/users/{id}
     * Name/email/role always update; password only changes when the
     * admin actually typed a new one (empty password field = keep it).
     */
    public function update(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $targetId = Request::int('user_id');
        $name = Request::string('name');
        $email = Request::string('email');
        $password = (string) (Request::input()['password'] ?? '');
        $role = Request::string('role');

        if ($targetId === null || !Validator::required($name) || !Validator::required($email)) {
            Response::error(['code' => 'validation_error', 'message' => 'user_id, name, and email are required.']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error(['code' => 'validation_error', 'message' => 'Enter a valid email address.']);
            return;
        }

        if (!in_array($role, ['advertiser', 'admin'], true)) {
            Response::error(['code' => 'validation_error', 'message' => 'Role must be advertiser or admin.']);
            return;
        }

        // An admin can't demote/lock themselves out through this screen —
        // they'd otherwise be able to remove their own admin access with
        // no other admin account guaranteed to exist to undo it.
        if ($targetId === $adminId && $role !== 'admin') {
            Response::error(['code' => 'cannot_demote_self', 'message' => 'You can\'t change your own role away from admin.'], 422);
            return;
        }

        if ($password !== '' && mb_strlen($password) < 8) {
            Response::error(['code' => 'validation_error', 'message' => 'Password must be at least 8 characters.']);
            return;
        }

        if ($this->users->emailTakenByOther($email, $targetId)) {
            Response::error(['code' => 'email_taken', 'message' => 'Another account already uses that email.'], 409);
            return;
        }

        $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

        if (!$this->users->update($targetId, $name, $email, $role, $passwordHash)) {
            Response::error(['code' => 'not_found', 'message' => 'User not found.'], 404);
            return;
        }

        AuditLog::write($adminId, 'user.update', 'user', $targetId);

        Response::success([]);
    }

    /**
     * DELETE /api/v1/admin/users/{id}
     * Cascades to every ad the user owns (ads_user_id_fk ON DELETE
     * CASCADE, 0004_create_ads_table.php) — the confirm dialog on
     * advertisers.php warns about that before this is ever called.
     */
    public function destroy(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $targetId = Request::int('user_id');
        if ($targetId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'user_id is required.']);
            return;
        }

        if ($targetId === $adminId) {
            Response::error(['code' => 'cannot_delete_self', 'message' => 'You can\'t delete your own account while logged in as it.'], 422);
            return;
        }

        if (!$this->users->delete($targetId)) {
            Response::error(['code' => 'not_found', 'message' => 'User not found.'], 404);
            return;
        }

        AuditLog::write($adminId, 'user.delete', 'user', $targetId);

        Response::success([]);
    }
}
