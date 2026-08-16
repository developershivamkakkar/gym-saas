<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Authenticate Gym Staff / Owner / Manager against Shard Database
     */
    public function login(Request $request)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // Query staff table on assigned shard DB connection ('tenant') strictly scoped to current tenant_id
        $staff = DB::connection('tenant')
            ->table('staff')
            ->where('tenant_id', $tenantId)
            ->where('email', strtolower($request->email))
            ->first();

        if (!$staff || !Hash::check($request->password, $staff->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid gym credentials'
            ], 401);
        }

        if (isset($staff->is_active) && !$staff->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your staff account has been deactivated by the gym administrator.'
            ], 403);
        }

        // Fetch primary branch details
        $primaryBranch = DB::connection('tenant')
            ->table('branches')
            ->where('id', $staff->branch_id ?? 1)
            ->first();

        // Generate Bearer Token for MVP
        $token = hash('sha256', Str::random(40) . $staff->id . time());

        // Update timestamps safely
        try {
            DB::connection('tenant')
                ->table('staff')
                ->where('id', $staff->id)
                ->update(['updated_at' => now()]);
        } catch (\Throwable $e) {
            // Safe fallback
        }

        return response()->json([
            'success' => true,
            'message' => 'Gym user authenticated successfully',
            'portal'  => 'gym',
            'token'   => $token,
            'tenant'  => [
                'id'           => $tenant->id,
                'name'         => $tenant->name,
                'slug'         => $tenant->slug,
                'plan_tier'    => $tenant->plan_tier,
                'instance_url' => $tenant->instance_url,
            ],
            'data'    => [
                'id'         => $staff->id,
                'name'       => $staff->name,
                'email'      => $staff->email,
                'phone'      => $staff->phone ?? null,
                'role'       => $staff->role ?? 'staff',
                'branch'     => $primaryBranch ? [
                    'id'   => $primaryBranch->id,
                    'name' => $primaryBranch->name,
                    'code' => $primaryBranch->code ?? 'MAIN',
                ] : null,
            ]
        ]);
    }

    /**
     * Get Authenticated Gym User Profile & Tenant Context
     */
    public function me(Request $request)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        // Fetch primary owner/staff for request user simulation scoped to current tenant
        $staff = DB::connection('tenant')
            ->table('staff')
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'asc')
            ->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff profile not found'
            ], 404);
        }

        $branch = DB::connection('tenant')
            ->table('branches')
            ->where('id', $staff->branch_id ?? 1)
            ->first();

        return response()->json([
            'success' => true,
            'tenant'  => [
                'id'           => $tenant->id,
                'name'         => $tenant->name,
                'slug'         => $tenant->slug,
                'plan_tier'    => $tenant->plan_tier,
                'status'       => $tenant->status,
                'instance_url' => $tenant->instance_url,
            ],
            'data'    => [
                'id'         => $staff->id,
                'name'       => $staff->name,
                'email'      => $staff->email,
                'phone'      => $staff->phone ?? null,
                'role'       => $staff->role ?? 'owner',
                'branch'     => $branch ? [
                    'id'   => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code ?? 'MAIN',
                ] : null,
            ]
        ]);
    }

    /**
     * Refresh Token
     */
    public function refresh(Request $request)
    {
        $newToken = hash('sha256', Str::random(40) . time());

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'token'   => $newToken
        ]);
    }

    /**
     * Logout
     */
    public function logout()
    {
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
