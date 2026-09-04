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

    /**
     * Get one field from input() as a de-duplicated list of positive
     * ints, silently dropping anything that isn't one. Used for
     * multi-select fields sent as repeated form keys (a client's
     * `FormData` with several `key[]` entries, or a JSON array body)
     * — PHP already turns either shape into a real array under
     * `input()[$key]`, so this only has to validate/clean it, not
     * parse it. Missing key or a non-array value both return `[]`
     * rather than null, since "no valid ids were sent" is the only
     * thing a caller needs to branch on (see AdController's
     * `placement_ids` — 10.p).
     *
     * @return array<int, int>
     */
    public static function intArray(string $key): array
    {
        $value = self::input()[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            $int = filter_var($item, FILTER_VALIDATE_INT);
            if ($int !== false && (int) $int > 0) {
                $result[] = (int) $int;
            }
        }

        return array_values(array_unique($result));
    }
}
