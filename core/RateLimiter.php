<?php

namespace Core;

/**
 * RateLimiter
 *
 * Skeleton class for per-key rate limiting (used on /ads/serve and
 * the tracking endpoints — see Section 6). Storage backend (file or
 * Redis, matching Cache.php) is wired in when it's applied to a
 * real route.
 */
class RateLimiter
{
    /**
     * Records one hit for the given key and reports whether the
     * caller is still within the allowed limit for the time window.
     *
     * @param string $key Unique identifier, e.g. an API key or IP.
     * @param int $maxHits Max hits allowed within $windowSeconds.
     * @param int $windowSeconds Length of the rate-limit window.
     */
    public function hit(string $key, int $maxHits = 60, int $windowSeconds = 60): bool
    {
        // Storage backend not wired up yet — stub always allows.
        return true;
    }
}
