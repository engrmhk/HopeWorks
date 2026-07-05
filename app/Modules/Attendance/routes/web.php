<?php

use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'module.enabled:attendance'])
    ->prefix('attendance')
    ->name('attendance.')
    ->group(function (): void {
        Route::get('/', function () {
            return response()->json([
                'module' => 'attendance',
                'enabled' => app(ModuleRegistry::class)->isEnabled('attendance'),
            ]);
        })->name('index');
    });
