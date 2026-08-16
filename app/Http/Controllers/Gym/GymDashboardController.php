<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GymDashboardController extends Controller
{
    /**
     * Get Gym Instance Dashboard Overview Metrics
     */
    public function index(Request $request)
    {
        $tenant = $request->attributes->get('tenant') ?: app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : $tenant->getKey();

        // Query shard database tables scoped by tenant_id
        $branchCount = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->count();

        $staffCount = DB::connection('tenant')
            ->table('staff')
            ->where('tenant_id', $tenantId)
            ->count();

        $memberCount = 0;
        try {
            $memberCount = DB::connection('tenant')
                ->table('members')
                ->where('tenant_id', $tenantId)
                ->count();
        } catch (\Throwable $e) {
            // Table created in phase 3 module
        }

        $maxBranchesAllowed = match ($tenant->plan_tier) {
            'basic'      => 1,
            'pro'        => 3,
            'enterprise' => 999,
            default      => 1,
        };

        $branches = DB::connection('tenant')
            ->table('branches')
            ->where('tenant_id', $tenantId)
            ->get();

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
                'branches_summary' => [
                    'total_branches'  => $branchCount,
                    'max_allowed'     => $maxBranchesAllowed,
                    'can_add_branch'  => $branchCount < $maxBranchesAllowed,
                    'usage_text'      => "{$branchCount}/{$maxBranchesAllowed} physical locations used",
                ],
                'counts' => [
                    'branches' => $branchCount,
                    'staff'    => $staffCount,
                    'members'  => $memberCount,
                ],
                'branches' => $branches,
            ]
        ]);
    }
}
