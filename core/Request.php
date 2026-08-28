<?php

namespace Core;

/**
 * Request
 *
 * Reads raw input for the current request — form-encoded POST data
 * or a JSON body — into a single array every controller can use the
 * same way, regardless of how the client sent it.
 *
 * Sanitizing helpers are added next — this stub only reads the data.
 */
class Request
{
    /**
     * @return array<string, mixed>
     */
    public static function input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        } else {
            $body = $_POST;
        }

        // $_GET is merged as a lower-priority fallback so query-string
        // params work on GET routes (e.g. `GET /ads/serve?placement=`,
        // documented in public/api-docs.php — 10.j) without changing
        // behavior for POST/JSON routes, where body values still win
        // on any key collision.
        return array_merge($_GET, $body);
    }

    /**
     * Get one field from input(), trimmed and stripped of tags.
     * Use for plain-text fields (titles, names, CTA text, etc.).
     */
    public static function string(string $key, string $default = ''): string
    {
        $value = self::input()[$key] ?? $default;

        return trim(strip_tags((string) $value));
    }

    /**
     * Get one field from input() as an int, or null if missing/invalid.
     * Use for ids and other numeric fields.
     */
    public static function int(string $key): ?int
    {
        $value = self::input()[$key] ?? null;

        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : null;
    }
}
