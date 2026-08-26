<?php

namespace App\Apps;

/**
 * AppController
 *
 * Handles HTTP requests for the Apps module — registering connected
 * apps and managing their API keys (Admin → Connected Apps).
 * Kept thin: validate input, call a repository method, return a response.
 */
use Core\Request;
use Core\Response;
use Core\Validator;
use Core\Auth\Middleware;
use Core\AuditLog;

class AppController
{
    private AppRepository $apps;

    public function __construct()
    {
        $this->apps = new AppRepository();
    }

    /**
     * GET /api/v1/admin/apps
     */
    public function index(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        Response::success(['apps' => $this->apps->all()]);
    }

    /**
     * POST /api/v1/admin/apps
     * Registers a connected app and issues its first API key (6.d),
     * stored as a hash (6.e). The plaintext key is returned exactly
     * once, in this response — it's never retrievable again.
     */
    public function store(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        $name = Request::string('name');
        $code = Request::string('code');
        $domain = Request::string('domain');

        if (!Validator::required($name) || !Validator::required($code) || !Validator::required($domain)) {
            Response::error(['code' => 'validation_error', 'message' => 'Name, code, and domain are required.']);
            return;
        }

        $result = $this->apps->createWithApiKey($name, $code, $domain);

        Response::success([
            'app' => $result['app'],
            'api_key' => $result['api_key'],
        ], 201);
    }

    /**
     * PATCH /api/v1/admin/apps/{id}
     * Empty handler — status/name/domain update added once the route
     * gains dynamic-segment matching (3.1 note in app/Ads/routes.php
     * applies here too).
     */
    public function update(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        Response::success([]);
    }

    /**
     * PATCH /api/v1/admin/apps/{id}/regenerate-key
     * Issues a fresh key, revokes the previous one, and writes an
     * audit-log entry for the action (6.w).
     */
    public function regenerateKey(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $appId = Request::int('app_id');
        if ($appId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'app_id is required.']);
            return;
        }

        $newKey = $this->apps->regenerateApiKey($appId);

        AuditLog::write($adminId, 'app.regenerate_key', 'app', $appId);

        Response::success(['api_key' => $newKey]);
    }
}
