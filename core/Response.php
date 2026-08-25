<?php

namespace Core;

/**
 * Response
 *
 * One place that shapes every JSON response, so client code (and
 * connected apps) never have to branch on response shape per
 * endpoint — every response follows `{ success, data | error }`.
 *
 * Error-shape helper is added next — this stub only handles success.
 */
class Response
{
    /**
     * @param array<string, mixed> $data
     */
    public static function success(array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * @param array<string, mixed> $error e.g. ['code' => 'validation_error', 'message' => '...']
     */
    public static function error(array $error, int $statusCode = 400): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'error' => $error,
        ]);
    }
}
