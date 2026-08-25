<?php

namespace Core;

/**
 * Cache
 *
 * Skeleton file-based cache backing the /ads/serve response cache
 * (Section 7). CACHE_DRIVER=file by default, per .env.example
 * (Section 11); a Redis-backed driver can replace this later without
 * changing the get()/set() call sites.
 */
class Cache
{
    private static string $directory = __DIR__ . '/../storage/cache';

    public static function get(string $key): mixed
    {
        $path = self::path($key);

        if (!is_file($path)) {
            return null;
        }

        $payload = json_decode(file_get_contents($path), true);

        if ($payload === null || $payload['expires_at'] < time()) {
            return null;
        }

        return $payload['value'];
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 60): void
    {
        if (!is_dir(self::$directory)) {
            mkdir(self::$directory, 0775, true);
        }

        file_put_contents(self::path($key), json_encode([
            'value' => $value,
            'expires_at' => time() + $ttlSeconds,
        ]));
    }

    private static function path(string $key): string
    {
        return self::$directory . '/' . md5($key) . '.json';
    }
}
