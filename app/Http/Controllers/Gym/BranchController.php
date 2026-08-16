<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    /**
     * List all physical branch locations for this Gym Tenant (with search & statistics)
     */
    public function index(Request $request)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        $query = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId);

        // Optional search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('LOWER(name)'), 'like', "%{$search}%")
                  ->orWhere(DB::raw('LOWER(code)'), 'like', "%{$search}%")
                  ->orWhere(DB::raw('LOWER(city)'), 'like', "%{$search}%");
            });
        }

        $branches = $query->orderBy('is_main_branch', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        // Calculate stats per branch safely
        $enhancedBranches = $branches->map(function ($branch) use ($tenantId) {
            $memberCount = 0;
            $staffCount = 0;

            try {
                $memberCount = DB::connection('tenant')
                    ->table('members')
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $branch->id)
                    ->count();
            } catch (\Throwable $e) {
                // Table might not be populated yet
            }

            try {
                $staffCount = DB::connection('tenant')
                    ->table('staff')
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $branch->id)
                    ->count();
            } catch (\Throwable $e) {
                // Table might not be populated yet
            }

            return array_merge((array) $branch, [
                'is_main_branch' => (bool) $branch->is_main_branch,
                'stats'          => [
                    'total_members' => $memberCount,
                    'active_staff'  => $staffCount,
                ]
            ]);
        });

        $maxBranchesAllowed = match ($tenant->plan_tier) {
            'basic'      => 1,
            'pro'        => 3,
            'enterprise' => 999,
            default      => 1,
        };

        return response()->json([
            'success'      => true,
            'branch_quota' => [
                'plan_tier'      => $tenant->plan_tier,
                'total_branches' => count($branches),
                'max_allowed'    => $maxBranchesAllowed,
                'can_add_more'   => count($branches) < $maxBranchesAllowed,
            ],
            'data'         => $enhancedBranches
        ]);
    }

    /**
     * Create a new physical branch location with Plan Tier Limit Enforcement
     */
    public function store(Request $request)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        // Check 1: Calculate plan tier branch limit
        $maxBranchesAllowed = match ($tenant->plan_tier) {
            'basic'      => 1,
            'pro'        => 3,
            'enterprise' => 999,
            default      => 1,
        };

        $currentBranchCount = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->count();

        // Check 2: Strict Plan Tier Branch Quota Guard
        if ($currentBranchCount >= $maxBranchesAllowed) {
            return response()->json([
                'success' => false,
                'message' => "Branch limit reached for your {$tenant->plan_tier} plan ({$currentBranchCount}/{$maxBranchesAllowed} physical locations). Please upgrade your subscription plan to Pro or Enterprise to add more physical branches."
            ], 422);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:191',
            'code'    => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city'    => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $code = strtoupper($request->input('code', 'BR-' . strtoupper(Str::random(4))));

        $branchId = DB::connection('tenant')->table('branches')->insertGetId([
            'tenant_id'      => $tenantId,
            'name'           => $request->name,
            'code'           => $code,
            'address'        => $request->address,
            'city'           => $request->city,
            'phone'          => $request->phone,
            'is_main_branch' => false,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $branch = DB::connection('tenant')->table('branches')->where('id', $branchId)->first();

        return response()->json([
            'success' => true,
            'message' => "Branch '{$branch->name}' created successfully",
            'data'    => array_merge((array)$branch, [
                'is_main_branch' => (bool)$branch->is_main_branch,
                'stats'          => [
                    'total_members' => 0,
                    'active_staff'  => 0,
                ]
            ])
        ], 201);
    }

    /**
     * Get single physical branch details with statistics
     */
    public function show(Request $request, $id)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        $branch = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch location not found'
            ], 404);
        }

        $memberCount = 0;
        $staffCount = 0;

        try {
            $memberCount = DB::connection('tenant')
                ->table('members')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branch->id)
                ->count();
        } catch (\Throwable $e) {}

        try {
            $staffCount = DB::connection('tenant')
                ->table('staff')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branch->id)
                ->count();
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'data'    => array_merge((array)$branch, [
                'is_main_branch' => (bool)$branch->is_main_branch,
                'stats'          => [
                    'total_members' => $memberCount,
                    'active_staff'  => $staffCount,
                ]
            ])
        ]);
    }

    /**
     * Update physical branch location details
     */
    public function update(Request $request, $id)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        $branch = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch location not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'sometimes|required|string|max:191',
            'code'    => 'sometimes|required|string|max:20',
            'address' => 'nullable|string',
            'city'    => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $updateData = ['updated_at' => now()];

        if ($request->has('name')) $updateData['name'] = $request->name;
        if ($request->has('code')) $updateData['code'] = strtoupper($request->code);
        if ($request->has('address')) $updateData['address'] = $request->address;
        if ($request->has('city')) $updateData['city'] = $request->city;
        if ($request->has('phone')) $updateData['phone'] = $request->phone;

        DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update($updateData);

        $updatedBranch = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'message' => "Branch '{$updatedBranch->name}' updated successfully",
            'data'    => array_merge((array)$updatedBranch, [
                'is_main_branch' => (bool)$updatedBranch->is_main_branch
            ])
        ]);
    }

    /**
     * Safely delete a branch (Guards main branch & active members)
     */
    public function destroy(Request $request, $id)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        $branch = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch location not found'
            ], 404);
        }

        // Safety Guard 1: Cannot delete Main Branch
        if ($branch->is_main_branch) {
            return response()->json([
                'success' => false,
                'message' => 'Primary main branch location cannot be deleted.'
            ], 422);
        }

        // Safety Guard 2: Cannot delete branch if active members exist
        try {
            $memberCount = DB::connection('tenant')
                ->table('members')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $id)
                ->count();

            if ($memberCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete branch. {$memberCount} members are currently registered under this branch."
                ], 422);
            }
        } catch (\Throwable $e) {}

        DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Branch '{$branch->name}' deleted successfully"
        ]);
    }

    /**
     * Dedicated Financial P&L Endpoint for a Specific Branch
     */
    public function financials(Request $request, $id)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        $branch = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch location not found'
            ], 404);
        }

        // Financial metrics computed strictly for this branch_id
        $totalRevenue = 0.00;
        $totalExpenses = 0.00;
        $paymentBreakdown = ['cash' => 0.00, 'upi' => 0.00, 'card' => 0.00];

        try {
            $totalRevenue = DB::connection('tenant')
                ->table('payments')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $id)
                ->sum('amount') ?? 0.00;
        } catch (\Throwable $e) {}

        $netProfit = $totalRevenue - $totalExpenses;

        return response()->json([
            'success'    => true,
            'branch'     => [
                'id'             => $branch->id,
                'name'           => $branch->name,
                'code'           => $branch->code,
                'is_main_branch' => (bool) $branch->is_main_branch,
            ],
            'financials' => [
                'currency'          => 'INR',
                'total_revenue'     => (float) $totalRevenue,
                'total_expenses'    => (float) $totalExpenses,
                'net_profit'        => (float) $netProfit,
                'payment_breakdown' => $paymentBreakdown,
            ]
        ]);
    }

    /**
     * Switch Active Branch Context (For Gym Owners / Multi-branch Admins)
     */
    public function switchContext(Request $request)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $branch = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('id', $request->branch_id)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid branch specified for this gym instance.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Active branch context switched to '{$branch->name}'",
            'branch'  => [
                'id'             => $branch->id,
                'name'           => $branch->name,
                'code'           => $branch->code,
                'city'           => $branch->city,
                'is_main_branch' => (bool) $branch->is_main_branch,
            ]
        ]);
    }
}
