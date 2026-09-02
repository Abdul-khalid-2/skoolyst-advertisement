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
     * The write only happens once the status update itself actually
     * matched a row — a 404 for a bad id shouldn't still log an
     * action that never happened.
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

        if (!$this->ads->updateStatus($adId, 'active')) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        AuditLog::write($adminId, 'ad.approve', 'ad', $adId);

        Response::success([]);
    }

    /**
     * PATCH /api/v1/admin/ads/{id}/reject
     * `reason` is optional — shown to the advertiser on their side
     * (my-ads.php, 10.g) when present.
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

        $reason = Request::string('reason') ?: null;

        if (!$this->ads->updateStatus($adId, 'rejected', $reason)) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        AuditLog::write($adminId, 'ad.reject', 'ad', $adId);

        Response::success([]);
    }

    /**
     * PATCH /api/v1/admin/ads/{id}/pause
     * Admin counterpart to the advertiser's pause() (AdController) —
     * unscoped by owner via the same updateStatus() approve/reject
     * already use above, since an admin can pause any advertiser's ad,
     * not just their own. No fromStatus check (unlike the advertiser
     * path): an admin already has unrestricted delete/edit here, so
     * pausing from any status is treated the same way.
     */
    public function pause(): void
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

        if (!$this->ads->updateStatus($adId, 'paused')) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        AuditLog::write($adminId, 'ad.pause', 'ad', $adId);

        Response::success([]);
    }

    /**
     * PATCH /api/v1/admin/ads/{id}/activate
     * Admin counterpart to the advertiser's activate() (AdController).
     * Unlike the advertiser path, this also accepts a 'draft' ad —
     * the moderation table's Activate button (views/components/ads-
     * table.php) is shown for both 'paused' and 'draft' rows, and an
     * admin activating an ad directly *is* the moderation decision,
     * same reasoning as updateById() leaving status untouched on plain
     * edits.
     */
    public function activate(): void
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

        if (!$this->ads->updateStatus($adId, 'active')) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        AuditLog::write($adminId, 'ad.activate', 'ad', $adId);

        Response::success([]);
    }
}
