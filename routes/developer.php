<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Developer\AuthController;
use App\Http\Controllers\Developer\PartnerController;
use App\Http\Controllers\Developer\ShardController;
use App\Http\Controllers\Developer\TenantController;
use App\Http\Controllers\Developer\AnalyticsController;
use App\Http\Controllers\Developer\AuditLogController;
use App\Http\Middleware\DeveloperAuthMiddleware;

/*
|--------------------------------------------------------------------------
| Developer Portal Routes (admin.fitcore.io)
|--------------------------------------------------------------------------
*/

// Public Login Endpoint
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// Protected Developer Admin APIs (Requires Bearer Token)
Route::middleware([DeveloperAuthMiddleware::class])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Analytics & Dashboard Stats
    Route::get('analytics', [AnalyticsController::class, 'index']);

    // Partner Management
    Route::get('partners', [PartnerController::class, 'index']);
    Route::post('partners', [PartnerController::class, 'store']);
    Route::get('partners/{id}', [PartnerController::class, 'show']);
    Route::patch('partners/{id}/quota', [PartnerController::class, 'updateQuota']);
    Route::patch('partners/{id}/status', [PartnerController::class, 'updateStatus']);
    Route::post('partners/{id}/reassign-tenants', [PartnerController::class, 'reassignTenants']);

    // Shard Database Management
    Route::get('shards', [ShardController::class, 'index']);
    Route::post('shards', [ShardController::class, 'store']);
    Route::patch('shards/{id}/capacity', [ShardController::class, 'updateCapacity']);

    // Gym Tenant Management
    Route::get('tenants', [TenantController::class, 'index']);
    Route::get('tenants/{id}', [TenantController::class, 'show']);
    Route::patch('tenants/{id}/status', [TenantController::class, 'updateStatus']);

    // System Audit Logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);
});
