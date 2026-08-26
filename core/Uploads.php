<?php

namespace Core;

/**
 * Uploads
 *
 * Handles ad-image uploads for AdController::store/update. Every
 * check here runs in order — real MIME sniff, size cap, re-encode,
 * rename, non-executable storage path — so a rejected file never
 * reaches disk (Section 6.o–6.r).
 */
class Uploads
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2MB (6.q)

    // 7.g — the actual rendered size of an ad card image (4:3, per the
    // "recommended 4:3" hint on the create-ad upload field). Anything
    // larger than this is wasted bytes the browser downloads and
    // discards.
    private const TARGET_WIDTH = 600;
    private const TARGET_HEIGHT = 450;

    private const ALLOWED_MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Directory ads are served from. Lives under public/ (Section 9.d)
     * so the web server can serve files here directly without a PHP
     * round-trip — hardened by public/uploads/ads/.htaccess, which
     * denies script execution regardless of a file's extension or
     * content, preserving the "non-executable path" guarantee from
     * 6.r even though this is now inside the public webroot.
     */
    private const STORAGE_DIR = __DIR__ . '/../public/uploads/ads';

    /**
     * @param array $file One entry from $_FILES, e.g. $_FILES['image'].
     * @return string|null The stored filename (not the full path), or
     *                      null if no file was uploaded at all.
     * @throws \RuntimeException if the file fails any check.
     */
    public static function storeAdImage(array $file): ?string
    {
        if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed.');
        }

        // 6.q — cap upload size before doing any further, costlier work.
        if ($file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('Image must be under 2MB.');
        }

        // 6.p — validate by real MIME type (finfo reads the file's
        // actual bytes), never by the client-supplied extension or
        // Content-Type header, both of which are trivially spoofable.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIME_TO_EXT[$mimeType])) {
            throw new \RuntimeException('Image must be JPEG, PNG, or WebP.');
        }

        $extension = self::ALLOWED_MIME_TO_EXT[$mimeType];

        // 6.o — re-encode via GD rather than copying the uploaded bytes
        // as-is. This both strips embedded metadata (EXIF GPS, XMP,
        // ICC profiles the advertiser may not intend to share) and
        // guarantees the stored file is a genuine image the decoder
        // could parse, not just something with an image-shaped header.
        $image = self::decode($file['tmp_name'], $mimeType);

        // 7.g — resize to the one size the ad card actually renders at
        // (create-ad.php recommends 4:3), instead of storing whatever
        // resolution the advertiser happened to upload. Only downscales;
        // a smaller source image is left as-is rather than upscaled.
        $image = self::resizeToFit($image, self::TARGET_WIDTH, self::TARGET_HEIGHT);

        // 6.q — rename on storage: a random name, never the client's
        // original filename, so it can't collide, overwrite another
        // ad's image, or carry a path-traversal payload.
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        if (!is_dir(self::STORAGE_DIR)) {
            mkdir(self::STORAGE_DIR, 0755, true);
        }

        // 7.h — compression is the encode-quality argument passed here;
        // see encode() below (85 for JPEG/WebP, PNG level 6).
        self::encode($image, self::STORAGE_DIR . '/' . $filename, $mimeType);
        imagedestroy($image);

        return $filename;
    }

    /**
     * Downscales an image to fit within $maxWidth x $maxHeight,
     * preserving aspect ratio. Never upscales — a source already
     * smaller than the target is returned unchanged (7.g).
     */
    private static function resizeToFit(\GdImage $image, int $maxWidth, int $maxHeight): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $scale = min($maxWidth / $width, $maxHeight / $height, 1.0);

        if ($scale >= 1.0) {
            return $image;
        }

        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /**
     * @return \GdImage
     */
    private static function decode(string $path, string $mimeType): \GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => throw new \RuntimeException('Unsupported image type.'),
        };

        if ($image === false) {
            throw new \RuntimeException('Uploaded file is not a valid image.');
        }

        return $image;
    }

    private static function encode(\GdImage $image, string $destination, string $mimeType): void
    {
        match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $destination, 85),
            'image/png' => imagepng($image, $destination, 6),
            'image/webp' => imagewebp($image, $destination, 85),
            default => throw new \RuntimeException('Unsupported image type.'),
        };
    }
}
