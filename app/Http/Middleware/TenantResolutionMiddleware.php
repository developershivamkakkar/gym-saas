<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ShardRouter;
use App\Models\Master\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantResolutionMiddleware
{
    protected ShardRouter $shardRouter;

    public function __construct(ShardRouter $shardRouter)
    {
        $this->shardRouter = $shardRouter;
    }

    /**
     * Handle an incoming Gym Instance API request
     * Resolves tenant slug from X-Tenant-Slug header or Subdomain Host
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Resolve slug from X-Tenant-Slug header or host subdomain
        $slug = $request->header('X-Tenant-Slug');

        if (!$slug) {
            $host = $request->getHost();
            $mainDomain = config('fitcore.main_domain', 'fitcore.io');

            if (str_contains($host, '.' . $mainDomain)) {
                $slug = explode('.', $host)[0];
            } elseif (str_contains($host, '.localhost')) {
                $slug = explode('.', $host)[0];
            }
        }

        if (!$slug) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant slug missing. Please provide X-Tenant-Slug header or access via tenant subdomain.'
            ], 400);
        }

        $slug = strtolower($slug);

        // 2. Fetch tenant record directly from master DB or cache
        $tenant = Tenant::where('slug', $slug)->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => "Gym tenant instance '{$slug}' not found"
            ], 404);
        }

        // 3. Check Gym Account Status
        if ($tenant->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => "This Gym account '{$tenant->name}' is currently suspended. Access is locked. Please contact platform support."
            ], 403);
        }

        // 4. Connect PDO dynamically to assigned database shard
        $shardId = is_numeric($tenant->shard_id) 
            ? (int) $tenant->shard_id 
            : \App\Services\HashIdService::decode($tenant->shard_id, 'SHD');

        $shard = $this->shardRouter->connectTenantToShard($tenant->getNumericId(), $shardId ?: 1);

        // 5. Attach tenant and shard to request and global container
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('shard', $shard);

        app()->instance('tenant', $tenant);
        app()->instance('shard', $shard);

        return $next($request);
    }
}
