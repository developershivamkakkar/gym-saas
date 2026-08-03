<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Partner\AuthController;
use App\Http\Controllers\Partner\PartnerGymController;
use App\Http\Controllers\Partner\PartnerDashboardController;
use App\Http\Middleware\PartnerAuthMiddleware;

/*
|--------------------------------------------------------------------------
| Partner Portal Routes (partner.fitcore.io)
|--------------------------------------------------------------------------
*/

// Public Authentication Endpoint
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// Protected Partner Portal APIs (Requires Bearer Token)
Route::middleware([PartnerAuthMiddleware::class])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Partner Dashboard Overview
    Route::get('dashboard', [PartnerDashboardController::class, 'index']);

    // Partner Gym Provisioning & Tenant List
    Route::get('gyms', [PartnerGymController::class, 'index']);
    Route::post('gyms', [PartnerGymController::class, 'store']);
    Route::get('gyms/{id}', [PartnerGymController::class, 'show']);
});
