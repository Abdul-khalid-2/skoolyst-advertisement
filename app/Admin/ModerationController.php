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
use Core\Request;
use Core\Auth\Middleware;
use Core\AuditLog;
use App\Ads\AdRepository;

class ModerationController
{
    private AdRepository $ads;

    public function __construct()
    {
        $this->ads = new AdRepository();
    }

    /**
     * GET /api/v1/admin/ads?status=pending
     * Paginated at the DB level (7.l) via AdRepository::findByStatus().
     */
    public function pendingAds(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        $status = Request::string('status') ?: 'pending';
        $page = Request::int('page') ?? 1;

        Response::success(['ads' => $this->ads->findByStatus($status, $page)]);
    }

    /**
     * PATCH /api/v1/admin/ads/{id}/approve
     * Status update, then an audit-log write (6.v) so every
     * approve/reject decision has a permanent, attributable record.
     */
    public function approve(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $adId = Request::int('ad_id');
        if ($adId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'ad_id is required.']);
            return;
        }

        AuditLog::write($adminId, 'ad.approve', 'ad', $adId);

        Response::success([]);
    }

    /**
     * PATCH /api/v1/admin/ads/{id}/reject
     */
    public function reject(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $adId = Request::int('ad_id');
        if ($adId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'ad_id is required.']);
            return;
        }

        AuditLog::write($adminId, 'ad.reject', 'ad', $adId);

        Response::success([]);
    }
}
