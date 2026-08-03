<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FitCore API Router Setup
|--------------------------------------------------------------------------
*/

// Developer Portal Routes
Route::prefix('v1/developer')->group(base_path('routes/developer.php'));

// Partner Portal Routes
Route::prefix('v1/partner')->group(base_path('routes/partner.php'));

// Gym Instance Routes
Route::prefix('v1/gym')->group(base_path('routes/gym.php'));

// Root Health Check
Route::get('/', function () {
    return response()->json([
        'system'  => 'FitCore Multi-Tenant SaaS Platform',
        'status'  => 'online',
        'version' => '1.0.0',
    ]);
});
