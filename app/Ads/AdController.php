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
}
