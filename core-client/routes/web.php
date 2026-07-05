<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::view('/billing-suspended', 'billing.suspended')->name('billing.suspended');

Route::get('/password/reset/{token}', fn () => view('auth.password-reset'))->name('password.reset');
Route::post('/password/reset', fn () => redirect('/admin'))->name('password.update');

Route::get('/test-write-endpoint', fn () => response('ok'))->name('test.write.get');
Route::post('/test-write-endpoint', fn () => response('ok'))->name('test.write');
