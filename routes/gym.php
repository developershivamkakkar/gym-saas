<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Gym\AuthController;
use App\Http\Controllers\Gym\GymDashboardController;
use App\Http\Controllers\Gym\GymConfigController;
use App\Http\Controllers\Gym\BranchController;
use App\Http\Controllers\Gym\MemberController;
use App\Http\Controllers\Gym\MembershipController;
use App\Http\Controllers\Gym\BillingController;
use App\Http\Controllers\Gym\DietPlanController;
use App\Http\Middleware\TenantResolutionMiddleware;

/*
|--------------------------------------------------------------------------
| Gym / Clinic Instance Routes ({slug}.fitcore.io)
|--------------------------------------------------------------------------
*/

// Public & Authenticated Gym Operations under Tenant Resolution Middleware
Route::middleware([TenantResolutionMiddleware::class])->group(function () {

    // Authentication APIs
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Gym Overview Dashboard
    Route::get('dashboard', [GymDashboardController::class, 'index']);

    // Gym Configuration Settings
    Route::get('config', [GymConfigController::class, 'show']);
    Route::put('config', [GymConfigController::class, 'update']);
    Route::patch('config', [GymConfigController::class, 'update']);

    // Multi-Branch Management (Physical Locations)
    Route::prefix('branches')->group(function () {
        Route::get('/', [BranchController::class, 'index']);
        Route::post('/', [BranchController::class, 'store']);
        Route::post('/switch', [BranchController::class, 'switchContext']);
        Route::get('/{id}', [BranchController::class, 'show']);
        Route::put('/{id}', [BranchController::class, 'update']);
        Route::patch('/{id}', [BranchController::class, 'update']);
        Route::delete('/{id}', [BranchController::class, 'destroy']);
        Route::get('/{id}/financials', [BranchController::class, 'financials']);
    });

    // Member Management
    Route::prefix('members')->group(function () {
        Route::get('/', [MemberController::class, 'index']);
        Route::post('/', [MemberController::class, 'store']);
        Route::get('/{id}', [MemberController::class, 'show']);
        Route::put('/{id}', [MemberController::class, 'update']);
        Route::patch('/{id}', [MemberController::class, 'update']);
        Route::post('/{id}/subscriptions', [MembershipController::class, 'subscribe']);
    });

    // Memberships & Subscriptions Engine
    Route::prefix('memberships')->group(function () {
        Route::get('/plans', [MembershipController::class, 'plans']);
        Route::post('/plans', [MembershipController::class, 'createPlan']);
        Route::post('/subscriptions/{id}/renew', [MembershipController::class, 'renew']);
        Route::post('/subscriptions/{id}/freeze', [MembershipController::class, 'freeze']);
        Route::post('/subscriptions/{id}/unfreeze', [MembershipController::class, 'unfreeze']);
        Route::post('/subscriptions/{id}/upgrade', [MembershipController::class, 'upgrade']);
    });

    // Billing & Payments
    Route::prefix('billing')->group(function () {
        Route::get('/invoices', [BillingController::class, 'invoices']);
        Route::get('/invoices/{id}', [BillingController::class, 'showInvoice']);
        Route::post('/invoices/{id}/payments', [BillingController::class, 'recordPayment']);
    });

    // Diet Plan Management Module
    Route::prefix('diet-plans')->group(function () {
        Route::get('/', [DietPlanController::class, 'index']);
        Route::post('/', [DietPlanController::class, 'store']);
        Route::get('/{id}', [DietPlanController::class, 'show']);
        Route::put('/{id}', [DietPlanController::class, 'update']);
        Route::patch('/{id}', [DietPlanController::class, 'update']);
        Route::delete('/{id}', [DietPlanController::class, 'destroy']);
        Route::post('/{id}/assign', [DietPlanController::class, 'assignToMember']);
    });
});
