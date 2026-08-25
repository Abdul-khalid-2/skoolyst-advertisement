<?php

namespace App\Apps;

/**
 * AppController
 *
 * Handles HTTP requests for the Apps module — registering connected
 * apps and managing their API keys (Admin → Connected Apps).
 * Kept thin: validate input, call a repository method, return a response.
 */
use Core\Response;

class AppController
{
    /**
     * GET /api/v1/admin/apps
     * Empty handler — query logic added once AppRepository has real
     * app-fetching methods (Section 5 schema required first).
     */
    public function index(): void
    {
        Response::success([]);
    }

    /**
     * POST /api/v1/admin/apps
     * Empty handler — validation and creation (incl. API key issuing,
     * Section 6) added once the `apps` table exists.
     */
    public function store(): void
    {
        Response::success([]);
    }

    /**
     * PATCH /api/v1/admin/apps/{id}
     * Empty handler — validation and update added once the `apps`
     * table exists.
     */
    public function update(): void
    {
        Response::success([]);
    }
}
