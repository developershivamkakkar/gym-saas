<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Gym\AuthController;
use App\Http\Middleware\TenantResolutionMiddleware;

/*
|--------------------------------------------------------------------------
| Gym / Clinic Instance Routes ({slug}.fitcore.io)
|--------------------------------------------------------------------------
*/

Route::middleware([TenantResolutionMiddleware::class])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
    });
});
