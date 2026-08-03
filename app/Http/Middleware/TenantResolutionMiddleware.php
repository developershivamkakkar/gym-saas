<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;

/**
 * ==============================================================================
 * 🌟 TENANT RESOLUTION MIDDLEWARE (CORE MULTI-TENANT SHARD ROUTER WITH REDIS)
 * ==============================================================================
 *
 * Purpose:
 * Every HTTP request sent to a Gym/Clinic instance ({slug}.fitcore.io) passes through
 * this middleware. It dynamically determines which Gym is making the request,
 * looks up its assigned database shard in fitcore_master (cached in Redis), and reconnects
 * Laravel's 'tenant' database connection to that specific shard DB on-the-fly.
 */
class TenantResolutionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // ----------------------------------------------------------------------
        // STEP 1: Extract Tenant Identifier (Slug)
        // ----------------------------------------------------------------------
        $slug = $this->resolveSlug($request);

        if (!$slug) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant slug not found in request header (X-Tenant-Slug) or domain'
            ], 400);
        }

        // ----------------------------------------------------------------------
        // STEP 2: Query Master Database / Redis Cache for Shard Location
        // ----------------------------------------------------------------------
        // We use Cache::remember() with Redis (TTL: 300 seconds / 5 minutes).
        // If cached in Redis, response time is < 0.5ms with ZERO database load!
        try {
            $tenant = Cache::remember("tenant:slug:{$slug}", 300, function () use ($slug) {
                return DB::connection('master')->table('tenants')
                    ->join('shards', 'tenants.shard_id', '=', 'shards.id')
                    ->where('tenants.slug', $slug)
                    ->where('tenants.status', '!=', 'cancelled')
                    ->select(
                        'tenants.id as tenant_id',
                        'tenants.name as tenant_name',
                        'tenants.slug',
                        'tenants.status as tenant_status',
                        'shards.db_host',
                        'shards.db_port',
                        'shards.db_name',
                        'shards.db_user',
                        'shards.db_password'
                    )->first();
            });
        } catch (\Throwable $e) {
            // Graceful Fallback if Redis server is unreachable in local dev environment
            $tenant = DB::connection('master')->table('tenants')
                ->join('shards', 'tenants.shard_id', '=', 'shards.id')
                ->where('tenants.slug', $slug)
                ->where('tenants.status', '!=', 'cancelled')
                ->select(
                    'tenants.id as tenant_id',
                    'tenants.name as tenant_name',
                    'tenants.slug',
                    'tenants.status as tenant_status',
                    'shards.db_host',
                    'shards.db_port',
                    'shards.db_name',
                    'shards.db_user',
                    'shards.db_password'
                )->first();
        }

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => "Gym / Tenant instance '{$slug}' not found or inactive"
            ], 404);
        }

        // ----------------------------------------------------------------------
        // STEP 3: Enforce Account Status Guard
        // ----------------------------------------------------------------------
        if ($tenant->tenant_status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'This Gym account is currently suspended. Please contact support.'
            ], 403);
        }

        // ----------------------------------------------------------------------
        // STEP 4: Decrypt Database Shard Password
        // ----------------------------------------------------------------------
        $dbPassword = $tenant->db_password;
        try {
            $dbPassword = Crypt::decryptString($tenant->db_password);
        } catch (\Throwable $e) {
            // Unencrypted password fallback
        }

        // ----------------------------------------------------------------------
        // STEP 5: Dynamically Purge & Reconfigure 'tenant' PDO Connection
        // ----------------------------------------------------------------------
        DB::purge('tenant');

        $driver = config('database.connections.master.driver', 'sqlite');

        if ($driver === 'sqlite') {
            $dbPath = database_path($tenant->db_name . '.sqlite');
            if (!file_exists($dbPath)) {
                touch($dbPath);
            }
            Config::set('database.connections.tenant.driver', 'sqlite');
            Config::set('database.connections.tenant.database', $dbPath);
        } else {
            Config::set('database.connections.tenant.driver', 'mysql');
            Config::set('database.connections.tenant.host', $tenant->db_host);
            Config::set('database.connections.tenant.port', $tenant->db_port);
            Config::set('database.connections.tenant.database', $tenant->db_name);
            Config::set('database.connections.tenant.username', $tenant->db_user);
            Config::set('database.connections.tenant.password', $dbPassword);
        }

        DB::reconnect('tenant');

        // ----------------------------------------------------------------------
        // STEP 6: Register Active Tenant in Container Scope
        // ----------------------------------------------------------------------
        app()->instance('tenant', $tenant);

        return $next($request);
    }

    /**
     * Extract tenant slug from X-Tenant-Slug header or request host subdomain.
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function resolveSlug(Request $request): ?string
    {
        // 1. Priority check: X-Tenant-Slug HTTP Header
        $headerSlug = $request->header(config('fitcore.tenant_header', 'X-Tenant-Slug'));
        if ($headerSlug) {
            return strtolower(trim($headerSlug));
        }

        // 2. Secondary check: Domain Subdomain (e.g. gold-gym.fitcore.io -> gold-gym)
        $host = $request->getHost();
        $mainDomain = config('fitcore.main_domain', 'fitcore.io');

        if (str_contains($host, '.' . $mainDomain)) {
            $subdomain = str_replace('.' . $mainDomain, '', $host);
            if (!in_array($subdomain, ['admin', 'partner', 'www', 'api'])) {
                return strtolower($subdomain);
            }
        }

        return null;
    }
}
