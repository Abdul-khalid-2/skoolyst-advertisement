<?php

namespace Core;

/**
 * RateLimiter
 *
 * Fixed-window counter, file-backed like Cache.php (same CACHE_DRIVER
 * convention from .env.example, Section 11 — a Redis-backed driver
 * can replace this later without changing hit() call sites). Applied
 * to /ads/serve and the tracking endpoints (Section 6.s/6.t) in
 * public/index.php, keyed on the caller's API key (or IP if none).
 */
class RateLimiter
{
    private static string $directory = __DIR__ . '/../storage/ratelimit';

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
        if (!is_dir(self::$directory)) {
            mkdir(self::$directory, 0775, true);
        }

        $window = intdiv(time(), $windowSeconds);
        $path = self::$directory . '/' . md5($key) . '_' . $window . '.txt';

        $count = is_file($path) ? (int) file_get_contents($path) : 0;
        $count++;
        file_put_contents($path, (string) $count);

        return $count <= $maxHits;
    }
}
