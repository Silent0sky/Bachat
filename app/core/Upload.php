<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Upload
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'application/pdf' => 'pdf',
    ];

    /**
     * Validate and store an uploaded file. Returns relative path like "uploads/products/ab12cd.jpg".
     * MIME is re-checked server-side with finfo; the original name is never used.
     *
     * @param array $file One entry from $_FILES
     * @param string $subdir Sub-directory inside /uploads
     * @param bool $imagesOnly Restrict to images (default true)
     * @throws RuntimeException
     */
    public static function file(array $file, string $subdir = 'misc', bool $imagesOnly = true): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(t('upload.failed'));
        }
        $maxKb = (int) config('security.upload_max_kb', 2048);
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxKb * 1024) {
            throw new RuntimeException(t('upload.too_large', ['size' => (string) $maxKb]));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);

        if ($imagesOnly && !isset(self::ALLOWED_MIME[$mime])) {
            throw new RuntimeException(t('upload.bad_type'));
        }
        if (!$imagesOnly) {
            $allowed = self::ALLOWED_MIME + ['text/plain' => 'txt'];
            if (!isset($allowed[$mime])) {
                throw new RuntimeException(t('upload.bad_type'));
            }
            $ext = $allowed[$mime];
        } else {
            $ext = self::ALLOWED_MIME[$mime];
        }

        $dir = BACHAT_ROOT . '/uploads/' . preg_replace('/[^a-z0-9_\-]/', '', strtolower($subdir));
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException(t('upload.failed'));
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new RuntimeException(t('upload.failed'));
        }
        @chmod($dir . '/' . $name, 0644);

        return 'uploads/' . basename($dir) . '/' . $name;
    }

    public static function image(array $file, string $subdir = 'misc'): string
    {
        return self::file($file, $subdir, true);
    }
}
