<?php

namespace Core;

/**
 * RateLimiter
 *
 * File-based fixed-window rate limiter backing /ads/serve and the
 * tracking endpoints (Section 6.s/6.t). Mirrors Cache.php's
 * file-backed approach — a Redis-backed driver can replace this
 * later without changing the hit() call site in public/index.php.
 */
class RateLimiter
{
    private static string $directory = __DIR__ . '/../storage/ratelimit';

    /**
     * Records one hit for the given key and reports whether the
     * caller is still within the allowed limit for the time window.
     * Fixed window (not sliding): the window resets $windowSeconds
     * after its first hit, not on a rolling basis — simple and good
     * enough for the traffic this app expects.
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

        $path = self::path($key);
        $now = time();

        // A single file handle held open for both the read and the
        // write, locked for the duration, so two concurrent requests
        // for the same key can't both read the same pre-increment
        // count and both be allowed through past the limit.
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            // Storage unavailable — fail open rather than take the
            // whole app down over a rate-limit file write.
            return true;
        }

        flock($handle, LOCK_EX);

        $raw = stream_get_contents($handle);
        $state = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($state) || $state['window_start'] + $windowSeconds <= $now) {
            $state = ['window_start' => $now, 'count' => 0];
        }

        $state['count']++;
        $allowed = $state['count'] <= $maxHits;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $allowed;
    }

    private static function path(string $key): string
    {
        return self::$directory . '/' . md5($key) . '.json';
    }
}
