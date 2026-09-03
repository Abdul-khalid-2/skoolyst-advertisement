<?php

namespace App\Apps;

/**
 * PlacementController
 *
 * Handles HTTP requests for the placement CRUD admin/apps.php was
 * missing (10.i wired up the app-level create/pause-activate/
 * regenerate-key actions, but never gave an app a way to actually
 * define the placement codes it serves ads into — every app has
 * shown a "Placements" count since 10.i, with no way to add to it
 * short of a manual DB insert). Kept thin, same shape as
 * AppController: validate input, call PlacementRepository, respond.
 *
 * Every action here is admin-only — placements are defined once per
 * app under Admin → Connected Apps (ads.skoolyst.com/admin/apps.php),
 * not something an advertiser or a connected app itself manages.
 */
use Core\Request;
use Core\Response;
use Core\Validator;
use Core\Auth\Middleware;
use Core\AuditLog;

class PlacementController
{
    private PlacementRepository $placements;
    private AppRepository $apps;

    public function __construct()
    {
        $this->placements = new PlacementRepository();
        $this->apps = new AppRepository();
    }

    /**
     * GET /api/v1/admin/apps/{id}/placements
     * The `{id}` path segment only matches the route (see
     * public/index.php's routePathToRegex() doc-block, and
     * AppController::update()'s same convention) — the real app id
     * is read from the query string here, since Request::input()
     * merges $_GET as a fallback.
     */
    public function index(): void
    {
        if (Middleware::requireRole(['admin']) === null) {
            return;
        }

        $appId = Request::int('app_id');
        if ($appId === null || $this->apps->find($appId) === null) {
            Response::error(['code' => 'not_found', 'message' => 'App not found.'], 404);
            return;
        }

        Response::success(['placements' => $this->placements->allForApp($appId)]);
    }

    /**
     * POST /api/v1/admin/apps/{id}/placements
     * Creates a new placement code under an app. `code` only needs to
     * be unique within that app (migration 0014) — checked here so a
     * duplicate reports as a clean validation_error rather than the
     * DB's unique-constraint exception surfacing as a 500.
     */
    public function store(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $appId = Request::int('app_id');
        $code = Request::string('code');
        $label = Request::string('label');

        if ($appId === null || $this->apps->find($appId) === null) {
            Response::error(['code' => 'not_found', 'message' => 'App not found.'], 404);
            return;
        }

        if (!Validator::required($code) || !Validator::required($label)) {
            Response::error(['code' => 'validation_error', 'message' => 'Code and label are required.']);
            return;
        }

        if (!Validator::maxLength($code, 100) || !Validator::maxLength($label, 150)) {
            Response::error(['code' => 'validation_error', 'message' => 'Code must be 100 characters or fewer, label 150.']);
            return;
        }

        if ($this->placements->codeExistsForApp($appId, $code)) {
            Response::error(['code' => 'validation_error', 'message' => 'This app already has a placement with that code.']);
            return;
        }

        $placement = $this->placements->create($appId, $code, $label);

        AuditLog::write($adminId, 'placement.create', 'placement', (int) $placement['id']);

        Response::success(['placement' => $placement], 201);
    }

    /**
     * PATCH /api/v1/admin/apps/{id}/placements/{placementId}
     * Both ids are cosmetic path segments (see index()'s doc-block);
     * `placement_id` is read from the body, same as `app_id` on
     * AppController::update(). The placement's own app_id is re-read
     * from the DB rather than trusted from the request, so this can
     * never be used to "move" a placement onto a different app id
     * than the one it actually belongs to.
     */
    public function update(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $placementId = Request::int('placement_id');
        $code = Request::string('code');
        $label = Request::string('label');

        if ($placementId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'placement_id is required.']);
            return;
        }

        $existing = $this->placements->find($placementId);
        if ($existing === null) {
            Response::error(['code' => 'not_found', 'message' => 'Placement not found.'], 404);
            return;
        }

        if (!Validator::required($code) || !Validator::required($label)) {
            Response::error(['code' => 'validation_error', 'message' => 'Code and label are required.']);
            return;
        }

        if (!Validator::maxLength($code, 100) || !Validator::maxLength($label, 150)) {
            Response::error(['code' => 'validation_error', 'message' => 'Code must be 100 characters or fewer, label 150.']);
            return;
        }

        if ($this->placements->codeExistsForApp((int) $existing['app_id'], $code, $placementId)) {
            Response::error(['code' => 'validation_error', 'message' => 'This app already has a placement with that code.']);
            return;
        }

        $this->placements->update($placementId, $code, $label);

        AuditLog::write($adminId, 'placement.update', 'placement', $placementId);

        Response::success(['placement' => $this->placements->find($placementId)]);
    }

    /**
     * DELETE /api/v1/admin/apps/{id}/placements/{placementId}
     * Refuses (409) rather than deletes when an ad still references
     * this placement — see PlacementRepository::hasAds()'s doc-block
     * for why: `ads.placement_id` cascades on delete, so deleting
     * through here without this check would silently take every ad
     * on that placement down with it.
     */
    public function destroy(): void
    {
        $adminId = Middleware::requireRole(['admin']);
        if ($adminId === null) {
            return;
        }

        $placementId = Request::int('placement_id');
        if ($placementId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'placement_id is required.']);
            return;
        }

        if ($this->placements->find($placementId) === null) {
            Response::error(['code' => 'not_found', 'message' => 'Placement not found.'], 404);
            return;
        }

        if ($this->placements->hasAds($placementId)) {
            Response::error([
                'code' => 'conflict',
                'message' => 'This placement has ads on it and can\'t be deleted. Pause or move those ads first.',
            ], 409);
            return;
        }

        $this->placements->delete($placementId);

        AuditLog::write($adminId, 'placement.delete', 'placement', $placementId);

        Response::success([]);
    }
}
