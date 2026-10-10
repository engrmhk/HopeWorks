<?php

use App\Http\Controllers\BrandingAssetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/branding/asset/{path}', BrandingAssetController::class)
    ->where('path', '.*')
    ->name('branding.asset');
