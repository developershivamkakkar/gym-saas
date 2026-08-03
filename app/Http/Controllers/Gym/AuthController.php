<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Shard\Staff;
use App\Models\Shard\GymConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * ==============================================================================
 * 🔐 GYM / CLINIC INSTANCE AUTHENTICATION CONTROLLER
 * ==============================================================================
 *
 * Purpose:
 * Handles login, token refresh, logout, and profile endpoints for all internal
 * Gym Instance users (Gym Owners, Branch Managers, Front Desk Staff, Trainers).
 *
 * Security:
 * Requires TenantResolutionMiddleware to be active on the route so that:
 * 1. Queries execute against the correct Shard Database.
 * 2. `Staff::where(...)` queries are automatically scoped by `tenant_id`.
 */
class AuthController extends Controller
{
    /**
     * Gym User Login (Owner, Manager, Front Desk, Trainer)
     */
    public function login(Request $request)
    {
        // Retrieve resolved active tenant context bound by TenantResolutionMiddleware
        $tenant = app('tenant');

        // 1. Validate Input Payload
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // 2. Query Staff table on the resolved shard DB (automatically scoped by tenant_id)
        $staff = Staff::where('email', $request->email)->first();

        // 3. Verify Password Hash
        if (!$staff || !Hash::check($request->password, $staff->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password for ' . $tenant->tenant_name
            ], 401);
        }

        // 4. Verify Active Status
        if (!$staff->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'User account is deactivated'
            ], 403);
        }

        // 5. Fetch Gym Branding Config & Generate API Bearer Token
        $gymConfig = GymConfig::first();
        $token = bin2hex(random_bytes(32));

        // Format role title (e.g. "owner" -> "Owner", "branch_manager" -> "Branch Manager")
        $roleTitle = ucwords(str_replace('_', ' ', $staff->role));

        // 6. Return Structured Authenticated Response
        return response()->json([
            'success' => true,
            'message' => "{$roleTitle} logged in successfully",
            'portal'  => 'gym',
            'token'   => $token,
            'tenant'  => [
                'id'          => $tenant->tenant_id,
                'name'        => $tenant->tenant_name,
                'slug'        => $tenant->slug,
                'gym_name'    => $gymConfig ? $gymConfig->gym_name : $tenant->tenant_name,
                'logo_url'    => $gymConfig ? $gymConfig->logo_url : null,
                'color'       => $gymConfig ? $gymConfig->primary_color : '#3B82F6',
            ],
            'data'    => [
                'id'        => $staff->id,
                'name'      => $staff->name,
                'email'     => $staff->email,
                'role'      => $staff->role,
                'role_title'=> $roleTitle,
                'branch_id' => $staff->branch_id,
            ]
        ]);
    }

    /**
     * Refresh Token Endpoint
     */
    public function refresh(Request $request)
    {
        $tenant = app('tenant');
        $token = bin2hex(random_bytes(32));

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'portal'  => 'gym',
            'tenant'  => [
                'id'   => $tenant->tenant_id,
                'name' => $tenant->tenant_name,
                'slug' => $tenant->slug,
            ],
            'token'   => $token
        ]);
    }

    /**
     * Logout Endpoint
     */
    public function logout(Request $request)
    {
        $tenant = app('tenant');

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully from ' . $tenant->tenant_name
        ]);
    }

    /**
     * Get Authenticated User Profile
     */
    public function me(Request $request)
    {
        $tenant = app('tenant');

        return response()->json([
            'success' => true,
            'tenant'  => [
                'id'   => $tenant->tenant_id,
                'name' => $tenant->tenant_name,
                'slug' => $tenant->slug,
            ],
            'data'    => $request->user()
        ]);
    }
}
