<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class BrandingAsset
{
    /**
     * Normalize Filament/theme logo values to a relative public-disk path.
     */
    public static function normalizePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = $path[0] ?? null;
        }

        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        if ($path === '' || strcasecmp($path, 'Array') === 0) {
            return null;
        }

        return $path;
    }

    public static function publicUrl(mixed $path): ?string
    {
        $path = self::normalizePath($path);

        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function dataUri(mixed $path): ?string
    {
        $path = self::normalizePath($path);

        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        $full = Storage::disk('public')->path($path);

        if (! is_file($full)) {
            return null;
        }

        $mime = mime_content_type($full) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
    }
}
