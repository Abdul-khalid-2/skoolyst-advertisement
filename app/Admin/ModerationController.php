<?php

namespace App\Admin;

/**
 * ModerationController
 *
 * Handles admin-facing moderation actions: viewing the pending-ads
 * queue, approving/rejecting ads, and connected-app management.
 * Kept thin: validate input, call a repository method, return a response.
 */
use Core\Response;

class ModerationController
{
    /**
     * GET /api/v1/admin/ads?status=pending
     * Empty handler — query logic added once AdRepository has real
     * ad-fetching methods (Section 5 schema required first).
     */
    public function pendingAds(): void
    {
        Response::success([]);
    }

    /**
     * PATCH /api/v1/admin/ads/{id}/approve
     * Empty handler — status update + audit log write (Section 6)
     * added once the `ads` table exists.
     */
    public function approve(): void
    {
        Response::success([]);
    }

    /**
     * PATCH /api/v1/admin/ads/{id}/reject
     * Empty handler — status update + audit log write (Section 6)
     * added once the `ads` table exists.
     */
    public function reject(): void
    {
        Response::success([]);
    }
}
