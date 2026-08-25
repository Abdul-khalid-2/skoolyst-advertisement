<?php

namespace App\Ads;

/**
 * AdController
 *
 * Handles HTTP requests for the Ads module (advertiser-facing and
 * public serve/track endpoints). Kept thin: validate input, call a
 * repository method, return a response.
 *
 * Stub only — methods added when routes in Section 4 are wired up.
 */
use Core\Response;

class AdController
{
    /**
     * Example method only — shows the standard lifecycle a real
     * controller method follows: call a repository method, return a
     * response via Response::success(). No query logic here.
     */
    public function ping(): void
    {
        $repository = new AdRepository();
        $data = $repository->ping();

        Response::success($data);
    }

    /**
     * GET /api/v1/ads/serve?placement={code}
     * Empty handler — query logic added once AdRepository has real
     * ad-fetching methods (Section 5 schema required first).
     */
    public function serve(): void
    {
        Response::success([]);
    }

    /**
     * POST /api/v1/ads/{id}/impression
     * Empty handler — logging added once ad_impressions table exists.
     */
    public function impression(): void
    {
        Response::success([]);
    }

    /**
     * POST /api/v1/ads/{id}/click
     * Empty handler — logging added once ad_clicks table exists.
     */
    public function click(): void
    {
        Response::success([]);
    }

    /**
     * POST /api/v1/advertiser/ads
     * Empty handler — validation (AdValidator) and creation added
     * once the `ads` table exists.
     */
    public function store(): void
    {
        Response::success([]);
    }

    /**
     * PATCH /api/v1/advertiser/ads/{id}
     * Empty handler — validation (AdValidator) and update added
     * once the `ads` table exists.
     */
    public function update(): void
    {
        Response::success([]);
    }
}
