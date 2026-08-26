<?php

namespace App\Ads;

/**
 * ImageController
 *
 * The only way an uploaded ad image is ever served — storage/uploads/
 * itself has no web-server mapping (6.r), so this thin, read-only
 * route is the sole path from disk to a response.
 */
use Core\Response;

class ImageController
{
    private const STORAGE_DIR = __DIR__ . '/../../storage/uploads';

    private const EXT_TO_MIME = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * GET /images/ads/{filename}
     * Far-future cache headers (7.i) are safe here because filenames
     * are random and content-addressed at creation (core/Uploads.php)
     * — the same filename can never later point at different bytes,
     * so a client (or CDN) can cache it "forever" without going stale.
     */
    public function show(string $filename): void
    {
        // Reject anything that isn't a bare filename before it ever
        // touches the filesystem — no directory traversal via `../`.
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
            http_response_code(404);
            return;
        }

        $path = self::STORAGE_DIR . '/' . $filename;

        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        header('Content-Type: ' . self::EXT_TO_MIME[$extension]);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

        readfile($path);
    }
}
