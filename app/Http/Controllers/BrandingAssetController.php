<?php

namespace App\Http\Controllers;

use App\Support\BrandingAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BrandingAssetController extends Controller
{
    public function __invoke(Request $request, string $path): Response
    {
        $path = BrandingAsset::normalizePath($path);

        if ($path === null || ! str_starts_with($path, 'branding/')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
