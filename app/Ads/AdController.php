<?php

namespace App\Ads;

/**
 * AdController
 *
 * Handles HTTP requests for the Ads module (advertiser-facing,
 * admin-edit, and public serve/track endpoints). Kept thin: validate
 * input, call a repository method, return a response.
 */
use Core\Response;
use Core\Request;
use Core\Validator;
use Core\Uploads;
use Core\Cache;
use Core\Auth\Middleware;
use App\Apps\AppRepository;

class AdController
{
    private AdRepository $ads;
    private AppRepository $apps;

    public function __construct()
    {
        $this->ads = new AdRepository();
        $this->apps = new AppRepository();
    }

    /**
     * Example method only — shows the standard lifecycle a real
     * controller method follows: call a repository method, return a
     * response via Response::success(). No query logic here.
     */
    public function ping(): void
    {
        Response::success(['module' => 'Ads', 'status' => 'ok']);
    }

    /**
     * GET /api/v1/ads/serve?placement={code}
     * Public route, but still scoped to the calling app: the bearer
     * key resolves to an app_id, and only that app's own placements
     * are ever queried (6.u). No key, no ads.
     */
    public function serve(): void
    {
        $apiKey = Middleware::checkApiKey();
        $appId = $apiKey ? $this->apps->resolveAppId($apiKey) : null;

        if ($appId === null) {
            Response::error(['code' => 'unauthorized', 'message' => 'A valid API key is required.'], 401);
            return;
        }

        $placementCode = Request::string('placement');
        if (!Validator::required($placementCode)) {
            Response::error(['code' => 'validation_error', 'message' => 'placement is required.']);
            return;
        }

        // 7.c — cache read before touching the DB. Keyed per app+placement
        // so one app's cached ad is never served to another (keeps the
        // 6.u scoping guarantee intact even through the cache layer).
        $cacheKey = "ads:serve:{$appId}:{$placementCode}";
        $ad = Cache::get($cacheKey);

        if ($ad === null) {
            $ad = $this->ads->findServableForPlacement($appId, $placementCode);

            // AdRepository returns the bare filename as stored by
            // core/Uploads.php (e.g. "abc123.jpg") — resolve it here
            // into the actual public path so consuming apps can turn
            // it into a real URL without knowing our storage layout.
            // Images live under public/uploads/ads/ and are served
            // directly from there (public/.htaccess); the documented
            // /images/ads/{filename} route doesn't actually work, since
            // its {filename} segment is never bound by the router.
            if ($ad !== null && $ad['image_path']) {
                $ad['image_path'] = 'uploads/ads/' . $ad['image_path'];
            }

            // 7.d — short TTL: long enough to absorb a traffic burst on a
            // popular placement, short enough that a newly-approved or
            // newly-expired ad shows up within a few seconds, not minutes.
            Cache::set($cacheKey, $ad, 30);
        }

        Response::success(['ad' => $ad]);
    }

    /**
     * POST /api/v1/ads/{id}/impression
     * Confirms the ad belongs to the calling app before logging —
     * an app can never inflate another app's ad's numbers (6.u).
     */
    public function impression(): void
    {
        $this->track('impression');
    }

    /**
     * POST /api/v1/ads/{id}/click
     */
    public function click(): void
    {
        $this->track('click');
    }

    private function track(string $type): void
    {
        $apiKey = Middleware::checkApiKey();
        $appId = $apiKey ? $this->apps->resolveAppId($apiKey) : null;

        if ($appId === null) {
            Response::error(['code' => 'unauthorized', 'message' => 'A valid API key is required.'], 401);
            return;
        }

        $adId = Request::int('ad_id');
        if ($adId === null || !$this->ads->belongsToApp($adId, $appId)) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found for this app.'], 404);
            return;
        }

        $type === 'impression' ? $this->ads->recordImpression($adId) : $this->ads->recordClick($adId);

        Response::success([]);
    }

    /**
     * POST /api/v1/advertiser/ads
     * Advertiser-only (6.g). Validates input, re-encodes/validates any
     * uploaded image (6.o–6.r), and inserts as 'pending' — never
     * directly 'active', so every new ad goes through moderation.
     */
    public function store(): void
    {
        $userId = Middleware::requireRole(['advertiser']);
        if ($userId === null) {
            return;
        }

        $data = $this->validatedAdInput();
        if ($data === null) {
            return; // validatedAdInput() has already sent the error response.
        }

        try {
            $data['image_path'] = isset($_FILES['image']) ? Uploads::storeAdImage($_FILES['image']) : null;
        } catch (\RuntimeException $e) {
            Response::error(['code' => 'invalid_image', 'message' => $e->getMessage()]);
            return;
        }

        $ad = $this->ads->create($userId, $data);

        Response::success($ad, 201);
    }

    /**
     * GET /api/v1/advertiser/ads/{id}
     * Advertiser-only (6.g). Backs the "Edit Ad" form's prefill —
     * findForUser() itself only returns a row owned by $userId, so an
     * advertiser can't load another advertiser's ad by guessing its id.
     */
    public function show(): void
    {
        $userId = Middleware::requireRole(['advertiser']);
        if ($userId === null) {
            return;
        }

        $adId = Request::int('ad_id');
        if ($adId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'ad_id is required.']);
            return;
        }

        $ad = $this->ads->findForUser($adId, $userId);

        if ($ad === null) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        Response::success(['ad' => $ad]);
    }

    /**
     * PATCH /api/v1/advertiser/ads/{id}
     * Advertiser-only (6.g), and updateForUser() itself only affects a
     * row owned by $userId — an advertiser can't edit another
     * advertiser's ad by guessing its id.
     */
    public function update(): void
    {
        $userId = Middleware::requireRole(['advertiser']);
        if ($userId === null) {
            return;
        }

        $adId = Request::int('ad_id');
        if ($adId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'ad_id is required.']);
            return;
        }

        $data = $this->validatedAdInput(requireAppPlacement: false);
        if ($data === null) {
            return;
        }

        $updated = $this->ads->updateForUser($adId, $userId, $data);

        if (!$updated) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        Response::success([]);
    }

    /**
     * POST /api/v1/advertiser/ads/{id}/image
     * Advertiser-only (6.g). Split out from update() as its own POST
     * endpoint rather than a field on the PATCH body: a new image can
     * only ever arrive as multipart/form-data, and PHP only populates
     * $_FILES for that content type on a POST request — never on
     * PATCH/PUT, whatever the client sends. Same re-encode/validate
     * pipeline as store() (6.o–6.r); updateImageForUser() itself only
     * affects a row owned by $userId, same ownership guarantee as
     * update().
     */
    public function updateImage(): void
    {
        $userId = Middleware::requireRole(['advertiser']);
        if ($userId === null) {
            return;
        }

        $adId = Request::int('ad_id');
        if ($adId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'ad_id is required.']);
            return;
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            Response::error(['code' => 'validation_error', 'message' => 'An image file is required.']);
            return;
        }

        try {
            $imagePath = Uploads::storeAdImage($_FILES['image']);
        } catch (\RuntimeException $e) {
            Response::error(['code' => 'invalid_image', 'message' => $e->getMessage()]);
            return;
        }

        $updated = $this->ads->updateImageForUser($adId, $userId, $imagePath);

        if (!$updated) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        Response::success(['image_path' => $imagePath]);
    }

    /**
     * PATCH /api/v1/advertiser/ads/{id}/pause
     * Advertiser-only (6.g). Only an already-'active' ad can be
     * paused — an advertiser flipping a 'pending'/'rejected'/'draft'
     * ad straight to 'paused' would let it skip moderation entirely,
     * so that's rejected as a validation error rather than silently
     * allowed or 404'd.
     */
    public function pause(): void
    {
        $this->setStatusForUser('active', 'paused', 'This ad can\'t be paused from its current status.');
    }

    /**
     * PATCH /api/v1/advertiser/ads/{id}/activate
     * Advertiser-only (6.g). Only a previously-'paused' ad can be
     * reactivated this way — re-activating a 'draft' still has to go
     * through update()/moderation like any new ad (it's never been
     * approved), so 'draft' is deliberately not accepted here.
     */
    public function activate(): void
    {
        $this->setStatusForUser('paused', 'active', 'This ad can\'t be activated from its current status.');
    }

    /**
     * Shared body for pause()/activate() — both are a single allowed
     * status transition, scoped to the requesting advertiser's own
     * ad. updateStatusForUser()'s WHERE clause already enforces
     * ownership; the extra findForUser() lookup here exists only to
     * tell "wrong current status" (422) apart from "no such ad, or
     * not yours" (404), which a single UPDATE's rowCount() can't
     * distinguish on its own.
     */
    private function setStatusForUser(string $fromStatus, string $toStatus, string $wrongStatusMessage): void
    {
        $userId = Middleware::requireRole(['advertiser']);
        if ($userId === null) {
            return;
        }

        $adId = Request::int('ad_id');
        if ($adId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'ad_id is required.']);
            return;
        }

        $ad = $this->ads->findForUser($adId, $userId);
        if ($ad === null) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        if ($ad['status'] !== $fromStatus) {
            Response::error(['code' => 'validation_error', 'message' => $wrongStatusMessage]);
            return;
        }

        $this->ads->updateStatusForUser($adId, $userId, $toStatus);

        Response::success([]);
    }

    /**
     * DELETE /api/v1/advertiser/ads/{id}
     * Advertiser-only (6.g). Soft delete — deleteForUser() flips the
     * ad's status to 'deleted' rather than removing the row (see that
     * method for why), scoped to $userId same as every other
     * advertiser-facing method here.
     */
    public function destroy(): void
    {
        $userId = Middleware::requireRole(['advertiser']);
        if ($userId === null) {
            return;
        }

        $adId = Request::int('ad_id');
        if ($adId === null) {
            Response::error(['code' => 'validation_error', 'message' => 'ad_id is required.']);
            return;
        }

        if (!$this->ads->deleteForUser($adId, $userId)) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        Response::success([]);
    }

    /**
     * GET /api/v1/admin/ads/{id}
     * Admin-only. Backs the "Edit Ad" form when opened from the
     * moderation table — findById() is unscoped by owner, since an
     * admin edits any advertiser's ad, not just their own.
     */
    public function adminShow(): void
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

        $ad = $this->ads->findById($adId);

        if ($ad === null) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        Response::success(['ad' => $ad]);
    }

    /**
     * PATCH /api/v1/admin/ads/{id}
     * Admin-only. Same validatedAdInput() as the advertiser's update(),
     * but updateById() is unscoped by owner and leaves `status`
     * untouched — see updateById()'s docblock for why.
     */
    public function adminUpdate(): void
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

        $data = $this->validatedAdInput(requireAppPlacement: false);
        if ($data === null) {
            return;
        }

        $updated = $this->ads->updateById($adId, $data);

        if (!$updated) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        Response::success([]);
    }

    /**
     * POST /api/v1/admin/ads/{id}/image
     * Admin-only. Same split-from-PATCH reasoning as the advertiser's
     * updateImage(); updateImageById() is unscoped by owner.
     */
    public function adminUpdateImage(): void
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

        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            Response::error(['code' => 'validation_error', 'message' => 'An image file is required.']);
            return;
        }

        try {
            $imagePath = Uploads::storeAdImage($_FILES['image']);
        } catch (\RuntimeException $e) {
            Response::error(['code' => 'invalid_image', 'message' => $e->getMessage()]);
            return;
        }

        $updated = $this->ads->updateImageById($adId, $imagePath);

        if (!$updated) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        Response::success(['image_path' => $imagePath]);
    }

    /**
     * DELETE /api/v1/admin/ads/{id}
     * Admin-only counterpart to the advertiser's destroy() — same
     * soft-delete behaviour (deleteById() flips status to 'deleted'
     * rather than removing the row), but unscoped by owner since an
     * admin deletes any advertiser's ad, not just their own.
     */
    public function adminDestroy(): void
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

        if (!$this->ads->deleteById($adId)) {
            Response::error(['code' => 'not_found', 'message' => 'Ad not found.'], 404);
            return;
        }

        Response::success([]);
    }

    /**
     * Shared validation for store()/update(). Ad copy is stored as
     * plain trimmed text here — output escaping (6.n) happens at
     * render time in views/components/ads-table.php via
     * htmlspecialchars(), not here, so the same stored value is safe
     * for both the JSON API and the HTML dashboard.
     *
     * @return array<string, mixed>|null Null once an error has been sent.
     */
    private function validatedAdInput(bool $requireAppPlacement = true): ?array
    {
        $advertiserName = Request::string('advertiser_name');
        $title = Request::string('title');
        $description = Request::string('description');
        $ctaText = Request::string('cta_text');
        $clickUrl = Request::string('click_url');
        $startDate = Request::string('start_date') ?: null;
        $endDate = Request::string('end_date') ?: null;

        if (!Validator::required($advertiserName) || !Validator::maxLength($advertiserName, 150)) {
            Response::error(['code' => 'validation_error', 'message' => 'Advertiser / business name is required (max 150 characters).']);
            return null;
        }

        if (!Validator::required($title) || !Validator::maxLength($title, 150)) {
            Response::error(['code' => 'validation_error', 'message' => 'Title is required (max 150 characters).']);
            return null;
        }

        if (!Validator::required($clickUrl) || !Validator::url($clickUrl)) {
            Response::error(['code' => 'validation_error', 'message' => 'A valid destination URL is required.']);
            return null;
        }

        if ($startDate !== null && !Validator::date($startDate)) {
            Response::error(['code' => 'validation_error', 'message' => 'start_date must be in Y-m-d format.']);
            return null;
        }

        if ($endDate !== null && !Validator::date($endDate)) {
            Response::error(['code' => 'validation_error', 'message' => 'end_date must be in Y-m-d format.']);
            return null;
        }

        $data = [
            'advertiser_name' => $advertiserName,
            'title' => $title,
            'description' => $description,
            'cta_text' => $ctaText,
            'click_url' => $clickUrl,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($requireAppPlacement) {
            $appId = Request::int('app_id');
            $placementId = Request::int('placement_id');

            if ($appId === null || $placementId === null) {
                Response::error(['code' => 'validation_error', 'message' => 'app_id and placement_id are required.']);
                return null;
            }

            $data['app_id'] = $appId;
            $data['placement_id'] = $placementId;
        }

        return $data;
    }
}
