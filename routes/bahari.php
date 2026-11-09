<?php

use Bahari\Laravel\Http\Controllers\AuthController;
use Bahari\Laravel\Http\Controllers\BackchannelLogoutController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('bahari.routes.prefix', 'auth'), '/');
$name = (string) config('bahari.routes.name', 'bahari.');
$kinds = array_keys(config('bahari.kinds', ['client' => [], 'admin' => []]));

Route::middleware(config('bahari.routes.middleware', ['web']))->prefix($prefix)->group(function () use ($name, $kinds): void {
    Route::get('login', [AuthController::class, 'signin'])->middleware('throttle:30,1')->name($name.'signin');
    Route::get('register', [AuthController::class, 'register'])->middleware('throttle:30,1')->name($name.'register');
    Route::get('{kind}/login', [AuthController::class, 'login'])->whereIn('kind', $kinds)->middleware('throttle:30,1')->name($name.'login');
    Route::get('{kind}/callback', [AuthController::class, 'callback'])->whereIn('kind', $kinds)->middleware('throttle:60,1')->name($name.'callback');
    Route::post('{kind}/logout', [AuthController::class, 'logout'])->whereIn('kind', $kinds)->name($name.'logout');
});

// Server-to-server: no session or CSRF.
Route::post($prefix.'/backchannel-logout', BackchannelLogoutController::class)->middleware('throttle:60,1')->name($name.'backchannel');
