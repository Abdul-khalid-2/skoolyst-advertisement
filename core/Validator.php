<?php

namespace Core;

/**
 * Validator
 *
 * Base validation rules shared by every module (e.g. AdValidator in
 * app/Ads/ builds on top of these). Each rule method returns true/false;
 * callers are responsible for collecting error messages.
 *
 * More rules (maxLength, url, date) are added next.
 */
class Validator
{
    public static function required($value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null && $value !== [];
    }

    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen($value) <= $max;
    }

    public static function url(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validates a date string against a given format (default Y-m-d,
     * matching ad start_date/end_date columns from Section 5).
     */
    public static function date(string $value, string $format = 'Y-m-d'): bool
    {
        $parsed = \DateTime::createFromFormat($format, $value);

        return $parsed !== false && $parsed->format($format) === $value;
    }
}
