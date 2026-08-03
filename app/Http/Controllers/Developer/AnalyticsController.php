<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Master\Partner;
use App\Models\Master\Shard;
use App\Models\Master\Tenant;
use App\Models\Master\AuditLog;

class AnalyticsController extends Controller
{
    /**
     * Get platform-wide Developer Dashboard analytics
     */
    public function index()
    {
        $totalPartners = Partner::count();
        $activePartners = Partner::where('status', 'active')->count();

        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $trialTenants = Tenant::where('status', 'trial')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();

        $totalShards = Shard::count();
        $activeShards = Shard::where('is_active', true)->count();

        $recentAuditLogs = AuditLog::orderBy('id', 'desc')->take(10)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'partners' => [
                    'total'  => $totalPartners,
                    'active' => $activePartners,
                ],
                'gym_tenants' => [
                    'total'     => $totalTenants,
                    'active'    => $activeTenants,
                    'trial'     => $trialTenants,
                    'suspended' => $suspendedTenants,
                ],
                'shards' => [
                    'total'  => $totalShards,
                    'active' => $activeShards,
                ],
                'recent_logs' => $recentAuditLogs,
            ]
        ]);
    }
}
