<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Master\Tenant;
use App\Models\Master\AuditLog;
use App\Services\HashIdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class TenantController extends Controller
{
    /**
     * Helper to resolve Tenant by numeric ID, Hash ID, or Slug
     */
    protected function resolveTenant($id): ?Tenant
    {
        if (is_numeric($id)) {
            return Tenant::find($id);
        }

        $numericId = HashIdService::decode($id, 'TNT');
        return $numericId ? Tenant::find($numericId) : Tenant::where('slug', $id)->first();
    }

    /**
     * List all Gym Tenants across all partners & shards
     */
    public function index(Request $request)
    {
        $query = Tenant::with(['partner:id,company_name,email', 'shard:id,name,db_name']);

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        $tenants = $query->orderBy('id', 'desc')->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $tenants
        ]);
    }

    /**
     * Get single Gym Tenant details
     */
    public function show($id)
    {
        $tenant = $this->resolveTenant($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        $tenant->load(['partner', 'shard']);

        return response()->json([
            'success' => true,
            'data'    => $tenant
        ]);
    }

    /**
     * Suspend or Activate a Gym Tenant
     */
    public function updateStatus(Request $request, $id)
    {
        $tenant = $this->resolveTenant($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,trial,suspended,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $tenant->update([
            'status'       => $request->status,
            'suspended_at' => $request->status === 'suspended' ? now() : null,
        ]);

        // Invalidate Redis cache key for this tenant
        try {
            Cache::forget("tenant:slug:{$tenant->slug}");
        } catch (\Throwable $e) {
            // Ignore cache error in local fallback
        }

        // Audit Log
        AuditLog::create([
            'actor_type'  => 'Developer',
            'actor_id'    => $request->user() ? $request->user()->getKey() : 1,
            'action'      => 'tenant.status_updated',
            'target_type' => 'Tenant',
            'target_id'   => $tenant->getKey(),
            'payload'     => ['hash_id' => $tenant->id, 'slug' => $tenant->slug, 'status' => $request->status],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Gym Tenant '{$tenant->name}' status updated to {$request->status}",
            'data'    => $tenant
        ]);
    }
}
